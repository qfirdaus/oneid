<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

use Jumbojett\OpenIDConnectClient;

final class MyDigitalIdProtocolGateway implements MyDigitalIdProtocolGatewayInterface
{
    public function __construct(private readonly MyDigitalIdProtocolClient $factory)
    {
    }

    public function complete(
        MyDigitalIdCallbackRequest $request,
        MyDigitalIdAuthorizationTransaction $transaction
    ): MyDigitalIdVerifiedIdentity {
        $client = $this->seededClient($transaction);
        $originalRequest = $_REQUEST;
        $_REQUEST = ['code' => $request->code, 'state' => $request->state];

        try {
            if (!$client->authenticate()) {
                throw new MyDigitalIdConfigurationException('MYDID_PROTOCOL_INCOMPLETE');
            }
            $idHeader = $client->getIdTokenHeader();
            if (
                !is_object($idHeader)
                || ($idHeader->alg ?? null) !== 'RS256'
                || !is_string($idHeader->kid ?? null)
                || trim($idHeader->kid) === ''
            ) {
                throw new MyDigitalIdConfigurationException('MYDID_TOKEN_ALGORITHM_INVALID');
            }
            $idClaims = $client->getIdTokenPayload();
            $userInfo = $client->requestUserInfo();
            if (!is_object($idClaims) || !is_object($userInfo)) {
                throw new MyDigitalIdConfigurationException('MYDID_CLAIMS_INVALID');
            }

            $idSubject = $this->claim($idClaims, 'sub');
            $userSubject = $this->claim($userInfo, 'sub');
            $tokenNonce = $this->claim($idClaims, 'nonce');
            if (!hash_equals($idSubject, $userSubject)) {
                throw new MyDigitalIdConfigurationException('MYDID_SUBJECT_MISMATCH');
            }
            if (!hash_equals($transaction->nonce, $tokenNonce)) {
                throw new MyDigitalIdConfigurationException('MYDID_NONCE_MISMATCH');
            }
            $this->assertRequiredTimeClaims($idClaims);

            return new MyDigitalIdVerifiedIdentity(
                $idSubject,
                $this->claim($userInfo, 'nama'),
                preg_replace('/\D+/', '', $this->claim($userInfo, 'nric')) ?? '',
                (string) $client->getIdToken()
            );
        } finally {
            $_REQUEST = $originalRequest;
            unset(
                $_SESSION['openid_connect_state'],
                $_SESSION['openid_connect_nonce'],
                $_SESSION['openid_connect_code_verifier']
            );
        }
    }

    private function seededClient(MyDigitalIdAuthorizationTransaction $transaction): OpenIDConnectClient
    {
        $base = $this->factory->build();
        $_SESSION['openid_connect_state'] = $transaction->state;
        $_SESSION['openid_connect_nonce'] = $transaction->nonce;
        $_SESSION['openid_connect_code_verifier'] = $transaction->codeVerifier;

        return $base;
    }

    private function claim(object $claims, string $name): string
    {
        $value = $claims->{$name} ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new MyDigitalIdConfigurationException('MYDID_REQUIRED_CLAIM_MISSING');
        }
        return trim($value);
    }

    private function assertRequiredTimeClaims(object $claims): void
    {
        $now = time();
        if (
            !isset($claims->exp, $claims->iat)
            || !is_int($claims->exp)
            || !is_int($claims->iat)
            || $claims->exp < ($now - 60)
            || $claims->iat > ($now + 60)
            || (isset($claims->nbf) && (!is_int($claims->nbf) || $claims->nbf > ($now + 60)))
        ) {
            throw new MyDigitalIdConfigurationException('MYDID_TOKEN_TIME_CLAIMS_INVALID');
        }
    }
}
