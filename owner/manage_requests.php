<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

// ยืนยันคำขอ (สร้างออเดอร์)
if (isset($_POST['confirm_request'])) {
    $rq_id = $_POST['rq_id'];
    $m_id = $_POST['m_id'];
    $date = date("Y-m-d H:i:s");

    $conn->begin_transaction();
    try {
        // 0. ดึงข้อมูลคำขอและที่อยู่ลูกค้า
        $rq_info = $conn->query("SELECT rq_sum, rq_note FROM request WHERE rq_id = '$rq_id'")->fetch_assoc();
        $initial_sum = $rq_info['rq_sum'] ?? 0;
        $rq_note = $rq_info['rq_note'] ?? '';
        
        $m_info = $conn->query("SELECT m_address FROM member WHERE m_id = '$m_id'")->fetch_assoc();
        $address = $conn->real_escape_string($m_info['m_address'] ?? '');

        // เช็คว่าเป็นคำขอสมัครสมาชิกหรือไม่
        $is_pkg = (strpos($rq_note, 'สมัครแพ็กเกจ') !== false);
        $order_id = ($is_pkg ? "PKG" : "ORD") . time();

        // 1. อัปเดตสถานะคำขอเป็น 'ยืนยันแล้ว' (1)
        $conn->query("UPDATE request SET rq_status = 1 WHERE rq_id = '$rq_id'");

        // 2. คำนวณลำดับรอบ (ถ้าเป็นงานซักปกติ)
        $round_num = 0;
        if (!$is_pkg) {
            $json_file = __DIR__ . '/../config/monthly_data.json';
            if (file_exists($json_file)) {
                $monthly_data = json_decode(file_get_contents($json_file), true);
                if (isset($monthly_data[$m_id])) {
                    $round_num = ($monthly_data[$m_id]['pickups_used'] ?? 0) + 1;
                    $monthly_data[$m_id]['pickups_used'] = $round_num;
                    file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));
                }
            }
        }

        // 3. สร้างออเดอร์ใหม่
        $order_status = $is_pkg ? 0 : 1;
        $order_note = $is_pkg ? $rq_note : "";

        $conn->query("INSERT INTO orders (order_id, m_id, rq_id, order_date, order_status, order_sum, order_note, order_pickup_round, order_address) 
                      VALUES ('$order_id', '$m_id', '$rq_id', '$date', '$order_status', '$initial_sum', '$order_note', '$round_num', '$address')");
        
        $conn->commit();
        header("Location: manage_orders.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }
}

// ปฏิเสธคำขอ
if (isset($_POST['reject_request'])) {
    $rq_id = $_POST['rq_id'];
    $conn->query("UPDATE request SET rq_status = 2 WHERE rq_id = '$rq_id'");
    header("Location: manage_requests.php");
    exit;
}

// ลบคำขอถาวร
if (isset($_GET['delete_rq'])) {
    $rq_id = $conn->real_escape_string($_GET['delete_rq']);
    $conn->query("DELETE FROM request WHERE rq_id = '$rq_id'");
    header("Location: manage_requests.php");
    exit;
}

$sql_reqs = "SELECT r.*, m.m_name, m.m_lastname, m.m_tel, m.m_address, m.m_latitude, m.m_longitude FROM request r JOIN member m ON r.m_id = m.m_id WHERE r.rq_status = 0 ORDER BY r.rq_date DESC";
$res_reqs = $conn->query($sql_reqs);

$sql_history = "SELECT r.*, m.m_name, m.m_lastname, m.m_latitude, m.m_longitude FROM request r JOIN member m ON r.m_id = m.m_id WHERE r.rq_status != 0 ORDER BY r.rq_date DESC LIMIT 10";
$res_history = $conn->query($sql_history);

$json_file = __DIR__ . '/../config/monthly_data.json';
$monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการคำขอ - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-light">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h2 class="fw-bold mb-4"><i class="fa fa-bell text-primary me-2"></i> จัดการคำขอ (งานซัก/สมัครสมาชิก)</h2>

        <div class="row g-3">
            <?php if($res_reqs->num_rows > 0): ?>
                <?php while($rq = $res_reqs->fetch_assoc()): 
                    $m_id = $rq['m_id'];
                    $is_monthly = isset($monthly_data[$m_id]);
                    $is_membership = (strpos($rq['rq_note'], 'สมัครแพ็กเกจ') !== false);
                ?>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($rq['m_name'] . ' ' . $rq['m_lastname']) ?></h5>
                                <?php if($is_membership): ?>
                                    <span class="badge bg-primary rounded-pill"><i class="fa fa-id-card me-1"></i> คำขอสมัครสมาชิก</span>
                                <?php elseif($is_monthly): ?>
                                    <span class="badge bg-warning text-dark rounded-pill"><i class="fa fa-star me-1"></i> งานซัก (สมาชิกรรายเดือน)</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border rounded-pill"><i class="fa fa-user me-1"></i> งานซัก (ลูกค้าทั่วไป)</span>
                                <?php endif; ?>
                            </div>
                            <span class="text-muted small"><?= date('H:i', strtotime($rq['rq_date'])) ?></span>
                        </div>
                        
                        <div class="bg-light rounded-3 p-3 mb-4 border-start border-4 <?= $is_membership ? 'border-primary' : 'border-info' ?>">
                            <div class="fw-bold small mb-1">รายละเอียด:</div>
                            <div class="text-dark small" style="white-space: pre-wrap;"><?= htmlspecialchars($rq['rq_note']) ?></div>
                            <?php if($rq['rq_sum'] > 0): ?>
                                <div class="mt-2 fw-bold text-primary">ยอดเงินประมาณการ: ฿<?= number_format($rq['rq_sum']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="small text-muted mb-4">
                            <i class="fa fa-phone me-1"></i> <?= $rq['m_tel'] ?><br>
                            <i class="fa fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($rq['m_address']) ?>
                            <?php 
                                $lat = $rq['rq_latitude'] ?: $rq['m_latitude'];
                                $lng = $rq['rq_longitude'] ?: $rq['m_longitude'];
                                if ($lat && $lng): 
                            ?>
                                <div class="mt-2">
                                    <a href="https://www.google.com/maps?q=<?= $lat ?>,<?= $lng ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="fa fa-map-marked-alt me-1"></i> ดูแผนที่ลูกค้า
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex gap-2 mt-auto">
                            <form method="post" class="flex-grow-1">
                                <input type="hidden" name="rq_id" value="<?= $rq['rq_id'] ?>">
                                <input type="hidden" name="m_id" value="<?= $rq['m_id'] ?>">
                                <button type="submit" name="confirm_request" class="btn btn-primary w-100 rounded-pill fw-bold py-2 shadow-sm">
                                    <i class="fa fa-check me-1"></i> ยืนยันรับเรื่อง
                                </button>
                            </form>
                            <form method="post" style="width: 100px;">
                                <input type="hidden" name="rq_id" value="<?= $rq['rq_id'] ?>">
                                <button type="submit" name="reject_request" class="btn btn-outline-danger w-100 rounded-pill py-2 shadow-sm" onclick="return confirm('ปฏิเสธคำขอนี้?')">
                                    <i class="fa fa-times me-1"></i> ปฏิเสธ
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 text-muted bg-white rounded-4 shadow-sm border">
                    <i class="fa fa-check-circle fa-4x mb-3 opacity-25"></i>
                    <p class="h5">ไม่มีคำขอค้างอยู่ในขณะนี้</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ประวัติ (ย่อ) -->
        <div class="mt-5">
            <h5 class="fw-bold mb-3">📜 ประวัติล่าสุด</h5>
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>วันที่</th><th>ลูกค้า</th><th>ประเภท</th><th>สถานะ</th></tr>
                    </thead>
                    <tbody>
                        <?php while($h = $res_history->fetch_assoc()): 
                            $is_m_h = (strpos($h['rq_note'], 'สมัครแพ็กเกจ') !== false);
                        ?>
                        <tr>
                            <td class="small"><?= $h['rq_date'] ?></td>
                            <td class="fw-bold">
                                <?= $h['m_name'].' '.$h['m_lastname'] ?>
                                <?php 
                                    $h_lat = $h['rq_latitude'] ?: $h['m_latitude'];
                                    $h_lng = $h['rq_longitude'] ?: $h['m_longitude'];
                                    if ($h_lat && $h_lng): ?>
                                    <a href="https://www.google.com/maps?q=<?= $h_lat ?>,<?= $h_lng ?>" target="_blank" class="text-primary ms-2" title="ดูแผนที่">
                                        <i class="fa fa-map-marker-alt"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td><?= $is_m_h ? 'สมัครสมาชิก' : 'งานซัก' ?></td>
                            <td><?= $h['rq_status'] == 1 ? '<span class="text-success">รับแล้ว</span>' : '<span class="text-danger">ปฏิเสธ</span>' ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
