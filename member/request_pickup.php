<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit;
}

$user = $_SESSION['user'];
$m_id = $user['m_id'];

// ดึงข้อมูลรายเดือนมาเตรียมไว้เช็คสถานะลูกค้า
$json_file = __DIR__ . '/../config/monthly_data.json';
$monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
$is_m = isset($monthly_data[$m_id]);

// 🎯 1. ดึงรายการบริการพิเศษ (แบบจ่ายเงินสด)
$res_special = $conn->query("SELECT s.*, t.svType_behavior FROM service s JOIN service_type t ON s.svType_id = t.svType_id WHERE t.svType_behavior = 'special' ORDER BY s.service_price ASC");
$services_json = [];
if ($res_special) {
    while($s = $res_special->fetch_assoc()) {
        $services_json[] = $s;
    }
}

// 🎯 2. ดึงรายการบริการแบบโควตา (หักจากรายเดือน)
$res_quota_srv = $conn->query("SELECT s.* FROM service s JOIN service_type t ON s.svType_id = t.svType_id WHERE t.svType_behavior = 'quota' AND s.service_quota = 0 LIMIT 1");
$quota_service = $res_quota_srv ? $res_quota_srv->fetch_assoc() : null;

if (isset($_POST['request'])) {
    $rq_id = "RQ" . time();
    $date = date("Y-m-d H:i:s");
    $lat = $user['m_latitude'] ?? 0;
    $lng = $user['m_longitude'] ?? 0;
    
    $items_json = $_POST['items_data'];
    $items = json_decode($items_json, true);
    $grand_total = (float)$_POST['grand_total'];
    
    $summary = "📦 รายการผ้าที่ส่งซัก:\n";
    if (!empty($items)) {
        foreach($items as $it) {
            $price_text = ($it['isQuota'] == 1) ? "หักโควตา" : "฿" . number_format($it['price'] * $it['qty']);
            $summary .= "- " . $it['name'] . " (" . $it['qty'] . " ชิ้น) : " . $price_text . "\n";
        }
    }
    $summary .= "💰 รวมยอดที่ต้องชำระ: ฿" . number_format($grand_total);
    
    $note = $conn->real_escape_string($_POST['note']);
    $full_note = $summary . "\n📝 หมายเหตุ: " . $note;

    $sql = "INSERT INTO request (rq_id, m_id, rq_date, rq_latitude, rq_longitude, rq_status, rq_sum, rq_note) 
            VALUES ('$rq_id', '$m_id', '$date', '$lat', '$lng', 0, '$grand_total', '$full_note')";
    
    if ($conn->query($sql)) {
        header("Location: home.php?msg=success");
        exit;
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แจ้งให้ร้านมารับผ้า - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .item-row { border-bottom: 1px dashed #dee2e6; padding: 10px 0; }
        .item-row:last-child { border-bottom: none; }
    </style>
</head>
<body class="bg-light py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h3 class="fw-bold text-center mb-2">🏠 แจ้งให้ร้านมารับผ้า</h3>
                    <p class="text-muted text-center small mb-4">เลือกประเภทผ้าที่ต้องการส่งเพื่อให้ร้านจัดเตรียมคิวงานครับ</p>
                    
                    <div class="alert alert-primary border-0 rounded-4 small p-3 mb-4 shadow-sm">
                        <div class="fw-bold mb-2 text-primary"><i class="fa fa-info-circle me-1"></i> บริการที่เลือกได้:</div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if($is_m && $quota_service): ?>
                                <span class="badge bg-white text-primary border p-2 rounded-3">🌟 ซักผ้าปกติ (หักโควตา)</span>
                            <?php endif; ?>
                            <?php foreach($services_json as $s): ?>
                                <span class="badge bg-white text-dark border fw-normal p-2 rounded-3">
                                    <?= htmlspecialchars($s['service_name']) ?> (฿<?= number_format($s['service_price']) ?>)
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-4 border mb-4 shadow-sm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">เลือกประเภทผ้า</label>
                                <select id="service_select" class="form-select rounded-3">
                                    <option value="">-- เลือกรายการ --</option>
                                    <?php if($is_m && $quota_service): ?>
                                        <option value="<?= $quota_service['service_id'] ?>" data-name="ซักผ้าปกติ" data-price="0" data-is-quota="1">
                                            🌟 ซักผ้าปกติ (ใช้โควตาสมาชิก)
                                        </option>
                                    <?php endif; ?>
                                    <?php foreach($services_json as $s): ?>
                                        <option value="<?= $s['service_id'] ?>" data-name="<?= htmlspecialchars($s['service_name']) ?>" data-price="<?= $s['service_price'] ?>" data-is-quota="0">
                                            <?= htmlspecialchars($s['service_name']) ?> (฿<?= number_format($s['service_price']) ?> / ชิ้น)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">จำนวน</label>
                                <input type="number" id="qty_input" class="form-control rounded-3" value="1" min="1">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="button" onclick="addItem()" class="btn btn-primary w-100 rounded-pill fw-bold">
                                    <i class="fa fa-plus-circle me-1"></i> เพิ่ม
                                </button>
                            </div>
                        </div>
                    </div>

                    <form method="post" id="requestForm">
                        <input type="hidden" name="items_data" id="items_data" value="[]">
                        <input type="hidden" name="grand_total" id="grand_total_input" value="0">
                        
                        <div id="basket_container" class="mb-4" style="display: none;">
                            <h6 class="fw-bold mb-3"><i class="fa fa-shopping-basket me-2 text-primary"></i> รายการที่ส่ง:</h6>
                            <div id="basket_list" class="bg-light p-3 rounded-4 border mb-3"></div>
                            <div class="text-end px-3">
                                <p class="mb-0 text-muted small">ยอดรวมที่ต้องชำระ (ประมาณการ)</p>
                                <h3 class="fw-bold text-primary">฿<span id="grand_total_display">0</span></h3>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small">📝 หมายเหตุ (เช่น พิกัดบ้าน หรือฝากวางผ้า)</label>
                            <textarea name="note" class="form-control rounded-3" rows="2" placeholder="ระบุรายละเอียดเพิ่มเติม..."></textarea>
                        </div>

                        <button type="submit" name="request" id="submitBtn" class="btn btn-primary btn-lg w-100 rounded-pill py-3 fw-bold shadow" disabled>
                            <i class="fa fa-paper-plane me-2"></i> ส่งคำขอให้ร้านค้า
                        </button>
                        <a href="home.php" class="btn btn-light btn-lg w-100 rounded-pill mt-3">ย้อนกลับ</a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    let basket = [];
    function addItem() {
        const select = document.getElementById('service_select');
        const option = select.options[select.selectedIndex];
        if (!option || !option.value) return;
        const id = option.value;
        const name = option.getAttribute('data-name');
        const price = parseFloat(option.getAttribute('data-price'));
        const isQuota = option.getAttribute('data-is-quota') === '1' ? 1 : 0;
        const qty = parseInt(document.getElementById('qty_input').value);
        const existing = basket.find(item => item.id === id);
        if (existing) { existing.qty += qty; } else { basket.push({ id, name, price, qty, isQuota }); }
        renderBasket();
        select.selectedIndex = 0;
        document.getElementById('qty_input').value = 1;
    }
    function removeItem(index) { basket.splice(index, 1); renderBasket(); }
    function renderBasket() {
        const list = document.getElementById('basket_list');
        const container = document.getElementById('basket_container');
        const totalDisplay = document.getElementById('grand_total_display');
        const totalInput = document.getElementById('grand_total_input');
        list.innerHTML = '';
        let total = 0;
        basket.forEach((item, index) => {
            const subtotal = item.isQuota ? 0 : (item.price * item.qty);
            total += subtotal;
            list.innerHTML += `<div class="item-row d-flex justify-content-between align-items-center">
                <div><div class="fw-bold">${item.name}</div><div class="small text-muted">${item.isQuota ? 'หักโควตา' : '฿'+item.price+' / ชิ้น'} x ${item.qty}</div></div>
                <div class="text-end"><div class="fw-bold text-primary">${item.isQuota ? 'ฟรี' : '฿'+subtotal.toLocaleString()}</div>
                <button type="button" onclick="removeItem(${index})" class="btn btn-sm text-danger border-0 small">ลบ</button></div>
            </div>`;
        });
        totalDisplay.innerText = total.toLocaleString();
        totalInput.value = total;
        container.style.display = basket.length > 0 ? 'block' : 'none';
        document.getElementById('submitBtn').disabled = basket.length === 0;
        document.getElementById('items_data').value = JSON.stringify(basket);
    }
    </script>
</body>
</html>
