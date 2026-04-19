<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

// ตรวจสอบว่ามีการกรองตามรายลูกค้า หรือสถานะหรือไม่
$m_id = isset($_GET['m_id']) ? $conn->real_escape_string($_GET['m_id']) : '';
$st_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$where_clauses = [];
if ($m_id) $where_clauses[] = "o.m_id = '$m_id'";

if ($st_filter === 'pending') $where_clauses[] = "o.order_status = 0";
elseif ($st_filter === 'verify') $where_clauses[] = "o.order_status = 1 AND (SELECT COUNT(*) FROM payment p WHERE p.order_id = o.order_id) > 0";
elseif ($st_filter === 'washing') $where_clauses[] = "o.order_status = 2";
elseif ($st_filter === 'delivery') $where_clauses[] = "o.order_status = 3";
elseif ($st_filter === 'completed') $where_clauses[] = "o.order_status = 4";

$where = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// ดึงจำนวนนับแยกตามสถานะสำหรับตัวเลขบน Tab
$counts = [
    'all' => $conn->query("SELECT COUNT(*) as total FROM orders")->fetch_assoc()['total'],
    'pending' => $conn->query("SELECT COUNT(*) as total FROM orders WHERE order_status = 0")->fetch_assoc()['total'],
    'verify' => $conn->query("SELECT COUNT(*) as total FROM orders o WHERE o.order_status = 1 AND (SELECT COUNT(*) FROM payment p WHERE p.order_id = o.order_id) > 0")->fetch_assoc()['total'],
    'washing' => $conn->query("SELECT COUNT(*) as total FROM orders WHERE order_status = 2")->fetch_assoc()['total'],
    'delivery' => $conn->query("SELECT COUNT(*) as total FROM orders WHERE order_status = 3")->fetch_assoc()['total']
];

// ดึงข้อมูลชื่อลูกค้ากรณีมีการกรอง
$filter_name = "";
if($m_id) {
    $cust = $conn->query("SELECT m_name, m_lastname FROM member WHERE m_id = '$m_id'")->fetch_assoc();
    $filter_name = $cust ? "ของ " . $cust['m_name'] . " " . $cust['m_lastname'] : "";
}

// ลบออเดอร์
if (isset($_GET['delete_id'])) {
    $del_id = $conn->real_escape_string($_GET['delete_id']);
    if ($conn->query("DELETE FROM orders WHERE order_id = '$del_id'")) {
        header("Location: manage_orders.php" . ($m_id ? "?m_id=$m_id" : ""));
        exit;
    }
}

$sql_orders = "SELECT o.*, m.m_name, m.m_lastname, 
                (SELECT COUNT(*) FROM payment p WHERE p.order_id = o.order_id) as pay_count 
                FROM orders o JOIN member m ON o.m_id = m.m_id $where ORDER BY o.order_date DESC";
