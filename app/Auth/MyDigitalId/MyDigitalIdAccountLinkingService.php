<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

use DateTimeImmutable;

final class MyDigitalIdAccountLinkingService implements MyDigitalIdAccountAuthorizerInterface
{
    private const ISSUER = 'https://sso.digital-id.my/realms/upnm';

    public function __construct(
        private readonly PdoMyDigitalIdIdentityRepository $identities,
        private readonly PdoMyDigitalIdAccountMatcher $accounts,
        private readonly MyDigitalIdIdentityProtector $protector,
        private readonly ?\Closure $notification = null
    ) {
    }

    /**
     * This service deliberately returns a decision and never creates user_tbl rows.
     *
     * @param array{ip?:string,user_agent?:string,session_id?:string} $context
     */
    public function authenticate(
        MyDigitalIdVerifiedIdentity $verified,
        DateTimeImmutable $occurredAt,
        array $context = []
    ): MyDigitalIdAuthenticationDecision {
        $normalizedNric = $this->protector->normalizedNric($verified->nric);
        $subjectHmac = $this->protector->subjectHmac(self::ISSUER, $verified->subject);
        $nricHmac = $this->protector->nricHmac($normalizedNric);
        $correlationId = bin2hex(random_bytes(16));
        $eventContext = [
            'subject_hmac' => $subjectHmac,
            'nric_hmac' => $nricHmac,
            'hmac_key_id' => $this->protector->keyId,
            'ip_hmac' => $this->protector->contextHmac('ip', (string) ($context['ip'] ?? '')),
            'user_agent_hmac' => $this->protector->contextHmac(
                'user-agent',
                (string) ($context['user_agent'] ?? '')
            ),
            'session_id_hmac' => $this->protector->contextHmac(
                'session-id',
                (string) ($context['session_id'] ?? '')
            ),
            'correlation_id' => $correlationId,
            'occurred_at' => $occurredAt,
        ];

        return $this->identities->transactional(function () use (
            $normalizedNric,
            $subjectHmac,
            $nricHmac,
            $occurredAt,
            $eventContext
        ): MyDigitalIdAuthenticationDecision {
            $link = $this->identities->findActiveBySubject($subjectHmac);
            if (is_array($link)) {
                if (!hash_equals((string) $link['nric_hmac'], $nricHmac)) {
                    return $this->reject(
                        'MYDID_IDENTITY_MISMATCH',
                        $eventContext,
                        (int) $link['identity_id'],
                        (string) $link['u_id']
                    );
                }
                $match = $this->accounts->matchLinkedUser(
                    (string) $link['u_id'],
                    $normalizedNric
                );
                if ($match->status !== 'MATCHED') {
                    $reason = $match->status === 'INACTIVE'
                        ? 'MYDID_USER_INACTIVE'
                        : 'MYDID_IDENTITY_MISMATCH';
                    return $this->reject(
                        $reason,
                        $eventContext,
                        (int) $link['identity_id'],
                        (string) $link['u_id']
                    );
                }
                return $this->succeed(
                    (int) $link['identity_id'],
                    $match,
                    $nricHmac,
                    $occurredAt,
                    $eventContext
                );
            }

            $match = $this->accounts->matchByNric($normalizedNric);
            if ($match->status !== 'MATCHED') {
                $reason = match ($match->status) {
                    'INACTIVE' => 'MYDID_USER_INACTIVE',
                    'AMBIGUOUS' => 'MYDID_IDENTITY_AMBIGUOUS',
                    default => 'MYDID_USER_NOT_FOUND',
                };
                return $this->reject($reason, $eventContext);
            }

            $userId = (string) $match->user['u_id'];
            $existingUserLink = $this->identities->findActiveByUser($userId);
            if (is_array($existingUserLink)) {
                return $this->reject(
                    'MYDID_IDENTITY_MISMATCH',
                    $eventContext,
                    (int) $existingUserLink['identity_id'],
                    $userId
                );
            }
            $identityId = $this->identities->createActiveLink(
                $userId,
                $subjectHmac,
                $nricHmac,
                $this->protector->keyId,
                $occurredAt
            );
            if ($this->notification !== null) {
                ($this->notification)('MYDIGITALID_LINKED',$userId,(string)$eventContext['correlation_id'],'link-'.$identityId,['Action time'=>$occurredAt->format('d/m/Y h:i A'),'Reference'=>(string)$eventContext['correlation_id']]);
            }
            return $this->succeed(
                $identityId,
                $match,
                $nricHmac,
                $occurredAt,
                $eventContext
            );
        });
    }

    /** @param array<string,mixed> $eventContext */
    private function succeed(
        int $identityId,
        MyDigitalIdAccountMatch $match,
        string $nricHmac,
        DateTimeImmutable $occurredAt,
        array $eventContext
    ): MyDigitalIdAuthenticationDecision {
        $user = $match->user;
        if ($user === null) {
            throw new MyDigitalIdPersistenceException('MYDID_MATCHED_USER_MISSING');
        }
        $userId = (string) $user['u_id'];
        $this->identities->touchSuccessfulLogin($identityId, $userId, $nricHmac, $occurredAt);
        $this->identities->recordEvent($eventContext + [
            'identity_id' => $identityId,
            'u_id' => $userId,
            'outcome' => 'SUCCESS',
            'reason_code' => 'MYDID_LOGIN_SUCCESS',
        ]);
        unset($user['normalized_nric']);
        return new MyDigitalIdAuthenticationDecision(
            true,
            'MYDID_LOGIN_SUCCESS',
            $user,
            $identityId
        );
    }

    /** @param array<string,mixed> $eventContext */
    private function reject(
        string $reason,
        array $eventContext,
        ?int $identityId = null,
        ?string $userId = null
    ): MyDigitalIdAuthenticationDecision {
        $this->identities->recordEvent($eventContext + [
            'identity_id' => $identityId,
            'u_id' => $userId,
            'outcome' => 'REJECTED',
            'reason_code' => $reason,
        ]);
        return new MyDigitalIdAuthenticationDecision(false, $reason, null, null);
    }
}
