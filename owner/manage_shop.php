<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

$shop_id = $_SESSION['user']['shop_id'];

if (isset($_POST['update_shop'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $address = $conn->real_escape_string($_POST['address']);
    $tel = $conn->real_escape_string($_POST['tel']);
    $mail = $conn->real_escape_string($_POST['mail']);
    $pass = $conn->real_escape_string($_POST['password']);
    
    // จัดการอัปโหลดคิวอาร์โค้ด
    if (!empty($_FILES['qrcode']['tmp_name'])) {
        $qrcode = addslashes(file_get_contents($_FILES['qrcode']['tmp_name']));
        $conn->query("UPDATE shop SET shop_QRcode = '$qrcode' WHERE shop_id = '$shop_id'");
    }

    $sql = "UPDATE shop SET 
            shop_name1 = '$name', 
            shop_address = '$address', 
            shop_tel = '$tel', 
            shop_mail = '$mail', 
            shop_password = '$pass' 
            WHERE shop_id = '$shop_id'";
    
    if ($conn->query($sql)) {
        // อัปเดตข้อมูลในเซสชันด้วย
        $_SESSION['user']['shop_name1'] = $name;
        $_SESSION['user']['shop_mail'] = $mail;
        $_SESSION['user']['shop_password'] = $pass;
        $success = "อัปเดตข้อมูลร้านค้าเรียบร้อยแล้ว";
    }
}

$shop = $conn->query("SELECT * FROM shop WHERE shop_id = '$shop_id'")->fetch_assoc();
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ตั้งค่าร้านค้า - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root { --primary: #2563EB; --bg: #F8FAFC; }
        body { background: var(--bg); font-family: 'Segoe UI', sans-serif; }
        .sidebar { width: 260px; height: 100vh; background: #fff; position: fixed; border-right: 1px solid #E2E8F0; padding: 20px; }
        .main-content { margin-left: 260px; padding: 30px; }
        .nav-link { color: #64748B; padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; }
        .nav-link.active { background: #EFF6FF; color: var(--primary); font-weight: 600; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h4 class="fw-bold text-primary mb-4 px-3">🧺 Shop Admin</h4>
        <nav class="nav flex-column">
            <a class="nav-link" href="owner_home.php"><i class="fa fa-home me-2"></i> หน้าแรก</a>
            <a class="nav-link" href="manage_requests.php"><i class="fa fa-bell me-2"></i> คำขอรับผ้า</a>
            <a class="nav-link" href="manage_orders.php"><i class="fa fa-file-invoice me-2"></i> รายการสั่งซื้อ</a>
            <a class="nav-link" href="manage_services.php"><i class="fa fa-tags me-2"></i> จัดการบริการ</a>
            <a class="nav-link" href="manage_members.php"><i class="fa fa-users me-2"></i> ข้อมูลลูกค้า</a>
            <a class="nav-link active" href="manage_shop.php"><i class="fa fa-cog me-2"></i> ตั้งค่าร้านค้า</a>
        </nav>
    </div>

    <div class="main-content">
        <h2 class="fw-bold mb-4">ตั้งค่าร้านค้า</h2>

        <?php if(isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
                <i class="fa fa-check-circle me-2"></i> <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 p-4">
            <form method="post" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold">ชื่อร้านค้า</label>
                        <input type="text" name="name" class="form-control rounded-pill" value="<?= $shop['shop_name1'] ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold">เบอร์โทรศัพท์</label>
                        <input type="text" name="tel" class="form-control rounded-pill" value="<?= $shop['shop_tel'] ?>" maxlength="10">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold">อีเมลเข้าใช้งาน</label>
                        <input type="email" name="mail" class="form-control rounded-pill" value="<?= $shop['shop_mail'] ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold">รหัสผ่าน</label>
                        <input type="text" name="password" class="form-control rounded-pill" value="<?= $shop['shop_password'] ?>" required>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label small fw-bold">ที่อยู่ร้านค้า</label>
                        <textarea name="address" class="form-control rounded-4" rows="3"><?= $shop['shop_address'] ?></textarea>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label small fw-bold">คิวอาร์โค้ดรับเงิน (PromptPay)</label>
                        <input type="file" name="qrcode" class="form-control rounded-pill" accept="image/*">
                        <?php if($shop['shop_QRcode']): ?>
                            <div class="mt-3">
                                <p class="small text-muted mb-2">รูปคิวอาร์โค้ดเดิม:</p>
                                <img src="data:image/jpeg;base64,<?= base64_encode($shop['shop_QRcode']) ?>" class="img-thumbnail rounded-4 shadow-sm" style="max-height: 200px;">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-end">
                    <button type="submit" name="update_shop" class="btn btn-primary rounded-pill px-5 py-2 fw-bold">
                        <i class="fa fa-save me-2"></i> บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
