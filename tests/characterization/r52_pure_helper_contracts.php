<?php

return [
    'hash_input' => [
        ' A ', 'B', ' C', 'D ', 'E', ' F ', 'G', 'H', 'I', 'J', 'K', 'L', ' Staff ',
    ],
    'hash_expected' => 'ced552aa321a30559a2c58930927b657d9e1103cac868292c2b5835f63d3af4a',
    'field_names' => [
        'data1', 'data2', 'data3', 'data4', 'data5', 'data6', 'data7', 'ext_data_source_category',
    ],
    'snapshot_input' => [
        'data1' => ' Alice ',
        'data2' => ' 123 ',
        'data4' => null,
        'data7' => 7,
        'ext_data_source_category' => ' Staff ',
        'ignored' => 'not logged',
    ],
    'snapshot_expected' => [
        'data1' => 'Alice',
        'data2' => '123',
        'data3' => '',
        'data4' => '',
        'data5' => '',
        'data6' => '',
        'data7' => '7',
        'ext_data_source_category' => 'Staff',
    ],
    'pick_fields' => ['data2', 'data5'],
    'pick_expected' => [
        'data2' => '123',
        'data5' => '',
        'ext_data_source_category' => 'Staff',
    ],
    'changed_old' => [
        'data1' => ' Alice ',
        'data2' => 'old',
        'data5' => '',
    ],
    'changed_new' => [
        'data1' => 'Alice',
        'data2' => 'new',
        'data5' => 'set',
    ],
    'changed_expected' => 'data2,data5',
];
