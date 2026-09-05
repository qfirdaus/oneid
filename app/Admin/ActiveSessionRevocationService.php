<?php

namespace OneId\App\Admin;

use OneId\App\Admin\Adapters\SessionRevocationPreviewStore;
use Throwable;

require_once dirname(__DIR__) . '/Notification/AdminEmailNotificationException.php';
require_once dirname(__DIR__) . '/Notification/AdminEmailNotificationComposer.php';

final class ActiveSessionRevocationService
{
    public function __construct(
        private readonly object $operation,
        private readonly SessionRevocationPreviewStore $store,
        private readonly float $lifetimeHours
    ) {}

    public function preview(array $input, string $actor, string $currentToken): array
    {
        $this->assertEnabled($actor);
        $this->exactFields($input, ['admin_preview_active_session_revocation', 'target_id']);
        $targetId = strtolower(trim((string)($input['target_id'] ?? '')));
        if (preg_match('/\A[a-f0-9]{48}\z/', $targetId) !== 1) throw new ActiveSessionRevocationException('AS3_TARGET_INVALID');
        $locator = $this->store->consumeTarget($targetId, $actor);
        if ($locator === null) throw new ActiveSessionRevocationException('AS3_TARGET_STALE');
        $row = $this->operation->admin_session_revocation_target((string)$locator['user_id'], (string)$locator['token_id'], $this->cutoffs($actor, $currentToken));
        $this->assertTarget($row);
        $fingerprint = $this->fingerprint($row);
        $masked = $this->mask((string)$row['user_id']);
        $confirmation = 'REVOKE SESSION '.$masked.' '.strtoupper(substr($fingerprint, 0, 8));
        $approval = $this->store->issueApproval($actor, ['user_id'=>(string)$row['user_id'],'token_id'=>(string)$row['token_id'],'fingerprint'=>$fingerprint,'confirmation'=>$confirmation]);
        return ['status'=>1,'code'=>'AS3_PREVIEW_READY','approval_id'=>$approval['id'],'expires_at'=>$approval['expires_at'],'target'=>['user_id'=>$masked,'name'=>(string)$row['name'],'device_info'=>(string)$row['device_info'],'state'=>(string)$row['lifecycle_status'],'issued_at'=>(string)$row['issued_at'],'last_activity_at'=>(string)$row['last_activity_at']],'confirmation_phrase'=>$confirmation];
    }

    public function apply(array $input, string $actor, string $currentToken, string $ip): array
    {
        $this->assertEnabled($actor);
        $this->exactFields($input, ['admin_apply_active_session_revocation', 'approval_id', 'reason', 'confirmation']);
        $approvalId = strtolower(trim((string)($input['approval_id'] ?? '')));
        if (preg_match('/\A[a-f0-9]{64}\z/', $approvalId) !== 1) throw new ActiveSessionRevocationException('AS3_APPROVAL_INVALID');
        $approval = $this->store->consumeApproval($approvalId, $actor);
        if ($approval === null) throw new ActiveSessionRevocationException('AS3_APPROVAL_NOT_AVAILABLE');
        $reason = trim((string)($input['reason'] ?? ''));
        if (strlen($reason) < 10 || strlen($reason) > 250 || preg_match('/[\x00-\x1F\x7F]/', $reason) === 1) throw new ActiveSessionRevocationException('AS3_REASON_INVALID');
        if (!hash_equals((string)$approval['confirmation'], trim((string)($input['confirmation'] ?? '')))) throw new ActiveSessionRevocationException('AS3_CONFIRMATION_INVALID');
        $cid = bin2hex(random_bytes(8)); $started = false;
        try {
            $this->operation->beginTransaction(); $started = true;
            $row = $this->operation->admin_session_revocation_target_for_update((string)$approval['user_id'], (string)$approval['token_id'], $this->cutoffs($actor, $currentToken));
            $this->assertTarget($row);
            if (!hash_equals((string)$approval['fingerprint'], $this->fingerprint($row))) throw new ActiveSessionRevocationException('AS3_TARGET_STALE', $cid);
            if ($this->operation->admin_revoke_exact_session((string)$row['user_id'], (string)$row['token_id']) !== 1) throw new ActiveSessionRevocationException('AS3_REVOKE_RECONCILIATION_FAILED', $cid);
            $detail = sprintf('actor=%s action=ADMIN_ACTIVE_SESSION_REVOKE target_user=%s target_session_fingerprint=%s state_before=%s requested=1 matched=1 revoked=1 audited=1 reason_digest=%s reason_length=%d correlation=%s', $actor, (string)$row['user_id'], substr((string)$approval['fingerprint'],0,16), (string)$row['lifecycle_status'], substr(hash('sha256',$reason),0,16), strlen($reason), $cid);
            if ($this->operation->syslog_record(66, $detail, filter_var($ip,FILTER_VALIDATE_IP)?$ip:'0.0.0.0') !== 1) throw new ActiveSessionRevocationException('AS3_AUDIT_FAILED', $cid);
            $notificationId=\OneId\App\Notification\AdminEmailNotificationComposer::queueUserEvent(
                $this->operation,'SESSION_REVOKED',(string)$row['user_id'],$cid,$cid,
                ['Device'=>(string)$row['device_info'],'Action time'=>date('d/m/Y h:i A'),'Reference'=>$cid]
            );
            $this->operation->commit(); $started = false;
            return ['status'=>1,'code'=>'AS3_SESSION_REVOKED','requested'=>1,'matched'=>1,'revoked'=>1,'audited'=>1,'notification_queued'=>$notificationId!==null,'correlation_id'=>$cid];
        } catch (Throwable $e) {
            if ($started) $this->operation->rollback();
            if ($e instanceof ActiveSessionRevocationException) throw $e;
            throw new ActiveSessionRevocationException('AS3_APPLY_FAILED', $cid);
        }
    }

