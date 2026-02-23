<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

// ยืนยันการรับผ้า (เปลี่ยนสถานะและสร้างออเดอร์)
if (isset($_POST['confirm_request'])) {
    $rq_id = $_POST['rq_id'];
    $m_id = $_POST['m_id'];
    $order_id = "ORD" . time();
    $date = date("Y-m-d H:i:s");

    $conn->begin_transaction();
    try {
        // 1. อัปเดตสถานะคำขอเป็น 'ยืนยันแล้ว' (1)
        $conn->query("UPDATE request SET rq_status = 1 WHERE rq_id = '$rq_id'");

        // 2. สร้างออเดอร์ใหม่ (สถานะ 1 = กำลังซัก/อบ/รีด)
        $conn->query("INSERT INTO orders (order_id, m_id, rq_id, order_date, order_status, order_sum) 
                      VALUES ('$order_id', '$m_id', '$rq_id', '$date', 1, 0)");
        
        $conn->commit();
        header("Location: manage_orders.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }
}

// ปฏิเสธคำขอ
if (isset($_POST['reject_request'])) {
    $rq_id = $_POST['rq_id'];
    $conn->query("UPDATE request SET rq_status = 2 WHERE rq_id = '$rq_id'");
    header("Location: manage_requests.php");
    exit;
}

$sql_reqs = "SELECT r.*, m.m_name, m.m_lastname, m.m_tel FROM request r JOIN member m ON r.m_id = m.m_id WHERE r.rq_status = 0 ORDER BY r.rq_date DESC";
$res_reqs = $conn->query($sql_reqs);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการคำขอรับผ้า - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root { --primary: #2563EB; --secondary: #64748B; --bg: #F8FAFC; }
        body { background: var(--bg); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { width: 260px; height: 100vh; background: #fff; position: fixed; border-right: 1px solid #E2E8F0; padding: 20px; }
        .main-content { margin-left: 260px; padding: 30px; }
        .nav-link { color: var(--secondary); padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; }
        .nav-link.active { background: #EFF6FF; color: var(--primary); font-weight: 600; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h4 class="fw-bold text-primary mb-4 px-3">🧺 Shop Admin</h4>
        <nav class="nav flex-column">
            <a class="nav-link" href="owner_home.php"><i class="fa fa-home me-2"></i> หน้าแรก</a>
            <a class="nav-link active" href="manage_requests.php"><i class="fa fa-bell me-2"></i> คำขอรับผ้า</a>
            <a class="nav-link" href="manage_orders.php"><i class="fa fa-file-invoice me-2"></i> รายการสั่งซื้อ</a>
            <a class="nav-link" href="manage_services.php"><i class="fa fa-tags me-2"></i> จัดการบริการ</a>
            <a class="nav-link" href="manage_members.php"><i class="fa fa-users me-2"></i> ข้อมูลลูกค้า</a>
            <a class="nav-link" href="manage_shop.php"><i class="fa fa-cog me-2"></i> ตั้งค่าร้านค้า</a>
            <hr>
            <a class="nav-link text-danger" href="../logout.php"><i class="fa fa-sign-out-alt me-2"></i> ออกจากระบบ</a>
        </nav>
    </div>

    <div class="main-content">
        <h2 class="fw-bold mb-4">จัดการคำขอรับผ้า</h2>

        <div class="row g-3">
            <?php if($res_reqs->num_rows > 0): ?>
                <?php while($rq = $res_reqs->fetch_assoc()): ?>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                        <div class="d-flex justify-content-between mb-3">
                            <h5 class="fw-bold mb-0"><?= $rq['m_name'] . ' ' . $rq['m_lastname'] ?></h5>
                            <span class="text-muted small"><?= $rq['rq_date'] ?></span>
                        </div>
                        <p class="mb-2"><i class="fa fa-phone me-2 text-primary"></i> <?= $rq['m_tel'] ?></p>
                        <p class="mb-3"><i class="fa fa-map-marker-alt me-2 text-danger"></i> พิกัด: <?= $rq['rq_latitude'] ?>, <?= $rq['rq_longitude'] ?></p>
                        <div class="alert alert-secondary py-2 small mb-4">
                            <strong>หมายเหตุ:</strong> <?= htmlspecialchars($rq['rq_note'] ?: '-') ?>
                        </div>
                        <div class="d-flex gap-2">
                            <form method="post" class="flex-grow-1">
                                <input type="hidden" name="rq_id" value="<?= $rq['rq_id'] ?>">
                                <input type="hidden" name="m_id" value="<?= $rq['m_id'] ?>">
                                <button type="submit" name="confirm_request" class="btn btn-primary w-100 rounded-pill">
                                    <i class="fa fa-check me-2"></i> ยืนยันรับผ้า
                                </button>
                            </form>
                            <form method="post" class="flex-grow-1">
                                <input type="hidden" name="rq_id" value="<?= $rq['rq_id'] ?>">
                                <button type="submit" name="reject_request" class="btn btn-outline-danger w-100 rounded-pill" onclick="return confirm('ปฏิเสธคำขอนี้?')">
                                    <i class="fa fa-times me-2"></i> ปฏิเสธ
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center py-5 text-muted bg-white rounded-4 shadow-sm">
                        <i class="fa fa-check-circle fa-4x mb-3 text-light"></i>
                        <p>ไม่มีคำขอที่ค้างอยู่</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
