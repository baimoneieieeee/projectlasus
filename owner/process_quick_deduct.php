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
    $owner_note = $conn->real_escape_string($_POST['owner_note'] ?? '');
    
    // 🧺 จัดการรายการผ้าแยกย่อย
    $item_names = $_POST['item_names'] ?? [];
    $item_qtys = $_POST['item_qtys'] ?? [];
    $item_ids = $_POST['item_ids'] ?? []; // รับรหัสบริการจากฟอร์ม
    $summary_parts = [];
    $items_to_save = [];

    for ($i = 0; $i < count($item_names); $i++) {
        $name = trim($item_names[$i]);
        $qty = (int)$item_qtys[$i];
        $sid = trim($item_ids[$i] ?? '');
        if ($name !== "" && $qty > 0 && $sid !== "") {
            $summary_parts[] = "$name ($qty)";
            $items_to_save[] = ['name' => $name, 'qty' => $qty, 'service_id' => $sid];
        }
    }

    $order_id = "ORD" . time();
    $date = date("Y-m-d H:i:s");

    $status = ($extra_cash > 0) ? 1 : 2;
    $note = "ซักรายเดือน (หักโควตา): " . implode(", ", $summary_parts);
    if (empty($summary_parts)) $note = "ซักรายเดือน (หักโควตาประจำสัปดาห์)";

    // --- 🚩 เริ่ม Transaction เพื่อความปลอดภัย ---
    $conn->begin_transaction();

    try {
        // ดึงรหัสบริการเริ่มต้นสำหรับกรณีฉุกเฉิน
        $default_srv_res = $conn->query("SELECT service_id FROM service LIMIT 1");
        $default_srv_id = ($default_srv_res->num_rows > 0) ? $default_srv_res->fetch_assoc()['service_id'] : '';

        // 1. ดึงรอบปัจจุบัน
        $json_file = __DIR__ . '/../config/monthly_data.json';
        $monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
        
        $current_used = isset($monthly_data[$m_id]['pickups_used']) ? (int)$monthly_data[$m_id]['pickups_used'] : 0;
        
        // รีเซ็ตรอบถ้าครบ 4 แล้ว (เริ่มเดือนใหม่) หรือเพิ่มทีละ 1
        if ($current_used >= 4) {
            $next_round = 1; 
        } else {
            $next_round = $current_used + 1;
        }

        // 2. สร้างออเดอร์
        $sql_order = "INSERT INTO orders (order_id, m_id, order_date, order_status, order_items_count, order_note, order_sum, order_extra_note, order_extra_cash, order_pickup_round, order_owner_note) 
                      VALUES ('$order_id', '$m_id', '$date', $status, $amount, '$note', $extra_cash, '$extra_note', $extra_cash, $next_round, '$owner_note')";
        
        if (!$conn->query($sql_order)) throw new Exception("Order insertion failed: " . $conn->error);

        // 🧺 3. บันทึกรายการลงตาราง item
        $id_base = substr($order_id, 3);
        if (!empty($items_to_save)) {
            foreach ($items_to_save as $idx => $item) {
                $itm_id = $id_base . sprintf("%03d", $idx + 1);
                $it_name = $conn->real_escape_string($item['name']);
                $sid = $conn->real_escape_string($item['service_id']);
                if (!$conn->query("INSERT INTO item (item_id, order_id, item_name, service_id, item_amount) VALUES ('$itm_id', '$order_id', '$it_name', '$sid', {$item['qty']})")) {
                    throw new Exception("Item insertion failed: " . $conn->error);
                }
            }
        } else if ($default_srv_id !== "") {
            $itm_id = $id_base . "001";
            if (!$conn->query("INSERT INTO item (item_id, order_id, item_name, service_id, item_amount) VALUES ('$itm_id', '$order_id', 'งานซักทั่วไป', '$default_srv_id', $amount)")) {
                throw new Exception("Default item insertion failed: " . $conn->error);
            }
        }

        // 4. หักโควตาใน JSON
        if (isset($monthly_data[$m_id])) {
            $monthly_data[$m_id]['quota'] -= $amount;
            $monthly_data[$m_id]['pickups_used'] = $next_round;
            $monthly_data[$m_id]['updated_at'] = $date;
            file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));
            
            $conn->commit(); // ✅ บันทึกทุกอย่างสำเร็จ
            echo "success";
        } else {
            throw new Exception("error_no_member");
        }

    } catch (Exception $e) {
        $conn->rollback(); // ❌ ยกเลิกทั้งหมดหากมีจุดใดผิดพลาด
        echo $e->getMessage();
    }
}
?>
