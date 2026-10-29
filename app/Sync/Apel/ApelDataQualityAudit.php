<?php

declare(strict_types=1);

namespace OneId\App\Sync\Apel;

use OneId\App\Sync\Provenance\ProvenanceBackfillPreview;

final class ApelDataQualityAudit
{
    /** @param list<array<string,mixed>> $rows @param list<array<string,mixed>> $users @param list<array<string,mixed>> $memberships */
    public function audit(array $rows, array $users, array $memberships): array
    {
        $fields=['name'=>'data1','ic'=>'data2','matric'=>'data4','email'=>'data5','faculty'=>'data6','program'=>'data7'];
        $limits=['name'=>100,'ic'=>100,'matric'=>20,'email'=>100,'faculty'=>100,'program'=>100];
        $blank=array_fill_keys(array_keys($fields),0);$over=array_fill_keys(array_keys($fields),0);
        $invalidUtf8=0;$invalidEmail=0;$invalidMatric=0;$statuses=[];
        foreach($rows as$row){$rowUtf8=false;foreach($fields as$label=>$field){
            $value=trim((string)($row[$field]??''));$blank[$label]+=$value===''?1:0;
            if(preg_match('//u',$value)!==1)$rowUtf8=true;elseif(mb_strlen($value,'UTF-8')>$limits[$label])$over[$label]++;
        }$invalidUtf8+=$rowUtf8?1:0;$email=trim((string)($row['data5']??''));
        $invalidEmail+=$email!==''&&filter_var($email,FILTER_VALIDATE_EMAIL)===false?1:0;
        $matric=trim((string)($row['data4']??''));$invalidMatric+=$matric!==''&&preg_match('/\A[A-Za-z0-9]+\z/',$matric)!==1?1:0;
        $status=trim((string)($row['external_status_code']??''));$statuses[$status]=($statuses[$status]??0)+1;}
        ksort($statuses,SORT_STRING);
        $identity=(new ProvenanceBackfillPreview())->preview(ApelSourceDefinition::SOURCE_CODE,$rows,$users,$memberships);
        $normalize=static fn(mixed$v):string=>preg_replace('/[\s\p{Pd}]+/u','',trim((string)$v))??trim((string)$v);
        $userPairs=[];foreach($users as$user)$userPairs[$normalize($user['u_id']??'').'|'.$normalize($user['data2']??'')][]=(string)($user['u_id']??'');
        $membersByUser=[];foreach($memberships as$m){if((int)($m['source_active']??1)===1)$membersByUser[$normalize($m['u_id']??'')][(string)($m['source_code']??'')]=true;}
        $overlap=['STUDENT_UG'=>0,'STUDENT_ODL_PG'=>0,'OTHER'=>0];
        foreach($rows as$row){$pair=$normalize($row['data4']??'').'|'.$normalize($row['data2']??'');$matches=array_unique($userPairs[$pair]??[]);
            if(count($matches)!==1)continue;foreach(array_keys($membersByUser[$normalize($matches[0])]??[])as$source){
                if($source===ApelSourceDefinition::SOURCE_CODE)continue;
                $key=in_array($source,['STUDENT_UG','STUDENT_ODL_PG'],true)?$source:'OTHER';$overlap[$key]++;
            }}
        $blocking=(int)$identity['blocking_findings']+$blank['name']+$blank['ic']+$blank['matric']+array_sum($over)+$invalidUtf8+$invalidEmail+$invalidMatric;
        return[
            'mode'=>'apel_read_only_audit','can_apply'=>false,'source_code'=>ApelSourceDefinition::SOURCE_CODE,
            'source_rows'=>count($rows),'status_counts'=>$statuses,'blank_fields'=>$blank,'overlength_fields'=>$over,
            'invalid_utf8_rows'=>$invalidUtf8,'invalid_email_rows'=>$invalidEmail,'invalid_matric_format_rows'=>$invalidMatric,
            'valid_student_identities'=>$identity['valid_student_identities'],'duplicate_pair_groups'=>$identity['duplicate_pair_groups'],
            'duplicate_pair_rows'=>$identity['duplicate_pair_rows'],'matric_conflict_groups'=>$identity['matric_conflict_groups'],
            'ic_conflict_groups'=>$identity['ic_conflict_groups'],'matched_active_users'=>$identity['matched_active_users'],
            'matched_inactive_users'=>$identity['matched_inactive_users'],'unmatched_external'=>$identity['unmatched_external'],
            'protected_collisions'=>$identity['protected_collisions'],'ambiguous_user_matches'=>$identity['ambiguous_user_matches'],
            'membership_conflicts'=>$identity['membership_conflicts'],'active_membership_overlap'=>$overlap,
            'blocking_findings'=>$blocking,'review_findings'=>(int)$identity['review_findings']+array_sum($overlap),
            'snapshot_digest'=>hash('sha256',json_encode([count($rows),$statuses,$blank,$over,$identity['plan_digest']],JSON_UNESCAPED_SLASHES)?:''),
            'mutation_statements'=>0,
        ];
    }
}
