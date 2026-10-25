<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Exceptions;

final class ApiError extends OukitelException
{
    public function __construct(public readonly int $apiCode, string $operation)
    {
        parent::__construct("Oukitel {$operation} request failed (code {$apiCode}).");
    }
}
