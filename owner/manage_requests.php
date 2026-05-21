<?php
session_start();
include __DIR__ . '/../config/db.php';

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return 0;
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    return $miles * 1.609344; // แปลงเป็นกิโลเมตร
}

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
        $rq_info = $conn->query("SELECT rq_sum, rq_note, rq_latitude, rq_longitude FROM request WHERE rq_id = '$rq_id'")->fetch_assoc();
        $initial_sum = $rq_info['rq_sum'] ?? 0;
        $rq_note = $rq_info['rq_note'] ?? '';
        $rq_lat = $rq_info['rq_latitude'];
        $rq_lng = $rq_info['rq_longitude'];
        
        $m_info = $conn->query("SELECT m_address, m_latitude, m_longitude FROM member WHERE m_id = '$m_id'")->fetch_assoc();
        $address = $conn->real_escape_string($m_info['m_address'] ?? '');
        $m_lat = $m_info['m_latitude'];
        $m_lng = $m_info['m_longitude'];

        // คำนวณค่าส่งใหม่ตอนยืนยัน
        $shop_info = $conn->query("SELECT shop_latitude, shop_longitude, shop_dist_1, shop_rate FROM shop LIMIT 1")->fetch_assoc();
        $shop_lat = (float)($shop_info['shop_latitude'] ?? 0);
        $shop_lng = (float)($shop_info['shop_longitude'] ?? 0);
        $dist_free = (float)($shop_info['shop_dist_1'] ?? 0);
        $rate_standard = (float)($shop_info['shop_rate'] ?? 0);

        $lat = $rq_lat ?: $m_lat;
        $lng = $rq_lng ?: $m_lng;
        $distance = calculateDistance($shop_lat, $shop_lng, $lat, $lng);
        $shipping_fee = ($distance > $dist_free) ? $rate_standard : 0;

        // เช็คว่าเป็นคำขอสมัครสมาชิกหรือไม่
        $is_pkg = (strpos($rq_note, 'สมัครแพ็กเกจ') !== false);
        $order_id = ($is_pkg ? "PKG" : "ORD") . time();

        // 1. อัปเดตสถานะคำขอเป็น 'ยืนยันแล้ว' (1)
        $conn->query("UPDATE request SET rq_status = 1 WHERE rq_id = '$rq_id'");

        // 2. คำนวณลำดับรอบ (เฉพาะงานรายเดือนจริงๆ)
        $round_num = 0;
        // เช็คว่าเป็นสมาชิกรายเดือน และไม่ใช่การซื้อแพ็กเกจใหม่
        if (!$is_pkg) {
            $json_file = __DIR__ . '/../config/monthly_data.json';
            if (file_exists($json_file)) {
                $monthly_data = json_decode(file_get_contents($json_file), true);
                
                // *** จุดสำคัญ: จะนับรอบเฉพาะงานที่ลูกค้าแจ้งรับผ้าในฐานะ "สมาชิกรายเดือน" เท่านั้น ***
                // โดยตรวจสอบจาก Note หรือเงื่อนไขที่คุณต้องการ (ในที่นี้เช็คว่ามีการสมัครสมาชิกอยู่)
                if (isset($monthly_data[$m_id])) {
                    // ถ้าใน Note มีการระบุว่าเป็นงานรายเดือน (จากหน้า request_pickup.php ที่คุณอาจจะแยกไว้)
                    // หรือถ้าคุณต้องการให้งาน Pickup ปกติ "ไม่นับรอบ" อัตโนมัติ ให้แก้ตรงนี้ครับ
                    
                    // เพื่อความแม่นยำ ผมจะให้ $round_num เป็น 0 ไว้ก่อน 
                    // แล้วจะไปนับรอบจริงๆ ตอนที่เจ้าของร้าน "หักผ้า" ในหน้า order_process เท่านั้นครับ
                    $round_num = 0; 
                }
            }
        }

        // 3. สร้างออเดอร์ใหม่
        // ปรับปรุง: ให้งานซักทุกประเภทเริ่มต้นที่สถานะ 0 (รอรับผ้า) 
        // เพื่อให้เจ้าของร้านบันทึกรายการผ้าจริงก่อนค่อยเปลี่ยนเป็นสถานะ 1 (รอชำระเงิน)
        $order_status = 0; 
        $order_note = $is_pkg ? $rq_note : "";
        
        // รับค่าจาก Flatpickr แล้วรวมเป็นข้อความเดียวเพื่อบันทึกลงช่องเดียว (ไม่ต้องแก้ฐานข้อมูล)
        $p_val = $_POST['est_pickup'] ?? '-';
        $d_val = $_POST['est_delivery'] ?? '-';
        $combined_est = "นัดรับ: $p_val | นัดส่ง: $d_val";
        $est_info = $conn->real_escape_string($combined_est);

        $final_initial_sum = $initial_sum + $shipping_fee;

        $conn->query("INSERT INTO orders (order_id, m_id, rq_id, order_date, order_status, order_sum, order_shipping_fee, order_note, order_pickup_round, order_address, order_est_pickup) 
                      VALUES ('$order_id', '$m_id', '$rq_id', '$date', '$order_status', '$final_initial_sum', '$shipping_fee', '$order_note', '$round_num', '$address', '$est_info')");
        
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
    $reject_note = $conn->real_escape_string($_POST['reject_note'] ?? '');
    $conn->query("UPDATE request SET rq_status = 2, rq_reject_note = '$reject_note' WHERE rq_id = '$rq_id'");
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

// ดึงข้อมูลร้านค้าเพื่อคำนวณระยะทางและค่าส่ง
$shop_info = $conn->query("SELECT shop_latitude, shop_longitude, shop_dist_1, shop_rate FROM shop LIMIT 1")->fetch_assoc();
$shop_lat = (float)($shop_info['shop_latitude'] ?? 0);
$shop_lng = (float)($shop_info['shop_longitude'] ?? 0);
$dist_free = (float)($shop_info['shop_dist_1'] ?? 0);
$rate_standard = (float)($shop_info['shop_rate'] ?? 0);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการคำขอ - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .main-content label {
            display: block;
            margin: 0.4rem 0 !important;
            font-weight: 600;
        }
        .main-content input {
            margin: 0.4rem 0 !important;
        }
        /* ตกแต่ง Flatpickr ให้เข้ากับ Bootstrap */
        .flatpickr-calendar {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border-radius: 15px;
            border: 1px solid #e2e8f0;
        }
    </style>
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

                    // คำนวณระยะทาง
                    $lat = $rq['rq_latitude'] ?: $rq['m_latitude'];
                    $lng = $rq['rq_longitude'] ?: $rq['m_longitude'];
                    $distance = calculateDistance($shop_lat, $shop_lng, $lat, $lng);
                    $shipping_fee = ($distance > $dist_free) ? $rate_standard : 0;
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
                            
                            <div class="mt-2 row g-0">
                                <div class="col-6">
                                    <div class="small text-muted">ห่างจากร้าน:</div>
                                    <div class="fw-bold text-dark"><?= number_format($distance, 2) ?> กม.</div>
                                </div>
                                <div class="col-6">
                                    <div class="small text-muted">ค่าจัดส่ง:</div>
                                    <div class="fw-bold <?= $shipping_fee > 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= $shipping_fee > 0 ? '฿' . number_format($shipping_fee) : 'ส่งฟรี' ?>
                                    </div>
                                </div>
                            </div>

                            <?php if($rq['rq_sum'] > 0): ?>
                                <div class="mt-2 pt-2 border-top fw-bold text-primary">
                                    ยอดเงินรวม (ประมาณการ): ฿<?= number_format($rq['rq_sum'] + $shipping_fee) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="small text-muted mb-4">
                            <i class="fa fa-phone me-1"></i> <?= $rq['m_tel'] ?><br>
                            <i class="fa fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($rq['m_address']) ?>
                            <?php if ($lat && $lng): ?>
                                <div class="mt-2">
                                    <a href="https://www.google.com/maps?q=<?= $lat ?>,<?= $lng ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="fa fa-map-marked-alt me-1"></i> ดูแผนที่ลูกค้า
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex flex-column gap-2 mt-auto">
                            <form method="post" class="w-100">
                                <input type="hidden" name="rq_id" value="<?= $rq['rq_id'] ?>">
                                <input type="hidden" name="m_id" value="<?= $rq['m_id'] ?>">
                                <div class="mb-2">
                                    <label class="form-label smaller fw-bold text-muted mb-1"><i class="fa fa-clock me-1"></i> เวลานัดรับผ้า</label>
                                    <input type="text" name="est_pickup" class="form-control form-control-sm rounded-3 datetime-picker" 
                                           placeholder="คลิกเพื่อเลือกวันที่และเวลา" readonly value="<?= date('Y-m-d H:i') ?>">
                                </div>
                                <?php if(!$is_membership): // 🚩 โชว์เวลาส่งเฉพาะงานซักปกติ ไม่โชว์ตอนสมัครสมาชิก ?>
                                <div class="mb-3">
                                    <label class="form-label smaller fw-bold text-muted mb-1"><i class="fa fa-paper-plane me-1"></i> เวลานัดส่งคืน</label>
                                    <input type="text" name="est_delivery" class="form-control form-control-sm rounded-3 datetime-picker" 
                                           placeholder="คลิกเพื่อเลือกวันที่และเวลา" readonly value="<?= date('Y-m-d H:i') ?>">
                                </div>
                                <?php endif; ?>
                                <button type="submit" name="confirm_request" class="btn btn-primary w-100 rounded-pill fw-bold py-2 shadow-sm">
                                    <i class="fa fa-check me-1"></i> ยืนยันรับเรื่อง
                                </button>
                            </form>
                            <button type="button" class="btn btn-outline-danger rounded-pill py-2 shadow-sm" 
                                    data-bs-toggle="modal" data-bs-target="#rejectModal" data-rqid="<?= $rq['rq_id'] ?>">
                                <i class="fa fa-times me-1"></i> ปฏิเสธ
                            </button>
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
                        <tr><th>วันที่</th><th>ลูกค้า</th><th>ประเภท</th><th>สถานะ</th><th>หมายเหตุปฏิเสธ</th></tr>
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
                            <td class="small text-muted"><?= htmlspecialchars($h['rq_reject_note'] ?? '-') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal ปฏิเสธคำขอ -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <form method="post">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold">ระบุเหตุผลที่ปฏิเสธ (ถ้ามี)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="rq_id" id="modal_rq_id">
                        <textarea name="reject_note" class="form-control rounded-3" rows="3" placeholder="เช่น ผ้าเยอะเกินไป, นอกพื้นที่ให้บริการ ฯลฯ"></textarea>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="reject_request" class="btn btn-danger rounded-pill px-4 fw-bold">ยืนยันปฏิเสธ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
    <script>
        // ตั้งค่า Flatpickr เป็นภาษาไทย
        flatpickr(".datetime-picker", {
            locale: "th",
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            time_24hr: true,
            minTime: "09:00",
            maxTime: "18:00",
            disableMobile: "true"
        });

        const rejectModal = document.getElementById('rejectModal');
        if (rejectModal) {
            rejectModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const rqId = button.getAttribute('data-rqid');
                document.getElementById('modal_rq_id').value = rqId;
            });
        }
    </script>
</body>
</html>
