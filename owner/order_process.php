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

// ดึงข้อมูลออเดอร์พร้อมข้อมูลสมาชิก
$sql = "SELECT o.*, m.m_name, m.m_lastname, m.m_tel, m.m_latitude, m.m_longitude, m.m_address
        FROM orders o 
        JOIN member m ON o.m_id = m.m_id 
        WHERE o.order_id = '$order_id'";
$res = $conn->query($sql);
$order = $res->fetch_assoc();

if (!$order) {
    die("ไม่พบข้อมูลออเดอร์");
}

// เช็คว่าเป็นสมาชิกรายเดือนหรือไม่
$json_file = __DIR__ . '/../config/monthly_data.json';
$monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
$is_monthly = isset($monthly_data[$order['m_id']]);
$member_quota = $is_monthly ? $monthly_data[$order['m_id']]['quota'] : 0;

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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">📦 จัดการออเดอร์ #<?= $order_id ?></h2>
                    <?php 
                    $s_text = [0=>'รอร้านไปรับผ้า', 1=>'รอการชำระเงิน', 2=>'ร้านกำลังดำเนินการซัก/รีด', 3=>'ซักเสร็จ/รอส่งคืน', 4=>'สำเร็จ/ส่งคืนแล้ว'];
                    $s_color = [0=>'bg-dark', 1=>'bg-warning text-dark', 2=>'bg-info text-white', 3=>'bg-primary text-white', 4=>'bg-success text-white'];
                    $st = (int)$order['order_status'];
                    ?>
                    <span class="status-badge <?= $s_color[$st] ?>"><?= $s_text[$st] ?></span>
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
                                <div class="info-label">สลิปจากลูกค้า (<?= date('d/m/y H:i', strtotime($payment['payment_date'])) ?>)</div>
                                <div class="mt-2 rounded-4 overflow-hidden border">
                                    <img src="data:image/jpeg;base64,<?= base64_encode($payment['payment_image']) ?>" class="img-fluid w-100" style="max-height: 400px; object-fit: contain; cursor: zoom-in;" onclick="window.open(this.src)">
                                </div>
                                <div class="text-center mt-2">
                                    <span class="small text-muted">ยอดแจ้งโอน: <b>฿<?= number_format($payment['payment_amount'], 2) ?></b></span>
                                </div>
                            </div>

                            <?php if($order['order_status'] == 1): ?>
                                <a href="confirm_payment.php?id=<?= $order_id ?>" class="btn btn-success btn-lg w-100 rounded-pill fw-bold py-3 shadow border-0" onclick="return confirm('ยืนยันว่าได้รับเงินถูกต้อง?')">
                                    <i class="fa fa-check-circle me-2"></i> ยืนยันยอดถูกต้อง
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fa fa-clock fa-3x text-light mb-3"></i>
                                <p class="text-muted small">รอลูกค้าแจ้งโอน <b>฿<?= number_format($order['order_sum'], 2) ?></b></p>
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
                                    <?php if($order['order_extra_cash'] > 0): ?>
                                        <div class="smaller text-danger fw-bold mt-1">+ รวมบวกเพิ่ม ฿<?= number_format($order['order_extra_cash'], 2) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ฟอร์มบันทึก (จะถูกพับไว้ถ้ามีข้อมูลแล้ว) -->
                        <div class="collapse <?= (empty($order['order_note']) && $order['order_items_count'] == 0) ? 'show' : '' ?> mb-4" id="editForm">
                            <form method="post" action="deduct_monthly.php" class="p-4 rounded-4 border bg-light shadow-sm">
                                <input type="hidden" name="order_id" value="<?= $order_id ?>">
                                <input type="hidden" name="m_id" value="<?= $order['m_id'] ?>">
                                <input type="hidden" name="manual_mode" value="1">

                                <div class="row g-4">
                                    <?php 
                                    $is_pkg_order = (strpos(trim($order['order_id']), 'PKG') === 0);
                                    if($is_monthly || $is_pkg_order): 
                                    ?>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold small text-secondary">จำนวนผ้าปกติที่หักโควตา (ชิ้น)</label>
                                        <input type="number" name="quota_deduct" class="form-control form-control-lg rounded-3 border-0 shadow-sm" value="<?= $order['order_items_count'] ?>" min="0" required>
                                        <div class="form-text smaller text-primary">ใส่จำนวนที่ส่งซักเพื่อคำนวณหักโควตา</div>
                                    </div>
                                    <?php else: ?>
                                        <input type="hidden" name="quota_deduct" value="0">
                                    <?php endif; ?>

                                    <div class="col-md-<?= $is_monthly ? '3' : '4' ?>">
                                        <label class="form-label fw-bold small text-secondary">เรียกเก็บเงินเพิ่ม (บาท)</label>
                                        <input type="number" step="0.01" name="extra_cash" class="form-control form-control-lg rounded-3 border-0 shadow-sm text-danger fw-bold" value="<?= $order['order_extra_cash'] ?>" min="0" required>
                                        <div class="form-text smaller text-danger">ระบุยอดรวมของรายการที่ต้องจ่ายเพิ่ม</div>
                                    </div>
                                    <div class="col-md-<?= $is_monthly ? '5' : '8' ?>">
                                        <label class="form-label fw-bold small text-secondary">เหตุผลที่เรียกเก็บเพิ่ม</label>
                                        <input type="text" name="extra_note" class="form-control form-control-lg rounded-3 border-0 shadow-sm" value="<?= htmlspecialchars($order['order_extra_note']) ?>" placeholder="เช่น ค่าผ้านวม 50, ซักเกิน 10 ชิ้น 100">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold small text-secondary">สรุปรายการ (โชว์ที่หน้าบิล)</label>
                                        <input type="text" name="summary_note" class="form-control form-control-lg rounded-3 shadow-sm" placeholder="เช่น ผ้า 10 + ผ้านวม 1" required>
                                    </div>
                                    <div class="col-12 mt-4">
                                        <button type="submit" name="use_quota" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold py-3 shadow border-0">
                                            <i class="fa fa-save me-2"></i> <?= (empty($order['order_note']) && $order['order_items_count'] == 0) ? 'บันทึกข้อมูลงาน' : 'อัปเดตข้อมูลงาน' ?>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
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
</body>
</html>
