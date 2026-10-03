<?php
declare(strict_types=1);
namespace OneId\Tests\MobileOidc;

require_once dirname(__DIR__, 2) . '/app/Auth/MobileOidc/bootstrap.php';

use OneId\App\Auth\MobileOidc\{IdentitySource, StateStore, ProviderAdmin, OtpDelivery, MobileIdentityAdapter};
use OneId\App\Auth\{TotpKeyring, TotpSecretCipher};
use OneId\App\Auth\UserMfa\UserMfaTotpPrimitive;

final class MemoryStore implements StateStore
{
    public array $rows = [];
    public bool $fail = false;
    public function transaction(callable $work): mixed
    {
        $old = $this->rows;
        try { if ($this->fail) throw new \RuntimeException('fixture store failure'); return $work(); }
        catch (\Throwable $e) { $this->rows = $old; throw $e; }
    }
    public function get(string $key): ?array { return $this->rows[$key] ?? null; }
    public function put(string $key, array $value): void { $this->rows[$key] = $value; }
}

final class Source implements IdentitySource
{
    public array $rows;
    public bool $online = true;
    public bool $forceMfa = false;
    public bool $emailEnabled = true;
    public bool $totpEnabled = true;
    public function __construct()
    {
        $common = ['u_password' => password_hash('Fixture-only!4927', PASSWORD_DEFAULT),
            'u_type' => 0, 'avail_status' => 1, 'password_change_required' => 0,
            'data1' => 'Synthetic identity', 'data2' => 'not-a-login',
            'data5' => 'fixture@example.invalid', 'data6'=>'Synthetic department', 'data7'=>'Synthetic position', 'totp' => null];
        $this->rows = [
            'STAFF_FIXTURE' => $common + ['u_id' => 'STAFF_FIXTURE', 'u_category' => 3,
                'data3' => '0530-09', 'data4' => 'synthetic-staff-default', 'families' => ['staff']],
            'STUDENT_FIXTURE' => $common + ['u_id' => 'STUDENT_FIXTURE', 'u_category' => 10,
                'data3' => '', 'data4' => 'M123456', 'families' => ['student']],
        ];
    }
    public function candidates(string $identifier): array { return array_values($this->rows); }
    public function account(string $userId): ?array { return $this->rows[$userId] ?? null; }
    public function policy(array $account): array
    {
        return ['required' => $this->forceMfa || $account['account_type'] === 'student' || (int) $account['u_type'] === 1,
            'email' => $this->emailEnabled, 'totp' => $this->totpEnabled, 'ttl' => 300,
            'otp_ttl' => 120, 'attempts' => 5, 'cooldown' => 60, 'hourly' => 10, 'mode' => 'ENFORCED', 'scope' => 'PASSWORD_ONLY'];
    }
    public function consumeTotp(array $factor, int $step): bool
    {
        foreach ($this->rows as &$row) {
            if (($row['totp']['factor_id'] ?? '') === $factor['factor_id']
                && ($row['totp']['last_used_time_step'] ?? null) === ($factor['last_used_time_step'] ?? null)
                && ($row['totp']['last_used_time_step'] ?? -1) < $step) {
                $row['totp']['last_used_time_step'] = $step;
                return true;
            }
        }
        return false;
    }
    public function available(): bool { return $this->online; }
}

final class Delivery implements OtpDelivery
{
    public ?string $otp = null;
    public bool $success = true;
    public int $calls = 0;
    public function send(string $destination, string $displayName, string $otp): bool
    { $this->otp = $otp; $this->calls++; return $this->success; }
}

final class Provider implements ProviderAdmin
{
    public array $accepted = [];
    public bool $fail = false;
    public string $client = 'fixture-client';
    public string $callback = 'http://127.0.0.1/callback';
    public ?\Closure $duringAccept = null;
    public function login(string $challenge): array
    { return ['client' => ['client_id' => $this->client], 'requested_scope' => ['openid'], 'skip' => true,
        'request_url' => 'http://127.0.0.1/oauth2/auth?' . http_build_query(['redirect_uri' => $this->callback])]; }
    public function accept(string $challenge, array $identity): string
    {
        $this->accepted[] = $identity;
        if ($this->duringAccept) ($this->duringAccept)();
        if ($this->fail) throw new \RuntimeException('fixture network failure');
        return 'http://127.0.0.1/oauth2/auth?login_verifier=fixture';
    }
}

final class Fixture
{
    public int $time = 1800000000;
    public Source $source;
    public MemoryStore $store;
    public Delivery $delivery;
    public Provider $provider;
    public UserMfaTotpPrimitive $primitive;
    public MobileIdentityAdapter $adapter;
    public string $binding = 'fixture-browser-secret-32-characters-long';
    public string $agent = 'FixtureBrowser';
    public function __construct(bool $enabled = true, string $environment = 'staging', ?ProviderAdmin $realProvider = null,
        array $clients = ['fixture-client' => ['http://127.0.0.1/callback']])
    {
        $this->source = new Source(); $this->store = new MemoryStore();
        $this->delivery = new Delivery(); $this->provider = new Provider();
        $path = tempnam(sys_get_temp_dir(), 'oneid-mobile-fixture-key-');
        if ($path === false) throw new \RuntimeException('fixture key creation');
        chmod($path, 0600);
        file_put_contents($path, '<?php return ' . var_export(['active_version' => 'test',
            'keys' => ['test' => base64_encode(random_bytes(32))]], true) . ';');
        try { $cipher = new TotpSecretCipher(TotpKeyring::fromFile($path)); }
        finally { unlink($path); }
        $this->primitive = new UserMfaTotpPrimitive($cipher, fn() => $this->time);
        $this->adapter = new MobileIdentityAdapter($this->source, $this->store, $realProvider ?? $this->provider,
            $this->delivery, $this->primitive, $clients, random_bytes(32), $enabled, $environment, fn() => $this->time);
    }
    public function begin(?string $challenge = null): string
    {
        $result = $this->adapter->begin($challenge ?? bin2hex(random_bytes(16)), $this->binding, $this->agent, '127.0.0.1');
        if (!isset($result['transaction_id'])) throw new \RuntimeException('fixture begin failed: ' . json_encode($result));
        return $result['transaction_id'];
    }
    public function password(string $id, string $number = '0530-09', string $password = 'Fixture-only!4927'): array
    { return $this->adapter->password($id, $this->binding, $this->agent, $number, $password); }
    public function send(string $id): array { return $this->adapter->sendEmail($id, $this->binding, $this->agent); }
    public function verify(string $id, string $factor, string $code): array
    { return $this->adapter->verify($id, $this->binding, $this->agent, $factor, $code); }
    public function finish(string $id): array { return $this->adapter->complete($id, $this->binding, $this->agent); }
    public function status(array $identity): array
    { return $this->adapter->session($identity['sid'], 'fixture-client', $identity['sub']); }
}
