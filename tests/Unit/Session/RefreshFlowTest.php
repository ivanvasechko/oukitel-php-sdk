<?php

use Http\Mock\Client;
use IvanVasechko\Oukitel\Config\ClientOptions;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\Session;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\AuthenticationFailed;
use IvanVasechko\Oukitel\Exceptions\LoginCoolingDown;
use IvanVasechko\Oukitel\Exceptions\MalformedResponse;
use IvanVasechko\Oukitel\Exceptions\RefreshInProgress;
use IvanVasechko\Oukitel\Exceptions\RefreshUnavailable;
use IvanVasechko\Oukitel\Exceptions\SessionExpired;
use IvanVasechko\Oukitel\Exceptions\TransportFailed;
use IvanVasechko\Oukitel\Http\AcceleronixCloudClient;
use IvanVasechko\Oukitel\Session\InMemorySessionStore;
use IvanVasechko\Oukitel\Session\Psr16SessionStore;
use IvanVasechko\Oukitel\Session\SessionManager;
use IvanVasechko\Oukitel\Testing\FakeOukitelCloud;
use IvanVasechko\Oukitel\Tests\Support\FrozenClock;
use IvanVasechko\Oukitel\Tests\Support\RecordingCache;
use IvanVasechko\Oukitel\Tests\Support\ScriptedCloud;
use Nyholm\Psr7\Factory\Psr17Factory;

beforeEach(function () {
    $this->clock = new FrozenClock;
    $this->cloud = new ScriptedCloud;
    $this->cloud->refreshExpiresAt = 1_800_010_000;
    $this->cache = new RecordingCache;
    $this->store = new Psr16SessionStore($this->cache);
    $this->manager = new SessionManager($this->cloud, $this->store, $this->clock, new ClientOptions(useRefreshToken: true));
    $this->credentials = new Credentials(Region::Eu, 'USER@example.test', 'example-password');
    $this->key = hash('sha256', 'oukitel.session|EU|user@example.test');
    $this->lockKey = hash('sha256', 'oukitel.session:refreshing|EU|user@example.test');
});

it('retains refresh material beyond access expiry and refreshes exactly at the margin', function () {
    $this->manager->devices($this->credentials);
    expect($this->cache->ttls[$this->key])->toBe(10_000);
    $this->clock->timestamp += 3479;
    $this->manager->devices($this->credentials);
    expect($this->cloud->refreshes)->toBe(0);
    $this->clock->timestamp++;
    $this->manager->devices($this->credentials);
    $this->manager->devices($this->credentials);

    expect($this->cloud->logins)->toBe(1);
    expect($this->cloud->refreshes)->toBe(1);
    expect($this->cloud->tokens)->toBe(['example-token-1', 'example-token-1', 'example-refreshed-1', 'example-refreshed-1']);
    expect($this->cache->ttls[$this->key])->toBe(6520);
    expect($this->cache->values[$this->key]['refresh_token'])->toBe('example-rotated-refresh');
    expect(json_encode($this->cache->values, JSON_THROW_ON_ERROR))->not->toContain('example-password');
    expect(array_keys($this->cache->values))->toBe([$this->key]);
});

it('logs in instead when refresh is disabled or known to be expired', function (bool $enabled) {
    $this->cloud->refreshExpiresAt = 1_800_000_100;
    $manager = new SessionManager($this->cloud, $this->store, $this->clock, new ClientOptions(useRefreshToken: $enabled));
    $manager->devices($this->credentials);
    $this->clock->timestamp += 3480;
    $this->cloud->expiresAt = $this->clock->timestamp + 3600;
    $manager->devices($this->credentials);

    expect($this->cloud->logins)->toBe(2);
    expect($this->cloud->refreshes)->toBe(0);
})->with([true, false]);

it('uses an unknown refresh expiry only while the access token has not expired', function (int $elapsed, int $refreshes) {
    $this->cloud->refreshExpiresAt = null;
    $this->manager->devices($this->credentials);
    expect($this->cache->ttls[$this->key])->toBe(3600);
    $this->clock->timestamp += $elapsed;
    $this->cloud->expiresAt = $this->clock->timestamp + 3600;
    $this->manager->devices($this->credentials);

    expect($this->cloud->refreshes)->toBe($refreshes);
    expect($this->cloud->logins)->toBe(2 - $refreshes);
})->with([[3480, 1], [3600, 0]]);

it('falls back to login only for refresh authentication errors or vendor 5032', function (string $failure) {
    $this->manager->devices($this->credentials);
    $this->clock->timestamp += 3480;
    $this->cloud->expiresAt = $this->clock->timestamp + 3600;
    $this->cloud->refreshFailure = $failure === 'auth' ? new AuthenticationFailed : new SessionExpired;
    $this->manager->devices($this->credentials);

    expect($this->cloud->refreshes)->toBe(1);
    expect($this->cloud->logins)->toBe(2);
    expect($this->cloud->tokens)->toBe(['example-token-1', 'example-token-2']);
    expect(array_keys($this->cache->values))->toBe([$this->key]);
})->with(['auth', '5032']);

