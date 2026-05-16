<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit;
}

$user = $_SESSION['user'];
$m_id = $user['m_id'];

// 🛡️ ตรวจสอบว่าสมาชิกยังมีตัวตนหรือไม่ (กรณีถูกเจ้าของลบทิ้ง)
$check_user = $conn->query("SELECT m_id FROM member WHERE m_id = '$m_id'");
if ($check_user->num_rows == 0) {
    session_destroy();
    header("Location: ../index.php?error=account_deleted");
    exit;
}

// ดึงข้อมูลชื่อร้านค้า
$shop_info = $conn->query("SELECT shop_name1 FROM shop LIMIT 1")->fetch_assoc();
$shop_name = $shop_info['shop_name1'] ?? 'Laundry System';

// ดึงข้อมูลโควตารายเดือนจาก JSON
$json_file = __DIR__ . '/../config/monthly_data.json';
$monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
$my_quota = (isset($monthly_data[$m_id]) && isset($monthly_data[$m_id]['quota'])) ? (int)$monthly_data[$m_id]['quota'] : null;
$my_total = (isset($monthly_data[$m_id]) && isset($monthly_data[$m_id]['total_quota'])) ? (int)$monthly_data[$m_id]['total_quota'] : 80;
$my_pickups = (isset($monthly_data[$m_id]) && isset($monthly_data[$m_id]['pickups_used'])) ? $monthly_data[$m_id]['pickups_used'] : 0;

// --- 🚩 [Auto Reset Safety Net] ---
// หากโควตาหมด หรือครบ 4 รอบ และไม่มีออเดอร์รายเดือนที่ยังค้างอยู่ (Status < 4)
// ให้รีเซ็ตสมาชิกโดยอัตโนมัติ (กรณีที่การรีเซ็ตที่หน้าเจ้าของร้านไม่ทำงานหรือทำไปก่อนอัปเดตโค้ด)
if ($my_quota !== null && ($my_quota <= 0 || $my_pickups >= 4)) {
    $check_active = $conn->query("SELECT order_id FROM orders WHERE m_id = '$m_id' AND order_status < 4 AND (order_id LIKE 'PKG%' OR order_pickup_round > 0)");
    if ($check_active->num_rows == 0) {
        unset($monthly_data[$m_id]);
        file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));
        $my_quota = null; // ปรับสถานะในหน้านี้ทันทีเพื่อให้แสดงหน้าสมัครใหม่
    }
}

// คำนวณยอดที่ใช้ไป
$used_pieces = ($my_quota !== null) ? ($my_total - $my_quota) : 0;
$extra_pieces = ($my_quota !== null && $my_quota < 0) ? abs($my_quota) : 0;
// แสดงจำนวนที่ส่งจริง (ถ้าเกินก็โชว์ตามจริง เช่น 81 / 80)
$display_used = $used_pieces;

// --- 🚩 ดึงชื่อแพ็กเกจล่าสุดที่สมัคร ---
$pkg_name = "แพ็กเกจสมาชิก";
if($my_quota !== null) {
    $sql_pkg = "SELECT order_note FROM orders WHERE m_id = '$m_id' AND order_id LIKE 'PKG%' AND order_status >= 1 ORDER BY order_date DESC LIMIT 1";
    $res_pkg = $conn->query($sql_pkg);
    if($res_pkg && $res_pkg->num_rows > 0) {
        $pkg_row = $res_pkg->fetch_assoc();
        // ตัดคำว่า "สมัครแพ็กเกจ:" ออกเพื่อให้เหลือแค่ชื่อ
        $pkg_name = trim(str_replace('สมัครแพ็กเกจ:', '', $pkg_row['order_note']));
        // ป้องกันกรณีมีโน้ตยาวๆ ให้เหลือแค่บรรทัดแรก
        $pkg_name = explode("\n", $pkg_name)[0];
    }
}

// ดึงข้อมูลคำสั่งซื้อของสมาชิก
$sql_orders = "SELECT o.*, 
               (SELECT COUNT(*) FROM payment p WHERE p.order_id = o.order_id) as pay_count 
               FROM orders o WHERE o.m_id = '$m_id' ORDER BY o.order_date DESC";
