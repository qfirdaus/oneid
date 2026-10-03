#!/usr/bin/env python3
"""Run actual legacy API branches with synthetic in-memory storage through FPM."""
import json
from pathlib import Path
from php84_fpm_probe import request_source
ROOT=Path(__file__).resolve().parent.parent
api=(ROOT/'api.php').read_text()
for path in ['lib/config.php','lib/stateless_jwt.php','lib/integration_security.php']:
    line="require_once __DIR__ . '/"+path+"';"
    assert api.count(line)==1
    api=api.replace(line,'')
api=api.replace('__DIR__',repr(str(ROOT)))
assert api.count("oneid_integration_guard('sso_token', 'sso:validate');")==1
api=api.replace("oneid_integration_guard('sso_token', 'sso:validate');",'')
api=api.replace("file_get_contents('php://input')", "json_encode(['flag'=>'1','data'=>['site_id'=>$site,'token'=>'fixture-only-token']])")
api=api.removeprefix('<?php').rstrip().removesuffix('?>')
cases=[('active',300,1,True,False,'1','1'),('revoked_active',300,0,True,False,'0','1'),
 ('future',-300,1,True,False,'0','1'),('refresh',86700,1,True,False,'1','2'),
 ('expired',90300,1,True,False,'0','1'),('revoked_refresh',86700,0,True,False,'0','1'),
 ('denied_refresh',86700,1,False,False,'0','1'),('policy_revoked_refresh',86700,1,True,True,'0','1')]
prefix='''<?php
error_reporting(E_ALL);
set_error_handler(static function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});
date_default_timezone_set('Asia/Kuala_Lumpur');
function oneid_generate_sso_token(){return bin2hex(random_bytes(32));}
function oneid_integration_client_ip(){return '127.0.0.1';}
class FixtureOperation {
 public array $writes=[];
 public function get_system_config(){return ['token_timeout'=>24];}
 public function resolve_site_api_code($c){return ['sp_id'=>'fixture-app'];}
 public function check_token($t){global $age,$status,$revoked;return ['token_id'=>'fixture-hash','token_issued_at'=>date('Y-m-d H:i:s',time()-$age),'status'=>$status,'user_id'=>'fixture-user','device_info'=>'fixture','policy_revoke_at'=>$revoked?date('Y-m-d H:i:s',time()-60):null];}
 public function enforce_due_token_revocation($t){$this->writes[]='policy_revoke';return 1;}
 public function syslog_record(...$a){}
 public function update_specific_token_status(...$a){$this->writes[]='deactivate_old';return 1;}
 public function refresh_legacy_token($t,$n,$authorized){if(!$authorized($this->check_token($t)))return false;$this->writes[]='deactivate_old';$this->writes[]='issue_new';return true;}
 public function add_new_token(...$a){$this->writes[]='issue_new';return 1;}
 public function get_specific_user_info($u){return ['u_id'=>'fixture-user','u_category'=>'fixture-category'];}
 public function specfic_user_get_sp_list_by_group($g){global $allowed;return $allowed?[['sp_id'=>'fixture-app','sp_domain'=>'https://fixture.invalid']]:[];}
 public function specfic_user_get_sp_list_by_specific_sp($u){return [];}
 public function specfic_user_get_sp_blacklist($u){return [];}
}
$site='fixture-code';$operation=new FixtureOperation();ob_start();
'''
footer='''
$body=json_decode(ob_get_clean(),true,64,JSON_THROW_ON_ERROR);
echo json_encode(['respond'=>$body['respond']??null,'flag'=>$body['respond_flag']??null,'new_token_present'=>isset($body['respond_new_token']),'user_packet_present'=>isset($body['respond_user_packet']),'writes'=>$operation->writes]);
'''
results=[]
for name,age,status,allowed,revoked,respond,flag in cases:
    states={}
    for version,sock in [('8.3','php8.3-fpm'),('8.4','oneid-web-uat84')]:
        setup=f'$age={age};$status={status};$allowed={str(allowed).lower()};$revoked={str(revoked).lower()};\n'
        states[version]=json.loads(request_source('/run/php/'+sock+'.sock',prefix+setup+api+footer))
    good=all(s['respond']==respond and s['flag']==flag and s['new_token_present']==(flag=='2') and (respond!='0' or not s['user_packet_present']) for s in states.values())
    results.append({'case':name,'expected_respond':respond,'expected_flag':flag,'security_expectation_pass':good,'runtime_parity':states['8.3']==states['8.4'],'observed':states})
report={'date':'2026-10-03','scope':'Actual API branch code in temporary copies, synthetic in-memory operation; config, integration auth guard and request body replaced. Not real database E2E. No live tokens/data modified.','results':results}
(ROOT/'docs/php84/phase4-legacy-expiry-fixtures.json').write_text(json.dumps(report,indent=2)+'\n')
for r in results: print(r['case'], 'PASS' if r['security_expectation_pass'] else 'FAIL_SECURITY_EXPECTATION', 'runtime_parity='+str(r['runtime_parity']))
raise SystemExit(0 if all(r['security_expectation_pass'] and r['runtime_parity'] for r in results) else 1)
