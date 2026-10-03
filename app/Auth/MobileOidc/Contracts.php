<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;

interface IdentitySource
{
    /** Internal server-only records; includes hash/material, never send to clients. */
    public function candidates(string $identifier): array;
    public function account(string $userId): ?array;
    public function policy(array $account): array;
    /** Atomic shared OneID TOTP replay protection, within StateStore transaction. */
    public function consumeTotp(array $factor, int $step): bool;
    public function available(): bool;
}

interface StateStore
{
    /** Serialize state changes; SQL implementation MUST share PDO with IdentitySource. */
    public function transaction(callable $work): mixed;
    public function get(string $key): ?array;
    public function put(string $key, array $value): void;
}

interface PasswordIdentitySource extends IdentitySource
{
    /** Caller holds identity/state transaction and has verified password + required MFA. */
    public function changePassword(array $account, string $newPassword): ?string;
}

interface ProviderOperations extends ProviderAdmin
{
    public function consent(string $challenge): array;
    public function acceptConsent(string $challenge, array $session, array $scopes, array $audiences): string;
    public function rejectLogin(string $challenge): string;
    public function introspect(string $token): array;
    public function revoke(string $token, string $client): void;
}

interface ProviderAdmin
{
    public function login(string $challenge): array;
    public function accept(string $challenge, array $identity): string;
}

interface OtpDelivery
{
    /** Server-side delivery only. Never return OTP to browser/mobile. */
    public function send(string $destination, string $displayName, string $otp): bool;
}

interface MobileMyDigitalIdAccounts
{
    /** Server-only proof and candidate IDs; never expose to the browser. */
    public function resolve(\OneId\App\Auth\MyDigitalId\MyDigitalIdVerifiedIdentity $verified): array;
    /** Called under the adapter's shared PDO transaction before account acceptance. */
    public function allows(string $userId, array $proof): bool;
}
