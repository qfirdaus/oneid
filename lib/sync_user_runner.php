<?php

require_once dirname(__DIR__) . '/app/Sync/SyncDataTransformer.php';

function sync_compute_hash($d1,$d2,$d3,$d4,$d5,$d6,$d7,$d8,$d9,$d10,$d11,$d12,$category=''){
    return \OneId\App\Sync\SyncDataTransformer::computeHash(
        $d1,$d2,$d3,$d4,$d5,$d6,$d7,$d8,$d9,$d10,$d11,$d12,$category
    );
}

function sync_log_field_names(){
    return \OneId\App\Sync\SyncDataTransformer::logFieldNames();
}

function sync_build_log_snapshot($row){
    return \OneId\App\Sync\SyncDataTransformer::buildLogSnapshot($row);
}

function sync_pick_log_fields($row, $fields){
    return \OneId\App\Sync\SyncDataTransformer::pickLogFields($row, $fields);
}

function sync_get_changed_fields($old, $new){
    return \OneId\App\Sync\SyncDataTransformer::getChangedFields($old, $new);
}

function sync_remove_duplicateKeys($rows){
    return \OneId\App\Sync\SyncDataTransformer::removeDuplicateKeys($rows);
}

function sync_get_exclude_uids(){
    return ['10'];
}

/**
 * Run full external → SSO user sync (same logic as admin_add_sync_user).
 *
 * @return array Sync session summary (ext_head_id, counts, status fields)
 * @throws Exception on failure (transaction rolled back)
 */
