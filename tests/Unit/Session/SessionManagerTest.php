<?php

use IvanVasechko\Oukitel\Config\ClientOptions;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\Session;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\ApiError;
use IvanVasechko\Oukitel\Exceptions\AuthenticationFailed;
use IvanVasechko\Oukitel\Exceptions\CacheFailed;
use IvanVasechko\Oukitel\Exceptions\LoginCoolingDown;
use IvanVasechko\Oukitel\Exceptions\MalformedResponse;
use IvanVasechko\Oukitel\Exceptions\SessionExpired;
use IvanVasechko\Oukitel\Exceptions\TransportFailed;
use IvanVasechko\Oukitel\Session\InMemorySessionStore;
use IvanVasechko\Oukitel\Session\Psr16SessionStore;
use IvanVasechko\Oukitel\Session\SessionManager;
use IvanVasechko\Oukitel\Tests\Support\FrozenClock;
use IvanVasechko\Oukitel\Tests\Support\RecordingCache;
use IvanVasechko\Oukitel\Tests\Support\ScriptedCloud;
use Psr\Log\AbstractLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

beforeEach(function () {
    $this->clock = new FrozenClock;
    $this->cloud = new ScriptedCloud;
    $this->cache = new RecordingCache;
    $this->store = new Psr16SessionStore($this->cache);
    $this->manager = new SessionManager($this->cloud, $this->store, $this->clock);
    $this->credentials = new Credentials(Region::Eu, 'USER@example.test', 'example-password');
    $this->device = new DeviceId('example-device', 'example-product');
});

it('caches minimal sessions with exact margin TTL and portable full identity hashes', function () {
    $this->manager->devices($this->credentials);
    $this->manager->devices(new Credentials(Region::Eu, 'user@example.test', 'different-password'));
    $key = array_key_first($this->cache->values);
    expect($this->cloud->logins)->toBe(1)
        ->and($key)->toMatch('/^[a-f0-9]{64}$/D')
        ->and($key)->toBe(hash('sha256', 'oukitel.session|EU|user@example.test'))
        ->and($this->cache->ttls[$key])->toBe(3480)
        ->and($this->cache->values[$key])->toBe([
            'region' => 'EU', 'access_token' => 'example-token-1',
            'refresh_token' => 'example-refresh', 'expires_at' => 1_800_003_600,
        ]);
    $this->manager->devices(new Credentials(Region::Us, 'user@example.test', 'example-password'));
    $this->manager->devices(new Credentials(Region::Eu, 'another@example.test', 'example-password'));
    expect($this->cloud->logins)->toBe(3)->and($this->cache->values)->toHaveCount(3);
});

it('refreshes exactly at the expiry margin and never caches short-lived tokens', function () {
    $this->manager->devices($this->credentials);
    $this->clock->timestamp += 3479;
    $this->manager->devices($this->credentials);
    expect($this->cloud->logins)->toBe(1);
    $this->clock->timestamp++;
    $this->manager->devices($this->credentials);
    expect($this->cloud->logins)->toBe(2)->and($this->cache->values)->toBe([]);
    $this->manager->devices($this->credentials);
    expect($this->cloud->logins)->toBe(3);
});

it('forces fresh verification even with a cached session and changed password', function () {
    $this->manager->devices($this->credentials);
    $this->cloud->loginFailure = new AuthenticationFailed;
    expect(fn () => $this->manager->verify(new Credentials(Region::Eu, 'user@example.test', 'wrong-password')))
        ->toThrow(AuthenticationFailed::class);
    expect($this->cloud->logins)->toBe(2)->and($this->cloud->calls)->toBe(['devices']);
});

it('distinguishes failed login from active cooldown and bypasses cooldown for verification', function () {
    $memory = new InMemorySessionStore($this->clock);
    $manager = new SessionManager($this->cloud, $memory, $this->clock);
    $this->cloud->loginFailure = new AuthenticationFailed;
    expect(fn () => $manager->devices($this->credentials))->toThrow(AuthenticationFailed::class);
    expect(fn () => $manager->devices($this->credentials))->toThrow(LoginCoolingDown::class);
    expect($this->cloud->logins)->toBe(1);
    expect(fn () => $manager->verify($this->credentials))->toThrow(AuthenticationFailed::class);
    expect($this->cloud->logins)->toBe(2);
    $this->clock->timestamp += 299;
    expect(fn () => $manager->devices($this->credentials))->toThrow(LoginCoolingDown::class);
    $this->clock->timestamp++;
    expect(fn () => $manager->devices($this->credentials))->toThrow(AuthenticationFailed::class);
    expect($this->cloud->logins)->toBe(3);
});

it('stores a separate 64-character cooldown key without account identifiers', function () {
    $this->cloud->loginFailure = new AuthenticationFailed;
    expect(fn () => $this->manager->devices($this->credentials))->toThrow(AuthenticationFailed::class);
    $key = array_key_first($this->cache->values);
    expect($key)->toBe(hash('sha256', 'oukitel.login-cooldown|EU|user@example.test'))
        ->and($this->cache->values[$key])->toBeTrue()
        ->and($this->cache->ttls[$key])->toBe(300);
});

it('supports disabled login cooldown', function () {
    $manager = new SessionManager($this->cloud, $this->store, $this->clock, new ClientOptions(loginFailureCooldownMinutes: 0));
    $this->cloud->loginFailure = new AuthenticationFailed;
    for ($attempt = 0; $attempt < 2; $attempt++) {
        expect(fn () => $manager->devices($this->credentials))->toThrow(AuthenticationFailed::class);
    }
    expect($this->cloud->logins)->toBe(2)->and($this->cache->values)->toBe([]);
});

