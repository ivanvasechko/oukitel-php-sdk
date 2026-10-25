<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Exceptions;

/** @experimental */
final class ControlsUnavailable extends OukitelException
{
    /** @experimental */
    public function __construct()
    {
        parent::__construct('The configured cloud transport does not support experimental controls.');
    }
}