function run_admin_sync_user($operation, $triggered_by){
    set_time_limit(0);
    ini_set('memory_limit', '256M');

    $sync_count_new = 0;
    $sync_count_updated = 0;
    $sync_count_deactivated = 0;
    $sync_count_reactivated = 0;
    $sync_log_buffer = [];

    $operation->beginTransaction();
    $header_id = $operation->action_add_new_ext_header(0);

    $list = EXTERNAL_DATA_SOURCE_GET_ALL_USER();

    if(!empty($list)){
        foreach ($list as $i => $ii) {
            $list[$i]['hash'] = sync_compute_hash($list[$i]['data1'],$list[$i]['data2'],$list[$i]['data3'],$list[$i]['data4'],$list[$i]['data5'],$list[$i]['data6'],$list[$i]['data7'],$list[$i]['data8'],$list[$i]['data9'],$list[$i]['data10'],$list[$i]['data11'],$list[$i]['data12'],$list[$i]['ext_data_source_category']);
            if(isset($list[$i]['data4'])){
                if($list[$i]['data4'] == "" || $list[$i]['data4'] == " "){
                    unset($list[$i]);
                    continue;
                }
            }else{
                unset($list[$i]);
                continue;
            }
        }

        $norm = fn($v) => trim((string)$v);
        $sync_exclude_uids = array_flip(array_map($norm, sync_get_exclude_uids()));
        $list = array_values(array_filter($list, function($row) use ($sync_exclude_uids, $norm) {
            return !isset($sync_exclude_uids[$norm($row['data4'] ?? '')]);
        }));

        $sso_list = $operation->sync_get_all_sso_user();
        $sso_list = array_values(array_filter($sso_list, function($sso) use ($sync_exclude_uids, $norm) {
            return !isset($sync_exclude_uids[$norm($sso['u_id'] ?? '')]);
        }));
        $sso_by_uid = [];
        foreach ($sso_list as $sso_row) {
            $sso_by_uid[$sso_row['u_id']] = $sso_row;
        }
        $inactive_uid_map = array_flip($operation->sync_get_inactive_user_ids());

        $ext_by_ic = [];
        $ext_by_student = [];
        foreach ($list as $row) {
            $isPelajar = isset($row['ext_data_source_category'])
                && $norm($row['ext_data_source_category']) === 'Pelajar';
            if ($isPelajar) {
                $matrik = $norm($row['data4'] ?? '');
                $ic = $norm($row['data2'] ?? '');
                if ($matrik !== '' && $ic !== '') {
                    $ext_by_student[$matrik . '|' . $ic] = $row;
                }
            } else {
                $ic = $norm($row['data4'] ?? '');
                if ($ic !== '') {
                    $ext_by_ic[$ic] = $row;
                }
            }
        }

        $to_remove_list = [];
        $matched_sso = [];

        foreach ($sso_list as $sso) {
            $row = null;
            $matrik = $norm($sso['u_id'] ?? '');
            $ic = $norm($sso['data2'] ?? '');
            $studentKey = $matrik . '|' . $ic;

            if ($matrik !== '' && $ic !== '' && isset($ext_by_student[$studentKey])) {
                $row = $ext_by_student[$studentKey];
            } elseif (isset($ext_by_ic[$norm($sso['data4'] ?? '')])) {
                $row = $ext_by_ic[$norm($sso['data4'])];
            }

            if ($row !== null) {
                $row['u_id'] = $sso['u_id'];
                $matched_sso[] = $row;
            } else {
                $to_remove_list[] = $sso;
            }
        }

        try {
            if(!empty($to_remove_list)){
                foreach ($to_remove_list as $rm_list) {
                    $operation->admin_update_user_status($rm_list['u_id'],0);
                    $sync_log_buffer[] = [
                        'ext_head_id' => $header_id,
                        'u_id' => $rm_list['u_id'],
                        'action' => 'DEACTIVATE',
                        'old_data' => sync_build_log_snapshot($rm_list),
                        'new_data' => null,
                        'changed_fields' => null
                    ];
                    $sync_count_deactivated++;
                }
            }

            if(!empty($matched_sso)){
                foreach ($matched_sso as $updt_list) {
                    $old = $sso_by_uid[$updt_list['u_id']] ?? null;
                    if ($old) {
                        $changed_fields = sync_get_changed_fields($old, $updt_list);
                        if ($changed_fields !== '') {
                            $fields = explode(',', $changed_fields);
                            $operation->admin_update_specific_user_info_all_data($updt_list['u_id'],$updt_list['data1'],$updt_list['data2'],$updt_list['data3'],$updt_list['data4'],$updt_list['data5'],$updt_list['data6'],$updt_list['data7'],$updt_list['data8'],$updt_list['data9'],$updt_list['data10'],$updt_list['data11'],$updt_list['data12'],$updt_list['hash']);
                            $sync_log_buffer[] = [
                                'ext_head_id' => $header_id,
                                'u_id' => $updt_list['u_id'],
                                'action' => 'UPDATE',
                                'old_data' => sync_pick_log_fields($old, $fields),
                                'new_data' => sync_pick_log_fields($updt_list, $fields),
                                'changed_fields' => $changed_fields
                            ];
                            $sync_count_updated++;
                        }
                    }
                }
            }

            $operation->admin_update_ext_header_status($header_id,1,'ext_head_initial_sourcedata',count($list));
            foreach ($list as $i => $ii) {
                $list[$i]['u_changes_hash'] = sync_compute_hash($list[$i]['data1'],$list[$i]['data2'],$list[$i]['data3'],$list[$i]['data4'],$list[$i]['data5'],$list[$i]['data6'],$list[$i]['data7'],$list[$i]['data8'],$list[$i]['data9'],$list[$i]['data10'],$list[$i]['data11'],$list[$i]['data12'],$list[$i]['ext_data_source_category']);
                $list[$i]['source'] = '2';
            }

            $sso_hash_map = [];
            foreach ($sso_list as $sso_row) {
                if (!empty($sso_row['u_changes_hash'])) {
                    $sso_hash_map[$sso_row['u_changes_hash']] = $sso_row;
                }
            }
            foreach ($matched_sso as $updt) {
                $new_hash = sync_compute_hash($updt['data1'],$updt['data2'],$updt['data3'],$updt['data4'],$updt['data5'],$updt['data6'],$updt['data7'],$updt['data8'],$updt['data9'],$updt['data10'],$updt['data11'],$updt['data12'],$updt['ext_data_source_category']);
                $old_hash = $sso_by_uid[$updt['u_id']]['u_changes_hash'] ?? null;
                if ($old_hash !== null && $old_hash !== '') {
                    unset($sso_hash_map[$old_hash]);
                }
                $sso_hash_map[$new_hash] = array_merge($updt, ['u_changes_hash' => $new_hash, 'source' => '1']);
            }
            $sso_user_list = array_values($sso_hash_map);

            $merged_data = array_merge($list, $sso_user_list);
            $filtered_data_A = sync_remove_duplicateKeys($merged_data);

            $matched_data4_map = [];
            foreach ($matched_sso as $_m) {
                $k = $norm($_m['data4'] ?? '');
                if ($k !== '') $matched_data4_map[$k] = true;
            }

            foreach ($filtered_data_A as $i => $ii) {
                if($filtered_data_A[$i]['source'] == "1"){
                    unset($filtered_data_A[$i]);
                    continue;
                }
                $row_data4 = $norm($filtered_data_A[$i]['data4'] ?? '');
                if ($row_data4 !== '' && isset($matched_data4_map[$row_data4])) {
                    unset($filtered_data_A[$i]);
                    continue;
                }
                $filtered_data_A[$i]['ext_body_id'] = $operation->action_add_external_temp_body($header_id,$filtered_data_A[$i]['data1'],$filtered_data_A[$i]['data2'],$filtered_data_A[$i]['data3'],$filtered_data_A[$i]['data4'],$filtered_data_A[$i]['data5'],$filtered_data_A[$i]['data6'],$filtered_data_A[$i]['data7'],$filtered_data_A[$i]['data8'],$filtered_data_A[$i]['data9'],$filtered_data_A[$i]['data10'],$filtered_data_A[$i]['data11'],$filtered_data_A[$i]['data12']);
            }

            $filtered_data_B = array_values($filtered_data_A);
            if(!empty($filtered_data_B)){
                foreach ($filtered_data_B as $i => $ii) {
                    $user_category = 0;
                    $password = "";
                    switch($filtered_data_B[$i]['ext_data_source_category']){
                        case "Akademik":
                            $user_category = 2;
                            $password = oneid_password_hash(bin2hex(random_bytes(32)));
                        break;
                        case "Pentadbiran":
                            $user_category = 3;
                            $password = oneid_password_hash(bin2hex(random_bytes(32)));
                        break;
                        case "Pelajar":
                            $user_category = 10;
                            $password = oneid_password_hash(bin2hex(random_bytes(32)));
                        break;
                        case "PelajarPelajar":
                            $user_category = 10;
                            $password = oneid_password_hash(bin2hex(random_bytes(32)));
                        break;
                        case "PentadbiranPelajar":
                            $user_category =11;
                            $password = oneid_password_hash(bin2hex(random_bytes(32)));
                        break;
                        case "AkademikPelajar":
                            $user_category =12;
                            $password = oneid_password_hash(bin2hex(random_bytes(32)));
                        break;
                        default:
                            $user_category = 0;
                            $password = oneid_password_hash(bin2hex(random_bytes(32)));
                        break;
                    }
                    $operation->action_add_new_user_from_external_source($filtered_data_B[$i]['data4'],$user_category,$password,$filtered_data_B[$i]['data1'],$filtered_data_B[$i]['data2'],$filtered_data_B[$i]['data3'],$filtered_data_B[$i]['data4'],$filtered_data_B[$i]['data5'],$filtered_data_B[$i]['data6'],$filtered_data_B[$i]['data7'],$filtered_data_B[$i]['data8'],$filtered_data_B[$i]['data9'],$filtered_data_B[$i]['data10'],$filtered_data_B[$i]['data11'],$filtered_data_B[$i]['data12'],sync_compute_hash($filtered_data_B[$i]['data1'],$filtered_data_B[$i]['data2'],$filtered_data_B[$i]['data3'],$filtered_data_B[$i]['data4'],$filtered_data_B[$i]['data5'],$filtered_data_B[$i]['data6'],$filtered_data_B[$i]['data7'],$filtered_data_B[$i]['data8'],$filtered_data_B[$i]['data9'],$filtered_data_B[$i]['data10'],$filtered_data_B[$i]['data11'],$filtered_data_B[$i]['data12'],$filtered_data_B[$i]['ext_data_source_category']));
                    $operation->admin_update_ext_body_status($header_id,$filtered_data_B[$i]['ext_body_id'],2);
                    $sync_uid = $filtered_data_B[$i]['data4'];
                    if (isset($inactive_uid_map[$sync_uid])) {
                        $sync_log_buffer[] = [
                            'ext_head_id' => $header_id,
                            'u_id' => $sync_uid,
                            'action' => 'REACTIVATE',
                            'old_data' => null,
                            'new_data' => sync_build_log_snapshot($filtered_data_B[$i]),
                            'changed_fields' => null
                        ];
                        $sync_count_reactivated++;
                    } else {
                        $sync_log_buffer[] = [
                            'ext_head_id' => $header_id,
                            'u_id' => $sync_uid,
                            'action' => 'NEW',
                            'old_data' => null,
                            'new_data' => sync_build_log_snapshot($filtered_data_B[$i]),
                            'changed_fields' => null
                        ];
                        $sync_count_new++;
                    }
                }
                $operation->admin_update_ext_header_status($header_id,2,'ext_head_uploaded_data',count($filtered_data_B));
            }else{
                $operation->admin_update_ext_header_status($header_id,4,'ext_head_uploaded_data',count($filtered_data_B));
            }

            $operation->sync_log_change_batch($sync_log_buffer);
            $operation->sync_update_header_summary(
                $header_id,
                $sync_count_new,
                $sync_count_updated,
                $sync_count_deactivated,
                $sync_count_reactivated,
                $triggered_by
            );
            $operation->commit();
        } catch (Exception $e) {
            $operation->rollback();
            throw $e;
        }
    }else{
        $operation->admin_update_ext_header_status($header_id,3,'ext_head_initial_sourcedata',0);
        $operation->sync_update_header_summary(
            $header_id,
            $sync_count_new,
            $sync_count_updated,
            $sync_count_deactivated,
            $sync_count_reactivated,
            $triggered_by
        );
        $operation->commit();
    }

    $header_info = $operation->action_get_ext_header($header_id);
    $header_info['Deactivate'] = $sync_count_deactivated;
    $header_info['Update'] = $sync_count_updated;
    $header_info['New'] = $sync_count_new;
    $header_info['Reactivate'] = $sync_count_reactivated;
    return $header_info;
}
