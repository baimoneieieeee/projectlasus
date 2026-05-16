<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit;
}

$order_id = $conn->real_escape_string($_GET['id']);
$user = $_SESSION['user'];

$sql_order = "SELECT * FROM orders WHERE order_id = '$order_id' AND m_id = '{$user['m_id']}'";
$res_order = $conn->query($sql_order);
$order = $res_order->fetch_assoc();

$shop = $conn->query("SELECT * FROM shop LIMIT 1")->fetch_assoc();

if (isset($_POST['submit_payment'])) {
    $date = date("Y-m-d H:i:s");
    $amount = $order['order_sum'];
    
    if (isset($_FILES['payment_image']) && $_FILES['payment_image']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['payment_image']['tmp_name'];
        $image_data = file_get_contents($tmp_name);
        
        $stmt = $conn->prepare("INSERT INTO payment (payment_date, payment_amount, payment_image, order_id) VALUES (?, ?, ?, ?)");
        $null = NULL;
        $stmt->bind_param("sdbs", $date, $amount, $null, $order_id);
        $stmt->send_long_data(2, $image_data);
        
        if ($stmt->execute()) {
            header("Location: home.php?msg=payment_submitted");
            exit;
        } else {
            $error = "บันทึกข้อมูลไม่สำเร็จ";
        }
    } else {
        $error = "กรุณาแนบสลิปโอนเงิน";
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ชำระเงิน - #<?= $order_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&display=swap');
        :root { --primary: #3B82F6; --bg: #F1F5F9; }
        body { background: var(--bg); font-family: 'Chakra Petch', sans-serif; font-size: 0.85rem; }
        .compact-container { max-width: 500px; margin: 0 auto; padding: 15px; }
        .card-custom { border: none; border-radius: 20px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); background: white; }
        .qr-box { background: white; padding: 10px; border-radius: 15px; border: 2px dashed #e2e8f0; display: inline-block; }
    </style>
</head>
<body class="py-3">
    <div class="compact-container">
        <!-- Header: ย้อนกลับ + ข้อมูลติดต่อ -->
        <div class="d-flex justify-content-between align-items-start mb-3 px-2">
            <a href="order_detail.php?id=<?= $order_id ?>" class="text-decoration-none text-muted fw-bold small mt-1"><i class="fa fa-arrow-left me-1"></i> ย้อนกลับ</a>
            <div class="text-end">
                <div class="d-flex align-items-center justify-content-end gap-2 mb-1">
                    <a href="tel:<?= $shop['shop_tel'] ?>" class="text-primary text-decoration-none small fw-bold">
                        <i class="fa fa-phone-alt me-1"></i> <?= $shop['shop_tel'] ?>
                    </a>
                    <span class="text-muted opacity-50">|</span>
                    <span class="text-success small fw-bold"><i class="fa fa-comment-dots me-1"></i> แชท</span>
                </div>
                <span class="badge bg-dark rounded-pill px-3 py-1" style="font-size: 0.7rem;">ID: #<?= $order_id ?></span>
            </div>
        </div>

        <div class="card card-custom p-4 text-center">
            <div class="mb-3">
                <?php if(file_exists('../owner/logo.png')): ?>
                    <img src="../owner/logo.png" class="img-fluid" style="max-height: 50px;">
                <?php else: ?>
                    <i class="fa fa-shirt fa-2x text-primary opacity-25"></i>
                <?php endif; ?>
            </div>
            
            <h5 class="fw-bold mb-1">ชำระค่าบริการ</h5>
            <p class="text-muted smaller mb-4">สแกน QR Code และแนบสลิปด้านล่าง</p>

            <?php if(isset($error)): ?>
                <div class="alert alert-danger py-2 rounded-3 small mb-3"><?= $error ?></div>
            <?php endif; ?>

            <!-- ยอดเงินเด่นๆ -->
            <div class="bg-primary bg-opacity-10 rounded-4 p-3 mb-4 border border-primary border-opacity-10">
                <div class="smaller text-primary fw-bold mb-1">ยอดชำระสุทธิ</div>
                <h2 class="fw-bold text-primary mb-0">฿<?= number_format($order['order_sum'], 2) ?></h2>
            </div>

            <!-- QR Code -->
            <div class="mb-4">
                <div class="qr-box shadow-sm">
                    <?php if($shop['shop_QRcode']): ?>
                        <img src="data:image/jpeg;base64,<?= base64_encode($shop['shop_QRcode']) ?>" class="img-fluid rounded-3" style="max-height: 180px;">
                    <?php else: ?>
                        <div class="p-5 text-muted small">ยังไม่ได้ตั้งค่า QR Code</div>
                    <?php endif; ?>
                </div>
                <div class="mt-2 fw-bold text-dark small"><?= $shop['shop_name1'] ?></div>
            </div>

            <!-- Form -->
            <form method="post" enctype="multipart/form-data" class="text-start border-top pt-4">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted"><i class="fa fa-image me-1"></i> เลือกรูปสลิปโอนเงิน</label>
                    <input type="file" name="payment_image" class="form-control rounded-3 border-2" accept="image/*" required>
                </div>
                <button type="submit" name="submit_payment" class="btn btn-primary btn-lg w-100 rounded-pill py-2 fw-bold shadow-sm" style="font-size: 1rem;">
                    <i class="fa fa-paper-plane me-2"></i> ส่งหลักฐานการโอน
                </button>
            </form>
        </div>

        <div class="text-center mt-3 text-muted" style="font-size: 0.65rem;">
            ระบุยอดเงินให้ตรงกับใบแจ้งหนี้เพื่อความรวดเร็วในการตรวจสอบ
        </div>
    </div>
</body>
</html>
