<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
    die("ไม่พบรหัสออเดอร์");
}

$order_id = $conn->real_escape_string(trim($_GET['id']));

// 1. ดึงข้อมูลออเดอร์
$sql = "SELECT o.*, m.m_name, m.m_lastname, m.m_tel, m.m_address, r.rq_note
        FROM orders o 
        JOIN member m ON o.m_id = m.m_id 
        LEFT JOIN request r ON o.rq_id = r.rq_id
        WHERE o.order_id = '$order_id'";
$res = $conn->query($sql);
$order = $res->fetch_assoc();

if (!$order) die("ไม่พบข้อมูลออเดอร์");

// แก้ไข logic: จะถือว่าบันทึกแล้วก็ต่อเมื่อสถานะเป็น 1 ขึ้นไป
$is_saved = ($order['order_status'] >= 1);

// 2. ดึงสลิป
$order_num = preg_replace('/[^0-9]/', '', $order_id);
$sql_pay = "SELECT * FROM payment WHERE order_id = '$order_id' OR order_id LIKE '%$order_num%' ORDER BY payment_date DESC LIMIT 1";
$res_pay = $conn->query($sql_pay);
$payment = $res_pay->fetch_assoc();

// 3. ดึงรายการบริการ
$res_services = $conn->query("SELECT s.*, t.svType_behavior FROM service s JOIN service_type t ON s.svType_id = t.svType_id ORDER BY t.svType_behavior DESC");
$services_list = [];
while($s = $res_services->fetch_assoc()) { $services_list[] = $s; }

// 4. Prefilled Items
$prefilled_items = [];
if (!empty($order['rq_note'])) {
    preg_match_all('/-\s+(.*?)\s+\((\d+)\s+ชิ้น\)/', $order['rq_note'], $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $idx => $name) {
            foreach($services_list as $sl) {
                if(trim($sl['service_name']) == trim($name)) {
                    $prefilled_items[] = ['id' => $sl['service_id'], 'qty' => (int)$matches[2][$idx]];
                    break;
                }
            }
        }
    }
}

// 5. เช็คโควตาสมาชิก
$json_file = __DIR__ . '/../config/monthly_data.json';
$monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
$is_monthly = isset($monthly_data[$order['m_id']]);
$member_quota = $is_monthly ? (int)$monthly_data[$order['m_id']]['quota'] : 999999; 

// ดึงราคาซักเกินโควตา
$shop_q = $conn->query("SELECT shop_extra_price FROM shop LIMIT 1")->fetch_assoc();
$extra_price_per_piece = (int)($shop_q['shop_extra_price'] ?? 10);

// 6. คำนวณราคาฐาน (สำหรับ PKG)
$base_price = 0;
if(strpos($order_id, 'PKG') === 0) {
    preg_match('/ราคา ([\d,]+) บาท/', $order['rq_note'], $matches);
    if (isset($matches[1])) {
        $base_price = (float)str_replace(',', '', $matches[1]);
    }
}

