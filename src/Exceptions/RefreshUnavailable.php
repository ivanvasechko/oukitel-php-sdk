<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Exceptions;

final class RefreshUnavailable extends OukitelException
{
    public function __construct()
    {
        parent::__construct('The refresh endpoint is unverified. Enable refresh only with a verified custom cloud transport.');
    }
}
