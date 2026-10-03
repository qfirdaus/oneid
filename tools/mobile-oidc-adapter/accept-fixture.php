<?php
declare(strict_types=1);
// CLI-only real-provider rehearsal. No real OneID bootstrap, accounts, SMTP or database.
if (PHP_SAPI !== 'cli' || realpath(dirname(__DIR__, 2)) !== '/var/www/oneid-uat') exit(2);
require_once dirname(__DIR__, 2) . '/tests/mobile-oidc/Fixtures.php';
use OneId\App\Auth\MobileOidc\{HydraAdminClient, ProviderAdmin};
use OneId\Tests\MobileOidc\Fixture;
use OneId\App\Auth\Totp;

try {
    $input = json_decode(stream_get_contents(STDIN, 16384), true, 32, JSON_THROW_ON_ERROR);
    foreach (['admin', 'issuer', 'callback'] as $key) {
        $url = parse_url($input[$key]);
        if (($url['host'] ?? '') !== '127.0.0.1' || ($url['scheme'] ?? '') !== 'http') throw new RuntimeException('fixture loopback only');
    }
    $provider = new class(new HydraAdminClient($input['admin'], $input['issuer'])) implements ProviderAdmin {
        public ?array $identity = null;
        public function __construct(private ProviderAdmin $inner) {}
        public function login(string $challenge): array { return $this->inner->login($challenge); }
        public function accept(string $challenge, array $identity): string
        { $this->identity = $identity; return $this->inner->accept($challenge, $identity); }
    };
    $f = new Fixture(true, 'staging', $provider, ['poc-android' => [$input['callback']]]);
    $f->time = time();
    $scenario = $input['scenario'];
    if ($scenario === 'totp') {
        $material = $f->primitive->enroll('Fixture', 'Student');
        $f->source->rows['STUDENT_FIXTURE']['totp'] = ['factor_id' => '1', 'last_used_time_step' => null] + $material;
    }
    $id = $f->begin($input['challenge']);
    $result = $f->password($id, $scenario === 'staff' || $scenario === 'wrong_password' ? '0530-09' : 'M123456',
        $scenario === 'wrong_password' ? 'incorrect' : 'Fixture-only!4927');
    if (($result['code'] ?? '') === 'MFA_REQUIRED') {
        // Completing early MUST not contact the provider.
        if (($f->finish($id)['error'] ?? '') !== 'MOBILE_TRANSACTION_INVALID' || $provider->identity !== null) throw new RuntimeException('MFA boundary failed');
        if ($scenario === 'totp') $result = $f->verify($id, 'totp', Totp::codeAt($material['secret'], $f->time));
        else {
            $f->send($id);
            $result = $f->verify($id, 'email', $scenario === 'wrong_otp' ? 'bad' : $f->delivery->otp);
        }
    }
    if (($result['code'] ?? '') !== 'AUTHENTICATION_READY') {
        echo json_encode(['denied' => $provider->identity === null, 'error' => $result['error'] ?? 'fixture error']);
        exit(0);
    }
    $result = $f->finish($id);
    if (($result['code'] ?? '') !== 'LOGIN_ACCEPTED') throw new RuntimeException('provider acceptance failed');
    $identity = $provider->identity;
    $profile = $f->adapter->session($identity['sid'], 'poc-android', $identity['sub']);
    if (isset($profile['error'])) throw new RuntimeException('adapter session failed');
    echo json_encode(['redirect_to' => $result['redirect_to'], 'identity' => $identity, 'profile' => $profile], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, 'Adapter fixture failed: ' . get_class($e) . PHP_EOL);
    exit(1);
}
