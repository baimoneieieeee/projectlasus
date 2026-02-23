-- 1. Create Database
CREATE DATABASE IF NOT EXISTS laundry_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE laundry_system;

-- 2. Shop Table (Added shop_password)
CREATE TABLE IF NOT EXISTS shop (
  shop_id VARCHAR(20) PRIMARY KEY,
  shop_name VARCHAR(50),
  shop_lastname VARCHAR(50),
  shop_name1 VARCHAR(60),
  shop_address VARCHAR(300),
  shop_latitude DOUBLE,
  shop_longitude DOUBLE,
  shop_tel VARCHAR(10),
  shop_mail VARCHAR(50),
  shop_password VARCHAR(50),
  shop_rate INT,
  shop_QRcode LONGBLOB
);

-- 3. Member Table
CREATE TABLE IF NOT EXISTS member (
  m_id VARCHAR(20) PRIMARY KEY,
  m_name VARCHAR(50),
  m_lastname VARCHAR(50),
  m_password VARCHAR(20),
  m_latitude DOUBLE,
  m_longitude DOUBLE,
  m_address VARCHAR(300),
  m_tel VARCHAR(10),
  m_mail VARCHAR(50)
);

-- 4. Service Type Table
CREATE TABLE IF NOT EXISTS service_type (
  svType_id VARCHAR(13) PRIMARY KEY,
  svType_name VARCHAR(50),
  svType_note TEXT
);

-- 5. Service Table
CREATE TABLE IF NOT EXISTS service (
  service_id VARCHAR(13) PRIMARY KEY,
  service_name VARCHAR(50),
  svType_id VARCHAR(13),
  service_image LONGBLOB,
  service_price DECIMAL(10,2),
  FOREIGN KEY (svType_id) REFERENCES service_type(svType_id) ON DELETE CASCADE
);

-- 6. Request Table
CREATE TABLE IF NOT EXISTS request (
  rq_id VARCHAR(13) PRIMARY KEY,
  m_id VARCHAR(20),
  rq_date DATETIME,
  rq_latitude DOUBLE,
  rq_longitude DOUBLE,
  rq_status INT DEFAULT 0,
  rq_note TEXT,
  FOREIGN KEY (m_id) REFERENCES member(m_id) ON DELETE CASCADE
);

-- 7. Orders Table
CREATE TABLE IF NOT EXISTS orders (
  order_id VARCHAR(13) PRIMARY KEY,
  m_id VARCHAR(20),
  rq_id VARCHAR(13),
  order_date DATETIME,
  order_date_return DATETIME,
  order_address VARCHAR(300),
  order_status INT DEFAULT 0,
  order_shipping_fee DECIMAL(10,2) DEFAULT 0,
  order_sum DECIMAL(10,2) DEFAULT 0,
  order_note TEXT,
  FOREIGN KEY (m_id) REFERENCES member(m_id) ON DELETE CASCADE,
  FOREIGN KEY (rq_id) REFERENCES request(rq_id) ON DELETE SET NULL
);

-- 8. Item Table
CREATE TABLE IF NOT EXISTS item (
  item_id VARCHAR(13) PRIMARY KEY,
  order_id VARCHAR(13),
  item_amount INT,
  service_id VARCHAR(13),
  FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES service(service_id) ON DELETE CASCADE
);

-- 9. Payment Table
CREATE TABLE IF NOT EXISTS payment (
  payment_id INT AUTO_INCREMENT PRIMARY KEY,
  payment_date DATETIME,
  payment_amount DECIMAL(10,2),
  payment_image LONGBLOB,
  order_id VARCHAR(13),
  FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);

-- 10. Delivery Table
CREATE TABLE IF NOT EXISTS delivery (
  deli_id VARCHAR(13) PRIMARY KEY,
  order_id VARCHAR(13),
  deli_date DATETIME,
  FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);

-- Initial Data
INSERT IGNORE INTO shop (shop_id, shop_name1, shop_mail, shop_password, shop_tel) 
VALUES ('SHOP01', 'อนุชาซักอบรีด', 'admin@laundry.com', 'admin123', '0812345678');

INSERT IGNORE INTO service_type (svType_id, svType_name) VALUES 
('T1', 'ซักผ้า'),
('T2', 'อบผ้า'),
('T3', 'รีดผ้า');

INSERT IGNORE INTO service (service_id, service_name, svType_id, service_price) VALUES 
('S1', 'ซักผ้าทั่วไป (กก.)', 'T1', 20.00),
('S2', 'อบผ้า (กก.)', 'T2', 30.00),
('S3', 'รีดผ้า (ชิ้น)', 'T3', 10.00);
