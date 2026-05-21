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

// 2. ดึงรายการเดินบัญชีละเอียด (ออเดอร์)
$sql_ledger = "SELECT o.*, m.m_name, m.m_lastname 
               FROM orders o 
               JOIN member m ON o.m_id = m.m_id 
               WHERE DATE(o.order_date) BETWEEN '$date_start' AND '$date_end'
               AND o.order_status >= 1
               ORDER BY o.order_date DESC";
$res_ledger = $conn->query($sql_ledger);

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
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>รายงานสรุปรายรับ - <?= htmlspecialchars($shop['shop_name1']) ?></title>
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
            @page { 
                size: A4; 
                margin: 0; /* ลบหัวกระดาษ/ท้ายกระดาษของเบราว์เซอร์ (URL/วันที่/ชื่อเว็บ) */
            }
            .no-print { display: none !important; }
            .only-print { display: block !important; }
            
            body { 
                background: white !important; 
                margin: 0 !important; 
                padding: 2.5cm 2cm 2cm 3cm !important; /* ตั้งขอบกระดาษจริงที่นี่ (ซ้าย 3, ขวา 2, บน 2.5, ล่าง 2) */
                width: 100% !important;
                font-family: 'TH Sarabun New', 'Sarabun', serif !important; 
                font-size: 16pt !important; 
                color: black !important;
                line-height: 1.2 !important;
            }
            
            .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            
            .glass-card, .card, .border, .shadow-sm { 
                box-shadow: none !important; border: none !important; border-radius: 0 !important; 
                padding: 0 !important; background: transparent !important;
            }

            .badge { border: none !important; padding: 0 !important; background: transparent !important; color: black !important; font-size: 16pt !important; }
            .stat-icon, .btn { display: none !important; }

            /* ส่วนหัวแบบรายงานสรุปบัญชีร้าน */
            .report-title { font-size: 24pt; font-weight: bold; margin-bottom: 20px; text-align: center; color: black !important; }
            .report-info { margin-bottom: 20px; font-size: 16pt; color: black !important; border-bottom: 2px solid black; padding-bottom: 15px; }
            .report-line { margin-bottom: 5px; display: flex; justify-content: space-between; }
            
            /* สรุปยอด */
            .text-summary-box { margin: 20px 0; padding: 15px; border: 1px solid black; font-size: 16pt; line-height: 1.6; }
            
            /* ตารางขาวดำเส้นตรง */
            .custom-table { border-collapse: collapse !important; width: 100% !important; margin-top: 10px !important; border: 1px solid black !important; font-size: 16pt !important; color: black !important; }
            .custom-table thead th { 
                background-color: #f8f9fa !important; border: 1px solid black !important; 
                color: black !important; font-weight: bold !important; text-align: center !important; padding: 6px !important;
            }
            .custom-table tbody tr { border-bottom: 1px solid black !important; background: transparent !important; }
            .custom-table td { border: 1px solid black !important; padding: 6px !important; vertical-align: top !important; color: black !important; }
            
            .text-primary, .text-danger, .text-success, .text-dark { color: black !important; }
            .money-text { font-family: 'TH Sarabun New', serif !important; font-weight: normal !important; }

            .signature-section { margin-top: 60px; display: flex; justify-content: space-between; page-break-inside: avoid; }
            .sig-box { width: 45%; text-align: center; }
            .sig-line { border-bottom: 1px dotted black; display: inline-block; width: 70%; margin-top: 25px; margin-bottom: 5px; }
        }
        .report-header, .signature-section { display: none; }
        .only-print { display: none; }
    </style>
