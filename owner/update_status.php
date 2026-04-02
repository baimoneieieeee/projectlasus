<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

if (isset($_POST['order_id']) && isset($_POST['new_status'])) {
    $order_id = $conn->real_escape_string($_POST['order_id']);
    $new_status = (int)$_POST['new_status'];

    $sql = "UPDATE orders SET order_status = $new_status WHERE order_id = '$order_id'";
    if ($conn->query($sql)) {
        // --- 🚚 ถ้าสถานะเป็น 4 (ส่งคืนเรียบร้อย) ให้บันทึกข้อมูลลงตาราง delivery ---
        if ($new_status == 4) {
            $deli_id = "DEL" . time();
            $now = date("Y-m-d H:i:s");
            
            // 1. บันทึกลงตาราง delivery
            $conn->query("INSERT INTO delivery (deli_id, order_id, deli_date) VALUES ('$deli_id', '$order_id', '$now')");
            
            // 2. อัปเดตวันที่ส่งคืนจริงลงในตาราง orders
            $conn->query("UPDATE orders SET order_date_return = '$now' WHERE order_id = '$order_id'");

            // 3. 🚩 เช็คว่าต้องรีเซ็ตสถานะสมาชิกรายเดือนหรือไม่ (ถ้าส่งครบ 4 ครั้งแล้ว)
            $res_order = $conn->query("SELECT m_id, order_pickup_round FROM orders WHERE order_id = '$order_id'");
            $order_info = $res_order->fetch_assoc();
            
            if ($order_info && (int)$order_info['order_pickup_round'] >= 4) {
                $m_id = $order_info['m_id'];
                $json_file = __DIR__ . '/../config/monthly_data.json';
                if (file_exists($json_file)) {
                    $monthly_data = json_decode(file_get_contents($json_file), true);
                    if (isset($monthly_data[$m_id])) {
                        // ลบออกจากรายชื่อสมาชิก Active (เพื่อให้ลูกค้ากลับไปหน้าสมัครใหม่)
                        unset($monthly_data[$m_id]);
                        file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));
                    }
                }
            }
        }

        if (isset($_POST['redirect']) && $_POST['redirect'] === 'manage') {
            header("Location: manage_orders.php");
        } else {
            header("Location: order_process.php?id=$order_id");
        }
        exit;
    }
}
?>