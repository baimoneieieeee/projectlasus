<?php
include 'config/db.php';
$conn->set_charset("utf8mb4");

$sql = "ALTER TABLE item MODIFY COLUMN item_name VARCHAR(100) COMMENT 'ชื่อรายการผ้า (เช่น เสื้อยืด, กางเกง)'";

if ($conn->query($sql)) {
    echo "แก้ไข Note ภาษาไทยของ item_name สำเร็จ!";
} else {
    echo "ผิดพลาด: " . $conn->error;
}
?>