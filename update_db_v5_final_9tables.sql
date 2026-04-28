-- 1. ปรับปรุงตาราง payment เพื่อรองรับรายจ่าย (Expense)
ALTER TABLE `payment` ADD COLUMN IF NOT EXISTS `payment_type` INT(1) DEFAULT 0 COMMENT 'ประเภทรายการ (0=รายรับจากลูกค้า, 1=รายจ่ายของร้าน)';
ALTER TABLE `payment` ADD COLUMN IF NOT EXISTS `payment_name` VARCHAR(200) DEFAULT NULL COMMENT 'ชื่อรายการ (เช่น ค่าไฟ, ซื้อของ)';
ALTER TABLE `payment` ADD COLUMN IF NOT EXISTS `payment_note` TEXT DEFAULT NULL COMMENT 'หมายเหตุเพิ่มเติม';

-- 2. เพิ่มฟิลด์ที่ขาดหายไปในตารางอื่นๆ
ALTER TABLE `shop` ADD COLUMN IF NOT EXISTS `shop_name` VARCHAR(50) AFTER `shop_id`;
ALTER TABLE `shop` ADD COLUMN IF NOT EXISTS `shop_lastname` VARCHAR(50) AFTER `shop_name`;
ALTER TABLE `service` ADD COLUMN IF NOT EXISTS `service_image` LONGBLOB AFTER `svType_id`;

