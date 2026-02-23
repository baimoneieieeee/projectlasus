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
        
        // 3. ถ้านำทางข้อมูลลูกค้ารายเดือน ให้นับครั้งที่รับผ้าเพิ่ม
        $json_file = __DIR__ . '/../config/monthly_data.json';
        if (file_exists($json_file)) {
            $monthly_data = json_decode(file_get_contents($json_file), true);
            if (isset($monthly_data[$m_id])) {
                if (!isset($monthly_data[$m_id]['pickups_used'])) {
                    $monthly_data[$m_id]['pickups_used'] = 0;
                }
                $monthly_data[$m_id]['pickups_used'] += 1; // นับเพิ่ม 1 ครั้ง
                file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));
            }
        }
        
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

$sql_reqs = "SELECT r.*, m.m_name, m.m_lastname, m.m_tel, m.m_address FROM request r JOIN member m ON r.m_id = m.m_id WHERE r.rq_status = 0 ORDER BY r.rq_date DESC";
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
</head>
<body>
    <?php include 'sidebar.php'; ?>

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
                        <p class="mb-2"><i class="fa fa-home me-2 text-success"></i> <?= nl2br(htmlspecialchars($rq['m_address'])) ?></p>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <p class="mb-0"><i class="fa fa-map-marker-alt me-2 text-danger"></i> พิกัด: <?= $rq['rq_latitude'] ?>, <?= $rq['rq_longitude'] ?></p>
                            <?php if($rq['rq_latitude'] && $rq['rq_longitude']): ?>
                                <a href="https://www.google.com/maps?q=<?= $rq['rq_latitude'] ?>,<?= $rq['rq_longitude'] ?>" target="_blank" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                    <i class="fa fa-map me-1"></i> เปิดแผนที่
                                </a>
                            <?php endif; ?>
                        </div>
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
