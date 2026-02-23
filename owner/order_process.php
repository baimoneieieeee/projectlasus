<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

$order_id = $conn->real_escape_string($_GET['id']);

// ดึงข้อมูลออเดอร์
$sql_order = "SELECT o.*, m.m_name, m.m_lastname, m.m_tel FROM orders o JOIN member m ON o.m_id = m.m_id WHERE o.order_id = '$order_id'";
$res_order = $conn->query($sql_order);
$order = $res_order->fetch_assoc();

// จัดการการเพิ่มรายการบริการ
if (isset($_POST['add_item'])) {
    $item_id = "ITM" . time();
    $service_id = $_POST['service_id'];
    $amount = $_POST['amount'];
    
    // ดึงราคางานบริการ
    $service = $conn->query("SELECT service_price FROM service WHERE service_id = '$service_id'")->fetch_assoc();
    $price = $service['service_price'];

    $conn->begin_transaction();
    try {
        // 1. เพิ่มรายการไอเทม
        $conn->query("INSERT INTO item (item_id, order_id, service_id, item_amount) VALUES ('$item_id', '$order_id', '$service_id', '$amount')");
        
        // 2. อัปเดตยอดรวมออเดอร์
        $conn->query("UPDATE orders SET order_sum = order_sum + ($price * $amount) WHERE order_id = '$order_id'");
        
        $conn->commit();
        header("Location: order_process.php?id=$order_id");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
    }
}

// อัปเดตสถานะออเดอร์ (เมื่อซักเสร็จ)
if (isset($_POST['set_payment_ready'])) {
    $conn->query("UPDATE orders SET order_status = 2 WHERE order_id = '$order_id'");
    header("Location: manage_orders.php");
    exit;
}

// ดึงรายการไอเทม
$sql_items = "SELECT i.*, s.service_name, s.service_price FROM item i JOIN service s ON i.service_id = s.service_id WHERE i.order_id = '$order_id'";
$res_items = $conn->query($sql_items);

// ดึงบริการที่มีให้เลือก
$res_services = $conn->query("SELECT * FROM service");

// ดึงข้อมูลการชำระเงิน (ถ้ามี)
$sql_payment = "SELECT * FROM payment WHERE order_id = '$order_id' ORDER BY payment_id DESC LIMIT 1";
$res_payment = $conn->query($sql_payment);
$payment = $res_payment->fetch_assoc();
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ดำเนินการสั่งซื้อ - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-light py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold mb-0">🛠 ดำเนินการออเดอร์ #<?= $order_id ?></h4>
                        <a href="manage_orders.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> ย้อนกลับ</a>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">ลูกค้า:</p>
                            <h5 class="fw-bold mb-0"><?= $order['m_name'] . ' ' . $order['m_lastname'] ?></h5>
                            <p class="text-primary"><?= $order['m_tel'] ?></p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p class="mb-1 text-muted small">วันที่สั่งซื้อ:</p>
                            <h5 class="fw-bold mb-0"><?= $order['order_date'] ?></h5>
                        </div>
                    </div>

                    <hr>

                    <h5 class="fw-bold mb-3">📋 รายการบริการในออเดอร์นี้</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-hover">
                            <thead class="table-light"><tr><th>บริการ</th><th>จำนวน</th><th>ราคา/หน่วย</th><th>รวม</th></tr></thead>
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

                    <?php if($order['order_status'] == 1): ?>
                        <div class="p-3 bg-white border rounded-4 mb-4">
                            <h6 class="fw-bold mb-3"><i class="fa fa-plus-circle me-2 text-success"></i> เพิ่มรายการบริการ</h6>
                            <form method="post" class="row g-2">
                                <div class="col-md-6">
                                    <select name="service_id" class="form-select rounded-pill" required>
                                        <option value="">เลือกประเภทบริการ...</option>
                                        <?php while($s = $res_services->fetch_assoc()): ?>
                                            <option value="<?= $s['service_id'] ?>"><?= $s['service_name'] ?> (฿<?= $s['service_price'] ?>)</option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="amount" class="form-control rounded-pill" placeholder="จำนวน" value="1" min="1" required>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" name="add_item" class="btn btn-success w-100 rounded-pill">เพิ่ม</button>
                                </div>
                            </form>
                        </div>

                        <form method="post">
                            <button type="submit" name="set_payment_ready" class="btn btn-primary w-100 btn-lg rounded-pill" 
                                    onclick="return confirm('ยืนยันว่าซักเสร็จแล้ว และพร้อมให้ลูกค้าจ่ายเงิน?')">
                                <i class="fa fa-check-circle me-2"></i> บันทึกงานเสร็จ (รอจ่ายเงิน)
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h5 class="fw-bold mb-4">💳 ข้อมูลการชำระเงิน</h5>
                    <?php if($payment): ?>
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="fa fa-info-circle me-1"></i> แจ้งเมื่อ: <?= $payment['payment_date'] ?>
                        </div>
                        <p class="mb-1 fw-bold">สลิปหลักฐาน:</p>
                        <img src="data:image/jpeg;base64,<?= base64_encode($payment['payment_image']) ?>" class="img-fluid rounded-4 mb-3 border shadow-sm">
                        
                        <?php if($order['order_status'] == 2): ?>
                            <a href="confirm_payment.php?id=<?= $order_id ?>" class="btn btn-primary w-100 rounded-pill mb-2">
                                <i class="fa fa-check me-2"></i> ยืนยันยอดเงินถูกต้อง
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa fa-receipt fa-3x mb-3 text-light"></i>
                            <p>ยังไม่มีการแจ้งชำระเงิน</p>
                        </div>
                    <?php endif; ?>

                    <?php if($order['order_status'] == 3): ?>
                        <hr>
                        <h5 class="fw-bold text-success mb-3"><i class="fa fa-truck me-2"></i> การจัดส่ง</h5>
                        <a href="send_delivery.php?id=<?= $order_id ?>" class="btn btn-success w-100 rounded-pill">
                            <i class="fa fa-box me-2"></i> ยืนยันการจัดส่งคืน
                        </a>
                    <?php endif; ?>

                    <?php if($order['order_status'] == 4): ?>
                        <div class="alert alert-success text-center py-4 mb-0 rounded-4">
                            <i class="fa fa-check-double fa-3x mb-3"></i>
                            <h5 class="fw-bold mb-0">งานสำเร็จแล้ว</h5>
                            <p class="small mb-0">ส่งคืนลูกค้าเรียบร้อย</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
