SET NAMES utf8mb4;
SET character_set_client = utf8mb4;
SET character_set_connection = utf8mb4;
SET character_set_results = utf8mb4;

-- ตาราง shop
ALTER TABLE `shop` 
MODIFY COLUMN `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'รหัสร้านค้า',
MODIFY COLUMN `shop_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'ชื่อจริงเจ้าของร้าน',
MODIFY COLUMN `shop_lastname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'นามสกุลเจ้าของร้าน',
MODIFY COLUMN `shop_name1` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'ชื่อร้านค้า/ชื่อแบรนด์',
MODIFY COLUMN `shop_address` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'ที่อยู่ตั้งร้าน',
MODIFY COLUMN `shop_tel` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'เบอร์โทรศัพท์ร้าน',
MODIFY COLUMN `shop_mail` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'อีเมลเข้าใช้งาน',
MODIFY COLUMN `shop_password` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'รหัสผ่าน',
MODIFY COLUMN `shop_rate` int(11) COMMENT 'ค่าส่งปกติ',
MODIFY COLUMN `shop_extra_price` int(11) COMMENT 'ค่าซักเกินโควตาต่อชิ้น',
MODIFY COLUMN `shop_QRcode` longblob COMMENT 'รูปภาพ QR Code รับเงิน',
MODIFY COLUMN `shop_dist_1` int(11) COMMENT 'ระยะส่งฟรี (กม.)',
MODIFY COLUMN `shop_dist_2` int(11) COMMENT 'ระยะส่งปกติสูงสุด (กม.)',
MODIFY COLUMN `shop_rate_far` int(11) COMMENT 'ค่าส่งระยะไกล';

-- ตาราง member
ALTER TABLE `member`
MODIFY COLUMN `m_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'รหัสสมาชิก',
MODIFY COLUMN `m_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'ชื่อลูกค้า',
MODIFY COLUMN `m_lastname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'นามสกุลลูกค้า',
MODIFY COLUMN `m_password` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'รหัสผ่าน',
MODIFY COLUMN `m_address` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'ที่อยู่ลูกค้า (ข้อความ)',
MODIFY COLUMN `m_tel` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'เบอร์โทรศัพท์ลูกค้า',
MODIFY COLUMN `m_mail` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'อีเมลลูกค้า';

-- ตาราง service_type
ALTER TABLE `service_type`
MODIFY COLUMN `svType_id` varchar(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'รหัสประเภทบริการ',
MODIFY COLUMN `svType_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'ชื่อประเภทบริการ',
MODIFY COLUMN `svType_behavior` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'พฤติกรรมการคิดเงิน (quota/special)';

-- ตาราง service
ALTER TABLE `service`
MODIFY COLUMN `service_id` varchar(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'รหัสบริการ',
MODIFY COLUMN `service_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'ชื่อบริการ',
MODIFY COLUMN `service_price` decimal(10,2) COMMENT 'ค่าบริการ',
MODIFY COLUMN `service_quota` int(11) COMMENT 'จำนวนโควตาที่ต้องใช้';

-- ตาราง payment
ALTER TABLE `payment`
MODIFY COLUMN `payment_id` int(11) COMMENT 'รหัสบันทึกการเงิน',
MODIFY COLUMN `payment_date` datetime COMMENT 'วันเวลาที่ทำรายการ',
MODIFY COLUMN `payment_amount` decimal(10,2) COMMENT 'จำนวนเงิน',
MODIFY COLUMN `payment_type` int(1) COMMENT 'ประเภทรายการ (0=รายรับจากลูกค้า, 1=รายจ่ายของร้าน)',
MODIFY COLUMN `payment_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'ชื่อรายการ (เช่น ค่าไฟ, ซื้อของ)',
MODIFY COLUMN `payment_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'หมายเหตุเพิ่มเติม';

-- ตาราง orders
ALTER TABLE `orders`
MODIFY COLUMN `order_id` varchar(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'รหัสออเดอร์',
MODIFY COLUMN `order_status` int(1) COMMENT 'สถานะ (0=รอรับ, 1=รอจ่าย, 2=กำลังซัก, 3=เสร็จ, 4=ส่งแล้ว)',
MODIFY COLUMN `order_sum` decimal(10,2) COMMENT 'ยอดรวมสุทธิ',
MODIFY COLUMN `order_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'สรุปรายการผ้า';
