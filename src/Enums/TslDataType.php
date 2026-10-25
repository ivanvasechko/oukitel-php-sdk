<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Enums;

enum TslDataType: string
{
    case Int = 'INT';
    case Bool = 'BOOL';
    case Float = 'FLOAT';
    case Double = 'DOUBLE';
    case Decimal = 'DECIMAL';
    case Struct = 'STRUCT';
    case String = 'STRING';
    case Unknown = 'UNKNOWN';
}
