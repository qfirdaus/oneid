<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;

use RuntimeException;

final class HydraAdminClient implements ProviderOperations
{
    public function __construct(private readonly string $adminUrl, private readonly string $issuer)
    {
        foreach ([$adminUrl, $issuer] as $value) {
            $parts = parse_url($value);
            if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query'])
                || isset($parts['fragment']) || !isset($parts['host'])
                || !($parts['scheme'] === 'https' || ($parts['scheme'] === 'http' && $parts['host'] === '127.0.0.1'))) {
                throw new RuntimeException('MOBILE_PROVIDER_CONFIGURATION_INVALID');
            }
        }
    }

    public function login(string $challenge): array
    {
        return $this->call('/admin/oauth2/auth/requests/login', $challenge);
    }

    public function accept(string $challenge, array $identity): string
    {
        $result = $this->call('/admin/oauth2/auth/requests/login/accept', $challenge, [
            'subject' => $identity['sub'], 'remember' => false,
            'amr' => $identity['amr'], 'acr' => in_array('mydigitalid', $identity['amr'], true) ? 'urn:oneid:mydigitalid' : (count($identity['amr']) > 1 ? 'urn:oneid:pwd:mfa' : 'urn:oneid:pwd'),
            'context' => ['mobile_sid' => $identity['sid'], 'account_type' => $identity['account_type'],
                'auth_time' => $identity['auth_time']],
        ]);
        return $this->redirect($result);
    }

    public function consent(string $challenge): array
    { return $this->call('/admin/oauth2/auth/requests/consent', $challenge, null, 'consent_challenge'); }

    public function acceptConsent(string $challenge, array $session, array $scopes, array $audiences): string
    {
        return $this->redirect($this->call('/admin/oauth2/auth/requests/consent/accept', $challenge,
            ['grant_scope' => $scopes, 'grant_access_token_audience' => $audiences, 'remember' => false, 'session' => $session], 'consent_challenge'));
    }

    public function rejectLogin(string $challenge): string
    { return $this->redirect($this->call('/admin/oauth2/auth/requests/login/reject', $challenge, ['error' => 'access_denied'])); }

    public function introspect(string $token): array
    { return $this->request(rtrim($this->adminUrl, '/') . '/admin/oauth2/introspect', 'POST', http_build_query(['token' => $token, 'token_type_hint' => 'access_token']), true); }

    public function revoke(string $token, string $client): void
    { $this->request(rtrim($this->issuer, '/') . '/oauth2/revoke', 'POST', http_build_query(['token' => $token, 'client_id' => $client]), true); }

    private function redirect(array $result): string
    {
        $redirect = (string) ($result['redirect_to'] ?? '');
        $target = parse_url($redirect);
        $issuer = parse_url($this->issuer);
        if (!$target || !$issuer || isset($target['user']) || isset($target['pass'])
            || ($target['scheme'] ?? '') !== $issuer['scheme']
            || ($target['host'] ?? '') !== $issuer['host']
            || ($target['port'] ?? null) !== ($issuer['port'] ?? null)
            || ($target['path'] ?? '') !== rtrim($issuer['path'] ?? '', '/') . '/oauth2/auth') {
            throw new RuntimeException('MOBILE_PROVIDER_REDIRECT_INVALID');
        }
        return $redirect;
    }

    private function call(string $path, string $challenge, ?array $body = null, string $parameter = 'login_challenge'): array
    {
        if ($challenge === '' || strlen($challenge) > 4096) throw new RuntimeException('MOBILE_CHALLENGE_INVALID');
        return $this->request(rtrim($this->adminUrl, '/') . $path . '?' . $parameter . '=' . rawurlencode($challenge),
            $body === null ? 'GET' : 'PUT', $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function request(string $url, string $method, ?string $body, bool $form = false): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 10, CURLOPT_PROXY => '',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Content-Type: ' . ($form ? 'application/x-www-form-urlencoded' : 'application/json'), 'Accept: application/json'],
            CURLOPT_CUSTOMREQUEST => $method]);
        if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        $raw = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        // Never put URLs, body, challenges or upstream diagnostics in public exceptions.
        if (!is_string($raw) || $status !== 200 || strlen($raw) > 262144) {
            throw new RuntimeException('MOBILE_PROVIDER_UNAVAILABLE');
        }
        $result = $raw === '' ? [] : json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($result)) throw new RuntimeException('MOBILE_PROVIDER_RESPONSE_INVALID');
        return $result;
    }
}
