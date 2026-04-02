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
$daily_income = $conn->query("SELECT SUM(IFNULL(order_sum, 0) + IFNULL(order_shipping_fee, 0)) as total FROM orders WHERE DATE(order_date) = '$today' AND order_status >= 3")->fetch_assoc()['total'] ?? 0;
$monthly_income = $conn->query("SELECT SUM(IFNULL(order_sum, 0) + IFNULL(order_shipping_fee, 0)) as total FROM orders WHERE order_date LIKE '$month%' AND order_status >= 3")->fetch_assoc()['total'] ?? 0;

// 2. สถิติสถานะออเดอร์ (สำหรับทำ Donut Chart)
// 0: รอรับผ้า, 1: รอจ่ายเงิน, 2: กำลังซัก/รีด, 3: เตรียมส่งคืน, 4: สำเร็จ
$res_status = $conn->query("SELECT order_status, COUNT(*) as count FROM orders GROUP BY order_status");
$status_data = [0,0,0,0,0];
while($row = $res_status->fetch_assoc()) {
    if(isset($status_data[$row['order_status']])) {
        $status_data[$row['order_status']] = (int)$row['count'];
    }
}

// 3. 5 อันดับบริการยอดนิยม
$popular_services = $conn->query("SELECT s.service_name, SUM(i.item_amount) as qty 
                                 FROM item i JOIN service s ON i.service_id = s.service_id 
                                 GROUP BY i.service_id ORDER BY qty DESC LIMIT 5");

// 4. รายชื่อลูกค้าที่ใช้บริการบ่อย
$top_customers = $conn->query("SELECT m.m_name, m.m_lastname, COUNT(o.order_id) as orders 
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
        @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&display=swap');
        :root { --primary: #3B82F6; --bg: #F8FAFC; }
        body { background: var(--bg); font-family: 'Chakra Petch', sans-serif; }
        .main-content { margin-left: 280px; padding: 40px; }
        .report-card { border: none; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: white; }
        .icon-shape { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .list-group-item { border: none; padding: 12px 0; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 80px 20px 20px 20px; } }
        @media print { .sidebar, .no-print { display: none !important; } .main-content { margin-left: 0; padding: 0; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">📈 รายงานสรุปผลประมวลผล</h2>
                <p class="text-muted mb-0">ข้อมูลล่าสุด ณ วันที่ <?= date('d/m/Y H:i') ?></p>
            </div>
            <button onclick="window.print()" class="btn btn-dark rounded-pill px-4 no-print shadow-sm">
                <i class="fa fa-print me-2"></i> พิมพ์รายงานสรุป
            </button>
        </div>

        <!-- ยอดขายสรุป -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card report-card p-4 border-start border-primary border-5">
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
                <div class="card report-card p-4 border-start border-success border-5">
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
                <div class="card report-card p-4 border-start border-warning border-5">
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

        <div class="row g-4 mb-5">
            <!-- กราฟสถานะออเดอร์ -->
            <div class="col-lg-7">
                <div class="card report-card p-4 h-100">
                    <h5 class="fw-bold mb-4 text-dark"><i class="fa fa-chart-pie me-2 text-primary"></i> สัดส่วนสถานะงานซักอบรีด</h5>
                    <div style="height: 350px;" class="d-flex align-items-center justify-content-center">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- บริการยอดนิยม -->
            <div class="col-lg-5">
                <div class="card report-card p-4 h-100">
                    <h5 class="fw-bold mb-4 text-dark"><i class="fa fa-star me-2 text-warning"></i> 5 อันดับบริการยอดนิยม</h5>
                    <div class="list-group list-group-flush">
                        <?php if($popular_services->num_rows > 0): ?>
                            <?php while($s = $popular_services->fetch_assoc()): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center border-bottom border-light">
                                    <div><i class="fa fa-shirt me-2 text-primary opacity-50"></i> <?= htmlspecialchars($s['service_name']) ?></div>
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3"><?= number_format($s['qty']) ?> ชิ้น</span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-center py-5 text-muted small">ยังไม่มีข้อมูลการใช้บริการ</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- ลูกค้าดีเด่น -->
            <div class="col-12">
                <div class="card report-card p-4">
                    <h5 class="fw-bold mb-4 text-dark"><i class="fa fa-crown me-2 text-warning"></i> ลูกค้าที่ใช้บริการบ่อยที่สุด</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">ชื่อ-นามสกุล</th>
                                    <th class="text-center">จำนวนครั้งที่ใช้บริการ</th>
                                    <th class="text-end pe-4">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($top_customers->num_rows > 0): ?>
                                    <?php while($tc = $top_customers->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($tc['m_name']) ?>&background=random" class="rounded-circle me-3" width="35">
                                                <div class="fw-bold"><?= htmlspecialchars($tc['m_name'].' '.$tc['m_lastname']) ?></div>
                                            </div>
                                        </td>
                                        <td class="text-center fw-bold text-primary"><?= number_format($tc['orders']) ?> ครั้ง</td>
                                        <td class="text-end pe-4"><span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">Active Customer</span></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center py-4 text-muted">ยังไม่มีข้อมูลลูกค้า</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
                labels: ['รอรับผ้า', 'รอจ่ายเงิน', 'กำลังซัก/รีด', 'เตรียมส่งคืน', 'สำเร็จ'],
                datasets: [{
                    data: <?= json_encode($status_data) ?>,
                    backgroundColor: ['#64748B', '#F59E0B', '#06B6D4', '#3B82F6', '#10B981'],
                    borderWidth: 5,
                    borderColor: '#ffffff',
                    hoverOffset: 20
                }]
            },
            options: { 
                cutout: '70%', 
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { 
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: { family: 'Chakra Petch', size: 12 }
                        }
                    } 
                } 
            }
        });
    </script>
</body>
</html>
