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
        
        // --- 🚩 คำนวณยอดเงินรวมใหม่ ---
        // 1. ดึงข้อมูลออเดอร์ปัจจุบัน
        $order = $conn->query("SELECT order_shipping_fee, order_id FROM orders WHERE order_id = '$order_id'")->fetch_assoc();
        
        // 2. ถ้าเป็นบิล PKG ต้องเอาราคาแพ็กเกจมาตั้งต้นด้วย
        $base_price = 0;
        if(strpos($order_id, 'PKG') === 0) {
            // ค้นหาราคาจากโน้ตเดิม (เช่น ราคา 500 บาท)
            $old_order = $conn->query("SELECT order_note FROM orders WHERE order_id = '$order_id'")->fetch_assoc();
            preg_match('/ราคา (\d+) บาท/', $old_order['order_note'], $matches);
            $base_price = isset($matches[1]) ? (float)$matches[1] : 0;
        }

        // 3. ยอดรวมใหม่ = ราคาตั้งต้น + ค่าส่ง + ยอดบวกเพิ่มใหม่
        $new_total_sum = $base_price + (float)$order['order_shipping_fee'] + $extra_cash;
        
        $note_appendix = ($quota_deduct > 0) ? " (เตรียมหักโควตา $quota_deduct ชิ้น)" : "";
        
        // สรุปหมายเหตุ: ถ้าเป็น PKG ให้คงบรรทัดแรกที่เป็นชื่อแพ็กเกจไว้
        $final_note = $summary_note . $note_appendix;
        if($base_price > 0) {
             // สำหรับ PKG ให้ต่อท้ายโน้ตเดิมบรรทัดแรก
             $lines = explode("\n", $old_order['order_note']);
             $final_note = $lines[0] . "\n" . $summary_note . $note_appendix;
        }

        $sql = "UPDATE orders SET 
                order_status = 1, 
                order_sum = $new_total_sum, 
                order_items_count = $quota_deduct, 
                order_note = '$final_note',
                order_extra_note = '$extra_note',
                order_extra_cash = $extra_cash
                WHERE order_id = '$order_id'";
        
        if ($conn->query($sql)) {
            header("Location: order_process.php?id=$order_id");
            exit;
        }
    }
}
?>
