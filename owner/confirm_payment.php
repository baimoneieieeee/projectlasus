<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

$order_id = $conn->real_escape_string($_GET['id']);

// ดึงข้อมูลออเดอร์มาตรวจสอบ
$sql_check = "SELECT * FROM orders WHERE order_id = '$order_id'";
$res = $conn->query($sql_check);
$order = $res->fetch_assoc();

if ($order) {
    // 1. อัปเดตสถานะออเดอร์เป็นจ่ายเงินแล้ว (3)
    $sql_update = "UPDATE orders SET order_status = 3 WHERE order_id = '$order_id'";
    
    if ($conn->query($sql_update)) {
        
        // 2. ตรวจสอบว่าเป็นออเดอร์รายเดือนหรือไม่ (ถ้า Note เป็น MONTHLY_PACKAGE_800)
        if ($order['order_note'] === 'MONTHLY_PACKAGE_800') {
            $m_id = $order['m_id'];
            $json_file = __DIR__ . '/../config/monthly_data.json';
            $monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];

            if (!isset($monthly_data[$m_id])) {
                $monthly_data[$m_id] = ['quota' => 0, 'updated_at' => ''];
            }
            
            // เติมโควตา 80 ชิ้น และรีเซ็ตจำนวนครั้งที่รับผ้า
            $monthly_data[$m_id]['quota'] += 80;
            $monthly_data[$m_id]['pickups_used'] = 0; // เริ่มนับครั้งใหม่
            $monthly_data[$m_id]['updated_at'] = date("Y-m-d H:i:s");
            
            file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));
        }

        header("Location: order_process.php?id=$order_id");
        exit;
    }
}
?>
