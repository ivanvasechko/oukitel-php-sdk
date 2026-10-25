<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Exceptions;

final class RefreshInProgress extends OukitelException
{
    public function __construct()
    {
        parent::__construct('Another worker is refreshing the session. Retry the operation later.');
    }
}
