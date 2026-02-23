<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit;
}

$user = $_SESSION['user'];
$m_id = $user['m_id'];

// ดึงข้อมูลโควตารายเดือนจาก JSON
$json_file = __DIR__ . '/../config/monthly_data.json';
$monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
$my_quota = isset($monthly_data[$m_id]) ? $monthly_data[$m_id]['quota'] : null;
$my_pickups = isset($monthly_data[$m_id]['pickups_used']) ? $monthly_data[$m_id]['pickups_used'] : 0;

// ดึงข้อมูลคำสั่งซื้อของสมาชิก
$sql_orders = "SELECT * FROM orders WHERE m_id = '$m_id' ORDER BY order_date DESC";
$res_orders = $conn->query($sql_orders);

// ดึงข้อมูลคำขอที่ยังค้างอยู่
$sql_reqs = "SELECT * FROM request WHERE m_id = '$m_id' AND rq_status = 0 ORDER BY rq_date DESC";
$res_reqs = $conn->query($sql_reqs);
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
        :root { --primary: #3B82F6; --bg-light: #F8FAFC; }
        body { background: var(--bg-light); font-family: system-ui; }
        .navbar { background: white; border-bottom: 1px solid #E2E8F0; }
        .card-custom { border-radius: 16px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .status-badge { padding: 6px 12px; border-radius: 999px; font-size: 0.85rem; font-weight: 600; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="home.php">👕 Laundry</a>
            <div class="d-flex align-items-center">
                <span class="me-3 d-none d-sm-inline">สวัสดี, <?= htmlspecialchars($user['m_name']) ?></span>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row g-4">
            <!-- Sidebar / Action Menu -->
            <div class="col-lg-4">
                <div class="card card-custom p-4 text-center mb-4">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['m_name']) ?>&background=3B82F6&color=fff" class="rounded-circle mx-auto mb-3" width="80">
                    <h5 class="fw-bold"><?= htmlspecialchars($user['m_name'] . ' ' . $user['m_lastname']) ?></h5>
                    <p class="text-muted small"><?= htmlspecialchars($user['m_mail']) ?></p>
                    <a href="edit_profile.php" class="btn btn-sm btn-outline-secondary rounded-pill mb-3">
                        <i class="fa fa-user-edit me-1"></i> แก้ไขโปรไฟล์
                    </a>
                    <hr>
                    <?php if($my_quota !== null): ?>
                        <div class="alert alert-primary rounded-4 mb-3 p-3">
                            <div class="mb-2">
                                <p class="mb-0 small opacity-75">โควตาซักผ้าคงเหลือ</p>
                                <h3 class="fw-bold mb-0"><?= $my_quota ?> <small class="fs-6">ชิ้น</small></h3>
                            </div>
                            <div class="border-top border-primary border-opacity-25 pt-2">
                                <p class="mb-0 small opacity-75">สิทธิ์เรียกรับผ้าคงเหลือ</p>
                                <h5 class="fw-bold mb-0"><?= max(0, 4 - $my_pickups) ?> <small class="fs-6 text-muted">/ 4 ครั้ง</small></h5>
                                <p class="mb-0 smaller opacity-50" style="font-size: 0.75rem;">* ครั้งละไม่เกิน 20 ชิ้น</p>
                                <hr class="my-2 border-primary border-opacity-25">
                                <p class="mb-0 smaller fw-bold text-danger" style="font-size: 0.7rem;">* เมื่อครบ 30 วัน สิทธิคงเหลือจะถูกตัดจบทั้งหมด แม้ผ้ายังไม่ครบจำนวน</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <a href="monthly_plans.php" class="btn btn-outline-primary w-100 rounded-pill mb-3">
                        <i class="fa fa-star me-2"></i> สมัคร/เติมรายเดือน
                    </a>
                    <a href="request_pickup.php" class="btn btn-primary btn-lg w-100 rounded-3 mb-3">
                        <i class="fa fa-truck-pickup me-2"></i> แจ้งรับผ้า
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- คำขอที่กำลังรอ -->
                <?php if($res_reqs->num_rows > 0): ?>
                <div class="card card-custom p-4 mb-4">
                    <h5 class="fw-bold mb-3">🕒 คำขอที่กำลังรอรับผ้า</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead><tr><th>วันที่</th><th>หมายเหตุ</th><th>สถานะ</th></tr></thead>
                            <tbody>
                                <?php while($rq = $res_reqs->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $rq['rq_date'] ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($rq['rq_note']) ?></td>
                                    <td><span class="badge bg-warning text-dark status-badge">กำลังรอร้านค้า</span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- รายการสั่งซื้อ -->
                <div class="card card-custom p-4">
                    <h5 class="fw-bold mb-3">📦 รายการบริการล่าสุด</h5>
                    <?php if($res_orders->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead><tr><th>ID</th><th>วันที่</th><th>ยอดรวม</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
                            <tbody>
                                <?php while($o = $res_orders->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= $o['order_id'] ?></td>
                                    <td><?= $o['order_date'] ?></td>
                                    <td>฿<?= number_format($o['order_sum'], 2) ?></td>
                                    <td>
                                        <?php 
                                        $status = ['รอดำเนินการ', 'กำลังซัก/อบ/รีด', 'เสร็จสิ้น/รอจ่ายเงิน', 'จ่ายเงินแล้ว', 'จัดส่งคืนแล้ว'];
                                        $colors = ['bg-secondary', 'bg-info', 'bg-warning text-dark', 'bg-primary', 'bg-success'];
                                        echo "<span class='badge {$colors[$o['order_status']]} status-badge'>{$status[$o['order_status']]}</span>";
                                        ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="order_detail.php?id=<?= $o['order_id'] ?>" class="btn btn-sm btn-outline-secondary">ดูรายละเอียด</a>
                                            <?php if($o['order_status'] == 2): ?>
                                                <a href="payment.php?id=<?= $o['order_id'] ?>" class="btn btn-sm btn-success">แจ้งชำระเงิน</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fa fa-receipt fa-3x text-light mb-3"></i>
                            <p class="text-muted">ยังไม่มีรายการสั่งซื้อ</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