-- 3. ย้ายข้อมูลรายจ่ายเดิม (ถ้ามี) มาไว้ที่ตาราง payment
-- ตรวจสอบก่อนว่ามีตาราง expenses หรือไม่
SET @exist = (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'laundry_system' AND table_name = 'expenses');
SET @sql = IF(@exist > 0, 'INSERT INTO payment (payment_date, payment_amount, payment_name, payment_note, payment_type) SELECT exp_date, exp_amount, exp_name, exp_note, 1 FROM expenses', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. ลบตาราง expenses
DROP TABLE IF EXISTS `expenses`;

-- 5. เติมคำอธิบาย (Comment) ภาษาไทยให้ครบทุกตาราง

-- ตาราง shop
ALTER TABLE `shop` MODIFY COLUMN `shop_id` varchar(20) COMMENT 'รหัสร้านค้า';
ALTER TABLE `shop` MODIFY COLUMN `shop_name` varchar(50) COMMENT 'ชื่อจริงเจ้าของร้าน';
ALTER TABLE `shop` MODIFY COLUMN `shop_lastname` varchar(50) COMMENT 'นามสกุลเจ้าของร้าน';
ALTER TABLE `shop` MODIFY COLUMN `shop_name1` varchar(60) COMMENT 'ชื่อร้านค้า/ชื่อแบรนด์';
ALTER TABLE `shop` MODIFY COLUMN `shop_address` varchar(300) COMMENT 'ที่อยู่ตั้งร้าน';
ALTER TABLE `shop` MODIFY COLUMN `shop_tel` varchar(10) COMMENT 'เบอร์โทรศัพท์ร้าน';
ALTER TABLE `shop` MODIFY COLUMN `shop_mail` varchar(50) COMMENT 'อีเมลเข้าใช้งาน';
ALTER TABLE `shop` MODIFY COLUMN `shop_password` varchar(50) COMMENT 'รหัสผ่าน';
ALTER TABLE `shop` MODIFY COLUMN `shop_rate` int(11) COMMENT 'ค่าส่งปกติ';
ALTER TABLE `shop` MODIFY COLUMN `shop_extra_price` int(11) COMMENT 'ค่าซักเกินโควตาต่อชิ้น';
ALTER TABLE `shop` MODIFY COLUMN `shop_QRcode` longblob COMMENT 'รูปภาพ QR Code รับเงิน';
ALTER TABLE `shop` MODIFY COLUMN `shop_dist_1` int(11) COMMENT 'ระยะส่งฟรี (กม.)';
ALTER TABLE `shop` MODIFY COLUMN `shop_dist_2` int(11) COMMENT 'ระยะส่งปกติสูงสุด (กม.)';
ALTER TABLE `shop` MODIFY COLUMN `shop_rate_far` int(11) COMMENT 'ค่าส่งระยะไกล';

-- ตาราง member
ALTER TABLE `member` MODIFY COLUMN `m_id` varchar(20) COMMENT 'รหัสสมาชิก';
ALTER TABLE `member` MODIFY COLUMN `m_name` varchar(50) COMMENT 'ชื่อลูกค้า';
ALTER TABLE `member` MODIFY COLUMN `m_lastname` varchar(50) COMMENT 'นามสกุลลูกค้า';
ALTER TABLE `member` MODIFY COLUMN `m_password` varchar(20) COMMENT 'รหัสผ่าน';
ALTER TABLE `member` MODIFY COLUMN `m_latitude` double COMMENT 'ละติจูดที่อยู่ลูกค้า';
ALTER TABLE `member` MODIFY COLUMN `m_longitude` double COMMENT 'ลองจิจูดที่อยู่ลูกค้า';
ALTER TABLE `member` MODIFY COLUMN `m_address` varchar(300) COMMENT 'ที่อยู่ลูกค้า (ข้อความ)';
ALTER TABLE `member` MODIFY COLUMN `m_tel` varchar(10) COMMENT 'เบอร์โทรศัพท์ลูกค้า';
ALTER TABLE `member` MODIFY COLUMN `m_mail` varchar(50) COMMENT 'อีเมลลูกค้า';

-- ตาราง service_type
ALTER TABLE `service_type` MODIFY COLUMN `svType_id` varchar(13) COMMENT 'รหัสประเภทบริการ';
ALTER TABLE `service_type` MODIFY COLUMN `svType_name` varchar(50) COMMENT 'ชื่อประเภทบริการ';
ALTER TABLE `service_type` MODIFY COLUMN `svType_behavior` varchar(20) COMMENT 'พฤติกรรมการคิดเงิน (quota/special)';

-- ตาราง service
ALTER TABLE `service` MODIFY COLUMN `service_id` varchar(13) COMMENT 'รหัสบริการ';
ALTER TABLE `service` MODIFY COLUMN `service_name` varchar(50) COMMENT 'ชื่อบริการ';
ALTER TABLE `service` MODIFY COLUMN `svType_id` varchar(13) COMMENT 'รหัสประเภท (FK)';
ALTER TABLE `service` MODIFY COLUMN `service_image` longblob COMMENT 'รูปภาพตัวอย่างบริการ';
ALTER TABLE `service` MODIFY COLUMN `service_price` decimal(10,2) COMMENT 'ค่าบริการ';
ALTER TABLE `service` MODIFY COLUMN `service_quota` int(11) COMMENT 'จำนวนโควตาที่ต้องใช้';

-- ตาราง payment
ALTER TABLE `payment` MODIFY COLUMN `payment_id` int(11) COMMENT 'รหัสบันทึกการเงิน';
ALTER TABLE `payment` MODIFY COLUMN `payment_date` datetime COMMENT 'วันเวลาที่ทำรายการ';
ALTER TABLE `payment` MODIFY COLUMN `payment_amount` decimal(10,2) COMMENT 'จำนวนเงิน';
ALTER TABLE `payment` MODIFY COLUMN `payment_image` longblob COMMENT 'รูปภาพสลิป (รายรับ)';
ALTER TABLE `payment` MODIFY COLUMN `order_id` varchar(13) COMMENT 'รหัสออเดอร์อ้างอิง (FK)';
ALTER TABLE `payment` MODIFY COLUMN `payment_type` int(1) COMMENT 'ประเภทรายการ (0=รายรับจากลูกค้า, 1=รายจ่ายของร้าน)';
ALTER TABLE `payment` MODIFY COLUMN `payment_name` varchar(200) COMMENT 'ชื่อรายการ (เช่น ค่าไฟ, ซื้อของ)';
ALTER TABLE `payment` MODIFY COLUMN `payment_note` text COMMENT 'หมายเหตุเพิ่มเติม';
