<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Tests\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class FrozenClock implements ClockInterface
{
    public function __construct(public int $timestamp = 1_800_000_000) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$this->timestamp);
    }
}
