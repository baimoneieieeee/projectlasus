<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
    die("
        <div style='text-align:center; padding:50px; font-family:sans-serif;'>
            <h2 style='color:#ef4444;'>❌ ไม่พบรหัสออเดอร์</h2>
            <p>กรุณากลับไปเลือกออเดอร์ใหม่อีกครั้งจากหน้ารายการสั่งซื้อครับ</p>
            <a href='manage_orders.php' style='display:inline-block; padding:10px 25px; background:#2563eb; color:white; text-decoration:none; border-radius:50px; font-weight:bold;'>กลับไปหน้ารายการสั่งซื้อ</a>
        </div>
    ");
}

$order_id = $conn->real_escape_string(trim($_GET['id']));

// ดึงข้อมูลออเดอร์พร้อมข้อมูลสมาชิก และหมายเหตุจากคำขอแรก
$sql = "SELECT o.*, m.m_name, m.m_lastname, m.m_tel, m.m_latitude, m.m_longitude, m.m_address, r.rq_note
        FROM orders o 
        JOIN member m ON o.m_id = m.m_id 
        LEFT JOIN request r ON o.rq_id = r.rq_id
        WHERE o.order_id = '$order_id'";
$res = $conn->query($sql);
$order = $res->fetch_assoc();

if (!$order) {
    die("ไม่พบข้อมูลออเดอร์");
}

// --- 🧺 ส่วนการดึงรายการผ้าจาก Note เพื่อนำมาขึ้นเป็นค่าเริ่มต้น ---
$prefilled_items = [];
if (!empty($order['rq_note'])) {
    // ใช้ Regex ดึงข้อมูลรูปแบบ "- ชื่อรายการ (X ชิ้น)"
    preg_match_all('/-\s+(.*?)\s+\((\d+)\s+ชิ้น\)/', $order['rq_note'], $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $idx => $name) {
            $prefilled_items[] = ['name' => trim($name), 'qty' => (int)$matches[2][$idx]];
        }
    }
}

// เช็คว่าเป็นสมาชิกรายเดือนหรือไม่
$json_file = __DIR__ . '/../config/monthly_data.json';
$monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
$is_monthly = isset($monthly_data[$order['m_id']]);
$member_quota = $is_monthly ? $monthly_data[$order['m_id']]['quota'] : 0;
$is_pkg_order = (strpos(trim($order['order_id']), 'PKG') === 0);

// --- 🚩 ตรวจสอบว่าใน Note มีการระบุ "หักโควตา" หรือไม่ ---
$rq_has_quota = (strpos($order['rq_note'], 'หักโควตา') !== false);
// ออเดอร์รายครั้งจริงๆ คือ (ไม่ใช่ PKG) และ (ไม่ใช่สมาชิกรายเดือน หรือ สมาชิกรายเดือนที่แจ้งมาแบบไม่หักโควตา)
$is_actually_one_time = (!$is_pkg_order) && (!$is_monthly || !$rq_has_quota);

// ดึงข้อมูลการชำระเงิน (ถ้ามี)
$sql_pay = "SELECT * FROM payment WHERE order_id = '$order_id' ORDER BY payment_date DESC LIMIT 1";
$res_pay = $conn->query($sql_pay);
$payment = $res_pay->fetch_assoc();

