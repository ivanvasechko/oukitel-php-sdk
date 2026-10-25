<?php

declare(strict_types=1);

use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\ApiError;
use IvanVasechko\Oukitel\Exceptions\AuthenticationFailed;
use IvanVasechko\Oukitel\Exceptions\MalformedResponse;
use IvanVasechko\Oukitel\Exceptions\SessionExpired;
use IvanVasechko\Oukitel\Parsing\ResponseParser;

it('parses login tokens and a numeric-string expiration', function (): void {
    $session = (new ResponseParser)->login(['code' => '200', 'data' => [
        'accessToken' => ['token' => 'fake-access', 'expirationTime' => '1900000000'],
        'refreshToken' => ['token' => 'fake-refresh'],
    ]], Region::Eu);

    expect($session->accessToken)->toBe('fake-access');
    expect($session->refreshToken)->toBe('fake-refresh');
    expect($session->expiresAt)->toBe(1_900_000_000);
});

it('rejects malformed envelopes and login data', function (mixed $payload): void {
    expect(fn () => (new ResponseParser)->login($payload, Region::Eu))->toThrow(MalformedResponse::class);
})->with([[null], [[]], [['code' => 'bad']], [['code' => 200]], [['code' => 200, 'data' => [
    'accessToken' => ['token' => '', 'expirationTime' => 100], 'refreshToken' => ['token' => 'test'],
]]], [['code' => 200, 'data' => ['accessToken' => [], 'refreshToken' => []]]]]);

it('distinguishes auth failures expiry and API failures without exposing server messages', function (): void {
    $parser = new ResponseParser;

    expect(fn () => $parser->login(['code' => 1004, 'message' => 'fake-private-marker'], Region::Eu))
        ->toThrow(AuthenticationFailed::class);
    expect(fn () => $parser->devices(['code' => 5032]))->toThrow(SessionExpired::class);
    expect(fn () => $parser->devices(['code' => 1004, 'message' => 'fake-private-marker']))
        ->toThrow(ApiError::class, 'Oukitel device list request failed (code 1004).');
    expect(fn () => $parser->decode('{broken'))->toThrow(MalformedResponse::class, 'JSON');
    $parser->assertSuccessful(['code' => 200], 'wake');
});

it('maps device online variants while dropping authKey', function (mixed $online, ?bool $expected): void {
    $devices = (new ResponseParser)->devices(['code' => 200, 'data' => ['list' => [[
        'deviceKey' => 'fake-dk', 'productKey' => 'fake-pk', 'deviceName' => 'Station A',
        'productName' => 'Model A', 'onlineStatus' => $online, 'authKey' => 'fake-private-marker',
    ]]]]);

    expect($devices[0]->isOnline)->toBe($expected);
    expect($devices[0]->id->deviceKey)->toBe('fake-dk');
    expect(json_encode($devices, JSON_THROW_ON_ERROR))->not->toContain('fake-private-marker')->not->toContain('fake-dk');
})->with([[true, true], [false, false], [1, true], [0.0, false], ['online', true], ['offline', false], ['unknown', null], [[], null], [null, null]]);

it('rejects malformed lists attributes and missing device keys', function (mixed $payload, string $method): void {
    expect(fn () => (new ResponseParser)->$method($payload))->toThrow(MalformedResponse::class);
})->with([
    [['code' => 200, 'data' => ['list' => 'bad']], 'devices'],
    [['code' => 200, 'data' => ['list' => [[]]]], 'devices'],
    [['code' => 200, 'data' => ['list' => [[12]]]], 'devices'],
    [['code' => 200, 'data' => ['customizeTslInfo' => 'bad']], 'telemetry'],
    [['code' => 200, 'data' => ['customizeTslInfo' => [[]]]], 'telemetry'],
    [['code' => 200, 'data' => ['customizeTslInfo' => [['resourceCode' => 'test', 'createTime' => []]]]], 'rawAttributes'],
]);
