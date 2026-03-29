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
$sql_order = "SELECT o.*, r.rq_note, (SELECT COUNT(*) FROM payment p WHERE p.order_id = o.order_id) as pay_count 
              FROM orders o 
              LEFT JOIN request r ON o.rq_id = r.rq_id
              WHERE o.order_id = '$order_id' AND o.m_id = '{$user['m_id']}'";
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
    <title>รายละเอียดออเดอร์ - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&display=swap');
        :root { --primary: #2563eb; --bg: #f8fafc; }
        body { background: var(--bg); font-family: 'Chakra Petch', sans-serif; }
        .card-custom { border: none; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: white; }
        .progress-step { width: 60px; position: relative; z-index: 2; }
        .step-icon { width: 40px; height: 40px; line-height: 40px; border-radius: 50%; margin: 0 auto 8px; display: block; text-align: center; transition: 0.3s; }
        .step-label { font-size: 0.65rem; font-weight: 700; color: #64748b; white-space: nowrap; }
        .active .step-icon { background: var(--primary) !important; color: white !important; box-shadow: 0 0 0 5px rgba(37, 99, 235, 0.1); }
        .active .step-label { color: var(--primary); }
        .ring-animation { animation: pulse 1.5s infinite; }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(37, 99, 235, 0); }
            100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
        }
    </style>
