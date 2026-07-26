<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

use DateTimeImmutable;

interface MyDigitalIdAccountAuthorizerInterface
{
    /** @param array{ip?:string,user_agent?:string,session_id?:string} $context */
    public function authenticate(
        MyDigitalIdVerifiedIdentity $verified,
        DateTimeImmutable $occurredAt,
        array $context = []
    ): MyDigitalIdAuthenticationDecision;
}
