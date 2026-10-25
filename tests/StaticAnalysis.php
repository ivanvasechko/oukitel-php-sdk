<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Tests;

final class StaticAnalysis
{
    public static function manifestPath(): string
    {
        return dirname(__DIR__).'/composer.json';
    }
}
