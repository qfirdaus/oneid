<?php
declare(strict_types=1);
namespace OneId\App\Sync\Odl;

use OneId\App\Sync\DTO\OdlFullPlan;
use OneId\App\Sync\SyncDataTransformer;

final class OdlFullPlanner
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $users
     * @param list<array<string,mixed>> $memberships
     */
    public function plan(
        array $rows,
        array $users,
        array $memberships,
        OdlFullConfig $config
    ): OdlFullPlan {
        $norm=static fn(mixed$v):string=>
            preg_replace('/[\s\p{Pd}]+/u','',trim((string)$v))
            ??trim((string)$v);
        $byPair=[];$byMatric=[];$byIc=[];
        foreach($users as$user){
            $matric=$norm($user['u_id']??'');$ic=$norm($user['data2']??'');
            if($matric!=='')$byMatric[$matric][]=$user;
            if($ic!=='')$byIc[$ic][]=$user;
            if($matric!==''&&$ic!=='')$byPair[$matric.'|'.$ic][]=$user;
        }
        $memberByExternal=[];$memberByUser=[];
        foreach($memberships as$m){
            if(($m['source_code']??'')!==OdlStudentSource::SOURCE_CODE)continue;
            $external=$norm($m['external_user_id']??'');
            $userId=$norm($m['u_id']??'');
            $memberByExternal[$external]=$m;
            $memberByUser[$userId]=$m;
        }
        $seen=[];$seenExternal=[];$new=[];$keep=0;
        foreach($rows as$row){
            if(($row['source_code']??'')!==OdlStudentSource::SOURCE_CODE){
                throw new \RuntimeException('ODL_FULL_SOURCE_MISMATCH');
            }
            $matric=$norm($row['data4']??'');$ic=$norm($row['data2']??'');
            if($matric===''||$ic==='')throw new \RuntimeException('ODL_FULL_IDENTITY_INVALID');
            $pair=$matric.'|'.$ic;
            if(isset($seen[$pair]))throw new \RuntimeException('ODL_FULL_SOURCE_DUPLICATE');
            $seen[$pair]=true;
            $seenExternal[$matric]=true;
            $matches=$byPair[$pair]??[];
            if(count($matches)>1)throw new \RuntimeException('ODL_FULL_IDENTITY_AMBIGUOUS');
            if($matches===[]){
                if(isset($byMatric[$matric])||isset($byIc[$ic])){
                    throw new \RuntimeException('ODL_FULL_CROSS_SOURCE_IDENTITY_CONFLICT');
                }
                $row['data2']=$ic;$row['data4']=$matric;
                $new[]=[
                    'action'=>'NEW','u_id'=>$matric,'row'=>$row,'category_id'=>10,
                    'change_hash'=>SyncDataTransformer::computeHash(
                        ...array_map(static fn(string$f):string=>(string)($row[$f]??''),[
                            'data1','data2','data3','data4','data5','data6',
                            'data7','data8','data9','data10','data11','data12',
                            'ext_data_source_category',
                        ])
                    ),
                ];
                continue;
            }
            $user=$matches[0];
            if(($user['account_source']??'')==='manual'
                &&(int)($user['sync_protected']??0)===1){
                throw new \RuntimeException('ODL_FULL_PROTECTED_COLLISION');
            }
            $membership=$memberByExternal[$matric]??null;
            if($membership===null
                ||$norm($membership['u_id']??'')!==$norm($user['u_id']??'')
                ||(int)($membership['source_active']??0)!==1){
                throw new \RuntimeException('ODL_FULL_MEMBERSHIP_CONFLICT');
            }
            $keep++;
        }
        foreach($memberByExternal as$external=>$membership){
            if((int)($membership['source_active']??0)===1
                &&!isset($seenExternal[$external])){
                throw new \RuntimeException('ODL_FULL_DEACTIVATION_NOT_ALLOWED');
            }
        }
        usort($new,static fn(array$a,array$b):int=>
            strcmp((string)$a['u_id'],(string)$b['u_id']));
        $plan=new OdlFullPlan($new,count($rows),$keep);
        if($plan->sourceRows!==$config->expectedSourceRows
            ||count($new)!==$config->expectedNew
            ||$keep!==$config->expectedKeep){
            throw new \RuntimeException('ODL_FULL_EXPECTED_COUNTS_MISMATCH');
        }
        return $plan;
    }
}
