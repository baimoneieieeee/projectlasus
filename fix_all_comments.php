<?php
include 'config/db.php';
$conn->set_charset("utf8mb4");
$conn->query("SET NAMES utf8mb4");

// ฟังก์ชันแปลงข้อความเป็น Hex สำหรับ MySQL
function toHex($str) {
    return "0x" . bin2hex($str);
}

echo "กำลังเริ่มแก้ไข...<br>";

// 1. ตาราง shop
$shop_com = toHex('ข้อมูลร้านค้า');
$conn->query("ALTER TABLE shop COMMENT $shop_com");
$shop_cols = [
    'shop_id' => 'รหัสร้านค้า',
    'shop_name1' => 'ชื่อร้านค้า',
    'shop_address' => 'ที่อยู่ร้าน',
    'shop_latitude' => 'ละติจูดร้าน',
    'shop_longitude' => 'ลองจิจูดร้าน',
    'shop_tel' => 'เบอร์โทรศัพท์',
    'shop_mail' => 'อีเมล',
    'shop_password' => 'รหัสผ่าน',
    'shop_rate' => 'ค่าส่งระยะกลาง',
    'shop_rate_far' => 'ค่าส่งระยะไกล',
    'shop_QRcode' => 'รูป QR Code ชำระเงิน',
    'shop_dist_1' => 'ระยะใกล้สุด (ส่งฟรี)',
    'shop_dist_2' => 'ระยะกลางสุด (ค่าส่งปกติ)'
];
foreach ($shop_cols as $col => $com) {
    $res = $conn->query("SHOW COLUMNS FROM shop LIKE '$col'")->fetch_assoc();
    $type = $res['Type'];
    $null = ($res['Null'] == 'YES') ? 'NULL' : 'NOT NULL';
    $hex_com = toHex($com);
    $conn->query("ALTER TABLE shop MODIFY $col $type $null COMMENT $hex_com");
}

// 2. ตาราง member
$mem_com = toHex('ข้อมูลสมาชิก');
$conn->query("ALTER TABLE member COMMENT $mem_com");
$mem_cols = [
    'm_id' => 'รหัสสมาชิก',
    'm_name' => 'ชื่อสมาชิก',
    'm_lastname' => 'นามสกุลสมาชิก',
    'm_password' => 'รหัสผ่าน',
    'm_latitude' => 'ละติจูดที่อยู่',
    'm_longitude' => 'ลองจิจูดที่อยู่',
    'm_address' => 'ที่อยู่ข้อความ',
    'm_tel' => 'เบอร์โทรศัพท์',
    'm_mail' => 'อีเมลสมาชิก'
];
foreach ($mem_cols as $col => $com) {
    $res = $conn->query("SHOW COLUMNS FROM member LIKE '$col'")->fetch_assoc();
    $type = $res['Type'];
    $null = ($res['Null'] == 'YES') ? 'NULL' : 'NOT NULL';
    $hex_com = toHex($com);
    $conn->query("ALTER TABLE member MODIFY $col $type $null COMMENT $hex_com");
}

// 3. ตาราง orders
$ord_com = toHex('ข้อมูลการใช้บริการ');
$conn->query("ALTER TABLE orders COMMENT $ord_com");
$ord_cols = [
    'order_id' => 'รหัสบริการ',
    'm_id' => 'รหัสสมาชิก',
    'rq_id' => 'รหัสคำขอรับผ้า',
    'order_date' => 'วันที่รับผ้า',
    'order_date_return' => 'วันที่ส่งคืน',
    'order_address' => 'ที่อยู่จัดส่ง',
    'order_status' => 'สถานะ (0=รอรับ, 1=รอจ่าย, 2=กำลังซัก, 3=เสร็จ, 4=ส่งแล้ว)',
    'order_shipping_fee' => 'ค่าจัดส่ง',
    'order_sum' => 'ยอดรวมค่าซัก+ส่ง',
    'order_extra_note' => 'เหตุผลการบวกเงินเพิ่ม',
    'order_items_count' => 'จำนวนผ้าทั้งหมด',
    'order_note' => 'สรุปรายการผ้าหรือโควตา',
    'order_pickup_round' => 'รอบการรับผ้า'
];
foreach ($ord_cols as $col => $com) {
    $res = $conn->query("SHOW COLUMNS FROM orders LIKE '$col'")->fetch_assoc();
    $type = $res['Type'];
    $null = ($res['Null'] == 'YES') ? 'NULL' : 'NOT NULL';
    $hex_com = toHex($com);
    $conn->query("ALTER TABLE orders MODIFY $col $type $null COMMENT $hex_com");
}

echo "<h3>✅ แก้ไขภาษาไทยในฐานข้อมูลสำเร็จแล้ว!</h3>";
echo "กรุณากลับไปที่ phpMyAdmin แล้วกด Refresh ดูอีกครั้งครับ";
?>