it('reauthenticates and replays each operation once only on explicit expiry', function (string $operation) {
    $this->cloud->expiries = 1;
    if ($operation === 'devices') {
        $this->manager->devices($this->credentials);
    } else {
        $this->manager->$operation($this->credentials, $this->device);
    }
    expect($this->cloud->logins)->toBe(2)
        ->and($this->cloud->calls)->toBe([$operation, $operation])
        ->and($this->cloud->tokens)->toBe(['example-token-1', 'example-token-2']);
})->with(['devices', 'telemetry', 'rawAttributes', 'wake']);

it('evicts the second expired session without a third login or operation', function (string $operation) {
    $this->cloud->expiries = 2;
    expect(fn () => $operation === 'devices'
        ? $this->manager->devices($this->credentials)
        : $this->manager->$operation($this->credentials, $this->device))->toThrow(SessionExpired::class);
    expect($this->cloud->logins)->toBe(2)
        ->and($this->cloud->calls)->toHaveCount(2)
        ->and($this->cache->values)->toBe([]);
})->with(['devices', 'telemetry', 'rawAttributes', 'wake']);

it('never replays ambiguous wake failures', function (string $error) {
    $this->cloud->operationFailure = $error === 'transport' ? new TransportFailed : new ApiError(503, 'wake');
    expect(fn () => $this->manager->wake($this->credentials, $this->device))
        ->toThrow($error === 'transport' ? TransportFailed::class : ApiError::class);
    expect($this->cloud->calls)->toBe(['wake'])->and($this->cloud->logins)->toBe(1);
})->with(['transport', 'http']);

it('rejects a login response from the wrong region and replaces a mismatched cached session', function () {
    $this->manager->devices($this->credentials);
    $key = array_key_first($this->cache->values);
    $this->cache->values[$key]['region'] = 'US';
    $this->manager->devices($this->credentials);
    expect($this->cloud->logins)->toBe(2);
    $this->cache->values = [];
    $this->cloud->loginRegion = Region::Us;
    expect(fn () => $this->manager->devices($this->credentials))->toThrow(MalformedResponse::class);
});

it('discards corrupt sessions with safe diagnostics and logs in again', function (mixed $bad) {
    $logger = new class extends AbstractLogger
    {
        public array $records = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->records[] = [$message, $context];
        }
    };
    $manager = new SessionManager($this->cloud, new Psr16SessionStore($this->cache, $logger), $this->clock);
    $manager->devices($this->credentials);
    $key = array_key_first($this->cache->values);
    $this->cache->values[$key] = $bad;
    $manager->devices($this->credentials);
    expect($this->cloud->logins)->toBe(2)
        ->and($logger->records)->toBe([['Discarding malformed Oukitel session cache entry.', []]]);
})->with([
    ['private malformed string'], [[]],
    [['region' => 'invalid', 'access_token' => 'private', 'refresh_token' => 'private', 'expires_at' => 1]],
    [['region' => 'EU', 'access_token' => '', 'refresh_token' => 'private', 'expires_at' => 1]],
]);

it('fails closed on malformed cooldown entries', function () {
    $this->cache->values['example-key'] = 'private';
    expect(fn () => $this->store->isCoolingDown('example-key'))->toThrow(CacheFailed::class);
});

it('translates cache exceptions and false mutations without sensitive chains', function (string $operation, bool $throw) {
    $this->cache->failure = $operation;
    $this->cache->throw = $throw;
    $action = match ($operation) {
        'get' => fn () => $this->store->get('example-key'),
        'delete' => fn () => $this->store->delete('example-key'),
        default => fn () => $this->store->put('example-key', new Session(Region::Eu, 'example', 'example', 1), 60),
    };
    try {
        $action();
        test()->fail('Expected cache failure.');
    } catch (CacheFailed $error) {
        expect($error->getMessage())->not->toContain('private')
            ->and($error->getPrevious())->toBeNull();
    }
})->with([['get', true], ['delete', true], ['delete', false], ['set', true], ['set', false]]);

it('fails closed when recording a login cooldown fails', function () {
    $this->cloud->loginFailure = new AuthenticationFailed;
    $this->cache->failure = 'set';
    expect(fn () => $this->manager->devices($this->credentials))->toThrow(CacheFailed::class);
});

it('works with a real PSR-16 adapter', function () {
    $store = new Psr16SessionStore(new Psr16Cache(new ArrayAdapter));
    $manager = new SessionManager($this->cloud, $store, $this->clock);
    $manager->devices($this->credentials);
    $manager->devices($this->credentials);
    expect($this->cloud->logins)->toBe(1);
});

it('keeps default memory stores isolated and expires sessions at the exact TTL boundary', function () {
    $one = new InMemorySessionStore($this->clock);
    $two = new InMemorySessionStore($this->clock);
    $session = new Session(Region::Eu, 'example', 'example', 1_800_003_600);
    $one->put('example-key', $session, 10);
    expect($one->get('example-key'))->toBe($session)->and($two->get('example-key'))->toBeNull();
    $this->clock->timestamp += 9;
    expect($one->get('example-key'))->toBe($session);
    $this->clock->timestamp++;
    expect($one->get('example-key'))->toBeNull();
    $one->put('example-key', $session, 10);
    $one->put('example-key', $session, 0);
    expect($one->get('example-key'))->toBeNull();
    $one->coolDown('example-key', 10);
    expect($one->isCoolingDown('example-key'))->toBeTrue();
    $one->coolDown('example-key', 0);
    expect($one->isCoolingDown('example-key'))->toBeFalse();
    $one->coolDown('example-key', 10);
    $one->delete('example-key');
    expect($one->isCoolingDown('example-key'))->toBeFalse();
});
