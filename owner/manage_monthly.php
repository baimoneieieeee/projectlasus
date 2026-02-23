<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

$json_file = __DIR__ . '/../config/monthly_data.json';

// อ่านข้อมูลจาก JSON (ถ้าไม่มีให้สร้างใหม่)
$monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];

if (isset($_POST['add_quota'])) {
    $m_id = $_POST['m_id'];
    $quota = (int)$_POST['quota'];
    $pickups = (int)$_POST['pickups_used']; // รับค่าจำนวนครั้งที่รับผ้ามาด้วย
    
    if (!isset($monthly_data[$m_id])) {
        $monthly_data[$m_id] = ['quota' => 0, 'pickups_used' => 0, 'updated_at' => ''];
    }
    
    $monthly_data[$m_id]['quota'] += $quota;
    $monthly_data[$m_id]['pickups_used'] = $pickups; // อัปเดตจำนวนครั้งตามที่กรอก
    $monthly_data[$m_id]['updated_at'] = date("Y-m-d H:i:s");
    
    file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));
    $success = "อัปเดตข้อมูลโควตาและจำนวนครั้งเรียบร้อยแล้ว";
}

$res_members = $conn->query("SELECT * FROM member");
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>จัดการลูกค้ารายเดือน - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-light">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h4 class="fw-bold mb-4">🌟 จัดการโควตารายเดือน (Prepaid)</h4>
            
            <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4">
                <i class="fa fa-info-circle me-2"></i>
                <strong>นโยบายแพ็กเกจ:</strong> เมื่อครบกำหนด 1 เดือน (30 วัน) สิทธิโควตาซักผ้าและสิทธิเรียกรับผ้าที่คงเหลือจะถูกตัดจบทั้งหมด แม้ลูกค้าจะยังใช้ไม่ครบจำนวน
            </div>

            <?php if(isset($success)): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <form method="post" class="row g-3 mb-5 border-bottom pb-4">
                <div class="col-md-5">
                    <label class="form-label small fw-bold">เลือกลูกค้า</label>
                    <select name="m_id" class="form-select rounded-pill" required>
                        <?php while($m = $res_members->fetch_assoc()): ?>
                            <option value="<?= $m['m_id'] ?>"><?= $m['m_name'] ?> (<?= $m['m_id'] ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">เติมจำนวนชิ้น (+)</label>
                    <input type="number" name="quota" class="form-control rounded-pill" placeholder="0" value="0" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">จำนวนครั้งที่รับผ้าแล้ว (0-4)</label>
                    <input type="number" name="pickups_used" class="form-control rounded-pill" placeholder="0" value="0" min="0" max="10" required>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" name="add_quota" class="btn btn-primary w-100 rounded-pill">บันทึก</button>
                </div>
            </form>

            <h5 class="fw-bold">รายชื่อลูกค้าที่มีโควตาคงเหลือ</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>ID ลูกค้า</th><th>โควตาคงเหลือ</th><th>ใช้สิทธิ์รับผ้าไปแล้ว</th><th>อัปเดตล่าสุด</th></tr></thead>
                    <tbody>
                        <?php foreach($monthly_data as $mid => $data): ?>
                        <tr>
                            <td><?= $mid ?></td>
                            <td class="fw-bold text-success"><?= $data['quota'] ?> ชิ้น</td>
                            <td class="fw-bold <?= $data['pickups_used'] >= 4 ? 'text-danger' : 'text-primary' ?>">
                                <?= $data['pickups_used'] ?> / 4 ครั้ง
                            </td>
                            <td class="small text-muted"><?= $data['updated_at'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <a href="owner_home.php" class="btn btn-light rounded-pill">กลับหน้าแรก</a>
            </div>
        </div>
    </div>
</body>
</html>
