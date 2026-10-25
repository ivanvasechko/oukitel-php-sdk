<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Exceptions;

final class MalformedResponse extends OukitelException
{
    public function __construct(string $field)
    {
        parent::__construct("Oukitel response field is malformed: {$field}.");
    }
}
