<?php

declare(strict_types=1);

use IvanVasechko\Oukitel\Crypto\RandomString;

it('generates sixteen protocol-compatible alphanumeric characters', function (): void {
    expect((new RandomString)->generate())->toMatch('/^[A-Za-z0-9]{16}$/D');
});
