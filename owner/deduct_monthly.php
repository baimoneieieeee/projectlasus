<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

if (isset($_POST['use_quota'])) {
    $order_id = $conn->real_escape_string($_POST['order_id']);
    $m_id = $conn->real_escape_string($_POST['m_id']);
    
    // --- 🚩 อัปเดตเฉพาะโน้ตร้านค้า (เวลาส่งผ้า) ---
    if (isset($_POST['update_note_only'])) {
        $owner_note = $conn->real_escape_string($_POST['order_owner_note'] ?? '');
        $sql = "UPDATE orders SET order_owner_note = '$owner_note' WHERE order_id = '$order_id'";
        if ($conn->query($sql)) {
            header("Location: order_process.php?id=$order_id");
            exit;
        }
    }

    if (isset($_POST['manual_mode'])) {
        $quota_deduct = (int)$_POST['quota_deduct'];
        $extra_cash = (float)$_POST['extra_cash'];
        $extra_note = $conn->real_escape_string($_POST['extra_note'] ?? '');
        $summary_note = $conn->real_escape_string($_POST['summary_note']);
        
        // 🧺 จัดการรายการผ้าแยกย่อย (จากฟอร์มจัดการออเดอร์)
        $item_names = $_POST['item_names'] ?? [];
        $item_qtys = $_POST['item_qtys'] ?? [];
        $service_ids = $_POST['service_ids'] ?? [];
        $itemized_summary = "";
        $items_to_save = [];

        for ($i = 0; $i < count($item_names); $i++) {
            $name = trim($item_names[$i]);
            $qty = (int)$item_qtys[$i];
            $sid = $conn->real_escape_string($service_ids[$i] ?? '');
            if ($name !== "" && $qty > 0 && $sid !== "") {
                $items_to_save[] = ['name' => $name, 'qty' => $qty, 'service_id' => $sid];
            }
        }

        if (!empty($items_to_save)) {
            $parts = array_map(function($it) { return "{$it['name']} ({$it['qty']})"; }, $items_to_save);
            $itemized_summary = "\n" . implode("\n", $parts);
        }

        // --- 🚩 1. ดึงข้อมูลออเดอร์ปัจจุบันก่อนเพื่อนำมาใช้คำนวณ ---
        $old_order_res = $conn->query("SELECT order_note, order_shipping_fee, order_items_count FROM orders WHERE order_id = '$order_id'");
        $old_order = $old_order_res->fetch_assoc();
        $old_order_items_count = (int)$old_order['order_items_count'];
        
        // --- 🚩 2. คำนวณยอดเงินรวมใหม่ ---
        $pkg_base_price = 0; // ราคาตัวแพ็กเกจ (ถ้ามี)
        $per_piece_total = 0; // ราคารวมของผ้าที่คิดเป็นรายชิ้นปกติ
        
        if(strpos($order_id, 'PKG') === 0) {
            preg_match('/ราคา (\d+) บาท/', $old_order['order_note'], $matches);
            $pkg_base_price = isset($matches[1]) ? (float)$matches[1] : 0;
        }

        // วนลูปเช็คราคารายชิ้นปกติจากรายการที่เลือกมา
        $sum_item_prices = 0;
        if (!empty($items_to_save)) {
            foreach ($items_to_save as $item) {
                $sid = $conn->real_escape_string($item['service_id']);
                $srv_p_res = $conn->query("SELECT service_price, svType_id FROM service WHERE service_id = '$sid'");
                $srv_data = $srv_p_res->fetch_assoc();
                $srv_p = (float)($srv_data['service_price'] ?? 0);
                
                // เช็คประเภทบริการ (ถ้าไม่ใช่รายเดือน ให้บวกเข้ายอดรายชิ้นปกติ)
                $sum_item_prices += ($srv_p * $item['qty']);
            }
        }

        // คำนวณหายอด "บวกเพิ่มจริงๆ" (เช่น ค่าซักเกินโควตา)
        // ยอด extra_cash จากหน้าเว็บคือ (ราคารายชิ้น + ค่าซักเกินโควตา)
        // เราต้องลบราคารายชิ้นออกเพื่อให้เหลือแค่ค่าซักเกินจริงๆ เพื่อลงช่อง order_extra_cash
        $real_extra_surcharge = $extra_cash - $sum_item_prices;
        if ($real_extra_surcharge < 0) $real_extra_surcharge = 0;

        // ยอดรวมสุทธิ = (ราคาแพ็กเกจ + ราคารายชิ้น) + ค่าส่ง + ยอดบวกเพิ่มจริงๆ
        $new_total_sum = $pkg_base_price + $sum_item_prices + (float)$old_order['order_shipping_fee'] + $real_extra_surcharge;
        
        $final_note = $summary_note;
        if(strpos($order_id, 'PKG') === 0) {
             $lines = explode("\n", trim($old_order['order_note']));
             $pkg_name = $lines[0];
             $final_note = $pkg_name . "\n" . $summary_note;
        }

        // ... (ส่วนการจัดการ JSON คงเดิม) ...
        $json_file = __DIR__ . '/../config/monthly_data.json';
        $monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
        
        $current_pickups = 0;
        $is_pkg_order = (strpos($order_id, 'PKG') === 0);
        
        if (!$is_pkg_order && $old_order_items_count == 0 && $quota_deduct > 0 && isset($monthly_data[$m_id])) {
            $current_pickups = (int)($monthly_data[$m_id]['pickups_used'] ?? 0);
            $monthly_data[$m_id]['quota'] -= $quota_deduct;
            if ($current_pickups >= 4) { $current_pickups = 1; } else { $current_pickups += 1; }
            $monthly_data[$m_id]['pickups_used'] = $current_pickups;
            $monthly_data[$m_id]['updated_at'] = date("Y-m-d H:i:s");
            file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));
        } else if (isset($monthly_data[$m_id])) {
            $current_pickups = (int)($monthly_data[$m_id]['pickups_used'] ?? 0);
            if ($quota_deduct <= 0 && !$is_pkg_order) { $current_pickups = 0; }
            if ($is_pkg_order && $quota_deduct > 0) { $current_pickups = 1; }
        } else {
            $current_pickups = 0;
        }

        $owner_note = $conn->real_escape_string($_POST['order_owner_note'] ?? '');

        $sql = "UPDATE orders SET 
                order_status = 1, 
                order_sum = $new_total_sum, 
                order_items_count = $quota_deduct, 
                order_note = '$final_note',
                order_extra_note = '$extra_note',
                order_extra_cash = $real_extra_surcharge,
                order_pickup_round = $current_pickups,
                order_owner_note = '$owner_note'
                WHERE order_id = '$order_id'";
        
        if ($conn->query($sql)) {
            $conn->query("DELETE FROM item WHERE order_id = '$order_id'");
            $id_base = substr($order_id, 3);
            $default_srv_res = $conn->query("SELECT service_id FROM service LIMIT 1");
            $default_srv_id = ($default_srv_res->num_rows > 0) ? $default_srv_res->fetch_assoc()['service_id'] : '';

            if (!empty($items_to_save)) {
                foreach ($items_to_save as $idx => $item) {
                    $itm_id = $id_base . sprintf("%03d", $idx + 1);
                    $it_name = $conn->real_escape_string($item['name']);
                    $sid = $conn->real_escape_string($item['service_id']);
                    $conn->query("INSERT INTO item (item_id, order_id, item_name, service_id, item_amount) VALUES ('$itm_id', '$order_id', '$it_name', '$sid', {$item['qty']})");
                }
            } else if($quota_deduct > 0 || $pkg_base_price > 0) {
                $item_id_main = $id_base . "001";
                $qty = ($quota_deduct > 0) ? $quota_deduct : 1;
                if ($default_srv_id !== "") {
                    $conn->query("INSERT INTO item (item_id, order_id, item_name, service_id, item_amount) VALUES ('$item_id_main', '$order_id', 'งานซักทั่วไป', '$default_srv_id', $qty)");
                }
            }

            // --- 🚩 บันทึกเฉพาะ "ยอดส่วนเกิน" จริงๆ (เช่น ค่าซักเกินโควตา) ---
            if($real_extra_surcharge > 0 && $default_srv_id !== "") {
                $item_id_extra = $id_base . "999"; 
                $srv_q = $conn->query("SELECT service_id FROM service WHERE service_name LIKE '%บวกเพิ่ม%' OR service_name LIKE '%ผ้านวม%' LIMIT 1");
                $srv_id = ($srv_q->num_rows > 0) ? $srv_q->fetch_assoc()['service_id'] : $default_srv_id;
                $it_extra_name = 'ค่าซักเกินโควตา/ส่วนต่าง';
                $conn->query("INSERT INTO item (item_id, order_id, item_name, service_id, item_amount) VALUES ('$item_id_extra', '$order_id', '$it_extra_name', '$srv_id', 1)");
            }

            header("Location: order_process.php?id=$order_id");
            exit;
        }
    }
}
?>