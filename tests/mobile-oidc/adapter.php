<?php
declare(strict_types=1);
require_once __DIR__ . '/Fixtures.php';
use OneId\Tests\MobileOidc\Fixture;
use OneId\App\Auth\Totp;
use OneId\App\Auth\MobileOidc\IdentityResolver;

$checks = 0;
function check(bool $ok, string $label): void { global $checks; $checks++; if (!$ok) throw new RuntimeException('FAIL ' . $label); echo 'PASS ' . $label . PHP_EOL; }
function code(array $r, string $v): bool { return ($r['code'] ?? $r['error'] ?? '') === $v; }

$f = new Fixture(false);
check(code($f->adapter->begin('x', $f->binding, $f->agent, '127.0.0.1'), 'MOBILE_UNAVAILABLE'), 'feature disabled blocks entry');
$f = new Fixture(true, 'unknown');
check(code($f->adapter->begin('x', $f->binding, $f->agent, '127.0.0.1'), 'MOBILE_UNAVAILABLE'), 'unknown environment refused');
$f = new Fixture(); $f->provider->client = 'unknown';
check(code($f->adapter->begin('x', $f->binding, $f->agent, '127.0.0.1'), 'MOBILE_CLIENT_INVALID'), 'unregistered client rejected');
$f->provider->client = 'fixture-client'; $f->provider->callback = 'https://evil.invalid/';
check(code($f->adapter->begin('x', $f->binding, $f->agent, '127.0.0.1'), 'MOBILE_CLIENT_INVALID'), 'unregistered redirect rejected');
$f = new Fixture(); $id = $f->begin('one-challenge');
check(code($f->adapter->begin('one-challenge', $f->binding, $f->agent, '127.0.0.1'), 'MOBILE_CHALLENGE_REPLAYED'), 'login challenge replay denied');
check(code($f->finish($id), 'MOBILE_TRANSACTION_INVALID'), 'provider skip cannot bypass password');
check(code($f->adapter->password($id, str_repeat('x', 40), $f->agent, '0530-09', 'Fixture-only!4927'), 'MOBILE_TRANSACTION_INVALID'), 'browser binding prevents transaction theft');
check(code($f->password($id), 'AUTHENTICATION_READY'), 'staff password follows staff policy');
check(code($f->finish($id), 'LOGIN_ACCEPTED'), 'staff accepted through provider boundary');
$staff = $f->provider->accepted[0];
check($staff['amr'] === ['pwd'] && $staff['account_type'] === 'staff', 'actual assurance and staff context provided');
check(!str_contains(json_encode($f->provider->accepted), 'Fixture-only') && !isset($staff['u_password']), 'no password or password hash sent to provider');
check(code($f->finish($id), 'MOBILE_TRANSACTION_INVALID'), 'completion replay denied');
check(isset($f->status($staff)['sub']), 'bound active mobile session validated');
check(code($f->adapter->session($staff['sid'], 'wrong-client', $staff['sub']), 'MOBILE_SESSION_INVALID'), 'cross-client session rejected');

