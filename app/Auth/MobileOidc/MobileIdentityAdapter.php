<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;

use OneId\App\Auth\UserMfa\UserMfaOtp;
use OneId\App\Auth\UserMfa\UserMfaTotpPrimitive;
use RuntimeException;
use Throwable;

/** Dormant server-side login domain service. No HTTP route or legacy session writes. */
final class MobileIdentityAdapter
{
    private readonly \Closure $clock;
    private readonly string $dummyHash;

    public function __construct(
        private readonly IdentitySource $identities,
        private readonly StateStore $store,
        private readonly ProviderAdmin $provider,
        private readonly OtpDelivery $delivery,
        private readonly UserMfaTotpPrimitive $totp,
        private readonly array $clients,
        private readonly string $bindingKey,
        private readonly bool $enabled = false,
        private readonly string $environment = 'staging',
        ?callable $clock = null,
        private readonly ?array $pilotIdentifiers = null
    ) {
        if (strlen($bindingKey) < 32) throw new RuntimeException('MOBILE_KEY_INVALID');
        $this->clock = $clock === null ? static fn(): int => time() : \Closure::fromCallable($clock);
        $this->dummyHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    }

    public function begin(string $challenge, string $browserSecret, string $userAgent, string $ip): array
    {
        if (!$this->ready()) return $this->error('UNAVAILABLE');
        if (strlen($browserSecret) < 32 || strlen($browserSecret) > 256 || strlen($userAgent) > 1024
            || filter_var($ip, FILTER_VALIDATE_IP) === false || $challenge === '' || strlen($challenge) > 4096) {
            return $this->error('REQUEST_INVALID');
        }
        $request = $this->provider->login($challenge);
        $client = (string) ($request['client']['client_id'] ?? '');
        $redirect = (string) ($request['request_url'] ?? '');
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $params);
        if (!isset($this->clients[$client]) || !is_string($params['redirect_uri'] ?? null)
            || !in_array($params['redirect_uri'], $this->clients[$client], true)
            || !in_array('openid', $request['requested_scope'] ?? [], true)) return $this->error('CLIENT_INVALID');
        // Never trust provider skip=true as proof of current OneID credentials/policy.
        return $this->store->transaction(function () use ($challenge, $browserSecret, $userAgent, $ip, $client): array {
            if (!$this->rate('begin-ip', $ip, 30, 900)) return $this->error('RATE_LIMITED');
            $key = 'challenge:' . hash('sha256', $challenge);
            if ($this->store->get($key)) return $this->error('CHALLENGE_REPLAYED');
            $id = bin2hex(random_bytes(32));
            $this->store->put($key, ['id' => $id, 'expires' => $this->now() + 900]);
            $this->store->put('tx:' . $id, ['state' => 'PASSWORD', 'challenge' => $challenge,
                'client' => $client, 'binding' => $this->binding($browserSecret, $userAgent),
                'expires' => $this->now() + 300, 'ip' => $ip, 'attempts' => 0]);
            return ['code' => 'PASSWORD_REQUIRED', 'transaction_id' => $id];
        });
    }

    public function password(string $id, string $browserSecret, string $agent, string $identifier, string $password): array
    {
        if (!$this->ready()) return $this->error('UNAVAILABLE');
        return $this->store->transaction(function () use ($id, $browserSecret, $agent, $identifier, $password): array {
            $tx = $this->bound($id, $browserSecret, $agent, ['PASSWORD']);
            if (!$tx) return $this->error('TRANSACTION_INVALID');
            $identifier = trim($identifier);
            if ($this->pilotIdentifiers !== null && !in_array($identifier, $this->pilotIdentifiers, true)) return $this->error('CREDENTIALS_INVALID');
            $a = $this->rate('password-ip', $tx['ip'], 20, 900);
            $b = $this->rate('password-identifier-ip', strtolower($identifier) . ':' . $tx['ip'], 5, 900);
            if (!$a || !$b) return $this->error('RATE_LIMITED');
            if (strlen($identifier) > 100 || strlen($password) > 1024 || $password === '') return $this->error('CREDENTIALS_INVALID');
            $account = IdentityResolver::resolve($this->identities->candidates($identifier), $identifier);
            $valid = \oneid_password_verify($password, (string) ($account['u_password'] ?? $this->dummyHash));
            $this->audit('PASSWORD_CHECK', $id, $account && $valid ? 'verified' : 'rejected');
            if (!$account || !$valid || (int) $account['avail_status'] !== 1) return $this->error('CREDENTIALS_INVALID');
            $default = trim((string) ($account['data3'] ?? '')) !== '' ? (string) ($account['data4'] ?? '') : (string) ($account['data2'] ?? '');
            $changeRequired = (int) $account['password_change_required'] === 1 || ($default !== '' && hash_equals($default, $password));
            $policy = $this->identities->policy($account);
            $tx += ['uid' => (string) $account['u_id'], 'kind' => $account['account_type'],
                'stamp' => $this->stamp($account, $policy), 'policy' => $policy,
                'version' => ($this->store->get('security:' . hash('sha256', (string) $account['u_id']))['version'] ?? 0),
                'change_required' => $changeRequired,
                'auth_time' => $this->now(), 'amr' => ['pwd']];
            $tx['expires'] = min($tx['expires'], $this->now() + $policy['ttl']);
            $tx['state'] = $policy['required'] ? 'MFA' : ($changeRequired ? 'CHANGE' : 'READY');
            $this->store->put('tx:' . $id, $tx);
            if ($tx['state'] === 'CHANGE') return $this->error('PASSWORD_CHANGE_REQUIRED');
            return ['code' => $policy['required'] ? 'MFA_REQUIRED' : 'AUTHENTICATION_READY',
                'factors' => $this->factors($account, $policy)];
        });
    }

    /** Reserve the bound password stage before leaving for an upstream provider. */
    public function beginMyDigitalId(string $id, string $secret, string $agent): bool
    {
        if (!$this->ready()) return false;
        return $this->store->transaction(function () use ($id, $secret, $agent): bool {
            $tx = $this->bound($id, $secret, $agent, ['PASSWORD']);
            if (!$tx) return false;
            $tx['state'] = 'FEDERATED';
            $this->store->put('tx:' . $id, $tx);
            return true;
        });
    }

    public function myDigitalIdPending(string $id, string $secret, string $agent): bool
    {
        if (!$this->ready()) return false;
        return $this->store->transaction(fn(): bool => $this->bound($id, $secret, $agent, ['FEDERATED']) !== null);
    }

    /** Candidate IDs originate solely from server-verified MyDigital ID matching. */
    public function offerMyDigitalId(string $id,string $secret,string $agent,array $ids,string $verifiedNric): array
    {
        if (!$this->ready()) return $this->error('UNAVAILABLE');
        return $this->store->transaction(function() use($id,$secret,$agent,$ids,$verifiedNric): array {
            $tx=$this->bound($id,$secret,$agent,['FEDERATED']);
            if(!$tx)return $this->error('TRANSACTION_INVALID');
            $tx['state']='FAILED';$this->store->put('tx:'.$id,$tx);
            if(!preg_match('/^\d{12}$/D',$verifiedNric))return $this->error('CREDENTIALS_INVALID');
            $options=[];$public=[];$kinds=[];
            foreach(array_unique($ids) as $uid){
                $row=$this->identities->account((string)$uid);
                if(!$row || (int)$row['avail_status']!==1 || !hash_equals($verifiedNric,$this->myDigitalIdNric($row)))continue;
                foreach(IdentityResolver::categories($row) as $kind){
                    $account=IdentityResolver::context($row,$kind);
                    $number=trim((string)($account[$kind==='staff'?'data3':'data4']??''));
                    if($kind==='student' && $number==='')$number=(string)$uid;
                    if($this->pilotIdentifiers!==null && !in_array($number,$this->pilotIdentifiers,true))continue;
                    if(isset($kinds[$kind]))return $this->error('IDENTITY_AMBIGUOUS');
                    $policy=$this->identities->policy($account);
                    if(($policy['scope']??'')!=='PASSWORD_ONLY')return $this->error('UNAVAILABLE');
                    $key=bin2hex(random_bytes(24));$kinds[$kind]=true;
                    $options[$key]=['uid'=>(string)$uid,'kind'=>$kind,'stamp'=>$this->stamp($account,$policy),
                        'policy'=>$policy,'version'=>$this->store->get('security:'.hash('sha256',(string)$uid))['version']??0,
                        'change_required'=>(int)$account['password_change_required']===1,
                        'nric'=>hash_hmac('sha256',$verifiedNric,$this->bindingKey)];
                    $public[]=['id'=>$key,'kind'=>$kind];
                }
            }
            if(!$options)return $this->error('CREDENTIALS_INVALID');
            $tx['state']='ACCOUNT';$tx['choices']=$options;$tx['auth_time']=$this->now();
            $tx['expires']=min($tx['expires'],$this->now()+300);
            $this->store->put('tx:'.$id,$tx);
            return ['code'=>'ACCOUNT_REQUIRED','choices'=>$public];
        });
    }

    public function chooseMyDigitalId(string $id,string $secret,string $agent,string $choice,callable $authorize): array
    {
        if(!$this->ready())return $this->error('UNAVAILABLE');
        return $this->store->transaction(function() use($id,$secret,$agent,$choice,$authorize): array {
            $tx=$this->bound($id,$secret,$agent,['ACCOUNT']);
            if(!$tx)return $this->error('TRANSACTION_INVALID');
            $selected=$tx['choices'][$choice]??null;
            unset($tx['choices']);$tx['state']='FAILED';$this->store->put('tx:'.$id,$tx);
            if(!$selected || !$authorize($selected['uid']))return $this->error('CREDENTIALS_INVALID');
            $account=$this->fresh($selected);
            if(!$account || !hash_equals($selected['nric'],hash_hmac('sha256',$this->myDigitalIdNric($account),$this->bindingKey)))return $this->error('CREDENTIALS_INVALID');
            unset($selected['nric']);$tx=array_merge($tx,$selected);
            $tx['amr']=['mydigitalid'];$tx['state']=$selected['change_required']?'CHANGE':'READY';
            $this->store->put('tx:'.$id,$tx);$this->audit('MYDIGITALID_CHECK',$id,'verified');
            return $selected['change_required']?$this->error('PASSWORD_CHANGE_REQUIRED'):['code'=>'AUTHENTICATION_READY'];
        });
    }

    private function myDigitalIdNric(array $account): string
    {
        return preg_replace('/[\s-]+/','',(string)($account[trim((string)($account['data3']??''))!==''?'data4':'data2']??''));
    }

    public function sendEmail(string $id, string $browserSecret, string $agent): array
    {
        if (!$this->ready()) return $this->error('UNAVAILABLE');
        $otp = UserMfaOtp::generate();
        $hash = UserMfaOtp::hash($otp);
        $generation = bin2hex(random_bytes(16));
        $prepared = $this->store->transaction(function () use ($id, $browserSecret, $agent, $hash, $generation): array {
            $tx = $this->bound($id, $browserSecret, $agent, ['MFA']);
            if (!$tx || !($account = $this->fresh($tx))) return $this->error('TRANSACTION_INVALID');
            if (!in_array('email', $this->factors($account, $tx['policy']), true)) return $this->error('FACTOR_UNAVAILABLE');
            if (($tx['last_send'] ?? 0) + $tx['policy']['cooldown'] > $this->now()) return $this->error('RESEND_COOLDOWN');
            $allowed = true;
            foreach (['email-user' => $tx['uid'], 'email-ip' => $tx['ip'], 'email-destination' => strtolower(trim($account['data5']))] as $kind => $value) {
                $allowed = $this->rate($kind, $value, $kind === 'email-ip' ? 50 : $tx['policy']['hourly'], 3600) && $allowed;
            }
            if (!$allowed) return $this->error('RATE_LIMITED');
            $tx['email'] = ['hash' => $hash, 'generation' => $generation, 'active' => false,
                'expires' => min($tx['expires'], $this->now() + $tx['policy']['otp_ttl'])];
            $tx['last_send'] = $this->now();
            $this->store->put('tx:' . $id, $tx);
            return ['destination' => $account['data5'], 'name' => $account['data1']];
        });
        if (isset($prepared['error'])) return $prepared;
        try { $sent = $this->delivery->send($prepared['destination'], $prepared['name'], $otp); }
        catch (Throwable) { $sent = false; }
        finally { unset($otp); }
        return $this->store->transaction(function () use ($id, $generation, $sent): array {
            $tx = $this->store->get('tx:' . $id);
            if (!$tx || $tx['state'] !== 'MFA' || ($tx['email']['generation'] ?? '') !== $generation) return $this->error('TRANSACTION_INVALID');
            $tx['email']['active'] = $sent;
            $this->store->put('tx:' . $id, $tx);
            return $sent ? ['code' => 'OTP_SENT'] : $this->error('DELIVERY_UNAVAILABLE');
        });
    }

    public function verify(string $id, string $browserSecret, string $agent, string $factor, string $code): array
    {
        if (!$this->ready()) return $this->error('UNAVAILABLE');
        return $this->store->transaction(function () use ($id, $browserSecret, $agent, $factor, $code): array {
            $tx = $this->bound($id, $browserSecret, $agent, ['MFA']);
            if (!$tx || !($account = $this->fresh($tx))) return $this->error('TRANSACTION_INVALID');
            if (!$this->rate('mfa-user', $tx['uid'], 10, 900)) return $this->error('RATE_LIMITED');
            if (!in_array($factor, $this->factors($account, $tx['policy']), true)) return $this->error('FACTOR_UNAVAILABLE');
            $valid = false;
            if ($factor === 'email') {
                $email = $tx['email'] ?? [];
                $valid = ($email['active'] ?? false) && ($email['expires'] ?? 0) > $this->now()
                    && UserMfaOtp::verify($code, $email['hash']);
            } elseif ($factor === 'totp') {
                $f = $account['totp'];
                try {
                    $step = $this->totp->matchEncrypted($f['encrypted_secret'], $f['secret_nonce'], $f['key_version'], $code,
                        isset($f['last_used_time_step']) ? (int) $f['last_used_time_step'] : null);
                    $valid = $this->identities->consumeTotp($f, $step);
                } catch (RuntimeException $e) {
                    if ($e->getMessage() !== 'USER_MFA_TOTP_INVALID_OR_REPLAYED') throw $e;
                }
            }
            $tx['attempts']++;
            if ($valid) {
                $tx['state'] = ($tx['change_required'] ?? false) ? 'CHANGE' : 'READY';
                $tx['amr'][] = $factor === 'totp' ? 'otp' : 'email';
                unset($tx['email']);
            } elseif ($tx['attempts'] >= $tx['policy']['attempts']) {
                $tx['state'] = 'BLOCKED';
                unset($tx['email']);
            }
            $this->store->put('tx:' . $id, $tx);
            $this->audit('FACTOR_CHECK', $id, $valid ? 'verified' : 'rejected');
            if ($valid && $tx['state'] === 'CHANGE') return $this->error('PASSWORD_CHANGE_REQUIRED');
            return $valid ? ['code' => 'AUTHENTICATION_READY'] : $this->error('FACTOR_INVALID');
        });
    }

    public function changePassword(string $id, string $secret, string $agent, string $new, string $confirmation): array
    {
        if (!$this->ready() || !$this->identities instanceof PasswordIdentitySource) return $this->error('UNAVAILABLE');
        return $this->store->transaction(function () use ($id, $secret, $agent, $new, $confirmation): array {
            $tx = $this->bound($id, $secret, $agent, ['CHANGE']);
            if (!$tx || !($account = $this->fresh($tx))) return $this->error('TRANSACTION_INVALID');
            if (!$this->rate('change-password', $tx['uid'], 10, 900)) return $this->error('RATE_LIMITED');
            if (!hash_equals($new, $confirmation)) return $this->error('PASSWORD_CONFIRMATION');
            if (strlen($new) > 72 || !\oneid_validate_new_password($new, $tx['uid'])[0]) return $this->error('PASSWORD_QUALITY');
            $error = $this->identities->changePassword($account, $new);
            if ($error !== null) return $this->error($error);
            // Writer and lifecycle trigger share this transaction; refresh proof after the authorized change.
            $account = IdentityResolver::context($this->identities->account($tx['uid']) ?? [], $tx['kind']);
            if (!$account || (int) $account['password_change_required'] !== 0) throw new RuntimeException('MOBILE_CHANGE_FAILED');
            $policy = $this->identities->policy($account);
            $tx['stamp'] = $this->stamp($account, $policy);
            $tx['policy'] = $policy;
            $tx['version'] = $this->store->get('security:' . hash('sha256', $tx['uid']))['version'] ?? 0;
            $tx['change_required'] = false;
            $tx['state'] = $policy['required'] && !in_array('mydigitalid', $tx['amr'], true) && count($tx['amr']) < 2 ? 'MFA' : 'READY';
            $this->store->put('tx:' . $id, $tx);
            $this->audit('PASSWORD_CHANGED', $id, 'success');
            return ['code' => $tx['state'] === 'MFA' ? 'MFA_REQUIRED' : 'AUTHENTICATION_READY', 'factors' => $this->factors($account, $policy)];
        });
    }

    public function cancel(string $id, string $secret, string $agent): array
    {
        if (!$this->ready() || !$this->provider instanceof ProviderOperations) return $this->error('UNAVAILABLE');
        $challenge = $this->store->transaction(function () use ($id, $secret, $agent): ?string {
            $tx = $this->bound($id, $secret, $agent, ['PASSWORD', 'FEDERATED', 'ACCOUNT', 'MFA', 'CHANGE', 'READY', 'BLOCKED']);
            if (!$tx) return null;
            $challenge = $tx['challenge'];
            $tx['state'] = 'CANCELLED'; unset($tx['challenge'], $tx['email'], $tx['choices']);
            $this->store->put('tx:' . $id, $tx);
            return $challenge;
        });
        return $challenge === null ? $this->error('TRANSACTION_INVALID')
            : ['code' => 'CANCELLED', 'redirect_to' => $this->provider->rejectLogin($challenge)];
    }

    public function complete(string $id, string $browserSecret, string $agent): array
    {
        if (!$this->ready()) return $this->error('UNAVAILABLE');
        $prepared = $this->store->transaction(function () use ($id, $browserSecret, $agent): array {
            $tx = $this->bound($id, $browserSecret, $agent, ['READY']);
            if (!$tx || !($account = $this->fresh($tx))) return $this->error('TRANSACTION_INVALID');
            // A delete/recreate receives a different database incarnation even for the same u_id.
            $subjectKey = 'subject:' . hash('sha256', $tx['uid'] . (isset($account['mobile_generation']) ? ':' . $account['mobile_generation'] : ''));
            $subject = $this->store->get($subjectKey) ?? ['sub' => 'oneid_' . bin2hex(random_bytes(24))];
            $this->store->put($subjectKey, $subject);
            $sid = bin2hex(random_bytes(32));
            $tx['state'] = 'FINISHING';
            $this->store->put('tx:' . $id, $tx);
            $session = ['uid' => $tx['uid'], 'kind' => $tx['kind'], 'sub' => $subject['sub'],
                'client' => $tx['client'], 'stamp' => $tx['stamp'], 'amr' => $tx['amr'],
                'version' => $tx['version'],
                'auth_time' => $tx['auth_time'], 'active' => false];
            $this->store->put('session:' . $sid, $session);
            return ['challenge' => $tx['challenge'], 'sid' => $sid, 'sub' => $subject['sub'],
                'account_type' => $tx['kind'], 'auth_time' => $tx['auth_time'], 'amr' => $tx['amr']];
        });
        if (isset($prepared['error'])) return $prepared;
        try { $redirect = $this->provider->accept($prepared['challenge'], $prepared); }
        catch (Throwable) { $redirect = null; } // Unknown outcome: abandon, never automatically replay acceptance.
        return $this->store->transaction(function () use ($id, $prepared, $redirect): array {
            $tx = $this->store->get('tx:' . $id);
            $session = $this->store->get('session:' . $prepared['sid']);
            $valid = $redirect !== null && $this->ready() && $tx && $session
                && $tx['state'] === 'FINISHING' && $tx['expires'] > $this->now() && $this->fresh($tx);
            if ($session) { $session['active'] = (bool) $valid; $this->store->put('session:' . $prepared['sid'], $session); }
            if ($tx) { $tx['state'] = $valid ? 'CONSUMED' : 'FAILED'; unset($tx['challenge']); $this->store->put('tx:' . $id, $tx); }
            $this->audit('PROVIDER_ACCEPT', $id, $valid ? 'accepted' : 'failed');
            return $valid ? ['code' => 'LOGIN_ACCEPTED', 'redirect_to' => $redirect] : $this->error('AUTHORIZATION_UNAVAILABLE');
        });
    }

    /** Internal hook/consent call only; caller must authenticate provider and validate access token separately. */
    public function session(string $sid, string $client, string $subject): array
    {
        if (!$this->ready()) return $this->error('UNAVAILABLE');
        return $this->store->transaction(function () use ($sid, $client, $subject): array {
            $s = $this->store->get('session:' . $sid);
            if (!$s || !$s['active'] || !hash_equals($s['client'], $client) || !hash_equals($s['sub'], $subject)) return $this->error('SESSION_INVALID');
            if (!($account = $this->fresh($s))) {
                $s['active'] = false;
                $this->store->put('session:' . $sid, $s);
                return $this->error('SESSION_INVALID');
            }
            $text=static fn($key)=>trim((string)($account[$key]??'')) ?: null;
            $staff=$s['kind']==='staff';
            $staffNumber=$staff && preg_match('/^\d{4}-\d{2}$/D',trim((string)($account['data3']??''))) ? trim($account['data3']) : null;
            // data4 is NRIC on staff records: never expose it as a matric number.
            $matric=!$staff && trim((string)($account['data3']??''))==='' ? $text('data4') : null;
            return ['sub' => $s['sub'], 'account_type' => $s['kind'], 'name' => $account['data1'], 'full_name'=>$text('data1'),
                'staff_number'=>$staffNumber, 'staff_number_short'=>$staffNumber!==null?substr($staffNumber,0,4):null,
                'student_matric_number'=>$matric, 'email'=>$text('data5'),
                'department'=>$text('data6'), 'job_title'=>$text('data7'),
                'amr' => $s['amr'], 'auth_time' => $s['auth_time']];
        });
    }

    private function fresh(array $tx): ?array
    {
        if (!$this->ready() || ($this->store->get('security:' . hash('sha256', $tx['uid']))['version'] ?? 0) !== $tx['version']) return null;
        $row = $this->identities->account($tx['uid']);
        if (!$row || (int) $row['avail_status'] !== 1 || ((int) $row['password_change_required'] !== 0 && !($tx['change_required'] ?? false))) return null;
        $row = IdentityResolver::context($row, $tx['kind']);
        if (!$row || !hash_equals($tx['stamp'], $this->stamp($row, $this->identities->policy($row)))) return null;
        return $row;
    }

    private function stamp(array $a, array $p): string
    {
        // Hash stays server-side; includes changing security state but not TOTP replay counter.
        $f = $a['totp'] ?? null;
        // With lifecycle installed, the atomic epoch distinguishes password changes from verified algorithm rehashes.
        return hash_hmac('sha256', serialize([$a['u_id'], isset($a['mobile_version']) ? null : $a['u_password'], $a['avail_status'],
            $a['password_change_required'], $a['u_type'], $a['u_category'], $a['account_type'],
            $a['families'] ?? [], $a['data5'], $a['data3'], $a['data4'],
            $f ? [$f['factor_id'], $f['encrypted_secret'], $f['secret_nonce'], $f['key_version']] : null,
            $a['mobile_generation'] ?? null, $a['mobile_version'] ?? null, $a['mobile_policy_epoch'] ?? null, $p]), $this->bindingKey);
    }

    private function factors(array $a, array $p): array
    {
        $factors = [];
        if ($p['email'] && filter_var($a['data5'], FILTER_VALIDATE_EMAIL)) $factors[] = 'email';
        if ($p['totp'] && !empty($a['totp'])) $factors[] = 'totp';
        return $factors;
    }

    /** Trusted security-writer integration, never a public userId endpoint. */
    public function invalidateAccount(string $userId, bool $retireSubject = false): void
    {
        if (!$this->enabled || !in_array($this->environment, ['staging','production'], true)) throw new RuntimeException('MOBILE_UNAVAILABLE');
        $this->store->transaction(function () use ($userId, $retireSubject): void {
            $suffix = hash('sha256', $userId);
            $version = ($this->store->get('security:' . $suffix)['version'] ?? 0) + 1;
            $this->store->put('security:' . $suffix, ['version' => $version]);
            if ($retireSubject) {
                $account = $this->identities->account($userId);
                $key = hash('sha256', $userId . (isset($account['mobile_generation']) ? ':' . $account['mobile_generation'] : ''));
                $this->store->put('subject:' . $key, ['sub' => 'oneid_' . bin2hex(random_bytes(24))]);
            }
            $this->audit('ACCOUNT_INVALIDATE', $suffix, 'revoked');
        });
    }

    public function revokeSession(string $sid, string $client, string $subject): bool
    {
        if (!$this->ready()) return false;
        return $this->store->transaction(function () use ($sid, $client, $subject): bool {
            $s = $this->store->get('session:' . $sid);
            if (!$s || !hash_equals($s['client'], $client) || !hash_equals($s['sub'], $subject)) return false;
            $s['active'] = false;
            $this->store->put('session:' . $sid, $s);
            $this->audit('SESSION_REVOKE', hash('sha256', $sid), 'revoked');
            return true;
        });
    }

    private function audit(string $event, string $reference, string $outcome): void
    {
        $this->store->put('audit:' . bin2hex(random_bytes(16)), ['event' => $event,
            'reference' => $reference, 'outcome' => $outcome, 'at' => $this->now()]);
    }

    private function bound(string $id, string $secret, string $agent, array $states): ?array
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $id) !== 1) return null;
        $tx = $this->store->get('tx:' . $id);
        return $tx && $tx['expires'] > $this->now() && in_array($tx['state'], $states, true)
            && hash_equals($tx['binding'], $this->binding($secret, $agent)) ? $tx : null;
    }

    private function rate(string $kind, string $value, int $limit, int $seconds): bool
    {
        $key = 'rate:' . hash_hmac('sha256', $kind . ':' . $value, $this->bindingKey);
        $entry = $this->store->get($key) ?? ['start' => $this->now(), 'count' => 0];
        if ($entry['start'] + $seconds <= $this->now()) $entry = ['start' => $this->now(), 'count' => 0];
        $entry['count'] = min($entry['count'] + 1, $limit + 1);
        $this->store->put($key, $entry);
        return $entry['count'] <= $limit;
    }

    private function binding(string $secret, string $agent): string { return hash_hmac('sha256', $secret . "\0" . $agent, $this->bindingKey); }
    private function now(): int { return ($this->clock)(); }
    private function ready(): bool { return $this->enabled && in_array($this->environment, ['staging','production'], true) && $this->identities->available(); }
    private function error(string $code): array { return ['error' => 'MOBILE_' . $code]; }
}
