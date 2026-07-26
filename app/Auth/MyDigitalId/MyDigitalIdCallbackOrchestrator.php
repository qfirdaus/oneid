<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

use DateTimeImmutable;

final class MyDigitalIdCallbackOrchestrator
{
    public function __construct(
        private readonly MyDigitalIdAuthorizationTransactionStore $transactions,
        private readonly MyDigitalIdProtocolGatewayInterface $protocol,
        private readonly MyDigitalIdAccountAuthorizerInterface $accounts,
        private readonly MyDigitalIdLocalLoginFinalizerInterface $finalizer
    ) {
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $session
     * @return array{allowed:bool,reason:string,redirect_path:?string}
     */
    public function handle(
        string $method,
        array $query,
        array &$session,
        int $now,
        string $ip,
        string $userAgent,
        string $deviceInfo
    ): array {
        $request = MyDigitalIdCallbackRequest::fromHttp($method, $query);
        $transaction = $this->transactions->consume($session, $request->state, $now);
        $verified = $this->protocol->complete($request, $transaction);
        $decision = $this->accounts->authenticate(
            $verified,
            (new DateTimeImmutable())->setTimestamp($now),
            [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'session_id' => session_id(),
            ]
        );
        if (!$decision->allowed || $decision->user === null) {
            return [
                'allowed' => false,
                'reason' => $decision->reason,
                'redirect_path' => null,
            ];
        }

        $this->finalizer->finalize($decision->user, $deviceInfo);
        $session['mydigitalid_id_token'] = $verified->idToken;
        return [
            'allowed' => true,
            'reason' => 'MYDID_LOGIN_SUCCESS',
            'redirect_path' => $transaction->returnPath,
        ];
    }
}