// คำนวณระยะทาง
$shop_sql = "SELECT shop_latitude, shop_longitude FROM shop LIMIT 1";
$shop = $conn->query($shop_sql)->fetch_assoc();
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการออเดอร์ - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&display=swap');
        :root { --primary: #2563eb; --bg: #f8fafc; }
        body { background: var(--bg); font-family: 'Chakra Petch', sans-serif; }
        .main-content { margin-left: 280px; padding: 40px; }
        .card-custom { border: none; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: white; }
        .status-badge { padding: 8px 20px; border-radius: 999px; font-weight: 700; font-size: 0.85rem; }
        .info-label { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- 🔔 แจ้งเตือนเมื่อบันทึกสำเร็จ -->
            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center">
                    <i class="fa fa-check-circle fa-2x me-3"></i>
                    <div>
                        <div class="fw-bold">บันทึกสำเร็จ!</div>
                        <div class="small">ระบบได้บันทึกข้อมูลรายการผ้าและยอดเงินเรียบร้อยแล้ว</div>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">📦 จัดการออเดอร์ #<?= $order_id ?></h2>
                    <?php 
                    $s_text = [0=>'รอร้านไปรับผ้า', 1=>'รอการชำระเงิน', 2=>'ร้านกำลังดำเนินการซัก/รีด', 3=>'ซักเสร็จ/รอส่งคืน', 4=>'สำเร็จ/ส่งคืนแล้ว'];
                    $s_color = [0=>'bg-dark', 1=>'bg-warning text-dark', 2=>'bg-info text-white', 3=>'bg-primary text-white', 4=>'bg-success text-white'];
                    $st = (int)$order['order_status'];
                    ?>
                    <div class="d-flex align-items-center gap-2">
                        <span class="status-badge <?= $s_color[$st] ?>"><?= $s_text[$st] ?></span>
                        <?php if($is_monthly): 
                            $pickups = $monthly_data[$order['m_id']]['pickups_used'] ?? 0;
                        ?>
                            <span class="badge bg-light text-primary border border-primary border-opacity-25 rounded-pill px-3 py-2 fw-bold">
                                <i class="fa fa-sync-alt me-1"></i> รอบรับ-ส่งสะสม: <?= $pickups ?> / 4 ครั้ง
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="manage_orders.php" class="btn btn-outline-secondary rounded-pill px-4 shadow-sm fw-bold">
                    <i class="fa fa-arrow-left me-2"></i> กลับหน้ารวม
                </a>
            </div>

            <div class="row g-4">
                <!-- ฝั่งซ้าย: ข้อมูลลูกค้าและการเงิน -->
                <div class="col-lg-4">
                    <!-- 1. ข้อมูลลูกค้า -->
                    <div class="card card-custom p-4 mb-4">
                        <h6 class="fw-bold mb-3 text-dark"><i class="fa fa-user me-2 text-primary"></i> ข้อมูลลูกค้า</h6>
                        <div class="d-flex align-items-center mb-3">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($order['m_name']) ?>&background=2563eb&color=fff" class="rounded-circle me-3" width="50">
                            <div>
                                <div class="fw-bold"><?= $order['m_name'] . ' ' . $order['m_lastname'] ?></div>
                                <div class="small text-muted"><?= $order['m_tel'] ?></div>
                            </div>
                        </div>
                        <?php if($is_monthly): ?>
                            <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3 text-center mb-3">
                                <i class="fa fa-gem me-1"></i> <small class="fw-bold">สมาชิกรรายเดือน (โควตาเหลือ <?= $member_quota ?>)</small>
                            </div>
                            <div class="mb-3">
                                <a href="manage_monthly.php" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm">
                                    <i class="fa fa-cut me-2"></i> ไปหน้าหักผ้าสมาชิก
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="border-top pt-3 mt-2">
                            <div class="info-label">สถานที่รับ-ส่ง</div>
                            <div class="small text-dark mb-2"><?= nl2br(htmlspecialchars($order['order_address'] ?: $order['m_address'])) ?></div>
                            <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $order['m_latitude'] ?>,<?= $order['m_longitude'] ?>" target="_blank" class="btn btn-sm btn-light w-100 rounded-pill">
                                <i class="fa fa-map-marker-alt me-1 text-danger"></i> นำทางด้วย Google Maps
                            </a>
                        </div>
                    </div>

                    <!-- 2. สถานะการเงิน -->
                    <div class="card card-custom p-4 shadow">
                        <h6 class="fw-bold mb-4 text-dark"><i class="fa fa-wallet me-2 text-success"></i> สถานะการเงิน</h6>
                        
                        <?php if($payment): ?>
                            <div class="mb-4">
                                <div class="info-label">สลิปการชำระเงิน (<?= date('d/m/y H:i', strtotime($payment['payment_date'])) ?>)</div>
                                <div class="mt-2 rounded-4 overflow-hidden border">
                                    <img src="data:image/jpeg;base64,<?= base64_encode($payment['payment_image']) ?>" class="img-fluid w-100" style="max-height: 400px; object-fit: contain; cursor: zoom-in;" onclick="window.open(this.src)">
                                </div>
                                <div class="text-center mt-2">
                                    <div class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 mb-1">ชำระเงินเรียบร้อยแล้ว</div><br>
                                    <span class="small text-muted">ยอดที่ชำระ: <b>฿<?= number_format($payment['payment_amount'], 2) ?></b></span>
                                </div>
                            </div>

                            <?php if($order['order_status'] == 1): ?>
                                <a href="confirm_payment.php?id=<?= $order_id ?>" class="btn btn-success btn-lg w-100 rounded-pill fw-bold py-3 shadow border-0" onclick="return confirm('ยืนยันว่าได้รับเงินถูกต้อง?')">
                                    <i class="fa fa-check-circle me-2"></i> ยืนยันยอดถูกต้อง
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <?php if($order['order_sum'] <= 0): ?>
                                    <i class="fa fa-check-circle fa-3x text-success mb-3"></i>
                                    <p class="text-success fw-bold">ฟรี (ไม่มีค่าใช้จ่าย)</p>
                                <?php else: ?>
                                    <i class="fa fa-clock fa-3x text-light mb-3"></i>
                                    <p class="text-muted small">รอลูกค้าแจ้งโอน <b>฿<?= number_format($order['order_sum'], 2) ?></b></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ฝั่งขวา: รายการผ้าและขั้นตอน -->
                <div class="col-lg-8">
                    <!-- 1. บันทึกรายการผ้า -->
                    <div class="card card-custom p-4 mb-4 shadow-sm">
                        <h5 class="fw-bold mb-4 text-dark"><i class="fa fa-clipboard-list me-2 text-primary"></i> บันทึกข้อมูลงานซัก</h5>

                        <!-- 🔍 ข้อมูลอ้างอิงจากลูกค้า -->
                        <?php if(!empty($order['rq_note'])): ?>
                        <div class="alert bg-primary bg-opacity-10 border-0 rounded-4 p-3 mb-4">
                            <div class="fw-bold mb-2 text-primary small"><i class="fa fa-info-circle me-1"></i> ข้อมูลที่ลูกค้าแจ้งผ่านแอป:</div>
                            <div class="bg-white p-3 rounded-3 shadow-sm small text-dark border-start border-primary border-4" style="white-space: pre-wrap;"><?= htmlspecialchars($order['rq_note']) ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if($order['order_status'] <= 1): // 🚩 โชว์ให้กรอกเฉพาะตอนรอรับหรือรอเงิน ?>
                        
                        <!-- 📝 แสดงสิ่งที่เคยบันทึกไว้แล้ว (ถ้ามี) -->
                        <?php if(!empty($order['order_note']) || !empty($order['order_extra_note']) || $order['order_items_count'] > 0): ?>
                        <div class="mb-4 p-4 bg-white rounded-4 border-start border-primary border-5 shadow-sm border border-light">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fa fa-clipboard-check me-2 text-primary"></i> ข้อมูลที่บันทึกไว้ปัจจุบัน</h6>
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#editForm">
                                    <i class="fa fa-edit me-1"></i> แก้ไขข้อมูลงานซัก
                                </button>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-8 text-start">
                                    <div class="small text-muted mb-1 uppercase fw-bold" style="font-size: 0.65rem;">สรุปรายการที่บันทึก:</div>
                                    <div class="fw-bold text-dark mb-2" style="white-space: pre-wrap;"><?= nl2br(htmlspecialchars($order['order_note'])) ?></div>
                                    <?php if($order['order_items_count'] > 0): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 fw-bold border border-primary border-opacity-25">หักโควตา: <?= $order['order_items_count'] ?> ชิ้น</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 text-md-end border-start border-light ps-4">
                                    <div class="small text-muted mb-1 uppercase fw-bold" style="font-size: 0.65rem;">ยอดรวมที่แจ้งลูกค้า:</div>
                                    <div class="h3 fw-bold text-primary mb-0">฿<?= number_format($order['order_sum'], 2) ?></div>
                                    
                                    <!-- 🚚 แสดงค่าจัดส่งให้ชัดเจน -->
                                    <?php if($order['order_shipping_fee'] > 0): ?>
                                        <div class="smaller text-muted fw-bold mt-1">+ ค่าจัดส่ง ฿<?= number_format($order['order_shipping_fee'], 2) ?></div>
                                    <?php endif; ?>

                                    <?php if($order['order_extra_cash'] > 0): ?>
                                        <div class="smaller text-danger fw-bold mt-1">+ รวมบวกเพิ่ม ฿<?= number_format($order['order_extra_cash'], 2) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ฟอร์มบันทึก (จะถูกพับไว้ถ้ามีข้อมูลแล้ว) -->
                        <?php 
                        // ตรวจสอบว่าเคยมีการบันทึกข้อมูลไปหรือยัง
                        $has_data = (!empty($order['order_note']) || $order['order_items_count'] > 0 || $order['order_extra_cash'] > 0);
                        ?>
                        <div class="collapse <?= (!$has_data) ? 'show' : '' ?> mb-4" id="editForm">
                            <form method="post" action="deduct_monthly.php" class="p-4 rounded-4 border bg-light shadow-sm">
                                <input type="hidden" name="order_id" value="<?= $order_id ?>">
                                <input type="hidden" name="m_id" value="<?= $order['m_id'] ?>">
                                <input type="hidden" name="manual_mode" value="1">

                                <!-- 🚚 ข้อมูลค่าจัดส่ง (แสดงให้ดูเฉยๆ ปรับเปลี่ยนไม่ได้เพราะคำนวณจากระยะทาง) -->
                                <?php if($order['order_shipping_fee'] > 0): ?>
                                <div class="alert bg-white border-0 rounded-4 p-3 mb-4 d-flex justify-content-between align-items-center shadow-sm">
                                    <div class="small fw-bold text-muted"><i class="fa fa-truck me-2 text-primary"></i> ค่าจัดส่ง (คำนวณจากระยะทาง):</div>
                                    <div class="fw-bold text-dark">฿<?= number_format($order['order_shipping_fee'], 2) ?></div>
                                </div>
                                <?php endif; ?>

                                <div class="row g-4">
                                    <div class="col-md-12">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label fw-bold small text-primary mb-0"><i class="fa fa-list me-1"></i> รายการผ้าที่ได้รับมาจริง</label>
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill border-0" onclick="addItemRow()">
                                                <i class="fa fa-plus-circle me-1"></i> เพิ่มรายการ
                                            </button>
                                        </div>
                                        <div id="item_list_container" class="bg-white p-3 rounded-4 border shadow-sm mb-3">
                                            <?php 
                                            // 1. ถ้าเป็นงานซักรายครั้ง (หรือสมาชิกที่ไม่ได้ส่งแบบหักโควตา) และมีข้อมูลที่ลูกค้าแจ้งมา -> ให้เติมค่าตามลูกค้า
                                            if($order['order_items_count'] == 0 && $is_actually_one_time && !empty($prefilled_items)): 
                                                foreach($prefilled_items as $p_it):
                                            ?>
                                            <div class="row g-2 mb-2 item-row">
                                                <div class="col-8"><input type="text" name="item_names[]" class="form-control rounded-3 item-name" value="<?= htmlspecialchars($p_it['name']) ?>" placeholder="เช่น เสื้อยืด" onchange="updateSummary()"></div>
                                                <div class="col-3"><input type="number" name="item_qtys[]" class="form-control rounded-3 text-center item-qty" value="<?= $p_it['qty'] ?>" min="1" onchange="calcTotal()"></div>
                                                <div class="col-1"><button type="button" class="btn btn-sm text-danger border-0 mt-1" onclick="this.closest('.item-row').remove(); calcTotal();"><i class="fa fa-times"></i></button></div>
                                            </div>
                                            <?php 
                                                endforeach;
                                            // 2. ถ้าเป็นงานรายเดือน (หรือสมัคร PKG) -> ใช้ค่าพื้นฐาน เสื้อยืด/กางเกง
                                            elseif($order['order_items_count'] == 0): 
                                            ?>
                                            <div class="row g-2 mb-2 item-row">
                                                <div class="col-8"><input type="text" name="item_names[]" class="form-control rounded-3 item-name" value="เสื้อยืด" placeholder="เช่น เสื้อยืด" onchange="updateSummary()"></div>
                                                <div class="col-3"><input type="number" name="item_qtys[]" class="form-control rounded-3 text-center item-qty" value="1" min="1" onchange="calcTotal()"></div>
                                                <div class="col-1"><button type="button" class="btn btn-sm text-danger border-0 mt-1" onclick="this.closest('.item-row').remove(); calcTotal();"><i class="fa fa-times"></i></button></div>
                                            </div>
                                            <div class="row g-2 mb-2 item-row">
                                                <div class="col-8"><input type="text" name="item_names[]" class="form-control rounded-3 item-name" value="กางเกง" placeholder="เช่น กางเกง" onchange="updateSummary()"></div>
                                                <div class="col-3"><input type="number" name="item_qtys[]" class="form-control rounded-3 text-center item-qty" value="1" min="1" onchange="calcTotal()"></div>
                                                <div class="col-1"><button type="button" class="btn btn-sm text-danger border-0 mt-1" onclick="this.closest('.item-row').remove(); calcTotal();"><i class="fa fa-times"></i></button></div>
                                            </div>
                                            <?php else: ?>
                                            <div class="row g-2 mb-2 item-row">
                                                <div class="col-8"><input type="text" name="item_names[]" class="form-control rounded-3 item-name" placeholder="เช่น เสื้อยืด" onchange="updateSummary()"></div>
                                                <div class="col-3"><input type="number" name="item_qtys[]" class="form-control rounded-3 text-center item-qty" value="1" min="1" onchange="calcTotal()"></div>
                                                <div class="col-1"></div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php 
                                    $is_pkg_order = (strpos(trim($order['order_id']), 'PKG') === 0);
                                    if($is_monthly || $is_pkg_order): 
                                        $default_deduct = $order['order_items_count'] ?: 0;
                                        if($default_deduct == 0 && strpos($order['rq_note'], 'หักโควตา') !== false) {
                                            preg_match_all('/หักโควตา[^\d]*(\d+)/', $order['rq_note'], $m_q);
                                            $default_deduct = array_sum($m_q[1] ?? [0]);
                                        }
                                    ?>
                                    <!-- 🔵 ส่วนจัดการโควตา (สำหรับสมาชิก) -->
                                    <div class="col-md-4">
                                        <div class="p-3 border border-primary border-opacity-25 rounded-4 bg-primary bg-opacity-10">
                                            <label class="form-label fw-bold small text-primary"><i class="fa fa-cut me-1"></i> หักจากโควตาที่มี (ชิ้น)</label>
                                            <input type="number" name="quota_deduct" id="deduct_amount" class="form-control form-control-lg rounded-3 border-primary shadow-sm bg-white fw-bold text-center" value="<?= $default_deduct ?>" min="0" required>
                                            <div class="form-text smaller text-primary">จำนวนผ้าที่ใช้สิทธิ์สมาชิกหักจาก 80 ชิ้น</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 border border-success border-opacity-25 rounded-4 bg-success bg-opacity-10">
                                            <label class="form-label fw-bold small text-success"><i class="fa fa-money-bill-wave me-1"></i> ค่าบริการหลัก / ราคาแพ็กเกจ (บาท)</label>
                                            <?php 
                                            $current_base = (float)$order['order_sum'] - (float)$order['order_extra_cash'] - (float)$order['order_shipping_fee'];
                                            ?>
                                            <input type="number" step="0.01" name="service_price" class="form-control form-control-lg rounded-3 border-success shadow-sm text-success fw-bold text-center" value="<?= $current_base ?>" min="0" required>
                                            <div class="form-text smaller text-success">ยอดเงินพื้นฐานที่ต้องเรียกเก็บ (ถ้ามี)</div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                        <!-- 🟢 ส่วนค่าบริการหลัก (สำหรับลูกค้าทั่วไป) -->
                                        <div class="col-md-4">
                                            <div class="p-3 border border-primary border-opacity-25 rounded-4 bg-primary bg-opacity-10">
                                                <label class="form-label fw-bold small text-primary"><i class="fa fa-money-bill-wave me-1"></i> ค่าบริการซักรีดหลัก (บาท)</label>
                                                <?php 
                                                $current_base = (float)$order['order_sum'] - (float)$order['order_extra_cash'] - (float)$order['order_shipping_fee'];
                                                if($current_base <= 0) {
                                                    $rq_info = $conn->query("SELECT rq_sum FROM request WHERE rq_id = '{$order['rq_id']}'")->fetch_assoc();
                                                    $current_base = (float)($rq_info['rq_sum'] ?? 0);
                                                }
                                                ?>
                                                <input type="number" step="0.01" name="service_price" class="form-control form-control-lg rounded-3 border-primary shadow-sm text-primary fw-bold text-center" value="<?= $current_base ?>" min="0" required>
                                                <div class="form-text smaller text-primary">ยอดเงินค่าบริการปกติที่ลูกค้าต้องจ่าย</div>
                                            </div>
                                        </div>
                                        <input type="hidden" name="quota_deduct" id="deduct_amount" value="0">
                                    <?php endif; ?>

                                    <!-- 🔴 ส่วนบวกเพิ่มพิเศษ -->
                                    <div class="col-md-4">
                                        <div class="p-3 border border-danger border-opacity-25 rounded-4 bg-danger bg-opacity-10">
                                            <label class="form-label fw-bold small text-danger"><i class="fa fa-plus-circle me-1"></i> ค่าบริการบวกเพิ่ม (บาท)</label>
                                            <input type="number" step="0.01" name="extra_cash" class="form-control form-control-lg rounded-3 border-danger shadow-sm text-danger fw-bold text-center" value="<?= $order['order_extra_cash'] ?>" min="0" required>
                                            <div class="form-text smaller text-danger">กรณีซักผ้าเกินโควตา หรือรายการพิเศษอื่นๆ</div>
                                        </div>
                                    </div>

                                    <div class="col-md-12 mt-3">
                                        <label class="form-label fw-bold small text-secondary">เหตุผล/รายการที่เรียกเก็บเพิ่ม (ถ้ามี)</label>
                                        <input type="text" name="extra_note" class="form-control form-control-lg rounded-3 border-0 shadow-sm" value="<?= htmlspecialchars($order['order_extra_note']) ?>" placeholder="เช่น ผ้านวม 1 ผืน, ซักแห้งสูท 1 ชุด">
                                    </div>
                                    <div class="col-md-12 d-none">
                                        <label class="form-label fw-bold small text-secondary">สรุปรายการ (ซ่อนไว้เพื่อส่งค่าไปยังฐานข้อมูล)</label>
                                        <input type="text" name="summary_note" id="summary_note" class="form-control" value="<?= ($order['order_items_count'] == 0) ? "เสื้อยืด (1)\nกางเกง (1)" : '' ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold small text-secondary">หมายเหตุจากร้านค้า (เช่น เวลาส่งผ้า หรือแจ้งเตือนอื่นๆ)</label>
                                        <input type="text" name="order_owner_note" class="form-control form-control-lg rounded-3 border-0 shadow-sm bg-white" value="<?= htmlspecialchars($order['order_owner_note'] ?? '') ?>" placeholder="เช่น จะนำผ้าไปส่งช่วงเย็นวันนี้ครับ">
                                    </div>
                                    <div class="col-12 mt-4">
                                        <button type="submit" name="use_quota" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold py-3 shadow border-0">
                                            <i class="fa fa-save me-2"></i> <?= ($order['order_items_count'] == 0) ? 'บันทึกข้อมูลงาน' : 'อัปเดตข้อมูลงาน' ?>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- 🕒 ส่วนระบุหมายเหตุ/เวลาส่ง (โชว์ตลอดเพื่อให้เจ้าของอัปเดตได้) -->
                        <?php if($order['order_items_count'] > 0): ?>
                        <div class="mt-4 p-4 bg-white rounded-4 border shadow-sm">
                            <h6 class="fw-bold mb-3 text-dark"><i class="fa fa-clock me-2 text-warning"></i> อัปเดตนัดหมาย/เวลาส่งผ้าคืน</h6>
                            <form method="post" action="deduct_monthly.php">
                                <input type="hidden" name="order_id" value="<?= $order_id ?>">
                                <input type="hidden" name="m_id" value="<?= $order['m_id'] ?>">
                                <input type="hidden" name="update_note_only" value="1">
                                <div class="row g-3">
                                    <div class="col-md-9">
                                        <label class="form-label fw-bold small text-secondary">แจ้งนัดหมายหรือเวลาส่งคืนให้ลูกค้าทราบ</label>
                                        <input type="text" name="order_owner_note" class="form-control form-control-lg rounded-3 bg-light border-0" value="<?= htmlspecialchars($order['order_owner_note'] ?? '') ?>" placeholder="เช่น จะส่งคืนวันพรุ่งนี้ 10:00 น.">
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" name="use_quota" class="btn btn-warning w-100 rounded-pill fw-bold py-3 shadow-sm">
                                            <i class="fa fa-save me-1"></i> อัปเดตโน้ต
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                        <?php else: // 🚩 เมื่อกดยืนยันแล้ว ให้โชว์แค่สรุป ไม่ต้องโชว์ช่องกรอก ?>
                            <div class="p-4 bg-light rounded-4 border-start border-primary border-5 shadow-sm">
                                <div class="row align-items-center">
                                    <div class="col-md-8 text-start">
                                        <div class="info-label">รายการที่บันทึกไว้</div>
                                        <div class="fw-bold fs-5 text-dark mt-1" style="white-space: pre-wrap;">
                                            <?php 
                                            // แยกชื่อแพ็กเกจออกจากโน้ตปกติ
                                            $clean_note = preg_replace('/MONTHLY_PACKAGE_\d+/', '', $order['order_note']);
                                            $clean_note = trim(str_replace('สมัครแพ็กเกจ:', '📦 ', $clean_note));
                                            echo nl2br(htmlspecialchars($clean_note)); 
                                            ?>
                                        </div>

                                        <!-- 🧺 แสดงตารางรายการผ้าแยกย่อย (ถ้ามี) -->
                                        <?php 
                                        $items_res = $conn->query("SELECT * FROM item WHERE order_id = '$order_id'");
                                        if($items_res && $items_res->num_rows > 0):
                                        ?>
                                        <div class="mt-3 table-responsive">
                                            <table class="table table-sm table-borderless bg-white rounded-3 shadow-sm mb-0">
                                                <thead class="smaller text-muted"><tr><th class="ps-3">รายการผ้า</th><th class="text-center">จำนวน (ชิ้น)</th></tr></thead>
                                                <tbody class="small">
                                                    <?php while($it = $items_res->fetch_assoc()): 
                                                        // ถ้าเป็นรายการมาตรฐาน (SRV...) ให้พยายามหาชื่อจากข้อความสรุป หรือใช้ชื่อบริการ
                                                        $it_name = "รายการทั่วไป";
                                                        // พยายามดึงชื่อจาก order_note ที่เราเซฟไว้ในรูปแบบ "ชื่อ (จำนวน)"
                                                        preg_match_all('/([^,\[\]]+)\s\((\d+)\)/', $order['order_note'], $matches);
                                                        if(!empty($matches[1])) {
                                                            // จับคู่ลำดับรายการ (คร่าวๆ)
                                                            // เพื่อความแม่นยำที่สุด ในอนาคตอาจต้องเพิ่มคอลัมน์ item_name ในตาราง item
                                                            // แต่ตอนนี้จะแสดงผลรวมจาก Note ให้เห็นชัดเจนครับ
                                                        }
                                                    ?>
                                                    <?php endwhile; ?>
                                                    <?php 
                                                    // แสดงผลจากสิ่งที่ดึงได้ใน Note ให้เป็นตาราง
                                                    preg_match_all('/([^,\[\]:]+)\s\((\d+)\)/', $order['order_note'], $matches);
                                                    if(!empty($matches[1])):
                                                        foreach($matches[1] as $idx => $m_name):
                                                    ?>
                                                        <tr class="border-top border-light">
                                                            <td class="ps-3 fw-bold text-secondary"><?= trim($m_name) ?></td>
                                                            <td class="text-center fw-bold text-primary"><?= $matches[2][$idx] ?></td>
                                                        </tr>
                                                    <?php 
                                                        endforeach;
                                                    endif; 
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php endif; ?>

                                        <?php if($order['order_items_count'] > 0): ?>
                                            <div class="badge bg-primary bg-opacity-10 text-primary mt-2">
                                                <i class="fa fa-cut me-1"></i> หักโควตาทันที: <?= $order['order_items_count'] ?> ชิ้น
                                            </div>
                                        <?php endif; ?>
                                        <?php if(!empty($order['order_extra_note'])): ?>
                                            <div class="mt-2 p-2 bg-danger bg-opacity-10 rounded text-danger small">
                                                <i class="fa fa-info-circle me-1"></i> <b>บริการที่คิดเงินเพิ่ม:</b> <?= htmlspecialchars($order['order_extra_note']) ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- 🚚 แสดงเวลานัดส่งคืน / หมายเหตุร้านค้า -->
                                        <?php if(!empty($order['order_owner_note'])): ?>
                                            <div class="mt-3 p-3 bg-warning bg-opacity-10 rounded-4 border-start border-warning border-4 text-dark shadow-sm">
                                                <div class="info-label text-warning mb-1"><i class="fa fa-clock me-1"></i> เวลานัดส่งคืน / หมายเหตุจากร้านค้า:</div>
                                                <div class="fw-bold fs-6"><?= nl2br(htmlspecialchars($order['order_owner_note'])) ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <div class="info-label">ยอดสุทธิที่ต้องชำระ</div>
                                        <div class="fw-bold fs-3 text-primary mt-1">฿<?= number_format($order['order_sum'], 2) ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 2. ขั้นตอนการจัดส่ง -->
                    <div class="card card-custom p-4">
                        <h6 class="fw-bold mb-4 text-dark"><i class="fa fa-truck me-2 text-primary"></i> ขั้นตอนการดำเนินการ</h6>
                        <form method="post" action="update_status.php">
                            <input type="hidden" name="order_id" value="<?= $order_id ?>">
                            <div class="row g-2">
                                <?php if($order['order_status'] == 0): ?>
                                    <div class="col-12">
                                        <button type="submit" name="new_status" value="1" class="btn btn-warning w-100 py-3 rounded-pill fw-bold shadow-sm">
                                            <i class="fa fa-truck-pickup me-2"></i> ร้านรับผ้ามาแล้ว (รอชำระเงิน)
                                        </button>
                                    </div>
                                <?php elseif($order['order_status'] == 2): ?>
                                    <div class="col-12">
                                        <button type="submit" name="new_status" value="3" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-sm">
                                            <i class="fa fa-check-circle me-2"></i> งานซักเสร็จสิ้น (เตรียมส่งคืน)
                                        </button>
                                    </div>
                                <?php elseif($order['order_status'] == 3): ?>
                                    <div class="col-12">
                                        <button type="submit" name="new_status" value="4" class="btn btn-success w-100 py-3 rounded-pill fw-bold shadow-sm">
                                            <i class="fa fa-paper-plane me-2"></i> ส่งคืนลูกค้าเรียบร้อยแล้ว (ปิดงาน)
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="col-12 text-center py-3">
                                        <span class="text-muted small">รอการตรวจสอบการชำระเงิน หรือ สถานะสำเร็จแล้ว</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function addItemRow() {
            const container = document.getElementById('item_list_container');
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 item-row';
            row.innerHTML = `
                <div class="col-8"><input type="text" name="item_names[]" class="form-control rounded-3 item-name" placeholder="รายการผ้า" onchange="updateSummary()"></div>
                <div class="col-3"><input type="number" name="item_qtys[]" class="form-control rounded-3 text-center item-qty" value="1" min="1" onchange="calcTotal()"></div>
                <div class="col-1"><button type="button" class="btn btn-sm text-danger border-0 mt-1" onclick="this.closest('.item-row').remove(); calcTotal();"><i class="fa fa-times"></i></button></div>
            `;
            container.appendChild(row);
            calcTotal();
        }

        function calcTotal() {
            let total = 0;
            document.querySelectorAll('.item-qty').forEach(input => {
                total += parseInt(input.value) || 0;
            });
            
            const amountInput = document.getElementById('deduct_amount');
            const isOneTime = <?= json_encode($is_actually_one_time) ?>;
            
            // 🚩 อัปเดตโควตาอัตโนมัติเฉพาะ "งานรายเดือน" เท่านั้น
            // งานรายครั้ง (เช่น ซักชุดราชการ) จะไม่หักโควตาอัตโนมัติ (เป็น 0 หรือเจ้าของแก้เอง)
            if (amountInput && !isOneTime) {
                amountInput.value = total;
            }
            updateSummary();
        }

        function updateSummary() {
            const items = [];
            document.querySelectorAll('.item-row').forEach(row => {
                const name = row.querySelector('.item-name').value.trim();
                const qty = row.querySelector('.item-qty').value;
                if (name) {
                    items.push(`${name} (${qty})`);
                }
            });
            const summaryInput = document.getElementById('summary_note');
            if (summaryInput) summaryInput.value = items.join('\n');
        }

        window.onload = function() {
            if (document.querySelectorAll('.item-row').length > 0) {
                calcTotal();
            }
        };
    </script>
</body>
</html>
