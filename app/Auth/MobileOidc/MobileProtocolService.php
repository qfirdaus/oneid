<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;

use RuntimeException;

final class MobileProtocolService
{
    public function __construct(private readonly MobileIdentityAdapter $adapter,
        private readonly ProviderOperations $provider, private readonly array $clients,
        private readonly string $hookKey, private readonly string $audience = 'oneid-mobile-session')
    { if (strlen($hookKey) < 32) throw new RuntimeException('MOBILE_HOOK_KEY_INVALID'); }

    public function consent(string $challenge): string
    {
        $consent = $this->provider->consent($challenge);
        $client = (string) ($consent['client']['client_id'] ?? '');
        $scopes = $consent['requested_scope'] ?? [];
        $audiences = $consent['requested_access_token_audience'] ?? [];
        if (!isset($this->clients[$client]) || !is_array($scopes) || !is_array($audiences)
            || !in_array('openid', $scopes, true) || !in_array('mobile:session', $scopes, true)
            || array_diff($scopes, ['openid','profile','offline_access','mobile:session']) !== []
            || $audiences !== [$this->audience]) throw new RuntimeException('MOBILE_CONSENT_DENIED');
        $sid = (string) ($consent['context']['mobile_sid'] ?? '');
        $subject = (string) ($consent['subject'] ?? '');
        $profile = $this->adapter->session($sid, $client, $subject);
        if (isset($profile['error'])) throw new RuntimeException('MOBILE_CONSENT_DENIED');
        // First-party app: no additional account approval or registration.
        // Context comes from accepted provider login, never from browser profile fields.
        $claims = ['account_type' => $profile['account_type']];
        if (in_array('profile', $scopes, true)) $claims['name'] = $profile['name'];
        return $this->provider->acceptConsent($challenge, [
            'access_token' => ['mobile_sid' => $sid, 'mobile_audience' => $this->audience, 'mobile_scopes' => $scopes], 'id_token' => $claims,
        ], $scopes, $audiences);
    }

    public function hook(string $providedKey, array $payload): array
    {
        if (!hash_equals($this->hookKey, $providedKey)) return ['status' => 403];
        $client = $payload['request']['client_id'] ?? '';
        $grant = $payload['request']['grant_types'] ?? [];
        $sid = $payload['session']['extra']['mobile_sid'] ?? '';
        $sub = $payload['session']['id_token']['subject'] ?? '';
        if (!is_string($client) || !isset($this->clients[$client]) || !is_string($sid) || !is_string($sub)
            || !in_array($grant, [['authorization_code'], ['refresh_token']], true)
            || ($payload['session']['client_id'] ?? '') !== $client
            // Hydra invokes this hook before populating granted_scopes/audience on code exchange.
            // Use the immutable consent-issued session context, not untrusted token POST fields.
            || ($payload['session']['extra']['mobile_audience'] ?? '') !== $this->audience
            || !in_array('mobile:session', $payload['session']['extra']['mobile_scopes'] ?? [], true)) return ['status' => 403];
        $profile = $this->adapter->session($sid, $client, $sub);
        if (($profile['error'] ?? '') === 'MOBILE_UNAVAILABLE') return ['status' => 503];
        return ['status' => isset($profile['error']) ? 403 : 204];
    }

    public function status(string $bearer, bool $logout = false): array
    {
        if (!preg_match('/\ABearer ([^\s]{1,4096})\z/', $bearer, $match)) return ['status' => 401, 'body' => ['error' => 'SESSION_INVALID']];
        $token = $this->provider->introspect($match[1]);
        $client = $token['client_id'] ?? '';
        if (($token['active'] ?? false) !== true || ($token['token_use'] ?? '') !== 'access_token'
            || !is_string($client) || !isset($this->clients[$client])
            || !in_array($this->audience, $token['aud'] ?? [], true)
            || !in_array('mobile:session', explode(' ', (string) ($token['scope'] ?? '')), true)
            || !is_string($token['ext']['mobile_sid'] ?? null) || !is_string($token['sub'] ?? null)) {
            return ['status' => 401, 'body' => ['error' => 'SESSION_INVALID']];
        }
        $sid = $token['ext']['mobile_sid']; $sub = $token['sub'];
        $profile = $this->adapter->session($sid, $client, $sub);
        if (isset($profile['error'])) return ['status' => $profile['error'] === 'MOBILE_UNAVAILABLE' ? 503 : 401,
            'body' => ['error' => $profile['error'] === 'MOBILE_UNAVAILABLE' ? 'TEMPORARILY_UNAVAILABLE' : 'SESSION_INVALID']];
        if ($logout) {
            if (!$this->adapter->revokeSession($sid, $client, $sub)) return ['status' => 503];
            // Local invalidation is authoritative even if provider revoke is temporarily unavailable.
            try { $this->provider->revoke($match[1], $client); } catch (\Throwable) {}
            return ['status' => 204];
        }
        return ['status' => 200, 'body' => ['session_status' => 'active', 'sub' => $sub,
            'account_type' => $profile['account_type'], 'display_name' => $profile['name'],
            'full_name'=>$profile['full_name'], 'staff_number'=>$profile['staff_number'],
            'staff_number_short'=>$profile['staff_number_short'], 'student_matric_number'=>$profile['student_matric_number'],
            'email'=>$profile['email'], 'department'=>$profile['department'], 'job_title'=>$profile['job_title']]];
    }
}