$res_orders = $conn->query($sql_orders);

$monthly_orders = [];
$normal_orders = [];

while($o = $res_orders->fetch_assoc()) {
    $is_pkg = (strpos($o['order_id'], 'PKG') === 0);
    $has_round = ((int)$o['order_pickup_round'] > 0);
    
    // รายเดือน: ต้องเป็น PKG หรือ มีการนับ "รอบสมาชิก" (1-4) เท่านั้น
    if($is_pkg || $has_round) {
        $monthly_orders[] = $o;
    } else {
        // งานซักทั่วไป (จ่ายเงินสด) จะมาอยู่ที่นี่เสมอ
        $normal_orders[] = $o;
    }
}

// 🛡️ ดึงข้อมูลคำขอที่ยังค้างอยู่ หรือเพิ่งถูกปฏิเสธ
$sql_reqs = "SELECT * FROM request WHERE m_id = '$m_id' AND rq_status IN (0, 2) ORDER BY rq_date DESC LIMIT 5";
$res_reqs = $conn->query($sql_reqs);

// --- 🚩 กำหนดข้อความและสีสถานะส่วนกลาง ---
$s_text = [0=>'รอรับผ้า', 1=>'รอจ่ายเงิน', 2=>'กำลังซักรีด', 3=>'เตรียมส่งคืน', 4=>'สำเร็จ'];
$s_color = [0=>'bg-secondary', 1=>'bg-warning text-dark', 2=>'bg-info', 3=>'bg-primary', 4=>'bg-success text-white'];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>หน้าแรกสมาชิก - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&display=swap');
        :root { --primary: #3B82F6; --bg-light: #F8FAFC; }
        body { background: var(--bg-light); font-family: 'Chakra Petch', sans-serif; }
        .navbar { background: white; border-bottom: 1px solid #E2E8F0; padding: 15px 0; }
        .card-custom { border-radius: 20px; border: none; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); background: white; }
        .status-badge { padding: 6px 14px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
        .quota-card { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: white; border: none; }
        .btn-action { transition: 0.3s; border-radius: 12px; padding: 12px; font-weight: 600; }
        .btn-action:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center fw-bold text-primary" href="home.php">
                <?php if(file_exists('../owner/logo.png')): ?>
                    <img src="../owner/logo.png?v=<?= time() ?>" alt="Logo" class="rounded-circle" style="width: 45px; height: 45px; object-fit: cover; margin-right: 12px; border: 2px solid #3B82F6;">
                <?php else: ?>
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 45px; height: 45px;">
                        <i class="fa fa-shirt" style="font-size: 1.2rem;"></i>
                    </div>
                <?php endif; ?>
                <span class="fs-5"><?= htmlspecialchars($shop_name) ?></span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="d-none d-md-inline small text-muted">สวัสดี, <b><?= htmlspecialchars($user['m_name']) ?></b></span>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row g-4">
            <!-- Sidebar / Member Info -->
            <div class="col-lg-4">
                <div class="card card-custom p-4 text-center mb-4">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['m_name']) ?>&background=3B82F6&color=fff" class="rounded-circle mx-auto mb-3 border p-1" width="80">
                    <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($user['m_name'] . ' ' . $user['m_lastname']) ?></h5>
                    <p class="text-muted small mb-3"><?= htmlspecialchars($user['m_mail']) ?></p>
                    
                    <a href="edit_profile.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 mb-4">
                        <i class="fa fa-user-edit me-1"></i> แก้ไขข้อมูลส่วนตัว
                    </a>
                    
                    <hr class="opacity-10 mb-4">

                    <?php if($my_quota !== null): ?>
                        <div class="card quota-card p-4 rounded-4 text-start mb-4 shadow">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <p class="mb-0 small fw-bold text-white">ชื่อแพ็กเกจที่สมัคร: <?= htmlspecialchars($pkg_name) ?></p>
                                <i class="fa fa-gem text-white opacity-50"></i>
                            </div>
                            <div class="d-flex justify-content-between align-items-end mb-1">
                                <h2 class="fw-bold mb-0">
                                    <?= $display_used ?> <small class="fs-6 fw-normal">/ <?= $my_total ?> ชิ้น</small>
                                </h2>
                                <span class="badge bg-white bg-opacity-20 rounded-pill px-2 py-1 smaller" style="font-size: 0.65rem;">ใช้ไปแล้ว</span>
                            </div>
                            
                            <?php if($extra_pieces > 0): ?>
                                <div class="bg-white bg-opacity-10 rounded-3 p-2 mb-3 border border-white border-opacity-20 mt-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="smaller"><i class="fa fa-exclamation-circle me-1"></i> ส่งซักเกินโควตา:</span>
                                        <span class="fw-bold text-warning">+ <?= $extra_pieces ?> ชิ้น</span>
                                    </div>
                                    <div class="smaller opacity-75 mt-1" style="font-size: 0.65rem;">* ระบบจะคิดค่าบริการส่วนเกินในรอบที่ส่งซักครับ</div>
                                </div>
                            <?php else: ?>
                                <p class="mb-3 small opacity-75">โควตาคงเหลือ: <?= $my_quota ?> ชิ้น</p>
                            <?php endif; ?>

                            <div class="border-top border-white border-opacity-20 pt-3">
                                <p class="mb-1 small opacity-75">สิทธิ์การส่งซักเดือนนี้</p>
                                <?php 
                                    // คำนวณรอบให้เป็น 1-4 เสมอ (Cycle)
                                    $display_pickups = ($my_pickups > 0) ? ($my_pickups % 4 ?: 4) : 0;
                                ?>
                                <h5 class="fw-bold mb-0">ครั้งที่ <?= $display_pickups ?> <small class="fs-6 fw-normal text-white-50">/ 4 ครั้ง</small></h5>
                                <div class="smaller text-white-50 mt-2" style="font-size: 0.65rem;">* ครบ 4 ครั้ง หรือใช้โควตาครบ ระบบจะรีเซ็ตเพื่อเริ่มรอบใหม่ทันที</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border rounded-4 text-start mb-4 p-3 shadow-sm">
                            <div class="text-primary fw-bold mb-1 small"><i class="fa fa-info-circle me-1"></i> ยังไม่มีแพ็กเกจสมาชิก</div>
                            <p class="mb-0 smaller text-muted">สมัครรายเดือนเพื่อรับสิทธิ์ซักผ้าสุดคุ้มและบริการรับส่งฟรี!</p>
                        </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2">
                        <a href="monthly_plans.php" class="btn btn-outline-primary btn-action mb-2">
                            <i class="fa fa-gem me-2"></i> รายเดือน
                        </a>
                        <a href="request_pickup.php" class="btn btn-primary btn-action py-3 shadow-sm text-white">
                            <i class="fa fa-truck-pickup me-2"></i> รายชิ้น
                        </a>
                    </div>

                    <!-- คู่มือการใช้งาน -->
                    <div class="mt-4 pt-4 border-top text-start">
                        <h6 class="fw-bold mb-3 text-primary small"><i class="fa fa-info-circle me-2"></i>ขั้นตอนการใช้งาน</h6>
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex align-items-start">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 24px; height: 24px; font-size: 0.7rem;">1</div>
                                <div class="smaller text-muted">
                                    <b>เลือกประเภทบริการ:</b><br>
                                    • <span class="text-dark fw-bold">รายครั้ง:</span> ซักเท่าไหร่จ่ายเท่านั้น<br>
                                    • <span class="text-dark fw-bold">รายเดือน:</span> เหมาจ่ายรายเดือน (คุ้มกว่า)
                                </div>
                            </div>
                            <div class="d-flex align-items-start">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 24px; height: 24px; font-size: 0.7rem;">2</div>
                                <div class="smaller text-muted"><b>แจ้งรับผ้า:</b> ระบุตำแหน่งปักหมุด รายเดือนจะหักโควตา "ครั้งที่รับ" (ได้ 4 ครั้ง/เดือน)</div>
                            </div>
                            <div class="d-flex align-items-start">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 24px; height: 24px; font-size: 0.7rem;">3</div>
                                <div class="smaller text-muted">
                                    <b>การชำระเงิน:</b><br>
                                    • <span class="text-dark fw-bold">รายครั้ง:</span> โอนเงินและแนบสลิปหลังร้านแจ้งยอด<br>
                                    • <span class="text-dark fw-bold">รายเดือน:</span> หักจากจำนวนชิ้นที่เหมาไว้ (ไม่ต้องโอนเพิ่ม)
                                </div>
                            </div>
                            <div class="d-flex align-items-start">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 24px; height: 24px; font-size: 0.7rem;">4</div>
                                <div class="smaller text-muted"><b>รอรับผ้าคืน:</b> ติดตามสถานะได้ตลอดเวลา เมื่อซักเสร็จร้านจะนำมาส่งคืนถึงที่ทันที</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content / History -->
            <div class="col-lg-8">
                <!-- คำขอที่รอรับ หรือ ถูกปฏิเสธ -->
                <?php if($res_reqs->num_rows > 0): ?>
                <div class="card card-custom p-4 mb-4 border-start border-warning border-5 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0"><i class="fa fa-bell me-2 text-warning"></i> แจ้งเตือนคำขอ</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle table-borderless">
                            <tbody>
                                <?php while($rq = $res_reqs->fetch_assoc()): 
                                    $is_rejected = ($rq['rq_status'] == 2);
                                ?>
                                <tr class="<?= $is_rejected ? 'bg-danger bg-opacity-10' : 'bg-light bg-opacity-50' ?> rounded-3">
                                    <td class="small text-muted py-3 ps-3" style="width: 100px;"><?= date('d/m H:i', strtotime($rq['rq_date'])) ?></td>
                                    <td>
                                        <div class="small text-dark fw-medium"><?= htmlspecialchars($rq['rq_note']) ?></div>
                                        <?php if($is_rejected): ?>
                                            <div class="mt-1 small text-danger fw-bold"><i class="fa fa-times-circle me-1"></i> ปฏิเสธ: <?= htmlspecialchars($rq['rq_reject_note'] ?: 'ไม่ระบุเหตุผล') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <?php if(!$is_rejected): ?>
                                            <span class="badge bg-warning text-dark px-3 rounded-pill smaller">รอร้านตอบรับ</span>
                                            <a href="cancel_request.php?id=<?= $rq['rq_id'] ?>" 
                                               class="btn btn-sm btn-link text-danger text-decoration-none fw-bold small ms-2" 
                                               onclick="return confirm('ยกเลิกคำขอนี้?')">ยกเลิก</a>
                                        <?php else: ?>
                                            <span class="badge bg-danger px-3 rounded-pill smaller">ถูกปฏิเสธ</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 🌟 ส่วนรายการรายเดือน / แพ็กเกจ -->
                <div class="card card-custom p-4 mb-4 border-start border-primary border-5 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fa fa-gem me-2 text-primary"></i> กิจกรรมสมาชิก & แพ็กเกจ</h6>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3"><?= count($monthly_orders) ?> รายการ</span>
                    </div>
                    <?php if(count($monthly_orders) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="text-muted small">
                                <tr>
                                    <th>รายการ</th>
                                    <th>วันที่</th>
                                    <th class="text-center">รอบที่</th>
                                    <th>การใช้สิทธิ์</th>
                                    <th>ยอดชำระเพิ่ม</th>
                                    <th>สถานะ</th>
                                    <th class="text-center">#</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($monthly_orders as $o): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark small">#<?= substr($o['order_id'], -6) ?></div>
                                        <?php if(strpos($o['order_id'], 'PKG') === 0): ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size: 0.6rem;">แพ็กเกจรายเดือน</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning text-dark" style="font-size: 0.6rem;">ส่งซักผ้า (รายเดือน)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= date('d/m/y', strtotime($o['order_date'])) ?></td>
                                    <td class="text-center">
                                        <?php 
                                        $round = (int)$o['order_pickup_round'];
                                        // ถ้าเป็นงานรายเดือน (มีหักโควตา) แต่เลขรอบเป็น 0 ให้พยายามดึงรอบจาก JSON มาโชว์
                                        if($round == 0 && (strpos($o['order_note'], 'หักโควตา') !== false)) {
                                            $round = $my_pickups; 
                                        }

                                        if($round > 0): 
                                            // คำนวณให้เป็น 1-4 เสมอ
                                            $cycle_round = ($round % 4 ?: 4);
                                        ?>
                                            <span class="badge bg-light text-dark border rounded-pill px-2" style="font-size: 0.7rem;"><?= $cycle_round ?> / 4</span>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.7rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $qty = isset($o['order_items_count']) ? (int)$o['order_items_count'] : 0;
                                        if($qty <= 0) {
                                            preg_match('/หัก[^\d]*(\d+)\s*ชิ้น/u', $o['order_note'], $m);
                                            $qty = isset($m[1]) ? $m[1] : 0;
                                        }

                                        if(strpos($o['order_id'], 'PKG') === 0): ?>
                                            <?php if($qty > 0): ?>
                                                <div class="smaller fw-bold text-success"><i class="fa fa-plus-circle"></i> รอบที่1</div>
                                                <div class="smaller fw-bold text-primary"><i class="fa fa-cut"></i> รับผ้าจำนวน <?= $qty ?> ชิ้น</div>
                                            <?php else: ?>
                                                <div class="smaller fw-bold text-success"><i class="fa fa-plus-circle"></i> เริ่มต้นแพ็กเกจรายเดือน</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="smaller fw-bold text-primary"><i class="fa fa-cut"></i> รับผ้าจำนวน <?= $qty ?> ชิ้น</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($o['order_sum'] <= 0): ?>
                                            <span class="smaller fw-bold text-success">ฟรี (ในโควตา)</span>
                                        <?php else: ?>
                                            <div class="fw-bold small text-danger">฿<?= number_format($o['order_sum'], 2) ?></div>
                                            <?php if(!empty($o['order_extra_note'])): ?>
                                                <div class="smaller text-muted" style="font-size: 0.6rem;">+ <?= htmlspecialchars($o['order_extra_note']) ?></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $idx = (int)$o['order_status'];
                                        echo "<span class='badge {$s_color[$idx]} status-badge shadow-sm' style='font-size: 0.65rem;'>{$s_text[$idx]}</span>";
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="order_detail.php?id=<?= $o['order_id'] ?>" class="btn btn-sm btn-light rounded-pill px-3 shadow-sm fw-bold">บิล</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p class="text-center text-muted py-4 small mb-0">ยังไม่มีประวัติการใช้งานรายเดือน</p>
                    <?php endif; ?>
                </div>

                <!-- 📦 ส่วนรายการซักรายครั้ง -->
                <div class="card card-custom p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fa fa-receipt me-2 text-success"></i> รายการบริการรายครั้ง (เงินสด)</h6>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3"><?= count($normal_orders) ?> รายการ</span>
                    </div>
                    <?php if(count($normal_orders) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="text-muted small">
                                <tr><th>ออเดอร์</th><th>วันที่</th><th>ยอดรวม</th><th>สถานะงาน</th><th class="text-center">#</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($normal_orders as $o): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-success small">#<?= substr($o['order_id'], -6) ?></div>
                                        <span class="badge bg-success bg-opacity-10 text-success" style="font-size: 0.6rem;">รายการซักรายครั้ง</span>
                                    </td>
                                    <td class="small text-muted"><?= date('d/m/y', strtotime($o['order_date'])) ?></td>
                                    <td class="fw-bold small text-dark">฿<?= number_format($o['order_sum'], 2) ?></td>
                                    <td>
                                        <?php 
                                        if ($o['order_status'] == 1 && $o['pay_count'] > 0) {
                                            echo "<span class='badge bg-info bg-opacity-10 text-info status-badge border border-info border-opacity-25'><i class='fa fa-clock me-1'></i> รอตรวจสลิป</span>";
                                        } else {
                                            $idx = (int)$o['order_status'];
                                            echo "<span class='badge {$s_color[$idx]} status-badge shadow-sm'>{$s_text[$idx]}</span>";
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="order_detail.php?id=<?= $o['order_id'] ?>" class="btn btn-sm btn-light rounded-pill px-3 shadow-sm fw-bold">ดู</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p class="text-center text-muted py-4 small mb-0">ยังไม่มีรายการซักรายครั้ง</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
