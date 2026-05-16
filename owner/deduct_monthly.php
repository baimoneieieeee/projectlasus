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
        
        // สรุปหมายเหตุ: ใช้จากที่ร้านกรอกมาโดยตรง (ซึ่ง UI สรุปให้แล้ว)
        $final_note = $summary_note;
        
        if(strpos($order_id, 'PKG') === 0) {
             // สำหรับ PKG ให้คงชื่อแพ็กเกจในบรรทัดแรกไว้
             $lines = explode("\n", trim($old_order['order_note']));
             $pkg_name = $lines[0];
             $final_note = $pkg_name . "\n" . $summary_note;
        }

        // ดึงข้อมูลรอบปัจจุบันมาใส่ด้วย
        $json_file = __DIR__ . '/../config/monthly_data.json';
        $monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
        
        $current_pickups = 0;
        $is_pkg_order = (strpos($order_id, 'PKG') === 0);
        
        // --- 🚩 อัปเดตโควตาใน JSON เฉพาะเมื่อเป็นการบันทึกครั้งแรก (order_items_count เดิมเป็น 0) ---
        // และต้องไม่ใช่บิล PKG เพราะบิล PKG จะไปหักและนับรอบที่ 1 ใน confirm_payment.php อยู่แล้ว
        if (!$is_pkg_order && $old_order_items_count == 0 && $quota_deduct > 0 && isset($monthly_data[$m_id])) {
            $current_pickups = (int)($monthly_data[$m_id]['pickups_used'] ?? 0);
            
            // หักโควตา
            $monthly_data[$m_id]['quota'] -= $quota_deduct;
            
            // เพิ่มรอบการใช้งาน (นับ 1-4)
            if ($current_pickups >= 4) {
                $current_pickups = 1; 
            } else {
                $current_pickups += 1;
            }
            
            $monthly_data[$m_id]['pickups_used'] = $current_pickups;
            $monthly_data[$m_id]['updated_at'] = date("Y-m-d H:i:s");
            file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));
        } else if (isset($monthly_data[$m_id])) {
            // ถ้าเป็นบิล PKG หรือเป็นการอัปเดต ให้ดึงรอบปัจจุบันจาก JSON มาใช้
            $current_pickups = (int)($monthly_data[$m_id]['pickups_used'] ?? 0);
            
            // 🚩 [สำคัญ] ถ้าไม่ได้มีการหักโควตาจริง (quota_deduct == 0) ให้รอบเป็น 0
            // เพื่อให้ระบบแยกไปอยู่ในส่วน "ซักรายครั้ง" ในหน้าบ้านลูกค้าครับ
            if ($quota_deduct <= 0 && !$is_pkg_order) {
                $current_pickups = 0;
            }

            // กรณีพิเศษ: ถ้าเป็นบิล PKG และมีการซักผ้ามาด้วย ให้ถือว่าเป็นรอบที่ 1 เสมอ
            if ($is_pkg_order && $quota_deduct > 0) {
                $current_pickups = 1;
            }
        } else {
            // ถ้าไม่ใช่สมาชิก ให้รอบเป็น 0 เสมอ
            $current_pickups = 0;
        }

        $owner_note = $conn->real_escape_string($_POST['order_owner_note'] ?? '');

        $sql = "UPDATE orders SET 
                order_status = 1, 
                order_sum = $new_total_sum, 
                order_items_count = $quota_deduct, 
                order_note = '$final_note',
                order_extra_note = '$extra_note',
                order_extra_cash = $extra_cash,
                order_pickup_round = $current_pickups,
                order_owner_note = '$owner_note'
                WHERE order_id = '$order_id'";
        
        if ($conn->query($sql)) {
            // --- 🧺 3. บันทึกรายการลงตาราง item (ความยาว ID ไม่เกิน 13 หลัก) ---
            $conn->query("DELETE FROM item WHERE order_id = '$order_id'");

            $id_base = substr($order_id, 3);

            // ดึงรหัสบริการเริ่มต้นสำหรับเคส "งานซักทั่วไป" เพื่อป้องกัน Error
            $default_srv_res = $conn->query("SELECT service_id FROM service LIMIT 1");
            $default_srv_id = ($default_srv_res->num_rows > 0) ? $default_srv_res->fetch_assoc()['service_id'] : '';

            // รายการซักหลัก (บันทึกแยกรายชิ้นตามที่กรอก)
            if (!empty($items_to_save)) {
                foreach ($items_to_save as $idx => $item) {
                    $itm_id = $id_base . sprintf("%03d", $idx + 1);
                    $it_name = $conn->real_escape_string($item['name']);
                    $sid = $conn->real_escape_string($item['service_id']);
                    $conn->query("INSERT INTO item (item_id, order_id, item_name, service_id, item_amount) VALUES ('$itm_id', '$order_id', '$it_name', '$sid', {$item['qty']})");
                }
            } else if($quota_deduct > 0 || $base_price > 0) {
                // กรณีไม่ได้กรอกแยกย่อยมา ให้บันทึกเป็นก้อนเดียวเหมือนเดิม
                $item_id_main = $id_base . "001";
                $qty = ($quota_deduct > 0) ? $quota_deduct : 1;
                if ($default_srv_id !== "") {
                    $conn->query("INSERT INTO item (item_id, order_id, item_name, service_id, item_amount) VALUES ('$item_id_main', '$order_id', 'งานซักทั่วไป', '$default_srv_id', $qty)");
                }
            }

            // รายการบวกเพิ่ม
            if($extra_cash > 0 && $default_srv_id !== "") {
                $item_id_extra = $id_base . "999"; 
                // ค้นหารหัสบริการ "ผ้านวม" หรือ "บริการบวกเพิ่ม" ถ้าไม่มีให้ใช้ Default
                $srv_q = $conn->query("SELECT service_id FROM service WHERE service_name LIKE '%ผ้านวม%' OR service_name LIKE '%บวกเพิ่ม%' LIMIT 1");
                $srv_id = ($srv_q->num_rows > 0) ? $srv_q->fetch_assoc()['service_id'] : $default_srv_id;
                
                $it_extra_name = 'บริการบวกเพิ่ม';
                $conn->query("INSERT INTO item (item_id, order_id, item_name, service_id, item_amount) VALUES ('$item_id_extra', '$order_id', '$it_extra_name', '$srv_id', 1)");
            }

            header("Location: order_process.php?id=$order_id");
            exit;
        }
    }
}
?>