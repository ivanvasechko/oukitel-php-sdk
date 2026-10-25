<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Crypto;

use SensitiveParameter;

final class LoginSignature
{
    public function sign(
        #[SensitiveParameter] string $login,
        #[SensitiveParameter] string $encryptedPassword,
        string $random,
        #[SensitiveParameter] string $userDomainSecret,
    ): string {
        return hash('sha256', $login.$encryptedPassword.$random.$userDomainSecret);
    }
}
