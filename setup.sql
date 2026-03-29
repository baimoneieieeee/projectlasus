-- ==========================================================
-- ฐานข้อมูลระบบซักอบรีด (Laundry System)
-- เวอร์ชัน: 2.0 (ตรงตามเอกสารบทที่ 4: Context, DFD, Data Dictionary)
-- ==========================================================

CREATE DATABASE IF NOT EXISTS laundry_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE laundry_system;

-- 1. ตารางข้อมูลร้านค้า (shop)
CREATE TABLE IF NOT EXISTS shop (
  shop_id VARCHAR(20) PRIMARY KEY,
  shop_name1 VARCHAR(60),
  shop_address VARCHAR(300),
  shop_tel VARCHAR(10),
  shop_mail VARCHAR(50),
  shop_password VARCHAR(50),
  shop_rate INT(11),
  shop_QRcode LONGBLOB
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. ตารางข้อมูลลูกค้า (member)
CREATE TABLE IF NOT EXISTS member (
  m_id VARCHAR(20) PRIMARY KEY,
  m_name VARCHAR(50),
  m_lastname VARCHAR(50),
  m_password VARCHAR(20),
  m_tel VARCHAR(10),
  m_mail VARCHAR(50),
  m_address VARCHAR(300),
  m_latitude DOUBLE,
  m_longitude DOUBLE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. ตารางประเภทบริการ (service_type)
CREATE TABLE IF NOT EXISTS service_type (
  svType_id VARCHAR(13) PRIMARY KEY,
  svType_name VARCHAR(50), -- 'รายเดือน (เหมา)' หรือ 'รายครั้ง (ผ้าพิเศษ)'
  svType_note TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. ตารางรายการบริการ (service)
CREATE TABLE IF NOT EXISTS service (
  service_id VARCHAR(13) PRIMARY KEY,
  service_name VARCHAR(50),
  svType_id VARCHAR(13),
  service_price DECIMAL(10,2),
  service_image LONGBLOB,
  FOREIGN KEY (svType_id) REFERENCES service_type(svType_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. ตารางการแจ้งรับผ้า (request)
CREATE TABLE IF NOT EXISTS request (
  rq_id VARCHAR(13) PRIMARY KEY,
  m_id VARCHAR(20),
  rq_date DATETIME,
  rq_latitude DOUBLE,
  rq_longitude DOUBLE,
  rq_status INT(1) DEFAULT 0, -- 0=รอรับ, 1=รับแล้ว, 2=ปฏิเสธ
  rq_note TEXT,
  FOREIGN KEY (m_id) REFERENCES member(m_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. ตารางรายการสั่งซื้อ (orders)
CREATE TABLE IF NOT EXISTS orders (
  order_id VARCHAR(13) PRIMARY KEY,
  m_id VARCHAR(20),
  rq_id VARCHAR(13),
  order_date DATETIME,
  order_date_return DATETIME,
  order_status INT(1) DEFAULT 0, -- 0=รอรับ, 1=ซัก, 2=รอจ่าย, 3=จ่ายแล้ว, 4=สำเร็จ
  order_address VARCHAR(300),
  order_sum DECIMAL(10,2) DEFAULT 0,
  order_shipping_fee DECIMAL(10,2) DEFAULT 0,
  order_note TEXT,
  order_pickup_round INT(2) DEFAULT 0,
  FOREIGN KEY (m_id) REFERENCES member(m_id) ON DELETE CASCADE,
  FOREIGN KEY (rq_id) REFERENCES request(rq_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. ตารางรายการย่อยในออเดอร์ (item)
CREATE TABLE IF NOT EXISTS item (
  item_id VARCHAR(13) PRIMARY KEY,
  order_id VARCHAR(13),
  service_id VARCHAR(13),
  item_amount INT(5),
  FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES service(service_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. ตารางการชำระเงิน (payment)
CREATE TABLE IF NOT EXISTS payment (
  payment_id INT AUTO_INCREMENT PRIMARY KEY,
  payment_date DATETIME,
  payment_amount DECIMAL(10,2),
  payment_image LONGBLOB,
  order_id VARCHAR(13),
  FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. ตารางการจัดส่ง (delivery)
CREATE TABLE IF NOT EXISTS delivery (
  deli_id VARCHAR(13) PRIMARY KEY,
  order_id VARCHAR(13),
  deli_date DATETIME,
  FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ข้อมูลเริ่มต้น (Initial Data)
INSERT IGNORE INTO shop (shop_id, shop_name1, shop_mail, shop_password, shop_tel) 
VALUES ('SHOP01', 'อนุชาซักอบรีด', 'admin@laundry.com', 'admin123', '0812345678');

INSERT IGNORE INTO service_type (svType_id, svType_name, svType_note) VALUES 
('T1', 'รายเดือน (เหมาซักทั่วไป)', 'ซักอบรีดผ้าทั่วไป หักจากโควตา 80 ชิ้น'),
('T2', 'รายครั้ง (ผ้าพิเศษ)', 'ผ้าที่ต้องการการดูแลสูง เช่น ผ้าไหม ชุดราชการ สูท');

INSERT IGNORE INTO service (service_id, service_name, svType_id, service_price) VALUES 
('S001', 'ซักอบรีดทั่วไป (หักโควตา)', 'T1', 0.00),
('S002', 'ชุดราชการ/ชุดปกติขาว', 'T2', 150.00),
('S003', 'ผ้าไหม/ผ้าทอมือ', 'T2', 250.00);
