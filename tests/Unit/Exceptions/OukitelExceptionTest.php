<?php

declare(strict_types=1);

use IvanVasechko\Oukitel\Exceptions\ApiError;
use IvanVasechko\Oukitel\Exceptions\AuthenticationFailed;
use IvanVasechko\Oukitel\Exceptions\CacheFailed;
use IvanVasechko\Oukitel\Exceptions\ConfigurationMissing;
use IvanVasechko\Oukitel\Exceptions\LoginCoolingDown;
use IvanVasechko\Oukitel\Exceptions\MalformedResponse;
use IvanVasechko\Oukitel\Exceptions\OukitelException;
use IvanVasechko\Oukitel\Exceptions\SessionExpired;
use IvanVasechko\Oukitel\Exceptions\TransportFailed;

it('offers a common catch boundary and safe actionable exception messages', function (): void {
    foreach ([new AuthenticationFailed, new CacheFailed, new ConfigurationMissing,
        new LoginCoolingDown, new SessionExpired, new TransportFailed,
        new MalformedResponse('data'), new ApiError(503, 'HTTP')] as $error) {
        expect($error)->toBeInstanceOf(OukitelException::class);
        expect($error->getMessage())->toStartWith('Oukitel ');
        expect($error->getPrevious())->toBeNull();
    }
    expect((new ApiError(503, 'HTTP'))->apiCode)->toBe(503);
});
