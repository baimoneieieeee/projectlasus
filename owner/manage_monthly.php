<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

// อ่านข้อมูลโควตาจาก JSON
$json_file = __DIR__ . '/../config/monthly_data.json';
$monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];

// --- 🗑️ ฟังก์ชันลบสมาชิก (เผื่อกรณีแอดมินต้องการลบคนออกเอง) ---
if (isset($_GET['remove_member'])) {
    $mid = $_GET['remove_member'];
    if (isset($monthly_data[$mid])) {
        unset($monthly_data[$mid]);
        file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));
        header("Location: manage_monthly.php");
        exit;
    }
}

// --- 🚩 ดึงข้อมูลคนสมัคร PKG แต่ยังไม่กดยืนยัน ---
$sql_pending_pkg = "SELECT o.*, m.m_name, m.m_lastname, m.m_tel 
                    FROM orders o 
                    JOIN member m ON o.m_id = m.m_id 
                    WHERE o.order_id LIKE 'PKG%' AND o.order_status <= 1 
                    ORDER BY o.order_date DESC";
$res_pending = $conn->query($sql_pending_pkg);

// ดึงราคาซักเกินจากฐานข้อมูล
$shop_q = $conn->query("SELECT shop_extra_price FROM shop LIMIT 1")->fetch_assoc();
$extra_price_per_piece = (int)($shop_q['shop_extra_price'] ?? 10);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการสมาชิกรรายเดือน - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&display=swap');
        :root { --primary: #2563eb; --bg: #f8fafc; }
        body { background: var(--bg); font-family: 'Chakra Petch', sans-serif; }
        .main-content { margin-left: 280px; padding: 40px; }
        .card-custom { border: none; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: white; }
        .quota-badge { font-size: 1.1rem; font-weight: 700; color: #2563eb; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- 🚩 ส่วนสมาชิกใหม่ที่รอเปิดสิทธิ์ -->
            <?php if($res_pending && $res_pending->num_rows > 0): ?>
            <div class="mb-5">
                <h5 class="fw-bold text-danger mb-3 small uppercase"><i class="fa fa-clock me-2"></i> รอเปิดสิทธิ์สมาชิกใหม่ (ค้างจ่าย/รอตรวจสลิป)</h5>
                <div class="card card-custom p-4 border-start border-danger border-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="smaller">
                                    <th>เลขออเดอร์</th>
                                    <th>ชื่อลูกค้า</th>
                                    <th>แพ็กเกจ</th>
                                    <th class="text-end">ยอดเงิน</th>
                                    <th class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($p = $res_pending->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold text-danger small"><?= $p['order_id'] ?></td>
                                    <td>
                                        <div class="fw-bold small"><?= htmlspecialchars($p['m_name'].' '.$p['m_lastname']) ?></div>
                                        <div class="smaller text-muted"><i class="fa fa-phone me-1 small"></i> <?= $p['m_tel'] ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-dark">
                                            <?php 
                                            preg_match('/MONTHLY_PACKAGE_(\d+)/', $p['order_note'], $matches);
                                            echo "เหมาซัก " . ($matches[1] ?? '80') . " ชิ้น";
                                            ?>
                                        </div>
                                        <div class="smaller text-muted"><?= date('d/m/y H:i', strtotime($p['order_date'])) ?></div>
                                    </td>
                                    <td class="text-end fw-bold text-dark small">฿<?= number_format($p['order_sum'], 2) ?></td>
                                    <td class="text-center">
                                        <a href="order_process.php?id=<?= $p['order_id'] ?>" class="btn btn-sm btn-danger rounded-pill px-3 shadow-sm">
                                            <i class="fa fa-wallet me-1"></i> จัดการสลิป
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0 text-primary small uppercase"><i class="fa fa-sync-alt me-2"></i> สมาชิกรรายเดือน (กำลังใช้งาน)</h5>
                <a href="quick_order.php" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
                    <i class="fa fa-plus-circle me-2"></i> เปิดบิลซักสมาชิก
                </a>
            </div>

            <div class="card card-custom p-4 mb-5 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">ชื่อสมาชิก</th>
                                <th class="text-center">โควตาผ้าที่เหลือ</th>
                                <th class="text-center">รอบรับส่ง (ครั้งนี้)</th>
                                <th>อัปเดตล่าสุด</th>
                                <th class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $has_active = false;
                            if(!empty($monthly_data)):
                                foreach($monthly_data as $mid => $data):
                                    // โชว์เฉพาะคนที่ยังใช้ไม่ครบ 4 รอบ
                                    if(($data['pickups_used'] ?? 0) >= 4) continue;
                                    
                                    $m_info = $conn->query("SELECT m_name, m_lastname, m_tel FROM member WHERE m_id = '$mid'")->fetch_assoc();
                                    if(!$m_info) continue;
                                    $has_active = true;
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($m_info['m_name'].' '.$m_info['m_lastname']) ?></div>
                                        <div class="small text-muted"><i class="fa fa-phone me-1 small"></i> <?= $m_info['m_tel'] ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="quota-badge"><?= $data['quota'] ?></span>
                                        <div class="smaller text-muted" style="font-size: 0.7rem;">จากโควตาสมัคร</div>
                                    </td>
                                    <td class="text-center">
                                        <div class="badge bg-light text-dark border rounded-pill px-3">
                                            <?= $data['pickups_used'] ?? 0 ?> / 4 ครั้ง
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small text-muted"><?= date('d/m/y H:i', strtotime($data['updated_at'])) ?></div>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" 
                                                    onclick="openDeductModal('<?= $mid ?>', '<?= htmlspecialchars($m_info['m_name'].' '.$m_info['m_lastname']) ?>', <?= $data['quota'] ?>, <?= ($data['pickups_used'] ?? 0) + 1 ?>)">
                                                <i class="fa fa-cut me-1"></i> บันทึกรอบที่ <?= ($data['pickups_used'] ?? 0) + 1 ?>
                                            </button>
                                            <a href="manage_orders.php?m_id=<?= $mid ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                                <i class="fa fa-history me-1"></i> ประวัติ
                                            </a>
                                            <a href="?remove_member=<?= $mid ?>" class="btn btn-sm btn-outline-danger border-0 rounded-circle" onclick="return confirm('ลบสมาชิกออกจากรายการ?')">
                                                <i class="fa fa-times"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; 
                            if(!$has_active): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted small">ไม่มีสมาชิกที่กำลังใช้งานในขณะนี้</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- สรุปสถิติ -->
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="card card-custom p-3 bg-white border-0 shadow-sm">
                        <div class="small fw-bold text-muted mb-1 uppercase" style="font-size: 0.65rem;">จำนวนสมาชิกรายเดือนรวม</div>
                        <h4 class="fw-bold mb-0 text-primary"><?= count($monthly_data) ?> คน</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 🟢 Modal หักผ้าด่วน -->
    <div class="modal fade" id="deductModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 25px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold"><i class="fa fa-cut text-primary me-2"></i>บันทึกการทำงาน <span id="modal_round_text" class="text-primary"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="text-center mb-4">
                        <div class="text-muted small">ชื่อสมาชิก</div>
                        <div class="fw-bold fs-5 text-dark" id="modal_m_name">-</div>
                        <div class="badge bg-primary bg-opacity-10 text-primary mt-1">โควตาปัจจุบัน: <span id="modal_m_quota">0</span> ชิ้น</div>
                    </div>
                    <form id="deductForm">
                        <input type="hidden" name="m_id" id="modal_m_id">
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label small fw-bold text-primary mb-0"><i class="fa fa-list me-1"></i> รายการผ้าที่ส่ง</label>
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill border-0" onclick="addItemRow()">
                                    <i class="fa fa-plus-circle me-1"></i> เพิ่มรายการ
                                </button>
                            </div>
                            <div id="item_list_container" class="bg-light p-2 rounded-4 border border-light"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">จำนวนผ้าที่ซักรวม (ชิ้น)</label>
                            <input type="number" name="amount" id="deduct_amount" class="form-control form-control-lg text-center fw-bold border-0 bg-light" style="border-radius: 15px;" min="1" readonly required>
                            <div id="quota_warning" class="mt-2 p-2 bg-danger bg-opacity-10 text-danger rounded-3 smaller d-none">
                                <i class="fa fa-exclamation-triangle me-1"></i> เกินโควตาไป <span id="over_count">0</span> ชิ้น (แนะนำเรียกเก็บเพิ่ม <span id="suggest_extra">0</span> บาท)
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-primary"><i class="fa fa-clock me-1"></i> เวลานัดส่งคืน / หมายเหตุจากร้านค้า</label>
                            <input type="text" name="owner_note" class="form-control border-0 bg-light" style="border-radius: 12px;" placeholder="เช่น วันศุกร์ 17:00 น.">
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-danger">เก็บเงินเพิ่ม (บาท)</label>
                                <input type="number" name="extra_cash" id="extra_cash" class="form-control border-0 bg-danger bg-opacity-10 text-danger fw-bold" style="border-radius: 12px;" value="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-secondary">เหตุผลที่เก็บเพิ่ม</label>
                                <input type="text" name="extra_note" id="extra_note" class="form-control border-0 bg-light" style="border-radius: 12px;" placeholder="ค่าผ้านวม">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow mt-4">
                            <i class="fa fa-check-circle me-2"></i> ยืนยันรายการ
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const deductModal = new bootstrap.Modal(document.getElementById('deductModal'));
        let current_quota = 0;
        
        function addItemRow(name = '', qty = 1) {
            const container = document.getElementById('item_list_container');
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 item-row';
            row.innerHTML = `
                <div class="col-7"><input type="text" name="item_names[]" class="form-control form-control-sm border-0 rounded-3" value="${name}" placeholder="รายการผ้า"></div>
                <div class="col-3"><input type="number" name="item_qtys[]" class="form-control form-control-sm border-0 rounded-3 text-center item-qty" value="${qty}" min="1" onchange="calcTotal()"></div>
                <div class="col-2"><button type="button" class="btn btn-sm text-danger border-0" onclick="this.closest('.item-row').remove(); calcTotal();"><i class="fa fa-times"></i></button></div>
            `;
            container.appendChild(row);
            calcTotal();
        }

        function calcTotal() {
            let total = 0;
            document.querySelectorAll('.item-qty').forEach(input => {
                total += parseInt(input.value) || 0;
            });
            document.getElementById('deduct_amount').value = total;

            const warning = document.getElementById('quota_warning');
            const overCountSpan = document.getElementById('over_count');
            const suggestExtraSpan = document.getElementById('suggest_extra');
            const extraCashInput = document.getElementById('extra_cash');
            const extraNoteInput = document.getElementById('extra_note');
            const extraPrice = <?= $extra_price_per_piece ?>;

            if (total > current_quota) {
                const over = total - current_quota;
                const suggest = over * extraPrice; 
                warning.classList.remove('d-none');
                overCountSpan.innerText = over;
                suggestExtraSpan.innerText = suggest;
                if (extraCashInput.value == 0) {
                    extraCashInput.value = suggest;
                    extraNoteInput.value = `ซักเกินโควตา ${over} ชิ้น`;
                }
            } else {
                warning.classList.add('d-none');
                if (extraNoteInput.value.includes('ซักเกินโควตา')) {
                    extraCashInput.value = 0;
                    extraNoteInput.value = '';
                }
            }
        }

        function openDeductModal(id, name, quota, nextRound) {
            current_quota = quota;
            document.getElementById('modal_m_id').value = id;
            document.getElementById('modal_m_name').innerText = name;
            document.getElementById('modal_m_quota').innerText = quota;
            document.getElementById('modal_round_text').innerText = 'รอบที่ ' + nextRound + ' / 4 ครั้ง';
            
            document.getElementById('item_list_container').innerHTML = '';
            addItemRow('เสื้อยืด', 1);
            addItemRow('กางเกง', 1);
            
            document.getElementById('extra_cash').value = 0;
            document.getElementById('extra_note').value = '';
            deductModal.show();
        }

        document.getElementById('deductForm').onsubmit = function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('process_quick_deduct.php', { method: 'POST', body: formData })
            .then(res => res.text())
            .then(data => {
                if(data.trim() === 'success') { location.reload(); }
                else { alert('เกิดข้อผิดพลาด: ' + data); }
            });
        };
    </script>
</body>
</html>
