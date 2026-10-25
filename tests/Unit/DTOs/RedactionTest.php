<?php

declare(strict_types=1);

use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\Device;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\Session;
use IvanVasechko\Oukitel\Enums\Region;

it('redacts credentials tokens and device identifiers from JSON and debug output', function (): void {
    $objects = [
        new Credentials(Region::Eu, 'fake-login@example.test', 'fake-password-marker'),
        new Session(Region::Eu, 'fake-access-marker', 'fake-refresh-marker', 1_900_000_000),
        new Device(new DeviceId('fake-device-marker', 'fake-product-marker'), 'Station A', null, true),
    ];

    foreach ($objects as $object) {
        ob_start();
        var_dump($object);
        $dump = ob_get_clean();
        $text = json_encode($object, JSON_THROW_ON_ERROR).print_r($object, true).$dump;

        foreach (['fake-login@example.test', 'fake-password-marker', 'fake-access-marker',
            'fake-refresh-marker', 'fake-device-marker', 'fake-product-marker'] as $secret) {
            expect($text)->not->toContain($secret);
        }
    }
});
