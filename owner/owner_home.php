<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

// ดึงข้อมูลสรุป
$today = date('Y-m-d');
$new_requests = $conn->query("SELECT COUNT(*) FROM request WHERE rq_status = 0")->fetch_row()[0];
$active_orders = $conn->query("SELECT COUNT(*) FROM orders WHERE order_status IN (0, 1, 2)")->fetch_row()[0];
$today_income = $conn->query("SELECT SUM(IFNULL(order_sum, 0) + IFNULL(order_shipping_fee, 0)) FROM orders WHERE DATE(order_date) = '$today' AND order_status >= 3")->fetch_row()[0] ?? 0;

// ออเดอร์ล่าสุด
$res_latest = $conn->query("SELECT o.*, m.m_name FROM orders o JOIN member m ON o.m_id = m.m_id ORDER BY o.order_date DESC LIMIT 5");
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>หน้าแรกผู้ดูแลระบบ - Laundry Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .stat-card { 
            border: none; border-radius: 20px; padding: 25px; 
            transition: 0.3s; background: white; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .icon-circle { 
            width: 55px; height: 55px; border-radius: 15px; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 1.5rem; margin-bottom: 15px;
        }
        .table-custom { background: white; border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h2 class="fw-bold mb-4">ยินดีต้อนรับกลับมา 👋</h2>
        
        <div class="row g-4 mb-5">
            <!-- ยอดขายวันนี้ -->
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="icon-circle bg-primary bg-opacity-10 text-primary"><i class="fa fa-coins"></i></div>
                    <p class="text-muted small mb-1">รายได้วันนี้</p>
                    <h2 class="fw-bold mb-0 text-primary">฿<?= number_format($today_income, 2) ?></h2>
                </div>
            </div>
            <!-- คำขอใหม่ -->
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="icon-circle bg-danger bg-opacity-10 text-danger"><i class="fa fa-bell"></i></div>
                    <p class="text-muted small mb-1">คำขอรับผ้าใหม่</p>
                    <h2 class="fw-bold mb-0 text-danger"><?= $new_requests ?> <small class="fs-6 text-muted">รายการ</small></h2>
                </div>
            </div>
            <!-- งานที่กำลังทำ -->
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="icon-circle bg-warning bg-opacity-10 text-warning"><i class="fa fa-clock"></i></div>
                    <p class="text-muted small mb-1">งานที่กำลังดำเนินการ</p>
                    <h2 class="fw-bold mb-0 text-warning"><?= $active_orders ?> <small class="fs-6 text-muted">ออเดอร์</small></h2>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">รายการสั่งซื้อล่าสุด</h5>
                    <a href="manage_orders.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">ดูทั้งหมด</a>
                </div>
                <div class="table-custom p-3">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="text-muted small">
                            <tr><th>เลขที่ออเดอร์</th><th>ลูกค้า</th><th>ยอดรวม</th><th>สถานะ</th></tr>
                        </thead>
                        <tbody>
                            <?php while($row = $res_latest->fetch_assoc()): ?>
                            <tr onclick="location.href='order_process.php?id=<?= $row['order_id'] ?>'" style="cursor:pointer">
                                <td class="fw-bold"><?= $row['order_id'] ?></td>
                                <td><?= htmlspecialchars($row['m_name']) ?></td>
                                <td class="fw-bold">฿<?= number_format($row['order_sum'] + $row['order_shipping_fee'], 2) ?></td>
                                <td>
                                    <?php 
                                    $s_text = ['รอดำเนินการ', 'ซัก/รีด', 'รอจ่ายเงิน', 'จ่ายแล้ว', 'สำเร็จ'];
                                    $s_color = ['bg-secondary', 'bg-info', 'bg-warning text-dark', 'bg-primary', 'bg-success'];
                                    echo "<span class='badge {$s_color[$row['order_status']]} rounded-pill px-3'>{$s_text[$row['order_status']]}</span>";
                                    ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-lg-4">
                <h5 class="fw-bold mb-3">ทางลัดด่วน</h5>
                <div class="d-grid gap-2">
                    <a href="manage_requests.php" class="btn btn-white bg-white border-0 shadow-sm p-3 text-start rounded-4 d-flex align-items-center">
                        <div class="icon-circle bg-danger bg-opacity-10 text-danger mb-0 me-3" style="width:40px; height:40px; font-size:1rem"><i class="fa fa-plus"></i></div>
                        <div>
                            <div class="fw-bold small">ดูคำขอรับผ้า</div>
                            <div class="text-muted smaller">มีงานใหม่รอคุณอยู่</div>
                        </div>
                    </a>
                    <a href="manage_services.php" class="btn btn-white bg-white border-0 shadow-sm p-3 text-start rounded-4 d-flex align-items-center">
                        <div class="icon-circle bg-primary bg-opacity-10 text-primary mb-0 me-3" style="width:40px; height:40px; font-size:1rem"><i class="fa fa-tags"></i></div>
                        <div>
                            <div class="fw-bold small">จัดการราคา/บริการ</div>
                            <div class="text-muted smaller">ปรับปรุงเมนูผ้าและราคา</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
