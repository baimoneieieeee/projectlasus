<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

$shop = $conn->query("SELECT * FROM shop LIMIT 1")->fetch_assoc();

// 1. จัดการการกรองวันที่
$date_start = $_GET['start_date'] ?? date('Y-m-01'); 
$date_end = $_GET['end_date'] ?? date('Y-m-d');    

// 1.1 จัดการการบันทึกค่าใช้จ่าย
if (isset($_POST['add_expense'])) {
    $exp_name = $conn->real_escape_string($_POST['exp_name']);
    $exp_amount = (float)$_POST['exp_amount'];
    $exp_date = $_POST['exp_date'] ?: date('Y-m-d H:i:s');
    $exp_note = $conn->real_escape_string($_POST['exp_note'] ?? '');
    
    // บันทึกลงตาราง payment โดยระบุ payment_type = 1 (รายจ่าย)
    $sql_add = "INSERT INTO payment (payment_name, payment_amount, payment_date, payment_note, payment_type) 
                VALUES ('$exp_name', $exp_amount, '$exp_date', '$exp_note', 1)";
    $conn->query($sql_add);
    header("Location: reports.php?start_date=$date_start&end_date=$date_end");
    exit;
}

// 1.2 ลบค่าใช้จ่าย
if (isset($_GET['del_exp'])) {
    $exp_id = (int)$_GET['del_exp'];
    // ลบจากตาราง payment โดยต้องเป็นรายจ่าย (type=1) เท่านั้นเพื่อความปลอดภัย
    $conn->query("DELETE FROM payment WHERE payment_id = $exp_id AND payment_type = 1");
    header("Location: reports.php?start_date=$date_start&end_date=$date_end");
    exit;
}

// 2. ดึงรายการเดินบัญชีละเอียด (ออเดอร์)
$sql_ledger = "SELECT o.*, m.m_name, m.m_lastname 
               FROM orders o 
               JOIN member m ON o.m_id = m.m_id 
               WHERE DATE(o.order_date) BETWEEN '$date_start' AND '$date_end'
               AND o.order_status >= 1
               ORDER BY o.order_date DESC";
$res_ledger = $conn->query($sql_ledger);

// 2.1 ดึงรายการค่าใช้จ่าย จากตาราง payment (type=1)
$sql_exp = "SELECT payment_id as exp_id, payment_name as exp_name, payment_amount as exp_amount, 
                   payment_date as exp_date, payment_note as exp_note 
            FROM payment 
            WHERE payment_type = 1 
            AND DATE(payment_date) BETWEEN '$date_start' AND '$date_end'
            ORDER BY payment_date DESC";
$res_exp = $conn->query($sql_exp);

// 3. คำนวณยอดสรุป (รายรับ)
$total_base = 0; $total_extra = 0; $total_shipping = 0; $total_grand = 0; $order_count = 0;
$ledger_data = [];
while($row = $res_ledger->fetch_assoc()) {
    $shipping = (float)($row['order_shipping_fee'] ?? 0);
    $extra = (float)($row['order_extra_cash'] ?? 0);
    $net = (float)$row['order_sum'];
    $base = $net - $shipping - $extra;
    $total_base += $base; $total_extra += $extra; $total_shipping += $shipping; $total_grand += $net;
    $order_count++;
    $ledger_data[] = array_merge($row, ['base'=>$base, 'extra'=>$extra, 'shipping'=>$shipping]);
}

// 4. คำนวณยอดสรุป (ค่าใช้จ่าย)
$total_expense = 0;
$expense_data = [];
while($row = $res_exp->fetch_assoc()) {
    $total_expense += (float)$row['exp_amount'];
    $expense_data[] = $row;
}

