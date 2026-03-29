<?php
include 'config/db.php';
$conn->set_charset("utf8mb4");

$sqls = [
    // อัปเดตคำอธิบายตาราง shop ให้เป็นระบบ ใกล้-กลาง-ไกล
    "ALTER TABLE shop COMMENT = 'ตารางตั้งค่าร้านค้าและนโยบายจัดส่ง (ใกล้-กลาง-ไกล)'",
    
    "ALTER TABLE shop MODIFY shop_dist_1 INT COMMENT 'ระยะทางสูงสุดของ [ระยะใกล้] (กม.) - ส่งฟรี'",
    "ALTER TABLE shop MODIFY shop_dist_2 INT COMMENT 'ระยะทางสูงสุดของ [ระยะกลาง] (กม.) - คิดค่าส่งมาตรฐาน'",
    "ALTER TABLE shop MODIFY shop_rate INT COMMENT 'ค่าบริการจัดส่งสำหรับ [ระยะกลาง] (บาท)'",
    
    "ALTER TABLE shop MODIFY shop_name1 VARCHAR(60) COMMENT 'ชื่อร้านค้าที่แสดงบนหน้าเว็บ'",
    "ALTER TABLE shop MODIFY shop_address VARCHAR(300) COMMENT 'ที่อยู่ร้านค้าสำหรับลูกค้าดู'",
    "ALTER TABLE shop MODIFY shop_latitude DOUBLE COMMENT 'พิกัดร้านค้า (Latitude)'",
    "ALTER TABLE shop MODIFY shop_longitude DOUBLE COMMENT 'พิกัดร้านค้า (Longitude)'",
    "ALTER TABLE shop MODIFY shop_QRcode LONGBLOB COMMENT 'รูปภาพ QR Code รับเงิน'",

    // อัปเดต Comment ในตาราง orders ให้ชัดเจนเรื่องค่าส่ง
    "ALTER TABLE orders MODIFY order_shipping_fee DECIMAL(10,2) DEFAULT 0 COMMENT 'ค่าส่งที่คำนวณตามระยะ ใกล้-กลาง-ไกล'",
    "ALTER TABLE orders MODIFY order_status INT DEFAULT 0 COMMENT 'สถานะ: 0=รอรับ, 1=กำลังซัก, 2=รอจ่าย, 3=จ่ายแล้ว, 4=ส่งคืนแล้ว'"
];

foreach ($sqls as $sql) {
    if (!$conn->query($sql)) {
        echo "Error: " . $conn->error . "
";
    }
}
echo "อัปเดตคำอธิบายฐานข้อมูลระบบ ใกล้-กลาง-ไกล เรียบร้อยแล้ว!";
?>