it('applies login cooldown if authentication also fails after refresh', function () {
    $this->manager->devices($this->credentials);
    $this->clock->timestamp += 3480;
    $this->cloud->refreshFailure = new SessionExpired;
    $this->cloud->loginFailure = new AuthenticationFailed;

    expect(fn () => $this->manager->devices($this->credentials))->toThrow(AuthenticationFailed::class);
    expect(fn () => $this->manager->devices($this->credentials))->toThrow(LoginCoolingDown::class);
    expect($this->cloud->refreshes)->toBe(1);
    expect($this->cloud->logins)->toBe(2);
    expect(array_keys($this->cache->values))->toBe([hash('sha256', 'oukitel.login-cooldown|EU|user@example.test')]);
});

it('propagates non-authentication refresh errors and releases the lease without logging in', function (string $failure) {
    $this->manager->devices($this->credentials);
    $this->clock->timestamp += 3480;
    $error = $failure === 'transport' ? new TransportFailed : new MalformedResponse('refresh');
    $this->cloud->refreshFailure = $error;

    expect(fn () => $this->manager->devices($this->credentials))->toThrow($error::class);
    expect($this->cloud->logins)->toBe(1);
    expect($this->cloud->refreshes)->toBe(1);
    expect(array_keys($this->cache->values))->toBe([$this->key]);
})->with(['transport', 'malformed']);

it('rejects refreshed sessions with wrong regions or already expired access tokens', function (bool $wrongRegion) {
    $this->manager->devices($this->credentials);
    $this->clock->timestamp += 3480;
    $this->cloud->refreshSession = new Session($wrongRegion ? Region::Us : Region::Eu, 'example', 'example',
        $wrongRegion ? 1_800_007_200 : $this->clock->timestamp);

    expect(fn () => $this->manager->devices($this->credentials))->toThrow(MalformedResponse::class, 'refresh session');
    expect($this->cloud->logins)->toBe(1);
    expect(array_keys($this->cache->values))->toBe([$this->key]);
})->with([true, false]);

it('fails explicitly while another worker owns the lease and permits refresh after its TTL', function () {
    $store = new InMemorySessionStore($this->clock);
    $manager = new SessionManager($this->cloud, $store, $this->clock, new ClientOptions(useRefreshToken: true));
    $manager->devices($this->credentials);
    $this->clock->timestamp += 3480;
    $store->acquireRefreshLock($this->lockKey, 30);

    expect(fn () => $manager->devices($this->credentials))->toThrow(RefreshInProgress::class);
    expect($this->cloud->refreshes)->toBe(0);
    expect($this->cloud->logins)->toBe(1);
    $this->clock->timestamp += 30;
    $manager->devices($this->credentials);
    expect($this->cloud->refreshes)->toBe(1);
});

it('reuses another workers fresh result instead of refreshing again', function (bool $lostLease) {
    $this->manager->devices($this->credentials);
    $this->clock->timestamp += 3480;
    $this->cache->afterSet = function (string $key) use ($lostLease): void {
        if ($key === $this->lockKey) {
            $this->cache->values[$this->key]['expires_at'] = 1_800_007_200;
            if ($lostLease) {
                $this->cache->values[$key] = str_repeat('a', 32);
            }
        }
    };
    $this->manager->devices($this->credentials);

    expect($this->cloud->refreshes)->toBe(0);
    expect($this->cloud->logins)->toBe(1);
    if ($lostLease) {
        expect($this->cache->values[$this->lockKey])->toBe(str_repeat('a', 32));
    } else {
        expect(array_keys($this->cache->values))->toBe([$this->key]);
    }
})->with([true, false]);

it('does not send any HTTP request to an unverified refresh endpoint', function () {
    $factory = new Psr17Factory;
    $http = new Client($factory);
    $cloud = new AcceleronixCloudClient($http, $factory, $factory, []);
    $session = new Session(Region::Eu, 'example', 'example', 1);

    expect(fn () => $cloud->refresh($session))->toThrow(RefreshUnavailable::class);
    expect(fn () => new AcceleronixCloudClient($http, $factory, $factory, [], new ClientOptions(useRefreshToken: true)))
        ->toThrow(RefreshUnavailable::class);
    expect($http->getRequests())->toHaveCount(0);
});

it('provides deterministic refresh success and explicit refresh failures in the shipped fake', function () {
    $fake = new FakeOukitelCloud($this->clock);
    $session = $fake->login($this->credentials);
    $refreshed = $fake->refresh($session);

    expect($refreshed->accessToken)->toBe('fake-refreshed-access-token');
    expect($refreshed->refreshExpiresAt)->toBe(1_802_592_000);
    expect($fake->refreshCount())->toBe(1);
    $fake->failRefreshWith(new SessionExpired);
    expect(fn () => $fake->refresh($session))->toThrow(SessionExpired::class);
    expect($fake->refreshCount())->toBe(2);
});
