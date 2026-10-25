<?php

declare(strict_types=1);

use IvanVasechko\Oukitel\Config\ClientOptions;

it('provides bounded protocol defaults', function (): void {
    $options = new ClientOptions;

    expect([$options->timeoutSeconds, $options->connectTimeoutSeconds, $options->retryTimes,
        $options->retryDelayMilliseconds, $options->refreshBeforeSeconds, $options->loginFailureCooldownMinutes])
        ->toBe([10, 3, 3, 250, 120, 5]);
    expect($options->userAgent)->toBe('okhttp/4.9.3');
});

it('rejects invalid options', function (array $options): void {
    expect(fn () => new ClientOptions(...$options))->toThrow(InvalidArgumentException::class);
})->with([
    [['timeoutSeconds' => 0]], [['connectTimeoutSeconds' => 0]], [['retryTimes' => 0]],
    [['retryDelayMilliseconds' => -1]], [['refreshBeforeSeconds' => -1]],
    [['loginFailureCooldownMinutes' => -1]], [['userAgent' => '']], [['userAgent' => "agent\r\nsecret"]],
]);
