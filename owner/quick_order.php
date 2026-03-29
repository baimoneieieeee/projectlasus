<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

// จัดการการบันทึกบิลด่วน
if (isset($_POST['create_quick_order'])) {
    $m_id = $_POST['m_id'];
    $quota_deduct = (int)$_POST['quota_deduct'];
    $extra_cash = (float)$_POST['extra_cash'];
    $pickup_round = (int)$_POST['pickup_round'];
    $note = $conn->real_escape_string($_POST['summary_note']);
    
    $order_id = "ORD" . time();
    $now = date("Y-m-d H:i:s");

    // ... (ส่วนจัดการ JSON เหมือนเดิม) ...
    $json_file = __DIR__ . '/../config/monthly_data.json';
    $monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];

    if (isset($monthly_data[$m_id])) {
        if ($monthly_data[$m_id]['quota'] >= $quota_deduct) {
            $monthly_data[$m_id]['quota'] -= $quota_deduct;
            if ($pickup_round > 0) { $monthly_data[$m_id]['pickups_used'] = $pickup_round; }
            $monthly_data[$m_id]['updated_at'] = $now;
            file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));
        }
    }

    // 2. สร้างออเดอร์ในฐานข้อมูล
    $note_appendix = ($quota_deduct > 0) ? " (หักโควตา $quota_deduct ชิ้น)" : "";
    $full_note = $note . $note_appendix;
    
    $status = ($extra_cash > 0) ? 1 : 2; 
    
    $sql = "INSERT INTO orders (order_id, m_id, order_date, order_status, order_sum, order_note, order_pickup_round) 
            VALUES ('$order_id', '$m_id', '$now', '$status', '$extra_cash', '$full_note', '$pickup_round')";
    
    if ($conn->query($sql)) {
        header("Location: manage_orders.php");
        exit;
    }
}

$res_members = $conn->query("SELECT m.* FROM member m");
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เปิดบิลซักด่วน - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&display=swap');
        :root { --primary: #2563eb; --bg: #f8fafc; }
        body { background: var(--bg); font-family: 'Chakra Petch', sans-serif; }
        .main-content { margin-left: 280px; padding: 40px; }
        .card-custom { border: none; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: white; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="mb-4">
                <h2 class="fw-bold mb-1">🚀 เปิดบิลซักด่วน (สมาชิก)</h2>
                <p class="text-muted">สำหรับบันทึกรายการกรณีลูกค้าหน้าร้าน หรือลูกค้านำผ้ามาส่งเองโดยไม่ได้แจ้งผ่านแอป</p>
            </div>
            
            <!-- 💡 กล่องคำแนะนำ -->
            <div class="alert alert-warning border-0 shadow-sm rounded-4 p-3 mb-4">
                <div class="fw-bold mb-1 small"><i class="fa fa-info-circle me-1"></i> วิธีใช้งาน:</div>
                <ul class="mb-0 smaller text-dark" style="font-size: 0.85rem;">
                    <li><b>หักผ้าปกติ:</b> ใส่จำนวนในช่อง "จำนวนผ้าปกติ" (จะไปลบจาก 80 ชิ้น)</li>
                    <li><b>นับรอบส่ง:</b> เลือกว่าครั้งนี้คือรอบที่เท่าไหร่จาก 4 ครั้งต่อเดือน</li>
                    <li><b>ผ้าพิเศษ:</b> ถ้ามีผ้านวมให้ใส่ราคาเพิ่ม ถ้าไม่มีใส่ 0</li>
                </ul>
            </div>

            <?php if(isset($error)): ?>
                <div class="alert alert-danger rounded-4 border-0 shadow-sm"><?= $error ?></div>
            <?php endif; ?>

            <div class="card card-custom p-5">
                <form method="post">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">1. เลือกชื่อลูกค้าสมาชิก</label>
                            <select name="m_id" class="form-select form-select-lg rounded-3 shadow-sm" required>
                                <option value="">-- เลือกสมาชิก --</option>
                                <?php while($m = $res_members->fetch_assoc()): ?>
                                    <option value="<?= $m['m_id'] ?>"><?= $m['m_name'] . ' ' . $m['m_lastname'] ?> (<?= $m['m_tel'] ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-primary">2. จำนวนผ้าปกติ (ชิ้น)</label>
                            <input type="number" name="quota_deduct" class="form-control form-control-lg rounded-3 border-primary shadow-sm" value="0" min="0" required>
                            <div class="form-text text-primary">ใส่จำนวนเสื้อผ้าทั่วไป <b>เพื่อหักโควตา</b></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">3. รอบการส่งผ้า (ครั้งที่)</label>
                            <select name="pickup_round" class="form-select form-select-lg rounded-3 shadow-sm">
                                <option value="0">ไม่นับรอบ (ลูกค้ามาส่งเอง)</option>
                                <option value="1">รอบที่ 1</option>
                                <option value="2">รอบที่ 2</option>
                                <option value="3">รอบที่ 3</option>
                                <option value="4">รอบที่ 4</option>
                            </select>
                            <div class="form-text small">ระบุเพื่อให้นักจำนวน 4 ครั้งต่อเดือน</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-danger">4. ยอดเงินเก็บเพิ่ม (บาท)</label>
                            <input type="number" step="0.01" name="extra_cash" class="form-control form-control-lg rounded-3 border-danger shadow-sm" value="0" min="0" required>
                            <div class="form-text text-danger">ราคาผ้าพิเศษ <b>ถ้าไม่มีใส่ 0</b></div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">5. หมายเหตุ (สรุปรายการ)</label>
                            <input type="text" name="summary_note" class="form-control form-control-lg rounded-3 shadow-sm" placeholder="เช่น ผ้า 10 ชิ้น + ผ้านวม 1" required>
                        </div>
                        
                        <div class="col-12 mt-5">
                            <button type="submit" name="create_quick_order" class="btn btn-primary btn-lg w-100 rounded-pill py-3 fw-bold shadow">
                                <i class="fa fa-save me-2"></i> บันทึกบิลและแจ้งยอดทันที
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
