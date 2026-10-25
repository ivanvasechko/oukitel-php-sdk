<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Crypto;

use InvalidArgumentException;
use SensitiveParameter;

final class PasswordCipher
{
    public function encrypt(#[SensitiveParameter] string $password, string $random): string
    {
        if (preg_match('/^[A-Za-z0-9]{16}$/D', $random) !== 1) {
            throw new InvalidArgumentException('Oukitel login random must contain 16 alphanumeric characters.');
        }
        $digest = strtoupper(md5($random));
        $key = substr($digest, 8, 16);
        $iv = substr($digest, 16, 8).substr($digest, 8, 8);
        $encrypted = openssl_encrypt($password, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new InvalidArgumentException('Oukitel password encryption failed.');
        }

        return base64_encode($encrypted);
    }
}
