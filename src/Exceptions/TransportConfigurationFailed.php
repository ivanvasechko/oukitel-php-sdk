<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Exceptions;

final class TransportConfigurationFailed extends OukitelException
{
    public function __construct()
    {
        parent::__construct('Unable to discover and configure HTTP transport. Install Guzzle with a PSR-17 implementation, or inject a PSR-18 client and PSR-17 factories. Unsupported discovered clients (including Symfony before 8.1) require explicit injection with configured timeouts.');
    }
}
