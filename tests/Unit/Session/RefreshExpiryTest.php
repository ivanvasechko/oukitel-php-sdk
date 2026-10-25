<?php

use IvanVasechko\Oukitel\DTOs\Session;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\MalformedResponse;
use IvanVasechko\Oukitel\Parsing\CacheValueParser;
use IvanVasechko\Oukitel\Parsing\ResponseParser;
use IvanVasechko\Oukitel\Session\Psr16SessionStore;
use IvanVasechko\Oukitel\Tests\Support\RecordingCache;

it('retains optional refresh expiry from login and redacts both tokens', function (?string $expiry, ?int $expected) {
    $session = (new ResponseParser)->login(['code' => 200, 'data' => [
        'accessToken' => ['token' => 'example-access', 'expirationTime' => 1_800_003_600],
        'refreshToken' => ['token' => 'example-refresh', 'expirationTime' => $expiry],
    ]], Region::Eu);

    expect($session->refreshExpiresAt)->toBe($expected);
    expect(json_encode($session, JSON_THROW_ON_ERROR))->not->toContain('example-access', 'example-refresh');
    expect($session->__debugInfo()['refreshExpiresAt'])->toBe($expected);
})->with([[null, null], ['1802592000', 1_802_592_000]]);

it('round trips refresh expiry without storing a password', function () {
    $cache = new RecordingCache;
    $store = new Psr16SessionStore($cache);
    $store->put('example-key', new Session(Region::Eu, 'example-access', 'example-refresh', 1_800_003_600, 1_802_592_000), 60);

    expect($store->get('example-key')->refreshExpiresAt)->toBe(1_802_592_000);
    expect(array_keys($cache->values['example-key']))->toBe([
        'region', 'access_token', 'refresh_token', 'expires_at', 'refresh_expires_at',
    ]);
});

it('accepts legacy constructors and cached sessions without refresh expiry', function () {
    $legacy = ['region' => 'EU', 'access_token' => 'example', 'refresh_token' => 'example', 'expires_at' => 1_800_003_600];

    expect((new Session(Region::Eu, 'example', 'example', 1))->refreshExpiresAt)->toBeNull();
    expect((new CacheValueParser)->session($legacy)->refreshExpiresAt)->toBeNull();
    expect(fn () => (new CacheValueParser)->session($legacy + ['refresh_expires_at' => 'private']))
        ->toThrow(MalformedResponse::class);
});

it('rejects malformed refresh expiration rather than silently disabling it', function () {
    expect(fn () => (new ResponseParser)->login(['code' => 200, 'data' => [
        'accessToken' => ['token' => 'example', 'expirationTime' => 1_800_003_600],
        'refreshToken' => ['token' => 'example', 'expirationTime' => 'private'],
    ]], Region::Eu))->toThrow(MalformedResponse::class, 'data.refreshToken.expirationTime');
});
