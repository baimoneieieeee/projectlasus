-- 10. ตารางค่าใช้จ่าย (expenses)
CREATE TABLE IF NOT EXISTS expenses (
  exp_id INT AUTO_INCREMENT PRIMARY KEY,
  exp_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  exp_name VARCHAR(200) NOT NULL,
  exp_amount DECIMAL(10,2) NOT NULL,
  exp_note TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
