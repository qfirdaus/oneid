<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;
use RuntimeException;
final class RuntimeEnvironment
{
    public static function origin(string $environment): string {
        return match($environment) {
            'staging'=>'https://oneid-uat.upnm.edu.my',
            'production'=>'https://oneid.upnm.edu.my',
            default=>throw new RuntimeException('MOBILE_ENVIRONMENT_INVALID'),
        };
    }
    public static function validate(string $root, array $config): string {
        $environment=(string)($config['environment']??'');
        $origin=self::origin($environment);
        $expected=$environment==='production'?'/var/www/oneid':'/var/www/oneid-uat';
        if($root!==$expected)throw new RuntimeException('MOBILE_ROOT_INVALID');
        $fixture=($config['loopback_fixture']??false)===true;
        if($fixture){
            if($environment!=='staging'
                || parse_url((string)($config['origin']??''),PHP_URL_HOST)!=='127.0.0.1')throw new RuntimeException('MOBILE_FIXTURE_INVALID');
            return $environment;
        }
        if(($config['origin']??'')!==$origin || rtrim((string)($config['issuer']??''),'/')!==$origin
            || ($config['database_scope_confirmed']??false)!==true)throw new RuntimeException('MOBILE_ORIGIN_INVALID');
        if($environment==='production'){
            if (($config['admin_url']??'')!=='http://127.0.0.1:24145'
                || strlen((string)($config['binding_key']??''))<32
                || strlen((string)($config['hook_key']??''))<32
                || ($config['binding_key']??'')===($config['hook_key']??'')) throw new RuntimeException('MOBILE_PRIVATE_CONFIG_INVALID');
            if(($config['production_ready']??false)!==true || empty($config['clients'])
                || !is_array($config['clients']) || isset($config['pilot_identifiers']) || isset($config['pilot_until']))throw new RuntimeException('MOBILE_PRODUCTION_NOT_APPROVED');
            foreach($config['clients'] as $id=>$uris){
                if(!is_string($id)||$id===''||preg_match('/staging|replace/i',$id)||!is_array($uris)||!$uris)throw new RuntimeException('MOBILE_CLIENT_INVALID');
                foreach($uris as $uri){
                    if(!is_string($uri)||str_contains($uri,'*')||parse_url($uri,PHP_URL_FRAGMENT)!==null||parse_url($uri,PHP_URL_USER)!==null||!preg_match('~\A[a-z][a-z0-9+.-]*://[^\s]+\z~i',$uri)
                        || in_array(strtolower((string)parse_url($uri,PHP_URL_SCHEME)),['http','javascript','data'],true)
                        || in_array(strtolower((string)parse_url($uri,PHP_URL_HOST)),['localhost','127.0.0.1','[::1]','oneid-uat.upnm.edu.my'],true))throw new RuntimeException('MOBILE_REDIRECT_INVALID');
                }
            }
            if(($config['mydigitalid_enabled']??false)===true && ($config['mydigitalid_callback_registered']??false)!==true)throw new RuntimeException('MOBILE_MYDID_CALLBACK_NOT_APPROVED');
        }
        return $environment;
    }
}
