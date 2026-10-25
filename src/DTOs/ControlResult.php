<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\DTOs;

/** @experimental Cloud telemetry confirmation, not proof of physical output state. */
final readonly class ControlResult
{
    /**
     * @experimental
     *
     * @param  array<string, mixed>  $observed
     */
    public function __construct(public bool $confirmed, public array $observed) {}
}
