<?php
header('Content-Type: text/html; charset=utf-8');
include 'config/db.php';

// บังคับการเชื่อมต่อเป็น utf8mb4
$conn->set_charset("utf8mb4");
$conn->query("SET NAMES utf8mb4");

$data = [
    'shop' => [
        '_table' => 'ข้อมูลร้านค้าและเจ้าของร้าน',
        'shop_id' => 'รหัสร้านค้า',
        'shop_name' => 'ชื่อจริงเจ้าของร้าน',
        'shop_lastname' => 'นามสกุลเจ้าของร้าน',
        'shop_name1' => 'ชื่อร้านค้า/ชื่อแบรนด์',
        'shop_address' => 'ที่อยู่ตั้งร้าน',
        'shop_tel' => 'เบอร์โทรศัพท์ร้าน',
        'shop_mail' => 'อีเมลเข้าใช้งาน',
        'shop_password' => 'รหัสผ่าน',
        'shop_rate' => 'ค่าส่งปกติ',
        'shop_extra_price' => 'ค่าซักเกินโควตาต่อชิ้น',
        'shop_QRcode' => 'รูปภาพ QR Code รับเงิน',
        'shop_dist_1' => 'ระยะส่งฟรี (กม.)',
        'shop_dist_2' => 'ระยะส่งปกติสูงสุด (กม.)',
        'shop_rate_far' => 'ค่าส่งระยะไกล'
    ],
    'member' => [
        '_table' => 'ข้อมูลสมาชิก/ลูกค้า',
        'm_id' => 'รหัสสมาชิก',
        'm_name' => 'ชื่อลูกค้า',
        'm_lastname' => 'นามสกุลลูกค้า',
        'm_password' => 'รหัสผ่าน',
        'm_latitude' => 'ละติจูดที่อยู่ลูกค้า',
        'm_longitude' => 'ลองจิจูดที่อยู่ลูกค้า',
        'm_address' => 'ที่อยู่ลูกค้า (ข้อความ)',
        'm_tel' => 'เบอร์โทรศัพท์ลูกค้า',
        'm_mail' => 'อีเมลลูกค้า',
        'm_created_at' => 'วันที่สมัครสมาชิก'
    ],
    'service_type' => [
        '_table' => 'หมวดหมู่บริการ',
        'svType_id' => 'รหัสประเภทบริการ',
        'svType_name' => 'ชื่อประเภทบริการ',
        'svType_behavior' => 'พฤติกรรมการคิดเงิน (quota/special)',
        'svType_note' => 'รายละเอียดหมวดหมู่'
    ],
    'service' => [
        '_table' => 'รายการบริการและราคา',
        'service_id' => 'รหัสบริการ',
        'service_name' => 'ชื่อบริการ',
        'svType_id' => 'รหัสประเภท (FK)',
        'service_image' => 'รูปภาพตัวอย่างบริการ',
        'service_price' => 'ค่าบริการ',
        'service_quota' => 'จำนวนโควตาที่ต้องใช้',
        'service_note' => 'หมายเหตุบริการ'
    ],
    'request' => [
        '_table' => 'การแจ้งความประสงค์รับผ้า',
        'rq_id' => 'รหัสคำแจ้งรับผ้า',
        'm_id' => 'รหัสลูกค้า (FK)',
        'rq_date' => 'วันเวลาที่แจ้ง',
        'rq_latitude' => 'ละติจูดจุดนัดรับ',
        'rq_longitude' => 'ลองจิจูดจุดนัดรับ',
        'rq_status' => 'สถานะ (0=รอรับ, 1=รับแล้ว/ปฏิเสธ)',
        'rq_sum' => 'ยอดเงินประเมินเบื้องต้น',
        'rq_note' => 'หมายเหตุจากลูกค้า',
        'rq_reject_note' => 'หมายเหตุการปฏิเสธ'
    ],
    'orders' => [
        '_table' => 'ข้อมูลการใช้บริการ/ออเดอร์',
        'order_id' => 'รหัสออเดอร์',
        'm_id' => 'รหัสลูกค้า (FK)',
        'rq_id' => 'รหัสคำแจ้งอ้างอิง (FK)',
        'order_date' => 'วันที่เริ่มบริการ',
        'order_date_return' => 'วันที่ส่งคืนจริง',
        'order_address' => 'ที่อยู่จัดส่งคืน',
        'order_status' => 'สถานะ (0=รอรับ, 1=รอจ่าย, 2=กำลังซัก, 3=เสร็จ, 4=ส่งแล้ว)',
        'order_shipping_fee' => 'ค่าจัดส่ง',
        'order_sum' => 'ยอดรวมสุทธิ',
        'order_extra_note' => 'เหตุผลค่าบริการเพิ่ม',
        'order_extra_cash' => 'ยอดเงินบวกเพิ่ม',
        'order_items_count' => 'จำนวนผ้าทั้งหมด',
        'order_note' => 'สรุปรายการผ้า',
        'order_pickup_round' => 'รอบที่รับของสมาชิกรรายเดือน',
        'order_est_pickup' => 'วันเวลาที่นัดรับผ้า',
        'order_est_delivery' => 'วันเวลาที่นัดส่งคืน',
        'order_owner_note' => 'หมายเหตุจากร้านค้า'
    ],
    'item' => [
        '_table' => 'รายการผ้าในแต่ละออเดอร์',
        'item_id' => 'รหัสรายการชิ้นผ้า',
        'order_id' => 'รหัสออเดอร์อ้างอิง (FK)',
        'item_amount' => 'จำนวนชิ้น',
        'service_id' => 'รหัสบริการที่ใช้ (FK)'
    ],
    'payment' => [
        '_table' => 'ข้อมูลการเงิน/รายรับ-รายจ่าย',
        'payment_id' => 'รหัสบันทึกการเงิน',
        'payment_date' => 'วันเวลาที่ทำรายการ',
        'payment_amount' => 'จำนวนเงิน',
        'payment_image' => 'รูปภาพสลิป (รายรับ)',
        'order_id' => 'รหัสออเดอร์อ้างอิง (FK)',
        'payment_type' => 'ประเภทรายการ (0=รายรับจากลูกค้า, 1=รายจ่ายของร้าน)',
        'payment_name' => 'ชื่อรายการ (เช่น ค่าไฟ, ซื้อของ)',
        'payment_note' => 'หมายเหตุเพิ่มเติม'
    ],
    'delivery' => [
        '_table' => 'ข้อมูลการจัดส่งคืนผ้า',
        'deli_id' => 'รหัสบันทึกการจัดส่ง',
        'order_id' => 'รหัสออเดอร์ที่จัดส่งคืน',
        'deli_date' => 'วันที่จัดส่งสำเร็จ'
    ]
];

foreach ($data as $table => $cols) {
    // แก้ไข Comment ของตาราง
    $tbl_comment = $conn->real_escape_string($cols['_table']);
    $conn->query("ALTER TABLE `$table` COMMENT '$tbl_comment'");
    
    // แก้ไข Comment ของแต่ละคอลัมน์
    foreach ($cols as $col => $comment) {
        if ($col === '_table') continue;
        
        // ดึงโครงสร้างเดิมมาเพื่อไม่ให้ DataType เพี้ยน
        $res = $conn->query("SHOW FULL COLUMNS FROM `$table` WHERE Field = '$col'");
        if ($row = $res->fetch_assoc()) {
            $type = $row['Type'];
            $null = ($row['Null'] === 'YES') ? 'NULL' : 'NOT NULL';
            $default = ($row['Default'] !== null) ? "DEFAULT '".$conn->real_escape_string($row['Default'])."'" : "";
            $comment_esc = $conn->real_escape_string($comment);
            
            $sql = "ALTER TABLE `$table` MODIFY COLUMN `$col` $type $null $default COMMENT '$comment_esc'";
            $conn->query($sql);
        }
    }
}

echo "แก้ไข Note ภาษาไทยครบทุกตารางแล้ว!";
?>