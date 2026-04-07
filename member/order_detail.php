<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit;
}

$order_id = $conn->real_escape_string($_GET['id']);
$user = $_SESSION['user'];

// ดึงข้อมูลออเดอร์พร้อมชื่อลูกค้า
$sql_order = "SELECT o.*, r.rq_note, m.m_name, m.m_lastname, m.m_tel as m_phone,
              (SELECT COUNT(*) FROM payment p WHERE p.order_id = o.order_id) as pay_count 
              FROM orders o 
              LEFT JOIN request r ON o.rq_id = r.rq_id
              JOIN member m ON o.m_id = m.m_id
              WHERE o.order_id = '$order_id' AND o.m_id = '{$user['m_id']}'";
$res_order = $conn->query($sql_order);
$order = $res_order->fetch_assoc();

if (!$order) {
    die("ไม่พบข้อมูลคำสั่งซื้อ");
}

// ดึงข้อมูลร้านค้า
$shop = $conn->query("SELECT * FROM shop LIMIT 1")->fetch_assoc();

// ดึงรายการไอเทม
$sql_items = "SELECT i.*, s.service_name, s.service_price FROM item i JOIN service s ON i.service_id = s.service_id WHERE i.order_id = '$order_id'";
$res_items = $conn->query($sql_items);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ใบเสร็จรับเงิน - <?= $order['order_id'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&display=swap');
        :root { --primary: #3B82F6; --bg: #F1F5F9; }
        body { background: var(--bg); font-family: 'Chakra Petch', sans-serif; }
        .card-custom { border: none; border-radius: 24px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); background: white; overflow: hidden; }
        .receipt-card { position: relative; border-radius: 20px; background: white; border: 2px dashed #CBD5E1; }
        .receipt-card::before, .receipt-card::after {
            content: ''; position: absolute; left: 50%; width: 40px; height: 40px; background: var(--bg); border-radius: 50%; transform: translateX(-50%); z-index: 10;
        }
        .receipt-card::before { top: -20px; }
        .receipt-card::after { bottom: -20px; }
        
        .progress-step { flex: 1; text-align: center; position: relative; z-index: 2; }
        .step-icon { width: 45px; height: 45px; line-height: 45px; border-radius: 50%; margin: 0 auto 10px; display: block; text-align: center; transition: 0.3s; background: #F1F5F9; color: #94A3B8; font-size: 1.1rem; }
        .step-label { font-size: 0.7rem; font-weight: 700; color: #64748B; }
        .active .step-icon { background: var(--primary); color: white; box-shadow: 0 0 0 6px rgba(59, 130, 246, 0.15); }
        .active .step-label { color: var(--primary); }
        .ring-animation { animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
            70% { box-shadow: 0 0 0 12px rgba(59, 130, 246, 0); }
            100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
        }
        .info-label { font-size: 0.7rem; font-weight: 700; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.05em; }
        .item-row { border-bottom: 1px dashed #F1F5F9; padding: 12px 0; }
        .item-row:last-child { border-bottom: none; }
    </style>
</head>
<body class="py-4">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <!-- 📦 หัวข้อและปุ่มกลับ -->
                <div class="d-flex justify-content-between align-items-center mb-4 px-2">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa fa-receipt me-2 text-primary"></i>รายละเอียดออเดอร์</h5>
                    <a href="home.php" class="btn btn-white btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 bg-white">
                        <i class="fa fa-arrow-left me-1"></i> ย้อนกลับ
                    </a>
                </div>

                <!-- 🚩 แถบความคืบหน้า (Progress Bar) -->
                <div class="card card-custom p-4 mb-4">
                    <div class="d-flex justify-content-between position-relative">
                        <div class="progress position-absolute top-50 start-0 translate-middle-y w-100" style="height: 3px; z-index: 1; margin-top: -10px;">
                            <div class="progress-bar bg-primary" style="width: <?= ($order['order_status'] / 4) * 100 ?>%"></div>
                        </div>
                        
                        <?php 
                        $is_waiting_verify = ($order['order_status'] == 1 && $order['pay_count'] > 0);
                        $st = (int)$order['order_status'];
                        
                        $steps = [
                            ['icon' => 'truck-pickup', 'label' => 'รอรับผ้า'],
                            ['icon' => ($is_waiting_verify ? 'clock' : 'wallet'), 'label' => ($is_waiting_verify ? 'รอตรวจสลิป' : 'จ่ายเงิน')],
                            ['icon' => 'soap', 'label' => 'ซักรีด'],
                            ['icon' => 'truck', 'label' => 'ส่งคืน'],
                            ['icon' => 'check-double', 'label' => 'สำเร็จ']
                        ];

                        foreach($steps as $idx => $s):
                            $is_active = ($st >= $idx);
                            if ($idx == 1 && $is_waiting_verify) $is_active = true;
                        ?>
                        <div class="progress-step <?= $is_active ? 'active' : '' ?>">
                            <div class="step-icon <?= ($idx == $st && $st != 4) || ($idx == 1 && $is_waiting_verify) ? 'ring-animation' : '' ?>">
                                <i class="fa fa-<?= $s['icon'] ?>"></i>
                            </div>
                            <div class="step-label d-none d-md-block"><?= $s['label'] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 🧾 ใบเสร็จ -->
                <div class="card card-custom p-0 receipt-card shadow-lg mb-4">
                    <div class="p-4 pt-5">
                        <div class="text-center mb-4">
                            <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($shop['shop_name1']) ?></h4>
                            <div class="small text-muted mb-3"><?= htmlspecialchars($shop['shop_address']) ?></div>
                            <div class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-4 py-2 fw-bold">ใบเสร็จรับเงิน / Receipt</div>
                        </div>

                        <hr class="opacity-10 mb-4">

                        <div class="row g-3 mb-4">
                            <div class="col-7">
                                <div class="info-label">ชื่อลูกค้า / Customer</div>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($order['m_name'] . ' ' . $order['m_lastname']) ?></div>
                                <div class="smaller text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($order['m_phone']) ?></div>
                            </div>
                            <div class="col-5 text-end">
                                <div class="info-label">เลขออเดอร์</div>
                                <div class="fw-bold text-dark">#<?= $order['order_id'] ?></div>
                                <div class="smaller text-muted" style="font-size: 0.7rem;"><?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></div>
                            </div>
                        </div>

                        <!-- Timeline & Notes -->
                        <?php if(!empty($order['order_est_pickup']) || !empty($order['order_owner_note'])): ?>
                        <div class="mb-4 p-3 bg-warning bg-opacity-10 rounded-4 border-start border-warning border-4">
                            <h6 class="info-label text-warning mb-2"><i class="fa fa-clock me-1"></i> นัดหมายจากร้านค้า</h6>
                            <?php if(!empty($order['order_est_pickup'])): ?>
                                <div class="small text-dark mb-1">
                                    <b>เวลามารับผ้า:</b> <?= htmlspecialchars($order['order_est_pickup']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if(!empty($order['order_owner_note'])): ?>
                                <div class="small text-dark">
                                    <b>แจ้งเตือน/เวลาส่ง:</b> <?= htmlspecialchars($order['order_owner_note']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- รายการสินค้า -->
                        <div class="bg-light bg-opacity-30 rounded-4 p-3 mb-4" style="border: 1px solid #F1F5F9;">
                            <div class="d-flex justify-content-between info-label mb-2 border-bottom pb-2">
                                <span>รายการบริการ / DESCRIPTION</span>
                                <span>จำนวนเงิน</span>
                            </div>
                            
                            <?php 
                            $extra_cash = (float)($order['order_extra_cash'] ?? 0);
                            $shipping_fee = (float)($order['order_shipping_fee'] ?? 0);
                            $base_sum = (float)$order['order_sum'] - $extra_cash - $shipping_fee;
                            ?>

                            <!-- งานซักหลัก -->
                            <div class="item-row d-flex justify-content-between align-items-start">
                                <div class="pe-3">
                                    <div class="fw-bold small text-dark">
                                        <?php if(strpos($order['order_id'], 'PKG') === 0): ?>
                                            แพ็กเกจรายเดือน (Monthly Package)
                                        <?php else: ?>
                                            บริการซักรีดหลัก (Laundry Service)
                                        <?php endif; ?>
                                    </div>
                                    <div class="smaller text-muted mt-1 lh-sm" style="font-size: 0.85rem; white-space: pre-wrap;"><?php 
                                        if(!empty($order['order_note'])) {
                                            $note_lines = explode("\n", trim($order['order_note']));
                                            if (strpos($order['order_id'], 'PKG') === 0 && count($note_lines) > 1) {
                                                // แสดงชื่อแพ็กเกจเป็นตัวหนา และรายการผ้าบรรทัดต่อมา
                                                echo '<b class="text-primary">' . htmlspecialchars($note_lines[0]) . '</b><br>';
                                                unset($note_lines[0]);
                                                echo nl2br(htmlspecialchars(implode("\n", $note_lines)));
                                            } else {
                                                echo nl2br(htmlspecialchars($order['order_note']));
                                            }
                                        } else {
                                            echo 'ซักรีดผ้าทั่วไป';
                                        }
                                    ?></div>
                                </div>
                                <div class="fw-bold text-dark">฿<?= number_format($base_sum, 2) ?></div>
                            </div>

                            <!-- หักโควตา (ถ้ามี) -->
                            <?php if($order['order_items_count'] > 0): ?>
                            <div class="item-row d-flex justify-content-between text-primary">
                                <div class="fw-bold small">
                                    <i class="fa <?= ($order['order_pickup_round'] > 0) ? 'fa-cut' : 'fa-shirt' ?> me-1"></i> 
                                    <?= ($order['order_pickup_round'] > 0) ? 'หักโควตาผ้าสมาชิก' : 'จำนวนผ้าที่ซักรวม' ?>
                                </div>
                                <div class="fw-bold"><?= $order['order_items_count'] ?> ชิ้น</div>
                            </div>
                            <?php endif; ?>

                            <!-- บริการเสริม (ถ้ามี) -->
                            <?php if($extra_cash > 0): ?>
                            <div class="item-row d-flex justify-content-between align-items-start text-danger">
                                <div>
                                    <div class="fw-bold small">บริการเพิ่มเติม / Extra Service</div>
                                    <div class="smaller mt-1" style="font-size: 0.75rem;"><?= htmlspecialchars($order['order_extra_note'] ?: 'รายการบวกเพิ่ม') ?></div>
                                </div>
                                <div class="fw-bold">฿<?= number_format($extra_cash, 2) ?></div>
                            </div>
                            <?php endif; ?>

                            <!-- ค่าส่ง (ถ้ามี) -->
                            <?php if($shipping_fee > 0): ?>
                            <div class="item-row d-flex justify-content-between text-muted">
                                <div class="small">ค่าจัดส่ง / Delivery Fee</div>
                                <div class="fw-bold">฿<?= number_format($shipping_fee, 2) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- ยอดรวม -->
                        <div class="d-flex justify-content-between align-items-center mb-4 px-2 py-3 bg-primary bg-opacity-10 rounded-4">
                            <div class="fw-bold text-primary">ยอดที่ต้องชำระ / TOTAL</div>
                            <div class="h3 fw-bold text-primary mb-0">฿<?= number_format($order['order_sum'], 2) ?></div>
                        </div>

                        <div class="text-center mb-2">
                            <div class="fw-bold text-muted small">ขอบคุณที่ใช้บริการ / THANK YOU</div>
                            <div class="smaller text-muted" style="font-size: 0.65rem;">กรุณาเก็บใบเสร็จไว้เป็นหลักฐาน</div>
                        </div>

                        <?php if($order['order_status'] == 1 && $order['pay_count'] == 0): ?>
                            <div class="mt-4 pt-3 border-top">
                                <?php if(!empty($order['order_note'])): ?>
                                    <a href="payment.php?id=<?= $order_id ?>" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold py-3 shadow border-0">
                                        <i class="fa fa-wallet me-2"></i> ชำระเงินตอนนี้
                                    </a>
                                <?php else: ?>
                                    <div class="text-center p-3 bg-light rounded-4">
                                        <i class="fa fa-clock-rotate-left fa-2x text-muted mb-2"></i>
                                        <p class="mb-0 small text-muted fw-bold">รอร้านสรุปรายการและราคา...</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 📞 ติดต่อร้านค้า -->
                <div class="card card-custom p-4 border-0 mb-5">
                    <h6 class="fw-bold mb-3 text-dark small"><i class="fa fa-headset me-2 text-primary"></i>ติดต่อสอบถามเพิ่มเติม</h6>
                    <div class="d-flex justify-content-between">
                        <a href="tel:<?= $shop['shop_tel'] ?>" class="text-decoration-none d-flex align-items-center gap-2">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2" style="width: 35px; height: 35px; text-align: center; line-height: 20px;">
                                <i class="fa fa-phone small"></i>
                            </div>
                            <span class="small fw-bold text-dark"><?= $shop['shop_tel'] ?></span>
                        </a>
                        <a href="mailto:<?= $shop['shop_mail'] ?>" class="text-decoration-none d-flex align-items-center gap-2">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2" style="width: 35px; height: 35px; text-align: center; line-height: 20px;">
                                <i class="fa fa-envelope small"></i>
                            </div>
                            <span class="small fw-bold text-dark">อีเมล / แชท</span>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
