<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

$sql_orders = "SELECT o.*, m.m_name, m.m_lastname FROM orders o JOIN member m ON o.m_id = m.m_id ORDER BY o.order_date DESC";
$res_orders = $conn->query($sql_orders);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>รายการสั่งซื้อ - Laundry</title>
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
            <a class="nav-link" href="manage_requests.php"><i class="fa fa-bell me-2"></i> คำขอรับผ้า</a>
            <a class="nav-link active" href="manage_orders.php"><i class="fa fa-file-invoice me-2"></i> รายการสั่งซื้อ</a>
            <a class="nav-link" href="manage_services.php"><i class="fa fa-tags me-2"></i> จัดการบริการ</a>
            <a class="nav-link" href="manage_members.php"><i class="fa fa-users me-2"></i> ข้อมูลลูกค้า</a>
            <a class="nav-link" href="manage_shop.php"><i class="fa fa-cog me-2"></i> ตั้งค่าร้านค้า</a>
            <hr>
            <a class="nav-link text-danger" href="../logout.php"><i class="fa fa-sign-out-alt me-2"></i> ออกจากระบบ</a>
        </nav>
    </div>

    <div class="main-content">
        <h2 class="fw-bold mb-4">รายการสั่งซื้อทั้งหมด</h2>

        <div class="card border-0 shadow-sm rounded-4 p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>เลขที่สั่งซื้อ</th><th>วันที่</th><th>ลูกค้า</th><th>สถานะ</th><th>ยอดรวม</th><th class="text-center">จัดการ</th></tr>
                    </thead>
                    <tbody>
                        <?php while($o = $res_orders->fetch_assoc()): ?>
                        <tr>
                            <td class="small fw-bold text-primary"><?= $o['order_id'] ?></td>
                            <td class="small"><?= $o['order_date'] ?></td>
                            <td class="small"><?= $o['m_name'] . ' ' . $o['m_lastname'] ?></td>
                            <td>
                                <?php 
                                $status = ['รอดำเนินการ', 'กำลังซัก/อบ/รีด', 'รอจ่ายเงิน', 'จ่ายเงินแล้ว', 'จัดส่งคืนแล้ว'];
                                $colors = ['bg-secondary', 'bg-info', 'bg-warning text-dark', 'bg-primary', 'bg-success'];
                                echo "<span class='badge {$colors[$o['order_status']]} small'>{$status[$o['order_status']]}</span>";
                                ?>
                            </td>
                            <td class="fw-bold">฿<?= number_format($o['order_sum'], 2) ?></td>
                            <td class="text-center">
                                <a href="order_process.php?id=<?= $o['order_id'] ?>" class="btn btn-primary btn-sm rounded-pill px-3">
                                    <i class="fa fa-cog me-1"></i> ดำเนินการ
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
