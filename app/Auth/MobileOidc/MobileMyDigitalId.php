<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;

use PDO;
use RuntimeException;
use OneId\App\Auth\MyDigitalId\MyDigitalIdConfig;
use OneId\App\Auth\MyDigitalId\MyDigitalIdAuthorizationTransactionStore;
use OneId\App\Auth\MyDigitalId\MyDigitalIdAuthorizationRequest;
use OneId\App\Auth\MyDigitalId\MyDigitalIdCallbackRequest;
use OneId\App\Auth\MyDigitalId\MyDigitalIdProtocolClient;
use OneId\App\Auth\MyDigitalId\MyDigitalIdProtocolGateway;
use OneId\App\Auth\MyDigitalId\MyDigitalIdIdentityProtector;

/** Dedicated mobile session; never invokes the web finalizer or web callback. */
final class MobileMyDigitalId
{
    public function __construct(private readonly MobileIdentityAdapter $adapter, private readonly MyDigitalIdConfig $config,
        private readonly \OneId\App\Auth\MyDigitalId\MyDigitalIdProtocolGatewayInterface $protocol,
        private readonly MobileMyDigitalIdAccounts $accounts) {}

    public static function fromRuntime(PDO $pdo, MobileIdentityAdapter $adapter, string $environment = 'staging'): self
    {
        $root = dirname(__DIR__,3);
        require_once $root . '/bootstrap/runtime_file.php';
        require_once $root . '/config/runtime.php';
        require_once $root . '/lib/secrets.php';
        require_once $root . '/vendor/autoload.php';
        $config = MyDigitalIdConfig::fromRuntime()->forMobile($environment);
        if (!$config->enabled) throw new RuntimeException('MYDID_DISABLED');
        return new self($adapter, $config, new MyDigitalIdProtocolGateway(new MyDigitalIdProtocolClient($config)),
            new PdoMobileMyDigitalIdAccounts($pdo, MyDigitalIdIdentityProtector::fromRuntime()));
    }

    public function start(array &$session, string $agent): string
    {
        if (!isset($session['tx'],$session['binding'])
            || !$this->adapter->beginMyDigitalId($session['tx'],$session['binding'],$agent)) throw new RuntimeException('MOBILE_MYDID_BINDING_INVALID');
        $session['mydid_mobile_binding'] = ['tx'=>$session['tx']];
        $transaction = (new MyDigitalIdAuthorizationTransactionStore())->create($session,time());
        return (new MyDigitalIdAuthorizationRequest($this->config))->url($transaction);
    }

    public function finish(array &$session, string $agent, string $ip, array $query): array
    {
        $binding = $session['mydid_mobile_binding'] ?? null;
        unset($session['mydid_mobile_binding']);
        // Consume state before protocol exchange, including rejected or malformed callbacks.
        $state = is_string($query['state']??null) ? $query['state'] : '';
        $transaction = (new MyDigitalIdAuthorizationTransactionStore())->consume($session,$state,time());
        if (!is_array($binding) || !isset($session['tx'],$session['binding']) || $binding['tx']!==$session['tx']
            || !$this->adapter->myDigitalIdPending($session['tx'],$session['binding'],$agent)) throw new RuntimeException('MOBILE_MYDID_BINDING_INVALID');
        $request = MyDigitalIdCallbackRequest::fromHttp('GET',$query);
        $verified = $this->protocol->complete($request,$transaction);
        $resolved=$this->accounts->resolve($verified);
        $result=$this->adapter->offerMyDigitalId($session['tx'],$session['binding'],$agent,$resolved['ids'],$verified->nric);
        if(($result['code']??'')!=='ACCOUNT_REQUIRED')return $result;
        $session['mydid_account_proof']=['tx'=>$session['tx'],'proof'=>$resolved['proof']];
        if(count($result['choices'])===1)return $this->select($session,$agent,$result['choices'][0]['id']);
        return $result;
    }

    public function select(array &$session,string $agent,string $choice): array
    {
        $pending=$session['mydid_account_proof']??null;
        unset($session['mydid_account_proof']);
        if(!is_array($pending) || !isset($session['tx'],$session['binding']) || $pending['tx']!==$session['tx'])return ['error'=>'MOBILE_TRANSACTION_INVALID'];
        return $this->adapter->chooseMyDigitalId($session['tx'],$session['binding'],$agent,$choice,
            fn(string $uid): bool=>$this->accounts->allows($uid,$pending['proof']));
    }
}
