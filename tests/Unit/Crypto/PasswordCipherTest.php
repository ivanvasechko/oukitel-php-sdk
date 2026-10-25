<?php

declare(strict_types=1);

use IvanVasechko\Oukitel\Crypto\PasswordCipher;

it('matches the deterministic AES key and IV derivation vector', function (): void {
    expect((new PasswordCipher)->encrypt('test-password', '0123456789ABCDEF'))
        ->toBe('pmYqG0PZl+ol+5uaLYDkqg==');
});

it('rejects invalid random values before encryption', function (string $random): void {
    expect(fn () => (new PasswordCipher)->encrypt('test-password', $random))
        ->toThrow(InvalidArgumentException::class);
})->with(['short', '0123456789ABCDE!', "0123456789ABCDE\n"]);
