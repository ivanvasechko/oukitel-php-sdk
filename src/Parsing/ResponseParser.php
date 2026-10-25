<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Parsing;

use IvanVasechko\Oukitel\DTOs\Device;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\Session;
use IvanVasechko\Oukitel\DTOs\Telemetry;
use IvanVasechko\Oukitel\DTOs\TslAttribute;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\ApiError;
use IvanVasechko\Oukitel\Exceptions\AuthenticationFailed;
use IvanVasechko\Oukitel\Exceptions\MalformedResponse;
use IvanVasechko\Oukitel\Exceptions\SessionExpired;
use JsonException;

final class ResponseParser
{
    public function __construct(
        private readonly TelemetryParser $telemetry = new TelemetryParser,
        private readonly Redactor $redactor = new Redactor,
    ) {}

    public function decode(string $json): mixed
    {
        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new MalformedResponse('JSON');
        }
    }

    public function login(mixed $payload, Region $region): Session
    {
        $data = $this->data($payload, 'login', true);
        $access = Values::map($data['accessToken'] ?? null, 'data.accessToken');
        $refresh = Values::map($data['refreshToken'] ?? null, 'data.refreshToken');
        $token = $access['token'] ?? null;
        $refreshToken = $refresh['token'] ?? null;
        $expiresAt = Values::integer($access['expirationTime'] ?? null, 'data.accessToken.expirationTime');
        if (! is_string($token) || $token === '' || ! is_string($refreshToken) || $refreshToken === '' || $expiresAt === null) {
            throw new MalformedResponse('data.accessToken');
        }

        return new Session($region, $token, $refreshToken, $expiresAt,
            Values::integer($refresh['expirationTime'] ?? null, 'data.refreshToken.expirationTime'));
    }

    /** @return list<Device> */
    public function devices(mixed $payload): array
    {
        $data = $this->data($payload, 'device list');
        $list = $data['list'] ?? null;
        if (! is_array($list) || ! array_is_list($list)) {
            throw new MalformedResponse('data.list');
        }
        $devices = [];
        foreach ($list as $item) {
            $device = Values::map($item, 'data.list.device');
            $key = $device['deviceKey'] ?? null;
            $product = $device['productKey'] ?? null;
            $name = $device['deviceName'] ?? 'Oukitel Power Station';
            $productName = $device['productName'] ?? null;
            if (! is_string($key) || $key === '' || ! is_string($product) || $product === ''
                || ! is_string($name) || ($productName !== null && ! is_string($productName))) {
                throw new MalformedResponse('data.list.device');
            }
            $devices[] = new Device(new DeviceId($key, $product), $name, $productName, $this->online($device));
        }

        return $devices;
    }

    public function telemetry(mixed $payload): Telemetry
    {
        $data = $this->data($payload, 'telemetry');

        return $this->telemetry->parse(
            Values::map($data['deviceData'] ?? [], 'data.deviceData'),
            $this->attributes($data['customizeTslInfo'] ?? []),
        );
    }

    /** @return list<TslAttribute> */
    public function rawAttributes(mixed $payload): array
    {
        $data = $this->data($payload, 'raw attributes');

        return $this->attributes($data['customizeTslInfo'] ?? []);
    }

    public function assertSuccessful(mixed $payload, string $operation): void
    {
        $this->envelope($payload, $operation);
    }

    /** @return array<string, mixed> */
    private function envelope(mixed $payload, string $operation, bool $login = false): array
    {
        $envelope = Values::map($payload, 'response');
        $code = Values::integer($envelope['code'] ?? null, 'code');
        if ($code === null) {
            throw new MalformedResponse('code');
        }
        if ($code === 5032) {
            throw new SessionExpired;
        }
        if ($code !== 200) {
            if ($login) {
                throw new AuthenticationFailed;
            }
            throw new ApiError($code, $operation);
        }

        return $envelope;
    }

    /** @return array<string, mixed> */
    private function data(mixed $payload, string $operation, bool $login = false): array
    {
        return Values::map($this->envelope($payload, $operation, $login)['data'] ?? null, 'data');
    }

    /** @return list<TslAttribute> */
    private function attributes(mixed $items): array
    {
        if (! is_array($items) || ! array_is_list($items)) {
            throw new MalformedResponse('data.customizeTslInfo');
        }
        $attributes = [];
        foreach ($items as $item) {
            $item = Values::map($item, 'customizeTslInfo.item');
            $code = $item['resourceCode'] ?? null;
            $type = $item['dataType'] ?? 'STRING';
            $time = $item['createTime'] ?? null;
            if (! is_string($code) || $code === '' || ! is_string($type)
                || ($time !== null && ! is_string($time) && ! is_int($time))) {
                throw new MalformedResponse('customizeTslInfo.item');
            }
            $attributes[] = new TslAttribute(
                $code, $this->redactor->value($item['resourceValce'] ?? null, $code),
                $type, array_key_exists('resourceValce', $item), $time,
            );
        }

        return $attributes;
    }

    /** @param array<string, mixed> $device */
    private function online(array $device): ?bool
    {
        foreach (['onlineStatus', 'isOnline', 'deviceStatus', 'online', 'status'] as $key) {
            $value = $device[$key] ?? null;
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                return $value;
            }
            if ((is_int($value) || is_float($value)) && in_array($value, [0, 1, 0.0, 1.0], true)) {
                return (int) $value === 1;
            }
            if (is_string($value)) {
                return match (strtolower(trim($value))) {
                    '1', 'true', 'online' => true,
                    '0', 'false', 'offline' => false,
                    default => null,
                };
            }

            return null;
        }

        return null;
    }
}
