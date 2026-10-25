<?php

declare(strict_types=1);

use IvanVasechko\Oukitel\Crypto\LoginSignature;

it('signs fields in the protocol order with the deterministic vector', function (): void {
    expect((new LoginSignature)->sign('user@example.test', 'encrypted-password', '0123456789ABCDEF', 'domain-secret'))
        ->toBe('bf8d70039b37ce128e6e58a3a341c5a691296c47e19d1ff4e98463d0457a4829');
});
