<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

$shop = $_SESSION['user'];

// ดึงสถิติต่างๆ
$count_pending = $conn->query("SELECT COUNT(*) FROM request WHERE rq_status = 0")->fetch_row()[0];
$count_washing = $conn->query("SELECT COUNT(*) FROM orders WHERE order_status = 1")->fetch_row()[0];
$count_payment = $conn->query("SELECT COUNT(*) FROM orders WHERE order_status = 2")->fetch_row()[0];
$count_total_order = $conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0];

// ดึงคำขอรับผ้าที่รอดำเนินการ
$sql_reqs = "SELECT r.*, m.m_name, m.m_lastname FROM request r JOIN member m ON r.m_id = m.m_id WHERE r.rq_status = 0 ORDER BY r.rq_date DESC";
$res_reqs = $conn->query($sql_reqs);

// ดึงรายการสั่งซื้อล่าสุด
$sql_orders = "SELECT o.*, m.m_name, m.m_lastname FROM orders o JOIN member m ON o.m_id = m.m_id ORDER BY o.order_date DESC LIMIT 5";
$res_orders = $conn->query($sql_orders);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แดชบอร์ดเจ้าของร้าน - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">ภาพรวมระบบ</h2>
            <span class="text-muted"><?= date("d/m/Y") ?></span>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card card-stat p-3 bg-white">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">คำขอใหม่</p>
                            <h3 class="fw-bold mb-0"><?= $count_pending ?></h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-2 rounded text-warning"><i class="fa fa-bell"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat p-3 bg-white">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">กำลังดำเนินการ</p>
                            <h3 class="fw-bold mb-0"><?= $count_washing ?></h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-2 rounded text-primary"><i class="fa fa-spinner fa-spin"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat p-3 bg-white">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">รอจ่ายเงิน</p>
                            <h3 class="fw-bold mb-0"><?= $count_payment ?></h3>
                        </div>
                        <div class="bg-info bg-opacity-10 p-2 rounded text-info"><i class="fa fa-hand-holding-usd"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat p-3 bg-white">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">รวมทั้งหมด</p>
                            <h3 class="fw-bold mb-0"><?= $count_total_order ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 p-2 rounded text-success"><i class="fa fa-check-circle"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0">📦 รายการสั่งซื้อล่าสุด</h5>
                        <a href="manage_orders.php" class="small text-decoration-none">ดูทั้งหมด</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light"><tr><th>ID</th><th>ลูกค้า</th><th>สถานะ</th><th>ยอดรวม</th></tr></thead>
                            <tbody>
                                <?php while($o = $res_orders->fetch_assoc()): ?>
                                <tr>
                                    <td class="small fw-bold"><?= $o['order_id'] ?></td>
                                    <td class="small"><?= $o['m_name'] ?></td>
                                    <td>
                                        <?php 
                                        $status = ['รอดำเนินการ', 'กำลังซัก/อบ/รีด', 'รอจ่ายเงิน', 'จ่ายเงินแล้ว', 'จัดส่งคืนแล้ว'];
                                        $colors = ['bg-secondary', 'bg-info', 'bg-warning text-dark', 'bg-primary', 'bg-success'];
                                        echo "<span class='badge {$colors[$o['order_status']]} small'>{$status[$o['order_status']]}</span>";
                                        ?>
                                    </td>
                                    <td class="small fw-bold">฿<?= number_format($o['order_sum'], 2) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <h5 class="fw-bold mb-3">🔔 คำขอรับผ้า (รอยืนยัน)</h5>
                    <?php if($res_reqs->num_rows > 0): ?>
                        <?php while($rq = $res_reqs->fetch_assoc()): ?>
                        <div class="d-flex align-items-center mb-3 p-2 bg-light rounded-3">
                            <div class="me-3">
                                <div class="bg-white p-2 rounded shadow-sm text-primary"><i class="fa fa-truck-pickup"></i></div>
                            </div>
                            <div class="flex-grow-1">
                                <p class="mb-0 fw-bold small"><?= $rq['m_name'] . ' ' . $rq['m_lastname'] ?></p>
                                <p class="mb-0 text-muted smaller" style="font-size: 0.75rem;"><?= $rq['rq_date'] ?></p>
                            </div>
                            <a href="manage_requests.php" class="btn btn-primary btn-sm rounded-pill">จัดการ</a>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">ไม่มีคำขอใหม่</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
