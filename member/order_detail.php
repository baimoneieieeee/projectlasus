<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit;
}

$order_id = $conn->real_escape_string($_GET['id']);
$user = $_SESSION['user'];

$sql_order = "SELECT o.*, r.rq_note, m.m_name, m.m_lastname, m.m_tel as m_phone,
              (SELECT COUNT(*) FROM payment p WHERE p.order_id = o.order_id) as pay_count 
              FROM orders o 
              LEFT JOIN request r ON o.rq_id = r.rq_id
              JOIN member m ON o.m_id = m.m_id
              WHERE o.order_id = '$order_id' AND o.m_id = '{$user['m_id']}'";
$res_order = $conn->query($sql_order);
$order = $res_order->fetch_assoc();

if (!$order) die("ไม่พบข้อมูลคำสั่งซื้อ");
$shop = $conn->query("SELECT * FROM shop LIMIT 1")->fetch_assoc();
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ใบเสร็จ - <?= $order['order_id'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&display=swap');
        :root { --primary: #3B82F6; --bg: #F1F5F9; }
        body { background: var(--bg); font-family: 'Chakra Petch', sans-serif; font-size: 0.85rem; }
        .main-container { max-width: 500px; margin: 0 auto; padding: 15px; }
        .card-custom { border: none; border-radius: 20px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); background: white; }
        
        .progress-step { flex: 1; text-align: center; position: relative; z-index: 2; }
        .step-icon { width: 35px; height: 35px; line-height: 35px; border-radius: 50%; margin: 0 auto 5px; display: block; background: #E2E8F0; color: #94A3B8; font-size: 0.9rem; transition: 0.3s; }
        .step-label { font-size: 0.65rem; font-weight: 700; color: #64748B; }
        .active .step-icon { background: var(--primary); color: white; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15); }
        .active .step-label { color: var(--primary); }
        .completed .step-icon { background: #10B981; color: white; }

        .receipt-card { position: relative; border-radius: 20px; background: white; border: 2px dashed #CBD5E1; overflow: visible; }
        .receipt-card::before, .receipt-card::after {
            content: ''; position: absolute; left: 50%; width: 30px; height: 30px; background: var(--bg); border-radius: 50%; transform: translateX(-50%); z-index: 10;
        }
        .receipt-card::before { top: -15px; }
        .receipt-card::after { bottom: -15px; }
        
        .info-label { font-size: 0.65rem; font-weight: 700; color: #94A3B8; text-transform: uppercase; }
        .item-row { border-bottom: 1px dashed #F1F5F9; padding: 10px 0; }
    </style>
</head>
<body class="py-3">
    <div class="main-container">
        <!-- header: ย้อนกลับ + ข้อมูลติดต่อ -->
        <div class="d-flex justify-content-between align-items-start mb-3 px-2">
            <a href="home.php" class="text-decoration-none text-muted fw-bold small mt-1"><i class="fa fa-arrow-left me-1"></i> ย้อนกลับ</a>
            <div class="text-end">
                <div class="d-flex align-items-center justify-content-end gap-2 mb-1">
                    <a href="tel:<?= $shop['shop_tel'] ?>" class="text-primary text-decoration-none small fw-bold">
                        <i class="fa fa-phone-alt me-1"></i> <?= $shop['shop_tel'] ?>
                    </a>
                    <span class="text-muted opacity-50">|</span>
                    <a href="#" class="text-success text-decoration-none small fw-bold">
                        <i class="fa fa-comment-dots me-1"></i> แชทกับร้าน
                    </a>
                </div>
                <span class="badge bg-dark rounded-pill px-3 py-1" style="font-size: 0.7rem;">Order: #<?= $order['order_id'] ?></span>
            </div>
        </div>

        <!-- 🚩 Progress Section -->
        <div class="card card-custom p-3 mb-3">
            <div class="d-flex justify-content-between">
                <?php 
                $st = (int)$order['order_status'];
                $steps = [
                    ['icon' => 'truck-pickup', 'label' => 'รับผ้า'],
                    ['icon' => 'wallet', 'label' => 'จ่ายเงิน'],
                    ['icon' => 'soap', 'label' => 'ซักรีด'],
                    ['icon' => 'truck', 'label' => 'ส่งคืน'],
                    ['icon' => 'check-double', 'label' => 'สำเร็จ']
                ];
                foreach($steps as $idx => $s):
                    $class = ($st == $idx) ? 'active' : (($st > $idx) ? 'completed' : '');
                ?>
                <div class="progress-step <?= $class ?>">
                    <div class="step-icon"><i class="fa fa-<?= $s['icon'] ?>"></i></div>
                    <div class="step-label"><?= $s['label'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 🧾 Receipt -->
        <div class="card card-custom receipt-card mb-3">
            <div class="p-4">
                <div class="text-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($shop['shop_name1']) ?></h5>
                    <div style="font-size: 0.65rem;" class="text-muted"><?= htmlspecialchars($shop['shop_address']) ?></div>
                    <div class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-bold mt-2" style="font-size: 0.6rem;">ใบแจ้งค่าบริการ</div>
                </div>

                <div class="row g-2 mb-3 border-top border-bottom py-3">
                    <div class="col-6 border-end">
                        <div class="info-label">ลูกค้า</div>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($order['m_name']) ?></div>
                    </div>
                    <div class="col-6 ps-3">
                        <div class="info-label">วันที่</div>
                        <div class="fw-bold text-dark"><?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></div>
                    </div>
                </div>

                <div class="bg-light bg-opacity-50 rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between info-label mb-2 border-bottom pb-1">
                        <span>รายการ</span>
                        <span>จำนวนเงิน</span>
                    </div>
                    <div class="item-row d-flex justify-content-between align-items-start">
                        <div class="pe-3">
                            <div class="fw-bold text-dark" style="white-space: pre-wrap; line-height: 1.4; font-size: 0.8rem;"><?= !empty($order['order_note']) ? htmlspecialchars($order['order_note']) : 'บริการซักอบรีดทั่วไป' ?></div>
                            <?php if($order['order_items_count'] > 0): ?>
                                <div class="text-primary fw-bold mt-1" style="font-size: 0.7rem;"><i class="fa fa-check-circle me-1"></i> หักสิทธิ์รายเดือน <?= $order['order_items_count'] ?> ชิ้น</div>
                            <?php endif; ?>
                        </div>
                        <div class="fw-bold text-dark">฿<?= number_format($order['order_sum'] - $order['order_shipping_fee'], 2) ?></div>
                    </div>
                    <?php if($order['order_shipping_fee'] > 0): ?>
                        <div class="d-flex justify-content-between text-muted mt-2 small fw-bold">
                            <span>ค่าจัดส่ง</span>
                            <span>฿<?= number_format($order['order_shipping_fee'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-primary bg-opacity-10 rounded-3 border border-primary border-opacity-20">
                    <div class="fw-bold text-primary">ยอดรวมสุทธิ</div>
                    <div class="h3 fw-bold text-primary mb-0">฿<?= number_format($order['order_sum'], 2) ?></div>
                </div>

                <?php if(!empty($order['order_owner_note'])): ?>
                    <div class="p-3 bg-warning bg-opacity-10 rounded-3 border-start border-warning border-3 mb-3">
                        <div class="info-label text-warning mb-1">นัดหมายส่งคืน</div>
                        <div class="fw-bold text-dark smaller"><?= htmlspecialchars($order['order_owner_note']) ?></div>
                    </div>
                <?php endif; ?>

                <div class="text-center text-muted smaller mb-1">ขอบคุณที่ใช้บริการ</div>

                <?php if($order['order_status'] == 1 && $order['pay_count'] == 0): ?>
                    <a href="payment.php?id=<?= $order_id ?>" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold py-2 mt-3 shadow-sm" style="font-size: 1rem;">
                        <i class="fa fa-wallet me-2"></i> ชำระเงินผ่านแอป
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
