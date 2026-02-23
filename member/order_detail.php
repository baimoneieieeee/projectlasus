<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit;
}

$order_id = $conn->real_escape_string($_GET['id']);
$user = $_SESSION['user'];

// ดึงข้อมูลออเดอร์
$sql_order = "SELECT o.* FROM orders o WHERE o.order_id = '$order_id' AND o.m_id = '{$user['m_id']}'";
$res_order = $conn->query($sql_order);
$order = $res_order->fetch_assoc();

if (!$order) {
    die("ไม่พบข้อมูลคำสั่งซื้อ");
}

// ดึงรายการไอเทม
$sql_items = "SELECT i.*, s.service_name, s.service_price FROM item i JOIN service s ON i.service_id = s.service_id WHERE i.order_id = '$order_id'";
$res_items = $conn->query($sql_items);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>รายละเอียดคำสั่งซื้อ - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-light py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold mb-0">📦 รายละเอียดออเดอร์ #<?= $order_id ?></h4>
                        <a href="home.php" class="btn btn-outline-secondary btn-sm">ย้อนกลับ</a>
                    </div>

                    <div class="row mb-4">
                        <div class="col-6">
                            <p class="mb-1 text-muted small">สถานะ:</p>
                            <?php 
                            $status = ['รอดำเนินการ', 'กำลังซัก/อบ/รีด', 'รอจ่ายเงิน', 'จ่ายเงินแล้ว', 'จัดส่งคืนแล้ว'];
                            $colors = ['bg-secondary', 'bg-info', 'bg-warning text-dark', 'bg-primary', 'bg-success'];
                            echo "<span class='badge {$colors[$order['order_status']]}'>{$status[$order['order_status']]}</span>";
                            ?>
                        </div>
                        <div class="col-6 text-end">
                            <p class="mb-1 text-muted small">วันที่สั่ง:</p>
                            <h6 class="fw-bold"><?= $order['order_date'] ?></h6>
                        </div>
                    </div>

                    <h5 class="fw-bold mb-3">📋 รายการบริการ</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="table-light"><tr><th>รายการ</th><th>จำนวน</th><th>ราคา/หน่วย</th><th>รวม</th></tr></thead>
                            <tbody>
                                <?php while($item = $res_items->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $item['service_name'] ?></td>
                                    <td><?= $item['item_amount'] ?></td>
                                    <td>฿<?= number_format($item['service_price'], 2) ?></td>
                                    <td class="fw-bold">฿<?= number_format($item['service_price'] * $item['item_amount'], 2) ?></td>
                                </tr>
                                <?php endwhile; ?>
                                <tr class="table-light">
                                    <td colspan="3" class="text-end fw-bold">ยอดรวมสุทธิ</td>
                                    <td class="fw-bold text-primary h5">฿<?= number_format($order['order_sum'], 2) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <?php if($order['order_status'] == 2): ?>
                        <div class="mt-4">
                            <a href="payment.php?id=<?= $order_id ?>" class="btn btn-primary w-100 rounded-pill py-3">
                                <i class="fa fa-wallet me-2"></i> ไปหน้าชำระเงิน
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
