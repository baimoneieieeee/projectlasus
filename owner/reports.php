<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

// รายงานรายได้รายเดือน
$sql_monthly = "SELECT DATE_FORMAT(order_date, '%Y-%m') as month, SUM(order_sum) as total 
                FROM orders WHERE order_status >= 3 
                GROUP BY month ORDER BY month DESC";
$res_monthly = $conn->query($sql_monthly);

// รายงานประเภทบริการที่นิยม
$sql_popular = "SELECT s.service_name, COUNT(i.item_id) as count 
                FROM item i JOIN service s ON i.service_id = s.service_id 
                GROUP BY s.service_id ORDER BY count DESC LIMIT 5";
$res_popular = $conn->query($sql_popular);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>รายงาน - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h2 class="fw-bold mb-4">รายงานสรุปผล</h2>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h5 class="fw-bold mb-3"><i class="fa fa-money-bill-wave me-2 text-success"></i> รายได้รายเดือน</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>เดือน</th><th>รายได้รวม</th></tr></thead>
                            <tbody>
                                <?php while($row = $res_monthly->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['month'] ?></td>
                                    <td class="fw-bold text-primary">฿<?= number_format($row['total'], 2) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h5 class="fw-bold mb-3"><i class="fa fa-star me-2 text-warning"></i> 5 อันดับบริการยอดนิยม</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>บริการ</th><th>จำนวนครั้ง</th></tr></thead>
                            <tbody>
                                <?php while($row = $res_popular->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['service_name'] ?></td>
                                    <td class="fw-bold"><?= $row['count'] ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