</head>
<body class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card card-custom p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold mb-0 text-dark">📦 รายละเอียดออเดอร์</h4>
                        <a href="home.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">ย้อนกลับ</a>
                    </div>

                    <!-- 🚩 แถบความคืบหน้า (Progress Bar) -->
                    <div class="mb-5 px-3">
                        <h6 class="fw-bold mb-4 text-muted small uppercase">ความคืบหน้าของออเดอร์</h6>
                        <div class="d-flex justify-content-between position-relative">
                            <div class="progress position-absolute top-50 start-0 translate-middle-y w-100" style="height: 2px; z-index: 1;">
                                <div class="progress-bar" style="width: <?= ($order['order_status'] / 4) * 100 ?>%"></div>
                            </div>
                            
                            <?php 
                            $is_waiting_verify = ($order['order_status'] == 1 && $order['pay_count'] > 0);
                            $st = (int)$order['order_status'];
                            
                            $steps = [
                                ['icon' => 'truck-pickup', 'label' => 'รอรับผ้า'],
                                ['icon' => ($is_waiting_verify ? 'clock' : 'wallet'), 'label' => ($is_waiting_verify ? 'รอตรวจสลิป' : 'ชำระเงิน')],
                                ['icon' => 'soap', 'label' => 'ซักรีด'],
                                ['icon' => 'truck', 'label' => 'ส่งคืน'],
                                ['icon' => 'check-double', 'label' => 'สำเร็จ']
                            ];

                            foreach($steps as $idx => $s):
                                $is_active = ($st >= $idx);
                                if ($idx == 1 && $is_waiting_verify) $is_active = true;
                            ?>
                            <div class="progress-step text-center <?= $is_active ? 'active' : '' ?>">
                                <div class="step-icon bg-light text-muted <?= ($idx == $st && $st != 4) || ($idx == 1 && $is_waiting_verify) ? 'ring-animation' : '' ?>">
                                    <i class="fa fa-<?= $s['icon'] ?>"></i>
                                </div>
                                <div class="step-label"><?= $s['label'] ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 🏷️ ป้ายสถานะปัจจุบัน -->
                    <div class="row mb-4 bg-light p-3 rounded-4 mx-1">
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">สถานะปัจจุบัน:</p>
                            <?php if ($is_waiting_verify): ?>
                                <span class="badge bg-info p-2 px-3 rounded-pill fw-bold"><i class="fa fa-clock me-1"></i> รอเจ้าของตรวจสอบการชำระเงิน</span>
                            <?php else: 
                                $status_text = [
                                    0 => 'รอร้านค้าไปรับผ้า', 
                                    1 => 'รอการชำระเงิน', 
                                    2 => 'ร้านกำลังดำเนินการซัก/รีด', 
                                    3 => 'ซักรีดเสร็จสิ้น (เตรียมส่งคืน)', 
                                    4 => 'จัดส่งผ้าคืนเรียบร้อย'
                                ];
                                $status_color = [0=>'bg-secondary', 1=>'bg-warning text-dark', 2=>'bg-info', 3=>'bg-primary', 4=>'bg-success'];
                                $curr = (int)$order['order_status'];
                            ?>
                                <span class="badge <?= $status_color[$curr] ?> p-2 px-3 rounded-pill fw-bold"><?= $status_text[$curr] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 text-md-end mt-2 mt-md-0">
                            <p class="mb-1 text-muted small">เลขออเดอร์:</p>
                            <h6 class="fw-bold mb-1">#<?= $order['order_id'] ?></h6>
                            <?php if($order['order_pickup_round'] > 0): ?>
                                <span class="badge bg-light text-primary border border-primary border-opacity-25 rounded-pill small">
                                    <i class="fa fa-sync-alt me-1"></i> รอบรับ-ส่งครั้งที่ <?= $order['order_pickup_round'] ?> / 4
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if($is_waiting_verify): ?>
                        <div class="alert alert-info border-0 rounded-4 mb-4 small fw-bold text-center">
                            <i class="fa fa-info-circle me-1"></i> ระบบได้รับสลิปแล้ว กำลังรอเจ้าของร้านยืนยันยอดเงินครับ
                        </div>
                    <?php endif; ?>

                    <!-- 📋 รายการบริการ -->
                    <h6 class="fw-bold mb-3 text-muted small uppercase">รายการบริการ</h6>
                    
                    <?php if(!empty($order['rq_note'])): ?>
                    <div class="mb-4 p-3 bg-light rounded-4 border-start border-primary border-4">
                        <div class="info-label mb-2 text-primary small fw-bold"><i class="fa fa-paper-plane me-1"></i> รายการที่ท่านแจ้งรับผ้า:</div>
                        <div class="small text-dark lh-base" style="white-space: pre-wrap;"><?= htmlspecialchars($order['rq_note']) ?></div>
                    </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th colspan="2">รายละเอียดจากร้านค้า</th>
                                    <th class="text-end">ยอดรวม</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $extra_cash = (float)($order['order_extra_cash'] ?? 0);
                                $shipping_fee = (float)($order['order_shipping_fee'] ?? 0);
                                $base_sum = (float)$order['order_sum'] - $extra_cash - $shipping_fee;
                                ?>
                                
                                <?php if(!empty($order['order_note'])): ?>
                                    <tr>
                                        <td colspan="2" class="py-3">
                                            <div class="fw-bold text-dark mb-1" style="white-space: pre-wrap;"><?= nl2br(htmlspecialchars($order['order_note'])) ?></div>
                                            <div class="smaller text-muted italic"><i class="fa fa-info-circle"></i> สรุปรายการโดยร้านค้า</div>
                                        </td>
                                        <td class="text-end fw-bold text-dark">฿<?= number_format($base_sum, 2) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if($order['order_items_count'] > 0): ?>
                                    <tr class="table-primary table-opacity-10">
                                        <td colspan="2" class="py-2">
                                            <div class="fw-bold text-primary small"><i class="fa fa-cut me-1"></i> หักโควตาผ้าสมาชิก</div>
                                        </td>
                                        <td class="text-end fw-bold text-primary"><?= $order['order_items_count'] ?> ชิ้น</td>
                                    </tr>
                                <?php endif; ?>

                                <?php if($shipping_fee > 0): ?>
                                    <tr>
                                        <td colspan="2" class="py-2">
                                            <div class="text-muted small"><i class="fa fa-truck me-1"></i> ค่าบริการจัดส่ง</div>
                                        </td>
                                        <td class="text-end fw-bold text-dark small">฿<?= number_format($shipping_fee, 2) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if($extra_cash > 0): ?>
                                    <tr class="table-danger table-opacity-10" style="background-color: rgba(239, 68, 68, 0.05);">
                                        <td colspan="2" class="py-2">
                                            <div class="fw-bold text-danger small"><i class="fa fa-plus-circle me-1"></i> บริการเพิ่มเติม: <?= htmlspecialchars($order['order_extra_note'] ?: 'อื่นๆ') ?></div>
                                        </td>
                                        <td class="text-end fw-bold text-danger">฿<?= number_format($extra_cash, 2) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <tr class="border-top border-dark border-opacity-10">
                                    <td colspan="2" class="text-end pt-4 fw-bold">ยอดที่ต้องชำระทั้งสิ้น</td>
                                    <td class="text-end pt-4 fw-bold text-primary h4">฿<?= number_format($order['order_sum'], 2) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <?php if($order['order_status'] == 1 && $order['pay_count'] == 0): ?>
                        <div class="mt-4">
                            <a href="payment.php?id=<?= $order_id ?>" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold py-3 shadow">
                                <i class="fa fa-wallet me-2"></i> ไปหน้าชำระเงิน ฿<?= number_format($order['order_sum'], 2) ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- 📞 ส่วนติดต่อร้านค้า -->
                    <div class="mt-5 pt-4 border-top">
                        <div class="p-3 bg-light rounded-4 border border-warning border-opacity-25">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fa fa-headset text-warning me-2"></i>
                                <h6 class="fw-bold mb-0 small text-dark">มีข้อสงสัยหรือพบปัญหา?</h6>
                            </div>
                            <p class="smaller text-muted mb-3">หากยอดเงินไม่ถูกต้อง หรือต้องการสอบถามเพิ่มเติม โปรดติดต่อร้านค้าได้ที่:</p>
                            
                            <?php 
                            $shop = $conn->query("SELECT shop_tel, shop_mail FROM shop LIMIT 1")->fetch_assoc();
                            ?>
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="d-flex align-items-center small">
                                        <i class="fa fa-phone text-primary me-2"></i>
                                        <span class="fw-bold"><?= htmlspecialchars($shop['shop_tel'] ?? '-') ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-center small">
                                        <i class="fa fa-envelope text-primary me-2"></i>
                                        <span class="fw-bold"><?= htmlspecialchars($shop['shop_mail'] ?? '-') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