$res_orders = $conn->query($sql_orders);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>รายการสั่งซื้อ <?= $filter_name ?> - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background: #F8FAFC; font-family: 'Sarabun', system-ui, sans-serif; }
        .card-custom { border-radius: 16px; border: none; }
        .badge-status { padding: 8px 16px; border-radius: 999px; font-weight: 700; font-size: 0.75rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .main-content { margin-left: 280px; padding: 40px; }
        .ring-animation { animation: pulse 2s infinite; border: 2px solid #ffc107; }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
        }
        .btn-white { background: white; border-color: #E2E8F0; }
        .scroll-hide::-webkit-scrollbar { display: none; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0 text-dark">📦 รายการสั่งซื้อ <?= $filter_name ?></h2>
                <p class="text-muted small mb-0">จัดการสถานะงานซักและตรวจสอบการชำระเงินของลูกค้า</p>
            </div>
            <?php if($m_id): ?>
                <a href="manage_orders.php" class="btn btn-outline-secondary btn-sm rounded-pill px-4">
                    <i class="fa fa-list me-2"></i> ดูออเดอร์ทั้งหมด
                </a>
            <?php endif; ?>
        </div>

        <!-- 📑 แถบกรองสถานะ (Status Tabs) -->
        <div class="d-flex gap-2 mb-4 overflow-auto pb-2 scroll-hide">
            <?php 
            $tabs = [
                ['id' => 'all', 'label' => 'ทั้งหมด', 'icon' => 'list', 'color' => 'secondary'],
                ['id' => 'pending', 'label' => 'งานใหม่/รอรับ', 'icon' => 'truck-pickup', 'color' => 'dark'],
                ['id' => 'verify', 'label' => 'รอตรวจสลิป', 'icon' => 'clock', 'color' => 'warning'],
                ['id' => 'washing', 'label' => 'กำลังซัก', 'icon' => 'soap', 'color' => 'info'],
                ['id' => 'delivery', 'label' => 'รอส่งคืน', 'icon' => 'truck', 'color' => 'primary'],
                ['id' => 'completed', 'label' => 'สำเร็จ', 'icon' => 'check-double', 'color' => 'success']
            ];
            foreach($tabs as $t): 
                $active = ($st_filter === $t['id']) ? 'btn-'.$t['color'].' shadow-sm fw-bold' : 'btn-white text-muted border';
                $count_val = isset($counts[$t['id']]) ? " <span class='badge bg-white text-".$t['color']." ms-1 rounded-pill'>".$counts[$t['id']]."</span>" : "";
            ?>
                <a href="?status=<?= $t['id'] ?><?= $m_id ? '&m_id='.$m_id : '' ?>" class="btn rounded-pill px-3 text-nowrap <?= $active ?>">
                    <i class="fa fa-<?= $t['icon'] ?> me-2"></i><?= $t['label'] ?><?= $count_val ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="card card-custom p-4 border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr class="small text-muted">
                            <th>เลขที่สั่งซื้อ</th>
                            <th>วันที่แจ้ง</th>
                            <th>ลูกค้า</th>
                            <th>สถานะงาน</th>
                            <th>ยอดเงินสุทธิ</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($res_orders->num_rows > 0): ?>
                            <?php while($o = $res_orders->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= $o['order_id'] ?></td>
                                <td class="small"><?= date('d/m/Y H:i', strtotime($o['order_date'])) ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($o['m_name'] . ' ' . $o['m_lastname']) ?></div>
                                </td>
                                <td>
                                    <?php 
                                    $s_text = [
                                        0 => 'รอร้านไปรับผ้า', 
                                        1 => 'รอการชำระเงิน', 
                                        2 => 'กำลังซัก/อบ/รีด', 
                                        3 => 'ซักเสร็จ/รอส่งคืน', 
                                        4 => 'สำเร็จ/ส่งคืนแล้ว'
                                    ];
                                    $s_color = [
                                        0 => 'bg-dark', 
                                        1 => 'bg-warning text-dark', 
                                        2 => 'bg-info', 
                                        3 => 'bg-primary', 
                                        4 => 'bg-success'
                                    ];
                                    
                                    if ($o['order_status'] == 1 && $o['pay_count'] > 0) {
                                        echo "<span class='badge bg-warning text-dark border border-warning badge-status ring-animation'><i class='fa fa-clock me-1'></i> รอตรวจสลิปโอนเงิน</span>";
                                    } else {
                                        $idx = (int)$o['order_status'];
                                        echo "<span class='badge {$s_color[$idx]} badge-status'><i class='fa fa-circle me-1 small opacity-50'></i> {$s_text[$idx]}</span>";
                                    }
                                    ?>
                                </td>
                                <td class="fw-bold text-dark">฿<?= number_format($o['order_sum'], 2) ?></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="order_process.php?id=<?= $o['order_id'] ?>" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                                            <i class="fa fa-search me-1"></i> รายละเอียด
                                        </a>
                                        <a href="?delete_id=<?= $o['order_id'] ?><?= $m_id ? '&m_id='.$m_id : '' ?>" 
                                           class="btn btn-outline-danger btn-sm rounded-circle shadow-sm"
                                           onclick="return confirm('ยืนยันการลบออเดอร์นี้? ข้อมูลไอเทมและสถานะจะหายทั้งหมด')">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">ไม่พบรายการสั่งซื้อในขณะนี้</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
