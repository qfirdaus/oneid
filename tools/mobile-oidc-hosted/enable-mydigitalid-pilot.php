<?php
declare(strict_types=1);
// Explicit root deployment step; only a dormant staging adapter can be changed.
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--apply'||posix_geteuid()!==0)exit(2);
$root=dirname(__DIR__,2);
if(realpath($root)!=='/var/www/oneid-uat')exit(2);
try {
 require_once $root.'/bootstrap/runtime_file.php';
 require_once $root.'/config/runtime.php';
 require_once $root.'/lib/secrets.php';
 require_once $root.'/vendor/autoload.php';
 $web=\OneId\App\Auth\MyDigitalId\MyDigitalIdConfig::fromRuntime();
 $mobile=$web->forMobileStaging();
 if(!$mobile->enabled)throw new RuntimeException('WEB_PROVIDER_DISABLED');
 \OneId\App\Auth\MyDigitalId\MyDigitalIdIdentityProtector::fromRuntime();
 $path=$root.'/.private/mobile-oidc-hosted.php';
 if(is_link($path)||fileowner($path)!==0)throw new RuntimeException('CONFIG_OWNER_INVALID');
 $config=require $path;
 if(($config['environment']??'')!=='staging'||($config['issuer']??'')!=='https://oneid-uat.upnm.edu.my'||($config['enabled']??null)!==false)throw new RuntimeException('STOP_PILOT_FIRST');
 if(($config['pilot_identifiers']??[])!==['0530-09'])throw new RuntimeException('CONTROLLED_PILOT_REQUIRED');
 if(($config['mydigitalid_enabled']??false)===true){echo "Already configured; dormant state retained.\n";exit;}
 umask(0077);
 $backup=$path.'.before-mydigitalid-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
 if(!copy($path,$backup)||!chmod($backup,0600))throw new RuntimeException('BACKUP_FAILED');
 $config['mydigitalid_enabled']=true;
 $tmp=$path.'.'.bin2hex(random_bytes(8));
 if(file_put_contents($tmp,"<?php\nreturn ".var_export($config,true).";\n")===false)throw new RuntimeException('WRITE_FAILED');
 if(!chmod($tmp,0640)||!chgrp($tmp,'www-data')||!rename($tmp,$path))throw new RuntimeException('INSTALL_FAILED');
 echo "PASS: mobile MyDigital ID configured; hosted remains OFF. Existing web config and callback unchanged.\n";
 echo "Next: pilot-control.py retry installs the loopback-only callback. Provider must allow the additional exact redirect URI.\n";
}catch(Throwable $e){if(isset($tmp)&&is_file($tmp))unlink($tmp);fwrite(STDERR,"STOP: configuration preflight/install failed. Ensure pilot is stopped and staging web MyDigital ID config is valid. No secrets printed.\n");exit(1);}
