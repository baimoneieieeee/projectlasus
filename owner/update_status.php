<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

if (isset($_POST['order_id']) && isset($_POST['new_status'])) {
    $order_id = $conn->real_escape_string($_POST['order_id']);
    $new_status = (int)$_POST['new_status'];

    $sql = "UPDATE orders SET order_status = $new_status WHERE order_id = '$order_id'";
    if ($conn->query($sql)) {
        if (isset($_POST['redirect']) && $_POST['redirect'] === 'manage') {
            header("Location: manage_orders.php");
        } else {
            header("Location: order_process.php?id=$order_id");
        }
        exit;
    }
}
?>