$net_profit = $total_grand - $total_expense;
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>Accounting Dashboard - <?= htmlspecialchars($shop['shop_name1']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&family=Sarabun:wght@300;400;600;700&display=swap');
        :root { 
            --primary: #4f46e5; --secondary: #64748b; --success: #10b981; 
            --danger: #ef4444; --warning: #f59e0b; --bg: #f8fafc;
        }
        body { background: var(--bg); font-family: 'Sarabun', sans-serif; color: #1e293b; }
        .main-content { margin-left: 280px; padding: 40px; transition: 0.3s; }
        
        /* Dashboard Styling */
        .glass-card { 
            background: white; border: none; border-radius: 24px; 
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.04), 0 4px 6px -2px rgba(0,0,0,0.02);
            transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.8);
        }
        .glass-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.06); }
        
        .stat-icon {
            width: 56px; height: 56px; border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin-bottom: 15px;
        }
        
        .btn-modern {
            padding: 12px 28px; border-radius: 16px; font-weight: 700;
            letter-spacing: 0.5px; transition: 0.3s; border: none;
        }
        .btn-modern:hover { transform: scale(1.02); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }

        /* Table Styling */
        .custom-table { border-collapse: separate; border-spacing: 0 12px; margin-top: -12px; }
        .custom-table thead th { 
            background: transparent; border: none; color: var(--secondary);
            font-size: 0.75rem; text-transform: uppercase; font-weight: 800; padding: 15px 20px;
        }
        .custom-table tbody tr { 
            background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.02); 
            border-radius: 16px; transition: 0.2s;
        }
        .custom-table tbody tr:hover { transform: scale(1.005); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        .custom-table td { border: none; padding: 20px; vertical-align: middle; }
        .custom-table td:first-child { border-radius: 16px 0 0 16px; }
        .custom-table td:last-child { border-radius: 0 16px 16px 0; }

        .money-text { font-family: 'Chakra Petch', sans-serif; font-weight: 700; }
        
        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 20px; } }
        
        @media print {
            @page { size: A4; margin: 1.5cm; }
            .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
            body { background: white !important; font-size: 10pt !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .glass-card { 
                box-shadow: none !important; 
                border: 1px solid #ddd !important; 
                border-radius: 10px !important; 
                padding: 15px !important;
                margin-bottom: 10px !important;
                background-color: #fff !important;
            }
            .stat-icon { display: none !important; } /* ซ่อนไอคอนตอนพิมพ์เพื่อประหยัดหมึกและพื้นที่ */
            .print-header { display: block !important; border-bottom: 3px solid #000 !important; margin-bottom: 30px !important; padding: 0 0 20px 0 !important; }
            
            /* บังคับให้การ์ดสรุปผลเรียง 4 คอลัมน์ */
            .row.g-4 { display: flex !important; flex-wrap: nowrap !important; margin: 0 -5px !important; }
            .col-xl-3 { width: 25% !important; padding: 0 5px !important; flex: 0 0 auto !important; }
            
            .h3 { font-size: 1.2rem !important; }
            .h4 { font-size: 1.1rem !important; }
            
            /* ปรับตารางให้ดูเป็นทางการ */
            .custom-table { border-spacing: 0 !important; width: 100% !important; border: 1px solid #eee !important; }
            .custom-table tbody tr { background: transparent !important; box-shadow: none !important; border-bottom: 1px solid #eee !important; }
            .custom-table td, .custom-table th { padding: 8px 10px !important; border: 1px solid #eee !important; border-radius: 0 !important; }
            .money-text { font-size: 11pt !important; }
            
            .text-primary, .text-danger, .text-success { color: black !important; } /* พิมพ์ขาวดำชัดๆ หรือจะคงสีไว้ก็ได้ */
            .bg-dark { background-color: #f8f9fa !important; color: black !important; border: 1px solid #000 !important; }
        }
        .print-header { display: none; padding: 40px; border-bottom: 2px solid #333; margin-bottom: 40px; }
    </style>
</head>
<body>
    <div class="no-print"><?php include 'sidebar.php'; ?></div>

    <div class="main-content">
        <!-- Print Header -->
        <div class="print-header">
            <div class="row align-items-center">
                <div class="col-8">
                    <h1 class="fw-bold text-primary mb-1"><?= htmlspecialchars($shop['shop_name1']) ?></h1>
                    <p class="mb-0 text-muted"><?= htmlspecialchars($shop['shop_address']) ?></p>
                    <p class="small text-muted">โทร: <?= $shop['shop_tel'] ?></p>
                </div>
                <div class="col-4 text-end">
                    <h3 class="fw-bold">รายงานสรุปรายได้-ค่าใช้จ่าย</h3>
                    <p class="mb-0"><?= date('d/m/Y', strtotime($date_start)) ?> - <?= date('d/m/Y', strtotime($date_end)) ?></p>
                </div>
            </div>
        </div>

        <!-- Dashboard Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-5 no-print">
            <div>
                <h2 class="fw-bold text-dark mb-1" style="font-family: 'Chakra Petch';">Accounting Dashboard</h2>
                <p class="text-secondary mb-0">ยินดีต้อนรับกลับมา, ตรวจสอบรายได้ของร้านวันนี้</p>
            </div>
            <div class="d-flex gap-3 mt-3 mt-md-0">
                <button onclick="window.print()" class="btn btn-white glass-card px-4 py-2 fw-bold text-dark">
                    <i class="fa fa-print me-2"></i> พิมพ์รายงาน
                </button>
                <a href="reports.php" class="btn btn-primary btn-modern shadow-sm">
                    <i class="fa fa-sync me-2"></i> รีเฟรชข้อมูล
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="glass-card p-4 mb-5 no-print">
            <form method="get" class="row g-4 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary uppercase">ช่วงวันที่เริ่มต้น</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 rounded-start-4"><i class="fa fa-calendar text-primary"></i></span>
                        <input type="date" name="start_date" class="form-control bg-light border-0 rounded-end-4 py-2" value="<?= $date_start ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary uppercase">จนถึงวันที่</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 rounded-start-4"><i class="fa fa-calendar-check text-primary"></i></span>
                        <input type="date" name="end_date" class="form-control bg-light border-0 rounded-end-4 py-2" value="<?= $date_end ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-dark w-100 btn-modern">
                        <i class="fa fa-filter me-2"></i> กรองข้อมูลอย่างละเอียด
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary Widgets -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-sm-6">
                <div class="glass-card p-4 border-start border-primary border-5">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fa fa-shopping-cart"></i></div>
                    <div class="text-secondary small fw-bold mb-1">จำนวนออเดอร์</div>
                    <div class="h3 fw-bold money-text mb-0"><?= number_format($order_count) ?> <span class="fs-6 fw-normal">รายการ</span></div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="glass-card p-4 border-start border-success border-5">
                    <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fa fa-wallet"></i></div>
                    <div class="text-secondary small fw-bold mb-1">รายได้รวม (Revenue)</div>
                    <div class="h3 fw-bold money-text mb-0 text-success">฿<?= number_format($total_grand, 2) ?></div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="glass-card p-4 border-start border-danger border-5">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="fa fa-minus-circle"></i></div>
                    <div class="text-secondary small fw-bold mb-1">ค่าใช้จ่ายรวม (Expenses)</div>
                    <div class="h3 fw-bold money-text mb-0 text-danger">฿<?= number_format($total_expense, 2) ?></div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="glass-card p-4 bg-dark text-white shadow-lg">
                    <div class="stat-icon bg-white bg-opacity-20 text-white"><i class="fa fa-chart-line"></i></div>
                    <div class="text-white text-opacity-75 small fw-bold mb-1">กำไรสุทธิ (Net Profit)</div>
                    <div class="h3 fw-bold money-text mb-0">฿<?= number_format($net_profit, 2) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5 no-print">
             <div class="col-xl-4 col-sm-6">
                <div class="glass-card p-3">
                    <div class="text-secondary small fw-bold mb-1">ค่าซักรีดรวม (Base)</div>
                    <div class="h5 fw-bold money-text mb-0">฿<?= number_format($total_base, 2) ?></div>
                </div>
            </div>
            <div class="col-xl-4 col-sm-6">
                <div class="glass-card p-3">
                    <div class="text-secondary small fw-bold mb-1">ยอดบวกเพิ่มรวม (Extra)</div>
                    <div class="h5 fw-bold money-text mb-0">฿<?= number_format($total_extra, 2) ?></div>
                </div>
            </div>
            <div class="col-xl-4 col-sm-6">
                <div class="glass-card p-3">
                    <div class="text-secondary small fw-bold mb-1">ค่าจัดส่งรวม (Shipping)</div>
                    <div class="h5 fw-bold money-text mb-0">฿<?= number_format($total_shipping, 2) ?></div>
                </div>
            </div>
        </div>

        <!-- Expense Table Section -->
        <div class="d-flex justify-content-between align-items-center mb-4 mt-5">
            <h4 class="fw-bold mb-0" style="font-family: 'Chakra Petch';">รายการค่าใช้จ่าย (Expenses)</h4>
            <button class="btn btn-danger btn-modern no-print" data-bs-toggle="modal" data-bs-target="#expenseModal">
                <i class="fa fa-plus me-2"></i> เพิ่มค่าใช้จ่าย
            </button>
        </div>

        <div class="table-responsive">
            <table class="table custom-table">
                <thead>
                    <tr>
                        <th>วัน/เวลา</th>
                        <th>รายการ</th>
                        <th>หมายเหตุ</th>
                        <th class="text-end">จำนวนเงิน</th>
                        <th class="text-center no-print">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expense_data)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted small">ไม่พบรายการค่าใช้จ่ายในช่วงเวลานี้</td></tr>
                    <?php endif; ?>
                    <?php foreach($expense_data as $exp): ?>
                    <tr>
                        <td class="small text-secondary">
                            <div class="fw-bold text-dark"><?= date('d M Y', strtotime($exp['exp_date'])) ?></div>
                            <div><?= date('H:i', strtotime($exp['exp_date'])) ?> น.</div>
                        </td>
                        <td><div class="fw-bold"><?= htmlspecialchars($exp['exp_name']) ?></div></td>
                        <td class="small text-secondary"><?= htmlspecialchars($exp['exp_note']) ?></td>
                        <td class="text-end money-text text-danger fw-bold fs-5">฿<?= number_format($exp['exp_amount'], 2) ?></td>
                        <td class="text-center no-print">
                            <a href="?start_date=<?= $date_start ?>&end_date=<?= $date_end ?>&del_exp=<?= $exp['exp_id'] ?>" 
                               class="btn btn-light rounded-circle p-2 text-danger" onclick="return confirm('ยืนยันการลบค่าใช้จ่ายนี้?')">
                                <i class="fa fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Ledger Table -->
        <div class="d-flex justify-content-between align-items-center mb-4 mt-5">
            <h4 class="fw-bold mb-0" style="font-family: 'Chakra Petch';">รายการลูกค้า (Revenue)</h4>
            <span class="badge bg-white text-dark shadow-sm rounded-pill px-3 py-2 border">พบออเดอร์ทั้งหมด <?= $order_count ?> รายการ</span>
        </div>

        <div class="table-responsive">
            <table class="table custom-table">
                <thead>
                    <tr>
                        <th class="text-center">ลำดับ</th>
                        <th>วันที่รับงาน</th>
                        <th>วันที่ส่งคืน</th>
                        <th>รหัสบิล</th>
                        <th>ชื่อลูกค้า</th>
                        <th>ประเภทงาน</th>
                        <th class="text-end">ค่าบริการหลัก</th>
                        <th class="text-end">บวกเพิ่ม</th>
                        <th class="text-end">ค่าจัดส่ง</th>
                        <th class="text-end">ยอดรวมสุทธิ</th>
                        <th class="text-center no-print">รายละเอียด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    foreach($ledger_data as $row): 
                        $is_pkg = (strpos($row['order_id'], 'PKG')===0);
                    ?>
                    <tr>
                        <td class="text-center text-secondary small"><?= $i++ ?></td>
                        <td class="small">
                            <div class="fw-bold"><?= date('d/m/Y', strtotime($row['order_date'])) ?></div>
                            <div class="smaller text-muted"><?= date('H:i', strtotime($row['order_date'])) ?></div>
                        </td>
                        <td class="small">
                            <?php if ($row['order_date_return'] && $row['order_date_return'] != '0000-00-00 00:00:00'): ?>
                                <div class="fw-bold"><?= date('d/m/Y', strtotime($row['order_date_return'])) ?></div>
                                <div class="smaller text-muted"><?= date('H:i', strtotime($row['order_date_return'])) ?></div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><div class="fw-bold text-primary">#<?= $row['order_id'] ?></div></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($row['m_name'].' '.$row['m_lastname']) ?></div>
                        </td>
                        <td>
                            <span class="badge <?= $is_pkg ? 'bg-primary' : 'bg-secondary' ?> bg-opacity-10 <?= $is_pkg ? 'text-primary' : 'text-secondary' ?> smaller" style="font-size: 0.65rem;">
                                <?= $is_pkg ? 'แพ็กเกจรายเดือน' : 'งานซักทั่วไป' ?>
                            </span>
                        </td>
                        <td class="text-end money-text small text-secondary">฿<?= number_format($row['base'], 2) ?></td>
                        <td class="text-end money-text small text-danger">฿<?= number_format($row['extra'], 2) ?></td>
                        <td class="text-end money-text small text-success">฿<?= number_format($row['shipping'], 2) ?></td>
                        <td class="text-end money-text fw-bold text-dark">฿<?= number_format($row['order_sum'], 2) ?></td>
                        <td class="text-center no-print">
                            <button class="btn btn-light rounded-circle p-2" onclick="viewHistory('<?= $row['m_id'] ?>', '<?= htmlspecialchars($row['m_name']) ?>')">
                                <i class="fa fa-search text-secondary"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer signatures for print -->
        <div class="row mt-5 d-none d-print-flex text-center small">
            <div class="col-4">
                <div style="height: 80px; border-bottom: 1px solid #ccc; width: 180px; margin: auto;"></div>
                <div class="mt-3 fw-bold">ผู้รวบรวมบัญชี</div>
            </div>
            <div class="col-4">
                <div style="height: 80px; border-bottom: 1px solid #ccc; width: 180px; margin: auto;"></div>
                <div class="mt-3 fw-bold">ผู้ตรวจสอบ</div>
            </div>
            <div class="col-4">
                <div style="height: 80px; border-bottom: 1px solid #ccc; width: 180px; margin: auto;"></div>
                <div class="mt-3 fw-bold">ผู้อนุมัติรายรับ</div>
            </div>
        </div>
    </div>

    <!-- Expense Modal -->
    <div class="modal fade no-print" id="expenseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card overflow-hidden">
                <form method="post">
                    <div class="modal-header border-0 p-4">
                        <h5 class="fw-bold mb-0"><i class="fa fa-plus-circle me-2 text-danger"></i> บันทึกค่าใช้จ่ายใหม่</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4 bg-light bg-opacity-50">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ชื่อรายการค่าใช้จ่าย</label>
                            <input type="text" name="exp_name" class="form-control rounded-4 border-0 shadow-sm" placeholder="เช่น ค่าผงซักฟอก, ค่าน้ำ, ค่าไฟ" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">จำนวนเงิน (บาท)</label>
                            <input type="number" step="0.01" name="exp_amount" class="form-control rounded-4 border-0 shadow-sm" placeholder="0.00" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">วันที่จ่าย (ปล่อยว่างถ้าเป็นปัจจุบัน)</label>
                            <input type="datetime-local" name="exp_date" class="form-control rounded-4 border-0 shadow-sm">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">หมายเหตุ (ถ้ามี)</label>
                            <textarea name="exp_note" class="form-control rounded-4 border-0 shadow-sm" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0 bg-light bg-opacity-50">
                        <button type="button" class="btn btn-white fw-bold rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="add_expense" class="btn btn-danger fw-bold rounded-pill px-4">บันทึกรายการ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade no-print" id="historyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content glass-card overflow-hidden">
                <div class="modal-header border-0 p-4">
                    <h5 class="fw-bold mb-0" id="historyTitle">รายละเอียดบิล</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0 bg-light bg-opacity-50" id="historyBody"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewHistory(m_id, name) {
            document.getElementById('historyTitle').innerHTML = '<i class="fa fa-user-circle me-2 text-primary"></i> ประวัติคุณ ' + name;
            const modal = new bootstrap.Modal(document.getElementById('historyModal'));
            modal.show();
            fetch('get_member_history.php?m_id=' + m_id).then(r => r.text()).then(h => { document.getElementById('historyBody').innerHTML = h; });
        }

        function printOfficialReceipt(divId) {
            const printContents = document.getElementById(divId).innerHTML;
            let iframe = document.getElementById('printFrame');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'printFrame';
                iframe.style.display = 'none';
                document.body.appendChild(iframe);
            }
            const doc = iframe.contentWindow.document;
            doc.open();
            doc.write('<html><head><title>Receipt</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"><style>@import url("https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;700&display=swap"); body { font-family: "Sarabun", sans-serif; padding: 40px; } .table-dark { background-color: #343a40 !important; color: #fff !important; -webkit-print-color-adjust: exact; } .table-light { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; } h4, h3 { color: #4f46e5 !important; -webkit-print-color-adjust: exact; } @media print { body { padding: 0; } }</style></head><body>' + printContents + '</body></html>');
            doc.close();
            setTimeout(() => { iframe.contentWindow.focus(); iframe.contentWindow.print(); }, 500);
        }
    </script>
</body>
</html>
