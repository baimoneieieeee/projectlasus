<?php
$d = [
    'MEM1774206633' => [
        'quota' => 0,
        'pickups_used' => 4,
        'updated_at' => date('Y-m-d H:i:s')
    ]
];
file_put_contents('config/monthly_data.json', json_encode($d, JSON_PRETTY_PRINT));
echo "กู้คืนข้อมูลสำเร็จ!";
?>