    private function assertEnabled(string $actor): void
    {
        if (!ActiveSessionRevocationConfig::enabled()) throw new ActiveSessionRevocationException('AS3_FEATURE_DISABLED');
        $session=session_id();$browser=hash('sha256',substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,1000));
        if ($session==='' || !method_exists($this->operation,'admin_step_up_authorization_state')) throw new ActiveSessionRevocationException('AS3_STEP_UP_REQUIRED');
        $state=$this->operation->admin_step_up_authorization_state($actor,hash('sha256',$session),$browser,'ACTIVE_SESSION_REVOCATION');
        if (!is_array($state)||(int)($state['admin_2fa_enabled']??0)!==1||(int)($state['u_type']??0)!==1||(int)($state['avail_status']??0)!==1||(int)($state['exact_valid']??0)<1) throw new ActiveSessionRevocationException('AS3_STEP_UP_REQUIRED');
    }
    private function exactFields(array $input, array $allowed): void { if (array_diff(array_keys($input), $allowed) !== []) throw new ActiveSessionRevocationException('AS3_UNEXPECTED_FIELD'); }
    private function assertTarget(mixed $row): void
    {
        if (!is_array($row)) throw new ActiveSessionRevocationException('AS3_TARGET_STALE');
        if ((int)($row['u_type'] ?? 0) === 1) throw new ActiveSessionRevocationException('AS3_ADMIN_TARGET_BLOCKED');
        if ((int)($row['is_current'] ?? 0) === 1) throw new ActiveSessionRevocationException('AS3_CURRENT_SESSION_BLOCKED');
        if (!in_array((string)($row['lifecycle_status'] ?? ''), ActiveSessionRevocationConfig::pilotStates(), true)) throw new ActiveSessionRevocationException('AS3_STATE_NOT_ALLOWED');
    }
    private function cutoffs(string $actor, string $currentToken): array
    {
        $now=time(); return ['now'=>date('Y-m-d H:i:s',$now),'active_cutoff'=>date('Y-m-d H:i:s',$now-(int)round($this->lifetimeHours*3600)),'refresh_cutoff'=>date('Y-m-d H:i:s',$now-(int)round($this->lifetimeHours*3600)-3600),'current_user_id'=>$actor,'current_token'=>$currentToken,'current_token_hash'=>$currentToken===''?'':\oneid_token_hash($currentToken)];
    }
    private function fingerprint(array $row): string { return hash('sha256', implode('|', [(string)$row['user_id'],(string)$row['token_id'],(string)$row['issued_at'],(string)$row['last_activity_at'],(string)($row['revoke_at']??''),(string)$row['lifecycle_status']])); }
    private function mask(string $id): string { $n=strlen($id); return $n<=4?str_repeat('*',$n):substr($id,0,2).str_repeat('*',max(2,$n-4)).substr($id,-2); }
}
