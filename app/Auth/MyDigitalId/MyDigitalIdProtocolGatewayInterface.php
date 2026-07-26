<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

interface MyDigitalIdProtocolGatewayInterface
{
    public function complete(
        MyDigitalIdCallbackRequest $request,
        MyDigitalIdAuthorizationTransaction $transaction
    ): MyDigitalIdVerifiedIdentity;
}
