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
    
    if (isset($_POST['manual_mode'])) {
        $quota_deduct = (int)$_POST['quota_deduct'];
        $extra_cash = (float)$_POST['extra_cash'];
        $extra_note = $conn->real_escape_string($_POST['extra_note'] ?? '');
        $summary_note = $conn->real_escape_string($_POST['summary_note']);
        
        // 🧺 จัดการรายการผ้าแยกย่อย (จากฟอร์มจัดการออเดอร์)
        $item_names = $_POST['item_names'] ?? [];
        $item_qtys = $_POST['item_qtys'] ?? [];
        $itemized_summary = "";
        $items_to_save = [];

        for ($i = 0; $i < count($item_names); $i++) {
            $name = trim($item_names[$i]);
            $qty = (int)$item_qtys[$i];
            if ($name !== "" && $qty > 0) {
                $items_to_save[] = ['name' => $name, 'qty' => $qty];
            }
        }

        if (!empty($items_to_save)) {
            $parts = array_map(function($it) { return "{$it['name']} ({$it['qty']})"; }, $items_to_save);
            $itemized_summary = " [" . implode(", ", $parts) . "]";
        }

        // --- 🚩 1. ดึงข้อมูลออเดอร์ปัจจุบันก่อนเพื่อนำมาใช้คำนวณ ---
        $old_order_res = $conn->query("SELECT order_note, order_shipping_fee FROM orders WHERE order_id = '$order_id'");
        $old_order = $old_order_res->fetch_assoc();
        
        // --- 🚩 2. คำนวณยอดเงินรวมใหม่ ---
        $base_price = 0;
        if(strpos($order_id, 'PKG') === 0) {
            // ค้นหาราคาจากโน้ตเดิม (เช่น ราคา 500 บาท)
            preg_match('/ราคา (\d+) บาท/', $old_order['order_note'], $matches);
            $base_price = isset($matches[1]) ? (float)$matches[1] : 0;
        } else {
            // สำหรับออเดอร์รายครั้ง ให้เอาราคาที่ร้านค้ากรอกมาเป็นราคาตั้งต้น
            $base_price = isset($_POST['service_price']) ? (float)$_POST['service_price'] : 0;
        }

        // ยอดรวมใหม่ = ราคาตั้งต้น + ค่าส่ง + ยอดบวกเพิ่มใหม่
        $new_total_sum = $base_price + (float)$old_order['order_shipping_fee'] + $extra_cash;
        
        $note_appendix = ($quota_deduct > 0) ? " (เตรียมหักโควตา $quota_deduct ชิ้น$itemized_summary)" : "";
        
        // สรุปหมายเหตุ
        $final_note = $summary_note . $note_appendix;
        if($base_price > 0 && strpos($order_id, 'PKG') === 0) {
             // สำหรับ PKG ให้ต่อท้ายโน้ตเดิมบรรทัดแรก
             $lines = explode("\n", $old_order['order_note']);
             $final_note = $lines[0] . "\n" . $summary_note . $note_appendix;
        }

        // ดึงข้อมูลรอบปัจจุบันมาใส่ด้วย
        $json_file = __DIR__ . '/../config/monthly_data.json';
        $monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
        $current_pickups = (isset($monthly_data[$m_id]) && $quota_deduct > 0) ? $monthly_data[$m_id]['pickups_used'] : 0;

        $sql = "UPDATE orders SET 
                order_status = 1, 
                order_sum = $new_total_sum, 
                order_items_count = $quota_deduct, 
                order_note = '$final_note',
                order_extra_note = '$extra_note',
                order_extra_cash = $extra_cash,
                order_pickup_round = $current_pickups
                WHERE order_id = '$order_id'";
        
        if ($conn->query($sql)) {
            // --- 🧺 3. บันทึกรายการลงตาราง item (ความยาว ID ไม่เกิน 13 หลัก) ---
            $conn->query("DELETE FROM item WHERE order_id = '$order_id'");

            $id_base = substr($order_id, 3);

            // รายการซักหลัก (บันทึกแยกรายชิ้นตามที่กรอก)
            if (!empty($items_to_save)) {
                foreach ($items_to_save as $idx => $item) {
                    $itm_id = $id_base . sprintf("%03d", $idx + 1);
                    $conn->query("INSERT INTO item (item_id, order_id, service_id, item_amount) VALUES ('$itm_id', '$order_id', 'SRV1771999730', {$item['qty']})");
                }
            } else if($quota_deduct > 0 || $base_price > 0) {
                // กรณีไม่ได้กรอกแยกย่อยมา ให้บันทึกเป็นก้อนเดียวเหมือนเดิม
                $item_id_main = $id_base . "001";
                $qty = ($quota_deduct > 0) ? $quota_deduct : 1;
                $conn->query("INSERT INTO item (item_id, order_id, service_id, item_amount) VALUES ('$item_id_main', '$order_id', 'SRV1771999730', $qty)");
            }

            // รายการบวกเพิ่ม
            if($extra_cash > 0) {
                $item_id_extra = $id_base . "999"; // ใช้ 999 เพื่อไม่ให้ซ้ำกับรายการปกติ
                $srv_id = 'SRV1772000644'; // ผ้านวม
                if(strpos($extra_note, 'ชุดราชการ') !== false) $srv_id = 'SRV1772000615';
                
                $conn->query("INSERT INTO item (item_id, order_id, service_id, item_amount) VALUES ('$item_id_extra', '$order_id', '$srv_id', 1)");
            }

            header("Location: order_process.php?id=$order_id");
            exit;
        }
    }
}
?>