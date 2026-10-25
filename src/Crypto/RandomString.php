<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Crypto;

use IvanVasechko\Oukitel\Contracts\RandomStringGenerator;

final class RandomString implements RandomStringGenerator
{
    public function generate(): string
    {
        return substr(strtr(base64_encode(random_bytes(12)), '+/', 'AZ'), 0, 16);
    }
}
