<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php"); exit;
}

$order_id = $conn->real_escape_string($_GET['id']);
$user = $_SESSION['user'];

// ดึงข้อมูลออเดอร์
$sql_order = "SELECT o.*, m.m_name, m.m_lastname, m.m_tel, m.m_address 
              FROM orders o JOIN member m ON o.m_id = m.m_id 
              WHERE o.order_id = '$order_id'";
$res_order = $conn->query($sql_order);
$order = $res_order->fetch_assoc();

if (!$order || ($user['role'] == 'member' && $order['m_id'] !== $user['m_id'])) {
    die("ไม่พบข้อมูลใบเสร็จ");
}

$res_items = $conn->query("SELECT i.*, s.service_name, s.service_price FROM item i JOIN service s ON i.service_id = s.service_id WHERE i.order_id = '$order_id'");
$shop = $conn->query("SELECT * FROM shop LIMIT 1")->fetch_assoc();
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>ใบเสร็จรับเงิน - #<?= $order_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #eee; font-family: system-ui; }
        .receipt { background: white; width: 100%; max-width: 600px; margin: 30px auto; padding: 40px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .receipt-header { border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px; }
        .logo-text { font-size: 24px; font-weight: bold; color: #3B82F6; }
        .total-row { background: #f9fafb; font-weight: bold; }
        @media print { body { background: white; } .receipt { margin: 0; box-shadow: none; width: 100%; max-width: 100%; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="no-print text-end mb-4">
            <button onclick="window.print()" class="btn btn-primary btn-sm rounded-pill px-4"><i class="fa fa-print me-1"></i> พิมพ์ใบเสร็จ</button>
            <button onclick="window.history.back()" class="btn btn-light btn-sm rounded-pill px-4">ย้อนกลับ</button>
        </div>

        <div class="receipt-header d-flex justify-content-between align-items-center">
            <div>
                <div class="logo-text"><?= $shop['shop_name1'] ?: 'Laundry System' ?></div>
                <p class="text-muted small mb-0"><?= $shop['shop_address'] ?></p>
                <p class="text-muted small">โทร: <?= $shop['shop_tel'] ?></p>
            </div>
            <div class="text-end">
                <h4 class="fw-bold mb-0">ใบเสร็จรับเงิน</h4>
                <p class="text-muted small mb-0">เลขที่: <?= $order_id ?></p>
                <p class="text-muted small">วันที่: <?= date('d/m/Y', strtotime($order['order_date'])) ?></p>
            </div>
        </div>

        <div class="mb-4">
            <h6 class="fw-bold mb-1">ข้อมูลลูกค้า:</h6>
            <p class="mb-0 small"><?= $order['m_name'].' '.$order['m_lastname'] ?></p>
            <p class="mb-0 small"><?= $order['m_tel'] ?></p>
            <p class="text-muted smaller"><?= $order['order_address'] ?></p>
        </div>

        <table class="table table-borderless small">
            <thead>
                <tr class="border-bottom text-muted"><th>รายการ</th><th class="text-center">จำนวน</th><th class="text-end">หน่วยละ</th><th class="text-end">รวม</th></tr>
            </thead>
            <tbody>
                <?php while($item = $res_items->fetch_assoc()): ?>
                <tr>
                    <td><?= $item['service_name'] ?></td>
                    <td class="text-center"><?= $item['item_amount'] ?></td>
                    <td class="text-end">฿<?= number_format($item['service_price'], 2) ?></td>
                    <td class="text-end fw-bold">฿<?= number_format($item['service_price'] * $item['item_amount'], 2) ?></td>
                </tr>
                <?php endwhile; ?>
                <tr class="border-top"><td colspan="3" class="text-end">รวมยอดบริการ</td><td class="text-end fw-bold">฿<?= number_format($order['order_sum'], 2) ?></td></tr>
                <tr><td colspan="3" class="text-end">ค่าจัดส่ง</td><td class="text-end fw-bold">฿<?= number_format($order['order_shipping_fee'], 2) ?></td></tr>
                <tr class="total-row h5"><td colspan="3" class="text-end py-3">ยอดสุทธิ</td><td class="text-end py-3 text-primary">฿<?= number_format($order['order_sum'] + $order['order_shipping_fee'], 2) ?></td></tr>
            </tbody>
        </table>

        <div class="mt-5 pt-4 text-center border-top">
            <p class="text-muted small mb-1">ขอบคุณที่ใช้บริการ</p>
            <p class="fw-bold small">- ยินดีให้บริการเสมอ -</p>
        </div>
    </div>
</body>
</html>
