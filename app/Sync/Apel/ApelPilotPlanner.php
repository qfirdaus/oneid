<?php
declare(strict_types=1);
namespace OneId\App\Sync\Apel;

use OneId\App\Sync\DTO\SyncPlan;
use OneId\App\Sync\SyncDataTransformer;

final class ApelPilotPlanner
{
    /** @param list<string> $allowlist */
    public function __construct(private readonly array $allowlist) {}

    /** @param list<array<string,mixed>> $rows @param list<array<string,mixed>> $users @param list<array<string,mixed>> $memberships */
    public function plan(array $rows, array $users, array $memberships): SyncPlan
    {
        if (count($this->allowlist) !== 3) throw new \RuntimeException('APEL_PILOT_SCOPE_MUST_EQUAL_THREE');
        $norm = static fn(mixed $v): string => preg_replace('/[\s\p{Pd}]+/u', '', trim((string)$v)) ?? trim((string)$v);
        $existing = [];
        foreach ($users as $user) {
            $matric=$norm($user['u_id']??''); $ic=$norm($user['data2']??'');
            if ($matric!=='' || $ic!=='') $existing[$matric.'|'.$ic]=true;
        }
        $existingMemberships=[];
        foreach ($memberships as $membership) {
            if (($membership['source_code']??'')===ApelSourceDefinition::SOURCE_CODE) {
                $existingMemberships[$norm($membership['external_user_id']??'')]=true;
            }
        }
        $selected=[];
        foreach ($rows as $row) {
            if (($row['source_code']??'')!==ApelSourceDefinition::SOURCE_CODE) throw new \RuntimeException('APEL_PILOT_SOURCE_MISMATCH');
            $matric=$norm($row['data4']??''); $ic=$norm($row['data2']??'');
            if ($matric==='' || $ic==='') throw new \RuntimeException('APEL_PILOT_IDENTITY_INVALID');
            $digest=hash('sha256',$matric.'|'.$ic);
            if (!in_array($digest,$this->allowlist,true)) continue;
            if (isset($selected[$digest])) throw new \RuntimeException('APEL_PILOT_IDENTITY_DUPLICATE');
            if (isset($existing[$matric.'|'.$ic]) || isset($existingMemberships[$matric])) throw new \RuntimeException('APEL_PILOT_NEW_ONLY_VIOLATION');
            $row['data2']=$ic; $row['data4']=$matric; $row['ext_data_source_category']='Pelajar';
            $selected[$digest]=$row;
        }
        if (count($selected)!==3) throw new \RuntimeException('APEL_PILOT_ALLOWLIST_NOT_FULLY_RESOLVED');
        $actions=[];
        foreach ($selected as $row) {
            $fields=array_map(static fn(string $f):string=>(string)($row[$f]??''),[
                'data1','data2','data3','data4','data5','data6','data7','data8','data9','data10','data11','data12','ext_data_source_category'
            ]);
            $actions[]=['action'=>'NEW','u_id'=>$row['data4'],'row'=>$row,'old_data'=>null,
                'new_data'=>SyncDataTransformer::buildLogSnapshot($row),'changed_fields'=>null,'category_id'=>10,
                'change_hash'=>SyncDataTransformer::computeHash(...$fields)];
        }
        return new SyncPlan($actions,count($rows),0,0);
    }
}