</head>
<body>
    <div class="no-print"><?php include 'sidebar.php'; ?></div>

    <div class="main-content">
        <!-- Print Header (Professional Business Style) -->
        <div class="only-print">
            <div class="report-title">รายงานสรุปรายรับ ประจำร้าน</div>
            <div class="report-info">
                <div class="report-line">
                    <span><strong>ชื่อร้าน:</strong> <?= htmlspecialchars($shop['shop_name1']) ?></span>
                    <span><strong>เลขที่เอกสารอ้างอิง:</strong> ...................................</span>
                </div>
                <div class="report-line">
                    <span><strong>ที่อยู่:</strong> <?= htmlspecialchars($shop['shop_address']) ?></span>
                    <span><strong>โทร:</strong> <?= htmlspecialchars($shop['shop_tel']) ?></span>
                </div>
                <?php 
                    $thai_months = [1=>'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
                    $s_ts = strtotime($date_start);
                    $e_ts = strtotime($date_end);
                ?>
                <div class="report-line" style="margin-top: 10px; font-weight: bold;">
                    <span>ช่วงเวลาสรุปผล: <?= date('j', $s_ts) . " " . $thai_months[(int)date('n', $s_ts)] . " " . (date('Y', $s_ts)+543) ?> ถึง <?= date('j', $e_ts) . " " . $thai_months[(int)date('n', $e_ts)] . " " . (date('Y', $e_ts)+543) ?></span>
                    <span>วันที่พิมพ์เอกสาร: <?= date('j') . " " . $thai_months[(int)date('n')] . " " . (date('Y')+543) ?></span>
                </div>
            </div>
            
            <p style="font-size: 16pt;"><strong>บทสรุปผู้บริหาร (Summary)</strong></p>
            <div class="text-summary-box">
                ๑. จำนวนรายการสั่งซื้อทั้งหมด: <?= number_format($order_count) ?> รายการ<br>
                ๒. รวมยอดค่าบริการหลัก: <?= number_format($total_base, 2) ?> บาท<br>
                ๓. รวมยอดชำระเพิ่มเติม: <?= number_format($total_extra, 2) ?> บาท<br>
                ๔. รวมยอดค่าจัดส่งสินค้า: <?= number_format($total_shipping, 2) ?> บาท<br>
                <strong>๕. รวมรายรับสุทธิทั้งสิ้น: <?= number_format($total_grand, 2) ?> บาท</strong>
            </div>

            <p style="font-size: 16pt; margin-top: 20px;"><strong>รายละเอียดรายการรับเงิน</strong></p>
        </div>

        <!-- Dashboard Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-5 no-print">
            <div>
                <h2 class="fw-bold text-dark mb-1" style="font-family: 'Chakra Petch';">Revenue Dashboard</h2>
                <p class="text-secondary mb-0">ตรวจสอบสถิติรายได้และออเดอร์ของทางร้าน</p>
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
        <div class="row g-4 mb-5 no-print">
            <div class="col-md-4">
                <div class="glass-card p-4 border-start border-primary border-5 h-100">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fa fa-shopping-cart"></i></div>
                    <div class="text-secondary small fw-bold mb-1">จำนวนออเดอร์</div>
                    <div class="h3 fw-bold money-text mb-0"><?= number_format($order_count) ?> <span class="fs-6 fw-normal">รายการ</span></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-card p-4 border-start border-success border-5 h-100">
                    <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fa fa-wallet"></i></div>
                    <div class="text-secondary small fw-bold mb-1">รายได้รวมสุทธิ (Revenue)</div>
                    <div class="h3 fw-bold money-text mb-0 text-success">฿<?= number_format($total_grand, 2) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-card p-4 border-start border-warning border-5 h-100">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fa fa-chart-pie"></i></div>
                    <div class="text-secondary small fw-bold mb-1">เฉลี่ยต่อออเดอร์</div>
                    <div class="h3 fw-bold money-text mb-0">฿<?= $order_count > 0 ? number_format($total_grand / $order_count, 2) : '0.00' ?></div>
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

        <!-- Ledger Table -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
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

        <!-- Signature Section (Thai Official Style) -->
        <div class="signature-section only-print">
            <div class="sig-box">
                <br>
                (ลงชื่อ)<span class="sig-line"></span><br>
                ( ..................................................................... )<br>
                ตำแหน่ง ผู้จัดทำรายงาน
            </div>
            <div class="sig-box">
                <br>
                (ลงชื่อ)<span class="sig-line"></span><br>
                ( ..................................................................... )<br>
                ตำแหน่ง ผู้ตรวจสอบ / เจ้าของกิจการ
            </div>
        </div>
        <div class="only-print" style="text-align: right; margin-top: 50px; font-size: 14pt;">
            พิมพ์โดยระบบบริหารจัดการร้านซักรีด เมื่อวันที่ <?= date('j') . " " . $thai_months[(int)date('n')] . " " . (date('Y')+543) . " เวลา " . date('H:i') . " น." ?>
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
    </script>
</body>
</html>
