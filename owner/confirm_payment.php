<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

$order_id = $conn->real_escape_string($_GET['id']);

// อัปเดตสถานะออเดอร์เป็นจ่ายเงินแล้ว (3)
$sql = "UPDATE orders SET order_status = 3 WHERE order_id = '$order_id'";
if ($conn->query($sql)) {
    header("Location: order_process.php?id=$order_id");
    exit;
}
?>