$id = $f->begin(); check(code($f->password($id, 'M123456'), 'MFA_REQUIRED'), 'student password requires MFA');
check(code($f->finish($id), 'MOBILE_TRANSACTION_INVALID'), 'password alone never accepts MFA user');
check(code($f->send($id), 'OTP_SENT'), 'email OTP delivered using server-side sink');
$otp = $f->delivery->otp;
check(!str_contains(json_encode($f->store->rows), '"' . $otp . '"'), 'OTP stored hash-only');
check(code($f->send($id), 'MOBILE_RESEND_COOLDOWN'), 'email resend cooldown enforced');
check(code($f->verify($id, 'email', '123'), 'MOBILE_FACTOR_INVALID'), 'malformed OTP rejected');
check(code($f->verify($id, 'email', $otp), 'AUTHENTICATION_READY'), 'email OTP verifies');
check(code($f->verify($id, 'email', $otp), 'MOBILE_TRANSACTION_INVALID'), 'OTP replay rejected');
check(code($f->finish($id), 'LOGIN_ACCEPTED'), 'student accepted only after MFA');
$student = $f->provider->accepted[1];
check($student['sub'] !== $staff['sub'] && $student['amr'] === ['pwd', 'email'], 'separate staff/student identities and correct email assurance');
$id = $f->begin(); $f->password($id); $f->finish($id);
check($f->provider->accepted[2]['sub'] === $staff['sub'], 'stable subject across staff logins');
$second = $f->provider->accepted[2];
check($f->adapter->revokeSession($staff['sid'], 'fixture-client', $staff['sub']), 'per-device adapter revoke succeeds');
check(code($f->status($staff), 'MOBILE_SESSION_INVALID') && isset($f->status($second)['sub']), 'revoke does not terminate second session');
$f->adapter->invalidateAccount('STAFF_FIXTURE');
check(code($f->status($second), 'MOBILE_SESSION_INVALID'), 'security version invalidates outstanding sessions');

$f = new Fixture(); $f->source->rows['STAFF_FIXTURE']['families'] = [];
$id = $f->begin(); check(code($f->password($id), 'AUTHENTICATION_READY'), 'manual active staff category needs no membership registration');
$f = new Fixture(); $f->source->rows['OTHER'] = $f->source->rows['STAFF_FIXTURE']; $f->source->rows['OTHER']['u_id'] = 'OTHER';
$id = $f->begin(); check(code($f->password($id), 'MOBILE_CREDENTIALS_INVALID'), 'duplicate staff identifier denied before choosing password');
$f = new Fixture(); $id = $f->begin();
check(code($f->password($id, 'not-a-login'), 'MOBILE_CREDENTIALS_INVALID'), 'IC/email not accepted as staff or matric login');
$f = new Fixture(); $f->source->rows['STAFF_FIXTURE']['avail_status'] = 0;
$id = $f->begin(); check(code($f->password($id), 'MOBILE_CREDENTIALS_INVALID'), 'inactive account cannot authenticate');
$f = new Fixture(); $f->source->rows['STAFF_FIXTURE']['password_change_required'] = 1;
$id = $f->begin(); check(code($f->password($id), 'MOBILE_PASSWORD_CHANGE_REQUIRED') && !$f->provider->accepted, 'forced change prevents provider acceptance');
$f = new Fixture(); $f->source->rows['STAFF_FIXTURE']['data4'] = 'Fixture-only!4927';
$id = $f->begin(); check(code($f->password($id), 'MOBILE_PASSWORD_CHANGE_REQUIRED'), 'default-password detection matches legacy rule');
$f = new Fixture(); $f->source->rows['STAFF_FIXTURE']['u_type'] = 1;
$id = $f->begin(); check(code($f->password($id), 'MFA_REQUIRED'), 'admin role is not automatic MFA bypass in fixture matrix');
$f = new Fixture(); $id = $f->begin();
for ($i = 0; $i < 5; $i++) check(code($f->password($id, '0530-09', 'wrong'), 'MOBILE_CREDENTIALS_INVALID'), 'wrong password attempt ' . ($i + 1));
check(code($f->password($id), 'MOBILE_RATE_LIMITED'), 'password limit persists after failed attempts');
$f = new Fixture(); $id = $f->begin(); $f->time += 300;
check(code($f->password($id), 'MOBILE_TRANSACTION_INVALID'), 'pending transaction expires at exact boundary');

