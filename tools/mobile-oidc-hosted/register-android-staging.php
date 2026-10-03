<?php
declare(strict_types=1);
// Register the approved Android callback on staging only; preserve all existing clients.
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--apply'||posix_geteuid()!==0)exit(2);
$root=dirname(__DIR__,2);
if(realpath($root)!=='/var/www/oneid-uat')exit(2);
function provider(string $path,?array $body=null):array {
 $ch=curl_init('http://127.0.0.1:24145'.$path);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
 if($body!==null)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body,JSON_THROW_ON_ERROR)]);
 $raw=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
 if($raw===false)throw new RuntimeException('PROVIDER_UNAVAILABLE');
 return [$status,$raw===''?[]:json_decode($raw,true,32,JSON_THROW_ON_ERROR)];
}
try {
 $path=$root.'/.private/mobile-oidc-hosted.php';
 if(is_link($path)||fileowner($path)!==0)throw new RuntimeException('CONFIG_OWNER_INVALID');
 $config=require $path;
 if(($config['environment']??'')!=='staging'||($config['issuer']??'')!=='https://oneid-uat.upnm.edu.my'||($config['admin_url']??'')!=='http://127.0.0.1:24145')throw new RuntimeException('STAGING_REQUIRED');
 $client=json_decode(file_get_contents($root.'/deployment/mobile-oidc/android-staging-client.json'),true,32,JSON_THROW_ON_ERROR);
 $id='upnm-mobile-android-staging';$uri='com.upnmmobile1.app://oneid/callback';
 if($client['client_id']!==$id||$client['redirect_uris']!==[$uri]||$client['token_endpoint_auth_method']!=='none')throw new RuntimeException('CLIENT_CONTRACT_CHANGED');
 if(isset($config['clients'][$id])&&$config['clients'][$id]!==[$uri])throw new RuntimeException('ADAPTER_CLIENT_CONFLICT');
 [$status,$existing]=provider('/admin/clients/'.$id);
 if($status!==404 && $status!==200)throw new RuntimeException('PROVIDER_LOOKUP_FAILED');
 if($status===404){[$status,$existing]=provider('/admin/clients',$client);if($status!==201)throw new RuntimeException('REGISTRATION_FAILED');}
 foreach($client as $key=>$value){
  $actual=$existing[$key]??null;
  if(is_array($value)&&is_array($actual)){sort($value);sort($actual);}
  if($actual!==$value)throw new RuntimeException('PROVIDER_CLIENT_CONFLICT');
 }
 umask(0077);
 $backup=$path.'.before-android-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
 if(!copy($path,$backup)||!chmod($backup,0600))throw new RuntimeException('BACKUP_FAILED');
 $config['clients'][$id]=[$uri];$tmp=$path.'.'.bin2hex(random_bytes(8));
 if(file_put_contents($tmp,"<?php\nreturn ".var_export($config,true).";\n")===false||!chmod($tmp,0640)||!chgrp($tmp,'www-data')||!rename($tmp,$path))throw new RuntimeException('INSTALL_FAILED');
 echo "SUCCESS: Android staging client registered and adapter allowlist updated.\nclient_id: $id\nredirect_uri: $uri\nNo client secret. Existing clients and web login preserved.\n";
 if(($config['enabled']??false)!==true || isset($config['pilot_until']) || isset($config['pilot_identifiers']))echo "NOTE: existing access restrictions remain; run pilot-control.py open for all-account staging testing.\n";
}catch(Throwable $e){if(isset($tmp)&&is_file($tmp))unlink($tmp);fwrite(STDERR,"STOP: Android registration/configuration incomplete; existing client credentials not printed. A newly created provider client may remain pending adapter activation.\n");exit(1);}
