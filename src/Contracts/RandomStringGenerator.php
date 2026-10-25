<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Contracts;

interface RandomStringGenerator
{
    public function generate(): string;
}