$oldDeadline = getenv('ONEID_LEGACY_MD5_DEADLINE');
try {
    putenv('ONEID_LEGACY_MD5_DEADLINE=2000-01-01');
    $f = new Fixture(); $f->source->rows['STAFF_FIXTURE']['u_password'] = md5('Fixture-only!4927');
    $id = $f->begin();
    check(code($f->password($id), 'MOBILE_CREDENTIALS_INVALID'), 'expired legacy MD5 policy is not bypassed');
    putenv('ONEID_LEGACY_MD5_DEADLINE=2099-01-01');
    $id = $f->begin();
    check(code($f->password($id), 'AUTHENTICATION_READY'), 'legacy MD5 accepted only within explicitly configured deadline');
} finally {
    putenv($oldDeadline === false ? 'ONEID_LEGACY_MD5_DEADLINE' : 'ONEID_LEGACY_MD5_DEADLINE=' . $oldDeadline);
}
$f = new Fixture(); $f->source->rows['STAFF_FIXTURE']['u_password'] = password_hash('Fixture-only!4927', PASSWORD_ARGON2ID);
$id = $f->begin(); check(code($f->password($id), 'AUTHENTICATION_READY'), 'modern Argon2id credentials use OneID verifier');
$f = new Fixture();
$f->source->rows['STAFF_FIXTURE']['families'] = ['staff', 'student'];
$f->source->rows['STAFF_FIXTURE']['data4'] = 'DUAL123';
$id = $f->begin(); check(code($f->password($id, 'DUAL123'), 'MFA_REQUIRED'), 'dual membership resolves matric to student policy');
$id = $f->begin(); check(code($f->password($id, '0530-09'), 'AUTHENTICATION_READY'), 'dual membership resolves staff number to staff policy');
$f = new Fixture(); $f->source->rows['STAFF_FIXTURE']['u_category'] = 999; $f->source->rows['STAFF_FIXTURE']['families'] = [];
$id = $f->begin(); check(code($f->password($id), 'MOBILE_CREDENTIALS_INVALID'), 'unknown category never inferred from identifier shape');

foreach (['avail_status', 'password_change_required', 'u_password', 'policy'] as $change) {
    $f = new Fixture(); $id = $f->begin(); $f->password($id);
    if ($change === 'policy') $f->source->forceMfa = true;
    else $f->source->rows['STAFF_FIXTURE'][$change] = match ($change) { 'avail_status' => 0, 'password_change_required' => 1, default => password_hash('changed', PASSWORD_DEFAULT) };
    check(code($f->finish($id), 'MOBILE_TRANSACTION_INVALID') && !$f->provider->accepted, 'revalidate ' . $change . ' before provider acceptance');
}
$f = new Fixture(); $id = $f->begin(); $f->password($id);
$f->provider->duringAccept = function () use ($f): void { $f->source->rows['STAFF_FIXTURE']['avail_status'] = 0; };
check(code($f->finish($id), 'MOBILE_AUTHORIZATION_UNAVAILABLE'), 'account changed during network acceptance cannot activate session');
check(code($f->status($f->provider->accepted[0]), 'MOBILE_SESSION_INVALID'), 'partial provider success remains unusable by hook');
$f = new Fixture(); $id = $f->begin(); $f->password($id); $f->provider->fail = true;
check(code($f->finish($id), 'MOBILE_AUTHORIZATION_UNAVAILABLE'), 'provider outage fails closed');
check(code($f->finish($id), 'MOBILE_TRANSACTION_INVALID') && count($f->provider->accepted) === 1, 'unknown provider result is never replayed');
$f = new Fixture(); $id = $f->begin(); $f->password($id, 'M123456'); $f->delivery->success = false;
check(code($f->send($id), 'MOBILE_DELIVERY_UNAVAILABLE'), 'SMTP failure reported without credential leak');
check(code($f->verify($id, 'email', $f->delivery->otp), 'MOBILE_FACTOR_INVALID'), 'failed-delivery OTP cannot authenticate');
$f = new Fixture(); $id = $f->begin(); $f->password($id, 'M123456'); $f->send($id); $old = $f->delivery->otp;
$f->time += 61; $f->send($id);
if ($old === $f->delivery->otp) { $f->time += 61; $f->send($id); }
check(code($f->verify($id, 'email', $old), 'MOBILE_FACTOR_INVALID'), 'resend invalidates previous OTP');
$f->time += 120;
check(code($f->verify($id, 'email', $f->delivery->otp), 'MOBILE_FACTOR_INVALID'), 'expired email OTP denied');
$f = new Fixture(); $id = $f->begin(); $f->password($id, 'M123456'); $f->send($id);
for ($i = 0; $i < 5; $i++) $f->verify($id, 'email', 'bad');
check(code($f->verify($id, 'email', $f->delivery->otp), 'MOBILE_TRANSACTION_INVALID'), 'MFA attempt exhaustion locks transaction');

