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
    if ($conn->query("UPDATE orders SET order_status = 2 WHERE order_id = '$order_id'")) {
        
        $json_file = __DIR__ . '/../config/monthly_data.json';
        $monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];

        if ($is_pkg) {
            // --- 🚩 กรณีสมัครสมาชิกใหม่ (PKG) ---
            preg_match('/MONTHLY_PACKAGE_(\d+)/', $order['order_note'], $matches);
            $quota_to_add = isset($matches[1]) ? (int)$matches[1] : 80;

            if (!isset($monthly_data[$m_id])) {
                $monthly_data[$m_id] = ['quota' => 0, 'updated_at' => '', 'pickups_used' => 0];
            }
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

            } else {
                // --- 🚩 กรณีออเดอร์ปกติของสมาชิกรายเดือน ---
                if (isset($monthly_data[$m_id])) {
                    // 🚨 สำคัญ: ต้องเป็นออเดอร์ที่มีการหักผ้า (items > 0) ถึงจะนับเป็นรอบสมาชิก (Round)
                    if ($items_to_deduct > 0) {
                        $current_round = (int)($monthly_data[$m_id]['pickups_used'] ?? 0);
                        
                        // กั้นรอบไม่ให้เกิน 4 (ตามกฎของร้าน)
                        if ($current_round < 4) {
                            $next_round = $current_round + 1;
                            
                            // หักโควตาผ้า
                            $monthly_data[$m_id]['quota'] -= $items_to_deduct;
                            
                            // บันทึกรอบลงในตาราง orders เพื่อให้ลูกค้าเห็นในกิจกรรมสมาชิก
                            $conn->query("UPDATE orders SET order_pickup_round = $next_round WHERE order_id = '$order_id'");
                            
                            // อัปเดตจำนวนครั้งที่มารับผ้าใน JSON
                            $monthly_data[$m_id]['pickups_used'] = $next_round;
                            $monthly_data[$m_id]['updated_at'] = date("Y-m-d H:i:s");
                            file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));
                        } else {
                            // ถ้าครบ 4 ครั้งแล้ว แต่ยังมีผ้ามาซัก (และโควตายังเหลือ) 
                            // ให้ถือเป็น "รายครั้ง" หรือ "หักโควตาพิเศษ" โดยไม่นับรอบเพิ่ม (Round=0)
                            // หรือเจ้าของร้านต้องบังคับให้ลูกค้าต่อแพ็กเกจใหม่ก่อน
                            $conn->query("UPDATE orders SET order_pickup_round = 0 WHERE order_id = '$order_id'");
                        }
                    } else {
                        // 💰 กรณีจ่ายเงินสด (รายครั้ง) แม้จะเป็นสมาชิกรายเดือน แต่ถ้าหักผ้าเป็น 0 ไม่ต้องนับรอบ
                        $conn->query("UPDATE orders SET order_pickup_round = 0 WHERE order_id = '$order_id'");
                    }
                }
            }

        header("Location: order_process.php?id=$order_id");
        exit;
    }
}
?>