// 7. กำหนดป้ายสถานะ
$status_list = [
    0 => ['label' => 'รอรับผ้า', 'color' => '#6c757d'],
    1 => ['label' => 'รอยืนยันยอดเงิน', 'color' => '#ffc107', 'text' => 'dark'],
    2 => ['label' => 'กำลังซักรีด', 'color' => '#0d6efd'],
    3 => ['label' => 'รอส่งคืน', 'color' => '#0dcaf0'],
    4 => ['label' => 'ส่งคืนเรียบร้อย', 'color' => '#198754']
];
$st = $status_list[$order['order_status']] ?? ['label' => 'ไม่ทราบสถานะ', 'color' => '#000'];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการออเดอร์ #<?= $order_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&display=swap');
        body { background: #f1f5f9; font-family: 'Chakra Petch', sans-serif; }
        .main-content { margin-left: 280px; padding: 30px; }
        .card { border: none; border-radius: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .item-row { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; margin-bottom: 10px; }
        .saved-summary { background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 15px; padding: 20px; }
        .quota-error { color: #dc3545; font-weight: bold; font-size: 0.85rem; margin-top: 5px; display: none; }
        .status-badge { padding: 8px 20px; border-radius: 50px; font-weight: 700; font-size: 0.9rem; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 15px; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="manage_orders.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3"><i class="fa fa-arrow-left me-1"></i> ย้อนกลับ</a>
            <div class="text-end d-flex align-items-center gap-3">
                <div class="status-badge" style="background: <?= $st['color'] ?>; color: <?= isset($st['text']) ? '#000' : '#fff' ?>;">
                    <i class="fa fa-clock me-1"></i> <?= $st['label'] ?>
                </div>
                <div class="badge bg-dark rounded-pill py-2 px-3">#<?= $order_id ?></div>
            </div>
        </div>

        <div class="card p-3 mb-4">
            <div class="d-flex justify-content-between text-center small fw-bold">
                <?php 
                $steps = [
                    0 => ['icon' => 'fa-truck-pickup', 'label' => 'รอรับผ้า'],
                    1 => ['icon' => 'fa-file-invoice-dollar', 'label' => 'รอยืนยันยอด'],
                    2 => ['icon' => 'fa-soap', 'label' => 'กำลังซัก'],
                    3 => ['icon' => 'fa-box', 'label' => 'ซักเสร็จ'],
                    4 => ['icon' => 'fa-check-double', 'label' => 'ส่งคืนแล้ว']
                ];
                foreach($steps as $idx => $step):
                    $is_active = $order['order_status'] >= $idx;
                ?>
                <div style="width: 20%;" class="<?= $is_active ? 'text-primary' : 'text-muted opacity-50' ?>">
                    <i class="fa <?= $step['icon'] ?> d-block mb-1 fs-5"></i>
                    <?= $step['label'] ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="progress mt-3" style="height: 8px; background: #e2e8f0; border-radius: 10px;">
                <?php
                $progress = ($order['order_status'] / 4) * 100;
                $color = 'bg-primary';
                if($order['order_status'] == 4) $color = 'bg-success';
                if($order['order_status'] == 1) $color = 'bg-warning';
                ?>
                <div class="progress-bar progress-bar-striped progress-bar-animated <?= $color ?>" role="progressbar" style="width: <?= $progress ?>%"></div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card p-4 mb-4">
                    <h6 class="fw-bold mb-3 text-muted">ข้อมูลลูกค้า</h6>
                    <div class="fw-bold fs-5 mb-1"><?= $order['m_name'] . ' ' . $order['m_lastname'] ?></div>
                    <div class="text-muted small mb-3"><?= $order['m_tel'] ?></div>
                    
                    <?php if($is_monthly): ?>
                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3 text-center mb-3">
                            <i class="fa fa-gem me-1"></i> สิทธิ์รายเดือนคงเหลือ: <b id="display_quota"><?= $member_quota ?></b> ชิ้น
                        </div>
                    <?php endif; ?>

                    <div class="border-top pt-3">
                        <small class="fw-bold text-muted d-block mb-1">ที่อยู่:</small>
                        <p class="small mb-0"><?= nl2br(htmlspecialchars($order['order_address'] ?: $order['m_address'])) ?></p>
                    </div>
                </div>

                <div class="card p-4 mb-4 text-center">
                    <h6 class="fw-bold mb-3 text-start">สลิปการโอนเงิน</h6>
                    <?php if($payment): ?>
                        <img src="data:image/jpeg;base64,<?= base64_encode($payment['payment_image']) ?>" class="img-fluid rounded-3 border mb-3" style="max-height: 350px; cursor: pointer;" onclick="window.open(this.src)">
                        <h4 class="fw-bold text-success">฿<?= number_format($payment['payment_amount'], 2) ?></h4>
                        <?php if($order['order_status'] == 1): ?>
                            <a href="confirm_payment.php?id=<?= $order_id ?>" class="btn btn-success w-100 rounded-pill fw-bold mt-3">ยืนยันยอดเงิน</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="py-5 bg-light rounded-3 text-muted opacity-50">รอลูกค้าโอนเงิน</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-8">
                <?php if(!empty($order['rq_note'])): ?>
                <div class="card p-4 mb-4 border-start border-primary border-4 shadow-sm bg-white">
                    <h6 class="fw-bold text-primary mb-2">สิ่งที่ลูกค้าแจ้งผ่านแอป:</h6>
                    <div style="white-space: pre-wrap; font-size: 0.95rem;"><?= htmlspecialchars($order['rq_note']) ?></div>
                </div>
                <?php endif; ?>

                <div class="card p-4 mb-4">
                    <?php if($is_saved): ?>
                        <div id="summary_section">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="fw-bold mb-0 text-success"><i class="fa fa-check-circle me-1"></i> รายการผ้าที่รับจริงแล้ว</h5>
                                <button class="btn btn-link btn-sm text-decoration-none" onclick="document.getElementById('form_section').style.display='block'; document.getElementById('summary_section').style.display='none';">
                                    <i class="fa fa-edit me-1"></i> บันทึกผ้า
                                </button>
                            </div>
                            <div class="saved-summary mb-4">
                                <div class="fw-bold text-dark fs-5" style="white-space: pre-wrap;"><?= nl2br(htmlspecialchars($order['order_note'])) ?></div>
                            </div>
                            <div class="row g-3 text-center">
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 h-100">
                                        <small class="text-muted d-block">ค่าบริการ/แพ็กเกจ</small>
                                        <h4 class="fw-bold mb-0">฿<?= number_format($order['order_sum'] - $order['order_shipping_fee'], 2) ?></h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 h-100">
                                        <small class="text-muted d-block">ค่าจัดส่ง</small>
                                        <h4 class="fw-bold text-warning mb-0">฿<?= number_format($order['order_shipping_fee'], 2) ?></h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-primary bg-opacity-10 rounded-3 border border-primary h-100">
                                        <small class="text-primary fw-bold d-block">รวมสุทธิ</small>
                                        <h4 class="fw-bold text-primary mb-0">฿<?= number_format($order['order_sum'], 2) ?></h4>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="p-2 bg-light rounded-pill">
                                        <small class="text-muted me-2">จำนวนผ้าที่ใช้สิทธิ์:</small>
                                        <span class="fw-bold text-dark"><?= (int)$order['order_items_count'] ?> ชิ้น</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0">บันทึกผ้า</h5>
                            <div class="text-end">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">ค่าจัดส่งที่ต้องบวกเพิ่ม</small>
                                <span class="badge bg-warning text-dark">฿<?= number_format($order['order_shipping_fee'], 2) ?></span>
                            </div>
                        </div>
                        <div class="p-3 bg-primary bg-opacity-10 rounded-3 mb-4 small border border-primary border-opacity-25">
                            <i class="fa fa-info-circle me-1"></i> กรุณาเลือกรายการผ้าจริงที่ได้รับเพื่อหักโควตาหรือคิดเงินเพิ่ม (ค่าจัดส่งจะถูกคำนวณรวมให้อัตโนมัติ)
                        </div>
                    <?php endif; ?>

                    <div id="form_section" style="<?= $is_saved ? 'display:none;' : '' ?>">
                        <div class="d-flex justify-content-end mb-3">
                            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" onclick="addItemRow()">เพิ่มรายการ</button>
                        </div>
                        <form method="post" action="deduct_monthly.php" id="mainForm">
                            <input type="hidden" name="order_id" value="<?= $order_id ?>">
                            <input type="hidden" name="m_id" value="<?= $order['m_id'] ?>">
                            <input type="hidden" name="manual_mode" value="1">
                            <input type="hidden" name="summary_note" id="summary_note">
                            <input type="hidden" name="service_price" id="service_price" value="0">

                            <div id="items_container" class="mb-4"></div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6" id="box_quota" style="display:none;">
                                    <div class="p-3 bg-primary bg-opacity-10 rounded-3 text-center border border-primary" id="quota_border">
                                        <small class="text-primary fw-bold">หักรายเดือน (ชิ้น)</small>
                                        <input type="number" name="quota_deduct" id="quota_deduct" class="form-control text-center fw-bold border-0 bg-transparent fs-3" value="0" readonly>
                                        <div id="quota_warning" class="quota-error"><i class="fa fa-exclamation-triangle"></i> เกินจำนวนสิทธิ์ที่ลูกค้ามี!</div>
                                    </div>
                                </div>
                                <div class="col-md-6" id="box_cash" style="display:none;">
                                    <div class="p-3 bg-success bg-opacity-10 rounded-3 text-center border border-success">
                                        <small class="text-success fw-bold">ราคาซักรายชิ้น (บาท)</small>
                                        <input type="number" step="0.01" name="extra_cash" id="extra_cash" class="form-control form-control-lg text-center fw-bold border-0 bg-transparent fs-3 text-success" value="0" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3 bg-dark text-white rounded-3 text-center mb-4">
                                <h3 class="fw-bold mb-0">฿<span id="grand_total">0.00</span></h3>
                                <small class="opacity-50">ยอดรวมสุทธิ</small>
                            </div>

                            <div class="mb-4">
                                <label class="small fw-bold text-muted">หมายเหตุ/เวลานัดส่งคืน:</label>
                                <input type="text" name="order_owner_note" class="form-control" placeholder="ระบุข้อความถึงลูกค้า" value="<?= htmlspecialchars($order['order_owner_note']) ?>">
                            </div>

                            <button type="submit" name="use_quota" id="submit_btn" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold">บันทึกข้อมูลและแจ้งลูกค้า</button>
                        </form>
                    </div>
                </div>

                <div class="card p-4">
                    <h6 class="fw-bold mb-3">ขั้นตอนต่อไป:</h6>
                    <form method="post" action="update_status.php">
                        <input type="hidden" name="order_id" value="<?= $order_id ?>">
                        <div class="d-grid gap-2">
                            <?php if($order['order_status'] == 0 && $is_saved): ?>
                                <button type="submit" name="new_status" value="1" class="btn btn-warning py-3 rounded-pill fw-bold">✅ ได้รับผ้ามาแล้ว</button>
                            <?php elseif($order['order_status'] == 2): ?>
                                <button type="submit" name="new_status" value="3" class="btn btn-info text-white py-3 rounded-pill fw-bold">✅ ซักรีดเสร็จสิ้นแล้ว</button>
                            <?php elseif($order['order_status'] == 3): ?>
                                <button type="submit" name="new_status" value="4" class="btn btn-success py-3 rounded-pill fw-bold">🚀 ส่งคืนผ้าเรียบร้อย</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php $is_monthly_order = (strpos($order_id, 'PKG') === 0 || (int)$order['order_pickup_round'] > 0); ?>
    <script>
    const services = <?= json_encode($services_list) ?>;
    const prefilled = <?= json_encode($order['order_status'] < 2 ? $prefilled_items : []) ?>;
    const shippingFee = <?= (float)($order['order_shipping_fee'] ?? 0) ?>;
    const basePrice = <?= (float)$base_price ?>;
    const extraPrice = <?= $extra_price_per_piece ?>;
    const memberQuota = <?= $member_quota ?>;
    const isMonthlyOrder = <?= json_encode($is_monthly_order) ?>;

    function addItemRow(srvId = '', q = 1) {
        const container = document.getElementById('items_container');
        if(!container) return;
        const row = document.createElement('div');
        row.className = 'item-row d-flex align-items-center gap-3';
        let opts = '<option value="">-- เลือกรายการ --</option>';
        
        // กรองรายการตามประเภทออเดอร์ และต้องไม่ใช่ตัวแพ็กเกจ (quota ต้องเป็น 0)
        const filteredServices = services.filter(s => {
            const isPackage = parseInt(s.service_quota) > 0;
            if (isPackage) return false; // ไม่เอาตัวแพ็กเกจมาโชว์ในที่บันทึกผ้า

            if (isMonthlyOrder) {
                return s.svType_behavior === 'monthly';
            } else {
                return s.svType_behavior !== 'monthly';
            }
        });

        filteredServices.forEach(s => {
            const lab = s.svType_behavior === 'monthly' ? '(หักสิทธิ์)' : `(฿${parseFloat(s.service_price)})`;
            opts += `<option value="${s.service_id}" data-name="${s.service_name}" data-price="${s.service_price}" data-behavior="${s.svType_behavior}" ${s.service_id===srvId?'selected':''}>${s.service_name} ${lab}</option>`;
        });
        
        row.innerHTML = `<div class="flex-grow-1"><select name="service_ids[]" class="form-select border-0 fw-bold" onchange="calc()" required>${opts}</select><input type="hidden" name="item_names[]" class="item-name-hidden"></div><div style="width: 80px;"><input type="number" name="item_qtys[]" class="form-control text-center border-0 bg-light" value="${q}" min="1" onchange="calc()"></div><button type="button" class="btn btn-link text-danger" onclick="this.closest('.item-row').remove(); calc();"><i class="fa fa-trash"></i></button>`;
        container.appendChild(row);
        calc();
    }

    function calc() {
        let qC = 0, cS = 0, n = [];
        document.querySelectorAll('.item-row').forEach(row => {
            const sel = row.querySelector('select'), qty = parseInt(row.querySelector('input[name="item_qtys[]"]').value) || 0, opt = sel.options[sel.selectedIndex];
            if(opt && opt.value) {
                const b = opt.getAttribute('data-behavior'), p = parseFloat(opt.getAttribute('data-price')), name = opt.getAttribute('data-name');
                if(b === 'monthly') qC += qty; else cS += (p * qty);
                row.querySelector('.item-name-hidden').value = name;
                n.push(`${name} (${qty})`);
            }
        });
        
        // --- 💰 คำนวณส่วนต่างโควตา ---
        let overQuotaFee = 0;
        let overQty = 0;
        if (qC > memberQuota) {
            overQty = qC - Math.max(0, memberQuota);
            overQuotaFee = overQty * extraPrice;
            cS += overQuotaFee;
            n.push(`+ ซักเกินโควตา ${overQty} ชิ้น (฿${overQuotaFee})`);
        }

        document.getElementById('box_quota').style.display = qC > 0 ? 'block' : 'none';
        document.getElementById('box_cash').style.display = cS > 0 ? 'block' : 'none';
        document.getElementById('quota_deduct').value = qC;
        document.getElementById('extra_cash').value = cS;
        document.getElementById('grand_total').innerText = (basePrice + cS + shippingFee).toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('summary_note').value = n.join('\n');

        // --- 🔒 ปลดล็อคปุ่มเสมอ (เพื่อให้บันทึกส่วนเกินได้) ---
        const btn = document.getElementById('submit_btn');
        const warn = document.getElementById('quota_warning');
        const border = document.getElementById('quota_border');

        if (qC > memberQuota) {
            btn.disabled = false; // ปลดล็อค
            btn.classList.replace('btn-secondary', 'btn-primary');
            warn.style.display = 'block';
            warn.innerHTML = `<i class="fa fa-exclamation-triangle"></i> เกินโควตา ${overQty} ชิ้น (คิดเพิ่ม ฿${overQuotaFee})`;
            border.classList.add('border-danger');
            border.classList.replace('bg-primary', 'bg-danger');
        } else {
            btn.disabled = false;
            btn.classList.replace('btn-secondary', 'btn-primary');
            warn.style.display = 'none';
            border.classList.remove('border-danger');
            border.classList.replace('bg-danger', 'bg-primary');
        }
    }

    window.onload = () => {
        if(prefilled.length > 0) prefilled.forEach(i => addItemRow(i.id, i.qty));
        else if(document.getElementById('items_container')) addItemRow();
    };
    </script>
</body>
</html>
