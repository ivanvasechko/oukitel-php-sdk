<?php

declare(strict_types=1);

use IvanVasechko\Oukitel\Clock\SystemClock;

it('returns current UTC time', function (): void {
    $before = time();
    $now = (new SystemClock)->now();

    expect($now->getTimestamp())->toBeGreaterThanOrEqual($before)->toBeLessThanOrEqual(time());
    expect($now->getTimezone()->getName())->toBe('UTC');
});
