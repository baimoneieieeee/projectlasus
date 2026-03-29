<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    die("Unauthorized");
}

if (isset($_POST['m_id'])) {
    $m_id = $conn->real_escape_string($_POST['m_id']);
    $amount = (int)$_POST['amount'];
    $extra_cash = (float)($_POST['extra_cash'] ?? 0);
    $extra_note = $conn->real_escape_string($_POST['extra_note'] ?? '');
    
    $order_id = "ORD" . time();
    $date = date("Y-m-d H:i:s");

    // 🚩 Logic สถานะ: ถ้ามีเงินเพิ่มต้องรอจ่าย (Status 1), ถ้าซักโควตาล้วนๆ ให้เริ่มซักเลย (Status 2)
    $status = ($extra_cash > 0) ? 1 : 2;
    $note = "ซักรายเดือน (หักโควตาประจำสัปดาห์)";

    // 1. ดึงรอบปัจจุบันเพื่อบันทึกลงออเดอร์
    $json_file = __DIR__ . '/../config/monthly_data.json';
    $monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
    $next_round = (isset($monthly_data[$m_id]['pickups_used']) ? $monthly_data[$m_id]['pickups_used'] : 0) + 1;

    // 2. สร้างออเดอร์
    $sql_order = "INSERT INTO orders (order_id, m_id, order_date, order_status, order_items_count, order_note, order_sum, order_extra_note, order_extra_cash, order_pickup_round) 
                  VALUES ('$order_id', '$m_id', '$date', $status, $amount, '$note', $extra_cash, '$extra_note', $extra_cash, $next_round)";
    
    if ($conn->query($sql_order)) {
        // 3. หักโควตาใน JSON
        if (isset($monthly_data[$m_id])) {
            $monthly_data[$m_id]['quota'] -= $amount;
            $monthly_data[$m_id]['pickups_used'] = $next_round;
            $monthly_data[$m_id]['updated_at'] = $date;
            file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));
            
            echo "success";
        } else {
            echo "error_no_member";
        }
    } else {
        echo "error_db: " . $conn->error;
    }
}
?>
