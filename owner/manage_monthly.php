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

// --- 🗑️ ฟังก์ชันลบสมาชิก ---
if (isset($_GET['remove_member'])) {
    $mid = $_GET['remove_member'];
    if (isset($monthly_data[$mid])) {
        unset($monthly_data[$mid]);
        file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));
        header("Location: manage_monthly.php");
        exit;
    }
}

// ดึงรายการบริการหมวดรายเดือนมาเตรียมไว้
$res_services = $conn->query("SELECT s.* FROM service s JOIN service_type t ON s.svType_id = t.svType_id WHERE t.svType_behavior = 'monthly' AND s.service_quota = 0 ORDER BY s.service_name ASC");
$services_list = [];
while($s = $res_services->fetch_assoc()) { $services_list[] = $s; }

// ดึงข้อมูลคนสมัคร PKG
$sql_pending_pkg = "SELECT o.*, m.m_name, m.m_lastname, m.m_tel 
                    FROM orders o 
                    JOIN member m ON o.m_id = m.m_id 
                    WHERE o.order_id LIKE 'PKG%' AND o.order_status <= 1 
                    ORDER BY o.order_date DESC";
$res_pending = $conn->query($sql_pending_pkg);

$shop_q = $conn->query("SELECT shop_extra_price FROM shop LIMIT 1")->fetch_assoc();
$extra_price_per_piece = (int)($shop_q['shop_extra_price'] ?? 10);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการสมาชิกรายเดือน - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&display=swap');
        :root { --primary: #2563eb; --bg: #f8fafc; }
        body { background: var(--bg); font-family: 'Chakra Petch', sans-serif; }
        .main-content { margin-left: 280px; padding: 40px; }
        .card-custom { border: none; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: white; }
        .quota-badge { font-size: 1.1rem; font-weight: 700; color: #2563eb; }
        .item-row-select { background: #f8fafc; border-radius: 12px; padding: 10px; margin-bottom: 8px; border: 1px solid #e2e8f0; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 20px; } }
        /* Flatpickr Custom */
        .flatpickr-calendar { border-radius: 15px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- สมาชิกใหม่รอเปิดสิทธิ์ -->
            <?php if($res_pending && $res_pending->num_rows > 0): ?>
            <div class="mb-5">
                <h5 class="fw-bold text-danger mb-3 small uppercase"><i class="fa fa-clock me-2"></i> รอเปิดสิทธิ์สมาชิกใหม่</h5>
                <div class="card card-custom p-4 border-start border-danger border-4 shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <tbody>
                                <?php while($p = $res_pending->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold text-danger small"><?= $p['order_id'] ?></td>
                                    <td>
                                        <div class="fw-bold small"><?= htmlspecialchars($p['m_name'].' '.$p['m_lastname']) ?></div>
                                        <div class="smaller text-muted"><?= $p['m_tel'] ?></div>
                                    </td>
                                    <td><div class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3">รอชำระเงิน/ตรวจสลิป</div></td>
                                    <td class="text-end fw-bold">฿<?= number_format($p['order_sum'], 2) ?></td>
                                    <td class="text-center">
                                        <a href="order_process.php?id=<?= $p['order_id'] ?>" class="btn btn-sm btn-danger rounded-pill px-3 shadow-sm">จัดการสลิป</a>
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
                <h5 class="fw-bold mb-0 text-primary small uppercase"><i class="fa fa-sync-alt me-2"></i> สมาชิกรายเดือน (Active)</h5>
                <a href="quick_order.php" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
                    <i class="fa fa-plus-circle me-2"></i> เปิดบิลซักสมาชิก
                </a>
            </div>

            <div class="card card-custom p-4 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">ชื่อสมาชิก</th>
                                <th class="text-center">โควตาเหลือ</th>
                                <th class="text-center">รอบถัดไป</th>
                                <th>อัปเดตล่าสุด</th>
                                <th class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $has_active = false;
                            foreach($monthly_data as $mid => $data):
                                if(($data['pickups_used'] ?? 0) >= 4) continue;
                                $m_info = $conn->query("SELECT m_name, m_lastname, m_tel FROM member WHERE m_id = '$mid'")->fetch_assoc();
                                if(!$m_info) continue;
                                $has_active = true;
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($m_info['m_name'].' '.$m_info['m_lastname']) ?></div>
                                    <div class="small text-muted"><?= $m_info['m_tel'] ?></div>
                                </td>
                                <td class="text-center"><span class="quota-badge"><?= $data['quota'] ?></span></td>
                                <td class="text-center">
                                    <div class="badge bg-light text-primary border border-primary border-opacity-10 rounded-pill px-3">
                                        ครั้งที่ <?= ($data['pickups_used'] ?? 0) + 1 ?> / 4
                                    </div>
                                </td>
                                <td class="small text-muted"><?= date('d/m/y H:i', strtotime($data['updated_at'])) ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm fw-bold" 
                                            onclick="openDeductModal('<?= $mid ?>', '<?= htmlspecialchars($m_info['m_name'].' '.$m_info['m_lastname']) ?>', <?= $data['quota'] ?>, <?= ($data['pickups_used'] ?? 0) + 1 ?>)">
                                        <i class="fa fa-cut me-1"></i> บันทึกงาน
                                    </button>
                                    <a href="manage_orders.php?m_id=<?= $mid ?>" class="btn btn-sm btn-light border rounded-circle ms-1"><i class="fa fa-history text-muted"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; 
                            if(!$has_active): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">ไม่มีสมาชิกกำลังใช้งานในขณะนี้</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 🟢 Modal บันทึกงานสมาชิก (เลือกจากเมนูบริการ) -->
    <div class="modal fade" id="deductModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 25px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold"><i class="fa fa-cut text-primary me-2"></i>บันทึกการซักรายเดือน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="text-center mb-4 p-3 bg-light rounded-4">
                        <div class="fw-bold fs-5" id="modal_m_name">-</div>
                        <div class="badge bg-primary rounded-pill mt-1">โควตาปัจจุบัน: <span id="modal_m_quota">0</span> ชิ้น</div>
                        <div class="text-muted smaller mt-1" id="modal_round_text"></div>
                    </div>
                    <form id="deductForm">
                        <input type="hidden" name="m_id" id="modal_m_id">
                        
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label small fw-bold text-primary mb-0">รายการที่นำมาส่ง</label>
                                <button type="button" class="btn btn-sm btn-outline-primary border-0 fw-bold" onclick="addItemRow()">
                                    <i class="fa fa-plus-circle me-1"></i> เพิ่มช่อง
                                </button>
                            </div>
                            <div id="item_list_container"></div>
                        </div>

                        <div class="p-3 bg-primary bg-opacity-10 rounded-4 text-center mb-3">
                            <div class="text-primary small fw-bold mb-1">จำนวนชิ้นที่หักรวม</div>
                            <input type="number" name="amount" id="deduct_amount" class="form-control text-center fw-bold border-0 bg-transparent fs-2" value="0" readonly required>
                            <div id="quota_warning" class="mt-2 text-danger smaller fw-bold d-none">
                                <i class="fa fa-exclamation-triangle"></i> เกินโควตาไป <span id="over_count">0</span> ชิ้น!
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted"><i class="fa fa-truck-pickup me-1"></i> เวลานัดรับผ้า</label>
                                <input type="text" name="est_pickup" class="form-control rounded-3 border-light shadow-sm datetime-picker" value="<?= date('Y-m-d H:i') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted"><i class="fa fa-paper-plane me-1"></i> เวลานัดส่งคืน</label>
                                <input type="text" name="est_delivery" class="form-control rounded-3 border-light shadow-sm datetime-picker" value="<?= date('Y-m-d H:i') ?>">
                            </div>
                        </div>

                        <!-- <div class="mb-3">
                            <label class="form-label small fw-bold">หมายเหตุ:</label>
                            <input type="text" name="owner_note" class="form-control rounded-3 border-light shadow-sm" placeholder="ระบุข้อความเพิ่มเติม (ถ้ามี)">
                        </div> -->

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-danger">เก็บเงินเพิ่ม (ถ้ามี)</label>
                                <input type="number" name="extra_cash" id="extra_cash" class="form-control rounded-3 border-danger bg-danger bg-opacity-5 fw-bold" value="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">เหตุผลที่เก็บเพิ่ม</label>
                                <input type="text" name="extra_note" id="extra_note" class="form-control rounded-3 border-light" placeholder="เช่น ค่าผ้าห่ม">
                            </div>
                        </div>

                        <button type="submit" id="submit_btn" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow mt-4">
                            ยืนยันและหักสิทธิ์สมาขิก
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
    <script>
        const services = <?= json_encode($services_list) ?>;
        const deductModal = new bootstrap.Modal(document.getElementById('deductModal'));
        let current_quota = 0;

        // ตั้งค่า Flatpickr ภาษาไทย
        flatpickr(".datetime-picker", {
            locale: "th",
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            time_24hr: true,
            minTime: "09:00",
            maxTime: "18:00",
            disableMobile: "true"
        });
        
        function addItemRow(srvId = '', qty = 1) {
            const container = document.getElementById('item_list_container');
            const row = document.createElement('div');
            row.className = 'item-row-select d-flex align-items-center gap-2';
            
            let opts = '<option value="">-- เลือกผ้า --</option>';
            services.forEach(s => {
                opts += `<option value="${s.service_id}" data-name="${s.service_name}" ${s.service_id===srvId?'selected':''}>${s.service_name}</option>`;
            });

            row.innerHTML = `
                <div class="flex-grow-1"><select name="item_ids[]" class="form-select border-0 bg-transparent fw-bold" onchange="calcTotal()">${opts}</select><input type="hidden" name="item_names[]" class="item-name-hidden"></div>
                <div style="width: 70px;"><input type="number" name="item_qtys[]" class="form-control form-control-sm border-0 bg-white text-center fw-bold item-qty" value="${qty}" min="1" onchange="calcTotal()"></div>
                <button type="button" class="btn btn-sm text-danger border-0" onclick="this.closest('.item-row-select').remove(); calcTotal();"><i class="fa fa-times"></i></button>
            `;
            container.appendChild(row);
            calcTotal();
        }

        function calcTotal() {
            let total = 0;
            document.querySelectorAll('.item-row-select').forEach(row => {
                const sel = row.querySelector('select');
                const qty = parseInt(row.querySelector('.item-qty').value) || 0;
                if(sel.value) {
                    total += qty;
                    row.querySelector('.item-name-hidden').value = sel.options[sel.selectedIndex].getAttribute('data-name');
                }
            });
            document.getElementById('deduct_amount').value = total;

            const warning = document.getElementById('quota_warning');
            const btn = document.getElementById('submit_btn');
            const extraCashInput = document.getElementById('extra_cash');
            const extraNoteInput = document.getElementById('extra_note');
            const extraPrice = <?= $extra_price_per_piece ?>;

            // 🛑 กรณีที่ 1: สิทธิ์หมดตั้งแต่เริ่ม (0 หรือ ติดลบ) -> ห้ามบันทึกเด็ดขาด
            if (current_quota <= 0) {
                warning.classList.remove('d-none');
                warning.innerHTML = `<i class="fa fa-ban"></i> สิทธิ์รายเดือนหมดแล้ว! (เหลือ ${current_quota} ชิ้น) กรุณาแจ้งลูกค้าซื้อแพ็กเกจใหม่`;
                btn.disabled = true;
                btn.classList.replace('btn-primary', 'btn-secondary');
                btn.classList.replace('btn-danger', 'btn-secondary');
                btn.innerText = 'ไม่สามารถบันทึกได้ (สิทธิ์หมด)';
                return;
            }

            // ✅ กรณีที่ 2: ยังมีสิทธิ์เหลือ แต่ครั้งนี้ส่งเกิน
            if (total > current_quota) {
                const over = total - current_quota;
                const suggestPrice = over * extraPrice;
                warning.classList.remove('d-none');
                warning.innerHTML = `<i class="fa fa-exclamation-triangle"></i> เกินโควตาไป ${over} ชิ้น! (แนะนำเรียกเก็บเพิ่ม ${suggestPrice} บาท)`;
                
                extraCashInput.value = suggestPrice;
                extraNoteInput.value = `ซักเกินโควตา ${over} ชิ้น`;
                
                btn.disabled = false; 
                btn.classList.replace('btn-secondary', 'btn-danger');
                btn.classList.replace('btn-primary', 'btn-danger');
                btn.innerText = `ยืนยัน (เรียกเก็บเพิ่ม ฿${suggestPrice})`;
            } else {
                // 🟢 กรณีที่ 3: ปกติ
                warning.classList.add('d-none');
                extraCashInput.value = 0;
                extraNoteInput.value = '';
                btn.disabled = false;
                btn.classList.replace('btn-secondary', 'btn-primary');
                btn.classList.replace('btn-danger', 'btn-primary');
                btn.innerText = 'ยืนยันและหักสิทธิ์สมาขิก';
            }
        }

        function openDeductModal(id, name, quota, nextRound) {
            current_quota = quota;
            document.getElementById('modal_m_id').value = id;
            document.getElementById('modal_m_name').innerText = name;
            document.getElementById('modal_m_quota').innerText = quota;
            document.getElementById('modal_round_text').innerText = 'หักผ้าครั้งที่ ' + nextRound + ' จากสิทธิ์การรับส่ง 4 ครั้ง';
            document.getElementById('item_list_container').innerHTML = '';
            
            // เพิ่มรายการเริ่มต้น (ถ้ามีรายการ เสื้อ/กางเกง ให้ดึง ID มาใส่)
            addItemRow(); 
            deductModal.show();
        }

        document.getElementById('deductForm').onsubmit = function(e) {
            e.preventDefault();
            const btn = document.getElementById('submit_btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>กำลังบันทึก...';

            const formData = new FormData(this);
            fetch('process_quick_deduct.php', { method: 'POST', body: formData })
            .then(res => res.text())
            .then(data => {
                if(data.trim() === 'success') { location.reload(); }
                else { 
                    alert('เกิดข้อผิดพลาด: ' + data); 
                    btn.disabled = false;
                    btn.innerText = 'ยืนยันและหักสิทธิ์สมาขิก';
                }
            })
            .catch(err => {
                alert('การเชื่อมต่อล้มเหลว');
                btn.disabled = false;
                btn.innerText = 'ยืนยันและหักสิทธิ์สมาขิก';
            });
        };
    </script>
</body>
</html>
