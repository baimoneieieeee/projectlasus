<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

// 1. สรุปรายได้รายวัน/รายเดือน
$today = date('Y-m-d'); 
$month = date('Y-m');
$daily_income = $conn->query("SELECT SUM(order_sum + order_shipping_fee) as total FROM orders WHERE DATE(order_date) = '$today' AND order_status >= 3")->fetch_assoc()['total'] ?? 0;
$monthly_income = $conn->query("SELECT SUM(order_sum + order_shipping_fee) as total FROM orders WHERE order_date LIKE '$month%' AND order_status >= 3")->fetch_assoc()['total'] ?? 0;

// 2. สถิติสถานะออเดอร์ (สำหรับทำ Donut Chart)
$res_status = $conn->query("SELECT order_status, COUNT(*) as count FROM orders GROUP BY order_status");
$status_data = [0,0,0,0,0];
while($row = $res_status->fetch_assoc()) $status_data[$row['order_status']] = (int)$row['count'];

// 3. 5 อันดับบริการยอดนิยม
$popular_services = $conn->query("SELECT s.service_name, SUM(i.item_amount) as qty 
                                 FROM item i JOIN service s ON i.service_id = s.service_id 
                                 GROUP BY i.service_id ORDER BY qty DESC LIMIT 5");

// 4. รายชื่อลูกค้าที่ใช้บริการบ่อย
$top_customers = $conn->query("SELECT m.m_name, COUNT(o.order_id) as orders 
                               FROM orders o JOIN member m ON o.m_id = m.m_id 
                               GROUP BY o.m_id ORDER BY orders DESC LIMIT 5");
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>รายงานสรุปผล - Laundry System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary: #3B82F6; --bg: #F8FAFC; }
        body { background: var(--bg); font-family: system-ui; }
        .main-content { margin-left: 260px; padding: 40px; }
        .report-card { border: none; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .icon-shape { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        @media print { .sidebar, .no-print { display: none !important; } .main-content { margin-left: 0; padding: 0; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">📈 รายงานสรุปผลประมวลผล</h2>
                <p class="text-muted mb-0">ข้อมูลประจำวันที่ <?= date('d/m/Y H:i') ?></p>
            </div>
            <button onclick="window.print()" class="btn btn-dark rounded-pill px-4 no-print shadow-sm">
                <i class="fa fa-print me-2"></i> พิมพ์รายงานสรุป
            </button>
        </div>

        <!-- ยอดขายสรุป -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card report-card p-4">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-primary text-white me-3"><i class="fa fa-hand-holding-dollar fs-4"></i></div>
                        <div>
                            <p class="text-muted small mb-0">รายได้วันนี้</p>
                            <h3 class="fw-bold mb-0 text-primary">฿<?= number_format($daily_income, 2) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card report-card p-4">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-success text-white me-3"><i class="fa fa-calendar-check fs-4"></i></div>
                        <div>
                            <p class="text-muted small mb-0">รายได้เดือนนี้</p>
                            <h3 class="fw-bold mb-0 text-success">฿<?= number_format($monthly_income, 2) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card report-card p-4">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-warning text-dark me-3"><i class="fa fa-users fs-4"></i></div>
                        <div>
                            <p class="text-muted small mb-0">ลูกค้าทั้งหมด</p>
                            <h3 class="fw-bold mb-0"><?= $conn->query("SELECT COUNT(*) FROM member")->fetch_row()[0] ?> <small class="fs-6 text-muted">ราย</small></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- กราฟสถานะออเดอร์ -->
            <div class="col-lg-6">
                <div class="card report-card p-4 h-100">
                    <h5 class="fw-bold mb-4">📊 สัดส่วนสถานะงานซักอบรีด</h5>
                    <div style="max-height: 300px;"><canvas id="statusChart"></canvas></div>
                </div>
            </div>

            <!-- บริการยอดนิยม -->
            <div class="col-lg-6">
                <div class="card report-card p-4 h-100">
                    <h5 class="fw-bold mb-4">⭐ บริการยอดนิยม (ตามจำนวนชิ้น)</h5>
                    <div class="list-group list-group-flush">
                        <?php while($s = $popular_services->fetch_assoc()): ?>
                            <div class="list-group-item px-0 border-light d-flex justify-content-between align-items-center">
                                <div><i class="fa fa-shirt me-2 text-primary opacity-50"></i> <?= $s['service_name'] ?></div>
                                <span class="badge bg-light text-dark rounded-pill"><?= $s['qty'] ?> ชิ้น</span>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('statusChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['รอรับผ้า', 'กำลังซัก/รีด', 'รอจ่ายเงิน', 'จ่ายเงินแล้ว', 'สำเร็จ'],
                datasets: [{
                    data: <?= json_encode($status_data) ?>,
                    backgroundColor: ['#64748B', '#06B6D4', '#F59E0B', '#3B82F6', '#10B981'],
                    borderWidth: 0
                }]
            },
            options: { cutout: '70%', plugins: { legend: { position: 'bottom' } } }
        });
    </script>
</body>
</html>
