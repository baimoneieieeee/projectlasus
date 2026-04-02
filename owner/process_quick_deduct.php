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
    
    // 🧺 จัดการรายการผ้าแยกย่อย
    $item_names = $_POST['item_names'] ?? [];
    $item_qtys = $_POST['item_qtys'] ?? [];
    $summary_parts = [];
    $items_to_save = [];

    for ($i = 0; $i < count($item_names); $i++) {
        $name = trim($item_names[$i]);
        $qty = (int)$item_qtys[$i];
        if ($name !== "" && $qty > 0) {
            $summary_parts[] = "$name ($qty)";
            $items_to_save[] = ['name' => $name, 'qty' => $qty];
        }
    }

    $order_id = "ORD" . time();
    $date = date("Y-m-d H:i:s");

    $status = ($extra_cash > 0) ? 1 : 2;
    $note = "ซักรายเดือน (หักโควตา): " . implode(", ", $summary_parts);
    if (empty($summary_parts)) $note = "ซักรายเดือน (หักโควตาประจำสัปดาห์)";

    // 1. ดึงรอบปัจจุบัน
    $json_file = __DIR__ . '/../config/monthly_data.json';
    $monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
    
    $current_used = isset($monthly_data[$m_id]['pickups_used']) ? (int)$monthly_data[$m_id]['pickups_used'] : 0;
    
    // กั้นรอบไม่ให้เกิน 4
    if ($current_used < 4) {
        $next_round = $current_used + 1;
    } else {
        $next_round = 0; // ถ้าเกิน 4 ครั้งแล้ว ให้ถือเป็นรอบพิเศษ (ไม่นับเพิ่ม)
    }

    // 2. สร้างออเดอร์
    $sql_order = "INSERT INTO orders (order_id, m_id, order_date, order_status, order_items_count, order_note, order_sum, order_extra_note, order_extra_cash, order_pickup_round) 
                  VALUES ('$order_id', '$m_id', '$date', $status, $amount, '$note', $extra_cash, '$extra_note', $extra_cash, $next_round)";
    
    if ($conn->query($sql_order)) {
        // 3. บันทึกรายการลงตาราง item
        $id_base = substr($order_id, 3);
        foreach ($items_to_save as $idx => $item) {
            $itm_id = $id_base . sprintf("%03d", $idx + 1);
            $conn->query("INSERT INTO item (item_id, order_id, service_id, item_amount) VALUES ('$itm_id', '$order_id', 'SRV1771999730', {$item['qty']})");
        }

        // 4. หักโควตาใน JSON
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
