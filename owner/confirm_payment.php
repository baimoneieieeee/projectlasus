<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

$order_id = $conn->real_escape_string(trim($_GET['id']));

// ดึงข้อมูลออเดอร์มาตรวจสอบ
$sql_check = "SELECT * FROM orders WHERE order_id = '$order_id'";
$res = $conn->query($sql_check);
$order = $res->fetch_assoc();

if ($order) {
    if ($order['order_status'] >= 2) {
        header("Location: order_process.php?id=$order_id");
        exit;
    }

    $m_id = $order['m_id'];
    $items_to_deduct = (int)$order['order_items_count'];
    $is_pkg = (strpos($order_id, 'PKG') === 0);

    // อัปเดตสถานะเป็น "กำลังดำเนินการซัก/รีด" (Status 2)
    // รักษาค่า order_est_pickup และ order_owner_note ไว้เหมือนเดิม
    if ($conn->query("UPDATE orders SET order_status = 2 WHERE order_id = '$order_id'")) {
        
        $json_file = __DIR__ . '/../config/monthly_data.json';
        $monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];

        if ($is_pkg) {
            // --- 🚩 กรณีสมัครสมาชิกใหม่ (PKG) ---
            // หมายเหตุเดิมจาก monthly_plans.php: "สมัครแพ็กเกจ: $pkg_name ($quota ชิ้น) ราคา $price บาท"
            preg_match('/\(([\d,]+) ชิ้น\)/', $order['order_note'], $matches);
            $quota_to_add = isset($matches[1]) ? (int)str_replace(',', '', $matches[1]) : 80;

            if (!isset($monthly_data[$m_id])) {
                $monthly_data[$m_id] = ['quota' => 0, 'updated_at' => '', 'pickups_used' => 0, 'total_quota' => 0];
            }
            // เก็บยอดโควตาตั้งต้นไว้คำนวณ
            $monthly_data[$m_id]['total_quota'] = $quota_to_add;
            
            // เพิ่มโควตาใหม่ - หักจำนวนที่ส่งซักมาพร้อมกัน
            $monthly_data[$m_id]['quota'] += ($quota_to_add - $items_to_deduct);
            $p_used = ($items_to_deduct > 0) ? 1 : 0;
            $monthly_data[$m_id]['pickups_used'] = $p_used;
            $monthly_data[$m_id]['updated_at'] = date("Y-m-d H:i:s");
            file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));

            // 🚩 บันทึกลง DB ด้วยว่าบิล PKG นี้คือรอบที่ 1 (ถ้ามีผ้ามาซัก)
            if($p_used > 0) {
                $conn->query("UPDATE orders SET order_pickup_round = 1 WHERE order_id = '$order_id'");
            }

        }
        // 🚨 ลบส่วน else ของออเดอร์ปกติออก เพราะเราหักยอดไปแล้วใน deduct_monthly.php

        header("Location: order_process.php?id=$order_id");
        exit;
    }
}
?>
