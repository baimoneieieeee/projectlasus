<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

$order_id = $conn->real_escape_string($_GET['id']);
$deli_id = "DEL" . time();
$date = date("Y-m-d H:i:s");

$conn->begin_transaction();
try {
    // 1. บันทึกวันจัดส่งลงในตาราง delivery
    $conn->query("INSERT INTO delivery (deli_id, order_id, deli_date) VALUES ('$deli_id', '$order_id', '$date')");
    
    // 2. อัปเดตสถานะออเดอร์เป็นจัดส่งคืนแล้ว (4)
    $conn->query("UPDATE orders SET order_status = 4 WHERE order_id = '$order_id'");
    
    $conn->commit();
    header("Location: order_process.php?id=$order_id");
    exit;
} catch (Exception $e) {
    $conn->rollback();
}
?>
