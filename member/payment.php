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
$sql_order = "SELECT * FROM orders WHERE order_id = '$order_id' AND m_id = '{$user['m_id']}'";
$res_order = $conn->query($sql_order);
$order = $res_order->fetch_assoc();

// ดึงข้อมูลร้าน (เพื่อแสดงคิวอาร์โค้ด)
$shop = $conn->query("SELECT * FROM shop LIMIT 1")->fetch_assoc();

if (isset($_POST['submit_payment'])) {
    $date = date("Y-m-d H:i:s");
    $amount = $order['order_sum'] + $order['order_shipping_fee'];
    
    // จัดการไฟล์รูปภาพสลิป
    $image = addslashes(file_get_contents($_FILES['payment_image']['tmp_name']));

    $sql = "INSERT INTO payment (payment_date, payment_amount, payment_image, order_id) 
            VALUES ('$date', '$amount', '$image', '$order_id')";
    
    if ($conn->query($sql)) {
        // ห้ามอัปเดตสถานะเป็น 2 ทันที ต้องรอเจ้าของร้านกดยืนยันเงินก่อน
        header("Location: home.php?msg=payment_submitted");
        exit;
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ชำระเงิน - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-light py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                    <h3 class="fw-bold mb-3">💰 ชำระค่าบริการ</h3>
                    <p class="text-muted">รายการสั่งซื้อ #<?= $order_id ?></p>
                    <hr>
                    
                    <div class="alert alert-info p-4 rounded-4 mb-4 shadow-sm">
                        <div class="d-flex justify-content-between mb-2 small opacity-75">
                            <span>ค่าบริการซักอบรีด:</span>
                            <span>฿<?= number_format($order['order_sum'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 small opacity-75 border-bottom pb-2">
                            <span>ค่าบริการจัดส่ง:</span>
                            <span>฿<?= number_format($order['order_shipping_fee'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="fw-bold h5 mb-0">ยอดรวมที่ต้องชำระ:</span>
                            <h2 class="fw-bold text-primary mb-0">฿<?= number_format($order['order_sum'] + $order['order_shipping_fee'], 2) ?></h2>
                        </div>
                    </div>

                    <div class="p-3 border rounded-4 bg-white mb-4">
                        <h5 class="fw-bold mb-3"><i class="fa fa-university me-2 text-primary"></i> ข้อมูลการโอนเงิน</h5>
                        <p class="mb-2"><strong>ร้าน:</strong> <?= $shop['shop_name1'] ?: 'อนุชาซักอบรีด' ?></p>
                        <?php if($shop['shop_QRcode']): ?>
                            <img src="data:image/jpeg;base64,<?= base64_encode($shop['shop_QRcode']) ?>" class="img-fluid rounded-4 mb-3" style="max-height: 250px;">
                        <?php endif; ?>
                        <p class="small text-muted mb-0">สแกนจ่ายด้วย PromptPay</p>
                    </div>

                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-4 text-start">
                            <label class="form-label fw-bold small"><i class="fa fa-upload me-2"></i> แนบหลักฐานการโอน (สลิป)</label>
                            <input type="file" name="payment_image" class="form-control rounded-pill" accept="image/*" required>
                        </div>
                        <button type="submit" name="submit_payment" class="btn btn-primary btn-lg w-100 rounded-pill py-3">
                            <i class="fa fa-check-circle me-2"></i> ยืนยันการชำระเงิน
                        </button>
                        <a href="home.php" class="btn btn-light btn-lg w-100 rounded-pill mt-3">กลับไปหน้าแรก</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
