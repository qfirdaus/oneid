<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

final class UserMfaPrimaryAuthDecision
{
    public function __construct(
        private readonly PdoUserMfaPolicyReader $policies,
        private readonly UserMfaPendingLoginCoordinator $pending
    ) {
    }

    /** @return array<string, mixed> */
    public function afterPasswordAccepted(
        string $userId,
        string $sessionId,
        string $userAgent,
        string $ipAddress,
        string $runtimeMode
    ): array {
        $this->policies->assertRuntimeParity($runtimeMode);
        $policy = $this->policies->policy();
        $pilot = $policy->mode === 'PILOT_ENFORCED'
            && $this->policies->pilotEligible($userId);
        return $this->pending->begin(
            $userId,
            'PASSWORD',
            $sessionId,
            $userAgent,
            $ipAddress,
            $policy,
            $pilot
        );
    }
}
