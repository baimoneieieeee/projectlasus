<?php
header('Content-Type: text/html; charset=utf-8');
include 'config/db.php';

// ตั้งค่าการเชื่อมต่อให้เป็น UTF-8
$conn->set_charset("utf8mb4");

$queries = [
    // ตาราง shop
    "ALTER TABLE shop COMMENT 'ข้อมูลร้านค้าและเจ้าของร้าน'",
    "ALTER TABLE shop MODIFY shop_id varchar(20) COMMENT 'รหัสร้านค้า'",
    "ALTER TABLE shop MODIFY shop_name varchar(50) COMMENT 'ชื่อจริงเจ้าของร้าน'",
    "ALTER TABLE shop MODIFY shop_lastname varchar(50) COMMENT 'นามสกุลเจ้าของร้าน'",
    "ALTER TABLE shop MODIFY shop_name1 varchar(60) COMMENT 'ชื่อร้านค้า/ชื่อแบรนด์'",
    "ALTER TABLE shop MODIFY shop_address varchar(300) COMMENT 'ที่อยู่ตั้งร้าน'",
    "ALTER TABLE shop MODIFY shop_tel varchar(10) COMMENT 'เบอร์โทรศัพท์ร้าน'",
    "ALTER TABLE shop MODIFY shop_mail varchar(50) COMMENT 'อีเมลเข้าใช้งาน'",
    "ALTER TABLE shop MODIFY shop_password varchar(50) COMMENT 'รหัสผ่าน'",
    "ALTER TABLE shop MODIFY shop_rate int(11) COMMENT 'ค่าส่งปกติ'",
    "ALTER TABLE shop MODIFY shop_extra_price int(11) COMMENT 'ค่าซักเกินโควตาต่อชิ้น'",
    "ALTER TABLE shop MODIFY shop_dist_1 int(11) COMMENT 'ระยะส่งฟรี (กม.)'",
    "ALTER TABLE shop MODIFY shop_dist_2 int(11) COMMENT 'ระยะส่งปกติสูงสุด (กม.)'",
    "ALTER TABLE shop MODIFY shop_rate_far int(11) COMMENT 'ค่าส่งระยะไกล'",

    // ตาราง member
    "ALTER TABLE member COMMENT 'ข้อมูลสมาชิก/ลูกค้า'",
    "ALTER TABLE member MODIFY m_id varchar(20) COMMENT 'รหัสสมาชิก'",
    "ALTER TABLE member MODIFY m_name varchar(50) COMMENT 'ชื่อลูกค้า'",
    "ALTER TABLE member MODIFY m_lastname varchar(50) COMMENT 'นามสกุลลูกค้า'",
    "ALTER TABLE member MODIFY m_password varchar(20) COMMENT 'รหัสผ่าน'",
    "ALTER TABLE member MODIFY m_address varchar(300) COMMENT 'ที่อยู่ลูกค้า (ข้อความ)'",
    "ALTER TABLE member MODIFY m_tel varchar(10) COMMENT 'เบอร์โทรศัพท์ลูกค้า'",
    "ALTER TABLE member MODIFY m_mail varchar(50) COMMENT 'อีเมลลูกค้า'",

    // ตาราง delivery
    "ALTER TABLE delivery COMMENT 'ข้อมูลการจัดส่งคืนผ้า'",
    "ALTER TABLE delivery MODIFY deli_id varchar(13) COMMENT 'รหัสบันทึกการจัดส่ง'",
    "ALTER TABLE delivery MODIFY order_id varchar(13) COMMENT 'รหัสออเดอร์ที่จัดส่งคืน'",
    "ALTER TABLE delivery MODIFY deli_date datetime COMMENT 'วันที่จัดส่งสำเร็จ'",

    // ตาราง orders
    "ALTER TABLE orders COMMENT 'ข้อมูลการใช้บริการ/ออเดอร์'",
    "ALTER TABLE orders MODIFY order_id varchar(13) COMMENT 'รหัสออเดอร์'",
    "ALTER TABLE orders MODIFY order_status int(1) COMMENT 'สถานะ (0=รอรับ, 1=รอจ่าย, 2=กำลังซัก, 3=เสร็จ, 4=ส่งแล้ว)'",
    "ALTER TABLE orders MODIFY order_sum decimal(10,2) COMMENT 'ยอดรวมสุทธิ'",
    "ALTER TABLE orders MODIFY order_note text COMMENT 'สรุปรายการผ้า'",

    // ตาราง payment
    "ALTER TABLE payment COMMENT 'ข้อมูลการเงิน/รายรับ-รายจ่าย'",
    "ALTER TABLE payment MODIFY payment_id int(11) COMMENT 'รหัสบันทึกการเงิน'",
    "ALTER TABLE payment MODIFY payment_amount decimal(10,2) COMMENT 'จำนวนเงิน'",
    "ALTER TABLE payment MODIFY payment_type int(1) COMMENT 'ประเภทรายการ (0=รายรับจากลูกค้า, 1=รายจ่ายของร้าน)'",
    "ALTER TABLE payment MODIFY payment_name varchar(200) COMMENT 'ชื่อรายการ (เช่น ค่าไฟ, ซื้อของ)'",
    "ALTER TABLE payment MODIFY payment_note text COMMENT 'หมายเหตุเพิ่มเติม'",

    // ตาราง service
    "ALTER TABLE service COMMENT 'รายการบริการและราคา'",
    "ALTER TABLE service MODIFY service_id varchar(13) COMMENT 'รหัสบริการ'",
    "ALTER TABLE service MODIFY service_name varchar(50) COMMENT 'ชื่อบริการ'",
    "ALTER TABLE service MODIFY service_price decimal(10,2) COMMENT 'ค่าบริการ'",
    "ALTER TABLE service MODIFY service_quota int(11) COMMENT 'จำนวนโควตาที่ต้องใช้'",

    // ตาราง request
    "ALTER TABLE request COMMENT 'การแจ้งความประสงค์รับผ้า'",
    "ALTER TABLE request MODIFY rq_id varchar(13) COMMENT 'รหัสคำแจ้งรับผ้า'",
    "ALTER TABLE request MODIFY rq_status int(1) COMMENT 'สถานะ (0=รอรับ, 1=รับแล้ว/ปฏิเสธ)'"
];

echo "เริ่มการแก้ไขภาษาไทย...\n";
foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "สำเร็จ: " . substr($sql, 0, 50) . "...\n";
    } else {
        echo "ผิดพลาด: " . $conn->error . "\n";
    }
}
echo "เสร็จสมบูรณ์!";
?>