$f = new Fixture(); $material = $f->primitive->enroll('Fixture', 'Student');
$f->source->rows['STUDENT_FIXTURE']['totp'] = ['factor_id' => '1', 'last_used_time_step' => null] + $material;
$id = $f->begin(); $f->password($id, 'M123456'); $totp = Totp::codeAt($material['secret'], $f->time);
check(code($f->verify($id, 'totp', 'abc'), 'MOBILE_FACTOR_INVALID'), 'TOTP malformed code denied');
check(code($f->verify($id, 'totp', $totp), 'AUTHENTICATION_READY'), 'existing encrypted TOTP primitive used successfully');
check(code($f->finish($id), 'LOGIN_ACCEPTED') && $f->provider->accepted[0]['amr'] === ['pwd', 'otp'], 'TOTP assurance accurately conveyed');
$id = $f->begin(); $f->password($id, 'M123456');
check(code($f->verify($id, 'totp', $totp), 'MOBILE_FACTOR_INVALID'), 'shared factor replay counter rejects TOTP across transactions');
$f->time += 30;
check(code($f->verify($id, 'totp', Totp::codeAt($material['secret'], $f->time)), 'AUTHENTICATION_READY'), 'next TOTP step accepted');
$f = new Fixture(); $id = $f->begin(); $f->password($id); $f->finish($id); $identity = $f->provider->accepted[0];
$f->source->rows['STAFF_FIXTURE']['avail_status'] = 0;
check(code($f->status($identity), 'MOBILE_SESSION_INVALID'), 'disabled account fails session recheck');
$f->source->rows['STAFF_FIXTURE']['avail_status'] = 1;
check(code($f->status($identity), 'MOBILE_SESSION_INVALID'), 'observed disable permanently retires session');
$f->adapter->invalidateAccount('STAFF_FIXTURE', true);
$id = $f->begin(); $f->password($id); $f->finish($id);
check($f->provider->accepted[1]['sub'] !== $identity['sub'], 'retire/recreate event yields different subject');
$f->source->online = false;
check(code($f->status($f->provider->accepted[1]), 'MOBILE_UNAVAILABLE'), 'maintenance/dependency gate fails closed');
$f = new Fixture(); $f->store->fail = true; $threw = false;
try { $f->begin(); } catch (RuntimeException) { $threw = true; }
check($threw && !$f->provider->accepted, 'store unavailable cannot authorize provider');
$sender = new class implements \OneId\App\Auth\UserMfa\UserMfaEmailSenderInterface {
    public array $received = [];
    public function send(string $otp, string $email, string $displayName, string $locale): bool
    { $this->received = [$otp, $email, $displayName, $locale]; return true; }
};
$delivery = new \OneId\App\Auth\MobileOidc\OneIdOtpDelivery($sender, 'invalid-locale');
check($delivery->send('fixture@example.invalid', 'Fixture', '123456')
    && $sender->received === ['123456', 'fixture@example.invalid', 'Fixture', 'ms'], 'OneID sender bridge preserves server-side OTP contract and locale fallback');
check(session_status() === PHP_SESSION_NONE && headers_list() === [], 'adapter starts no PHP session and emits no cookies');
echo "Result: {$checks}/{$checks} passed\n";
