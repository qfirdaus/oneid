<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/mobile-oidc/Fixtures.php';
use OneId\Tests\MobileOidc\Fixture;
use OneId\App\Auth\MobileOidc\{ProviderOperations, MobileProtocolService};
$f = new Fixture(); $id = $f->begin(); $f->password($id); $f->finish($id); $identity = $f->provider->accepted[0];
$provider = new class implements ProviderOperations {
    public array $consentData = []; public array $token = []; public array $accepted = []; public bool $revoked = false;
    public function login(string $challenge): array { return []; }
    public function accept(string $challenge, array $identity): string { return ''; }
    public function consent(string $challenge): array { return $this->consentData; }
    public function acceptConsent(string $challenge, array $session, array $scopes, array $audiences): string
    { $this->accepted = $session; return 'http://127.0.0.1/oauth2/auth'; }
    public function rejectLogin(string $challenge): string { return ''; }
    public function introspect(string $token): array { return $this->token; }
    public function revoke(string $token, string $client): void { $this->revoked = true; }
};
$key = str_repeat('test-hook-key-', 4);
$protocol = new MobileProtocolService($f->adapter, $provider, ['fixture-client'=>[], 'second-client'=>[]], $key);
$checks = 0;
function check(bool $value, string $label): void { global $checks; $checks++; if (!$value) throw new RuntimeException('FAIL ' . $label); echo 'PASS ' . $label . PHP_EOL; }
$provider->consentData = ['client'=>['client_id'=>'fixture-client'], 'subject'=>$identity['sub'],
    'context'=>['mobile_sid'=>$identity['sid']], 'requested_scope'=>['openid','profile','offline_access','mobile:session'],
    'requested_access_token_audience'=>['oneid-mobile-session']];
check($protocol->consent('challenge') !== '', 'consent authorizes only bound active first-party session');
check(isset($provider->accepted['access_token']['mobile_sid'], $provider->accepted['access_token']['mobile_audience'])
    && array_keys($provider->accepted['id_token']) === ['account_type','name'], 'consent issues minimal profile and trusted hook context');
$provider->consentData['requested_scope'][] = 'admin'; $denied = false;
try { $protocol->consent('challenge'); } catch (RuntimeException) { $denied = true; }
check($denied, 'unapproved scope cannot be auto-consented');
array_pop($provider->consentData['requested_scope']);
$provider->consentData['requested_access_token_audience'] = ['another-api']; $denied = false;
try { $protocol->consent('challenge'); } catch (RuntimeException) { $denied = true; }
check($denied, 'unapproved audience cannot be auto-consented');
$payload = ['session'=>['client_id'=>'fixture-client','id_token'=>['subject'=>$identity['sub']], 'extra'=>$provider->accepted['access_token']],
    'request'=>['client_id'=>'fixture-client','grant_types'=>['authorization_code'],'granted_scopes'=>[],'granted_audience'=>[]]];
check($protocol->hook('', $payload)['status'] === 403, 'hook requires authenticated provider');
check($protocol->hook($key, $payload)['status'] === 204, 'pre-grant code hook uses consent context while provider grant arrays are empty');
$payload['request']['grant_types'] = ['refresh_token'];
check($protocol->hook($key, $payload)['status'] === 204, 'refresh hook revalidates same bound session');
$copy=$payload; $copy['session']['id_token']['subject']='wrong';
check($protocol->hook($key,$copy)['status']===403, 'hook rejects subject substitution');
$copy=$payload; $copy['session']['client_id']='second-client'; $copy['request']['client_id']='second-client';
check($protocol->hook($key,$copy)['status']===403, 'hook rejects cross-client session reuse');
$copy=$payload; unset($copy['session']['extra']['mobile_audience']);
check($protocol->hook($key,$copy)['status']===403, 'hook rejects missing consent audience binding');
$copy=$payload; $copy['request']['grant_types']=['client_credentials'];
check($protocol->hook($key,$copy)['status']===403, 'hook refuses non-user grant types');
$provider->token = ['active'=>true,'token_use'=>'access_token','client_id'=>'fixture-client','sub'=>$identity['sub'],
    'ext'=>['mobile_sid'=>$identity['sid']], 'aud'=>['oneid-mobile-session'],'scope'=>'openid mobile:session'];
check($protocol->status('Bearer fixture-access')['status']===200, 'session requires introspected access token and trusted client binding');
check(array_keys($protocol->status('Bearer fixture-access')['body']) === ['session_status','sub','account_type','display_name','full_name','staff_number','staff_number_short','student_matric_number','email','department','job_title'], 'session response excludes internal session IDs and credentials');
foreach (['refresh_token','id_token'] as $use) {
    $provider->token['token_use']=$use;
    check($protocol->status('Bearer fixture')['status']===401, $use . ' cannot replace access token');
}
$provider->token['token_use']='access_token'; $provider->token['aud']=['wrong'];
check($protocol->status('Bearer fixture')['status']===401, 'introspected wrong-audience access token rejected');
$provider->token['aud']=['oneid-mobile-session']; $provider->token['scope']='openid';
check($protocol->status('Bearer fixture')['status']===401, 'session requires mobile scope');
$provider->token['scope']='openid mobile:session'; $f->source->online=false;
check($protocol->hook($key,$payload)['status']===503 && $protocol->status('Bearer fixture')['status']===503, 'dependency outage fails closed with retryable status');
$f->source->online=true;
check($protocol->status('Bearer fixture',true)['status']===204 && $provider->revoked, 'logout invalidates adapter session and asks provider to revoke');
check($protocol->hook($key,$payload)['status']===403, 'logged-out session cannot mint further tokens');
echo "Result: {$checks}/{$checks} passed\n";
