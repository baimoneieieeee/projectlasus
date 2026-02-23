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

if (isset($_POST['clear_payments'])) {
    // ลบข้อมูลทั้งหมดจากตาราง payment
    if ($conn->query("DELETE FROM payment")) {
        // แนะนำให้รีเซ็ต AUTO_INCREMENT ด้วยเพื่อให้ ID เริ่มต้นใหม่ที่ 1
        $conn->query("ALTER TABLE payment AUTO_INCREMENT = 1");
        $success = "ลบประวัติการชำระเงินทั้งหมดสำเร็จแล้ว!";
    } else {
        $error = "เกิดข้อผิดพลาดในการลบข้อมูล: " . $conn->error;
    }
}

if (isset($_POST['clear_orders'])) {
    if ($conn->query("DELETE FROM orders")) {
        $success = "ลบรายการสั่งซื้อและประวัติที่เกี่ยวข้องทั้งหมดสำเร็จแล้ว!";
    } else {
        $error = "เกิดข้อผิดพลาด: " . $conn->error;
    }
}

if (isset($_POST['clear_requests'])) {
    if ($conn->query("DELETE FROM request")) {
        $success = "ลบคำขอรับผ้าทั้งหมดสำเร็จแล้ว!";
    } else {
        $error = "เกิดข้อผิดพลาด: " . $conn->error;
    }
}

if (isset($_POST['clear_monthly'])) {
    $json_file = __DIR__ . '/../config/monthly_data.json';
    if (file_exists($json_file)) {
        // เขียนไฟล์ว่างเปล่าทับลงไป
        if (file_put_contents($json_file, json_encode([], JSON_PRETTY_PRINT))) {
            $success = "ล้างข้อมูลโควตารายเดือนของลูกค้าทุกคนเรียบร้อยแล้ว!";
        } else {
            $error = "ไม่สามารถเข้าถึงไฟล์ข้อมูลได้";
        }
    } else {
        $success = "ไม่มีข้อมูลโควตาในระบบอยู่แล้ว";
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
</head>
<body>
    <?php include 'sidebar.php'; ?>

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

        <!-- Danger Zone -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mt-4 border-top border-danger border-5">
            <h5 class="fw-bold text-danger mb-3"><i class="fa fa-exclamation-triangle me-2"></i> พื้นที่อันตราย (Danger Zone)</h5>
            <p class="text-muted small">การดำเนินการนี้จะไม่สามารถย้อนกลับได้ กรุณาตรวจสอบให้แน่ใจก่อนกดปุ่ม</p>
            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fw-bold mb-1">ลบประวัติการชำระเงินทั้งหมด</h6>
                    <p class="text-muted small mb-0">ลบข้อมูลการชำระเงินและรูปภาพสลิปทั้งหมดออกจากระบบ</p>
                </div>
                <form method="post" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบประวัติการชำระเงินทั้งหมด?')">
                    <button type="submit" name="clear_payments" class="btn btn-outline-danger rounded-pill px-4">
                        ลบประวัติชำระเงิน
                    </button>
                </form>
            </div>
            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fw-bold mb-1">ลบรายการสั่งซื้อทั้งหมด</h6>
                    <p class="text-muted small mb-0">ลบออเดอร์, รายการผ้า, สลิป และข้อมูลการจัดส่งทั้งหมด (Reset ระบบใหม่)</p>
                </div>
                <form method="post" onsubmit="return confirm('อันตราย! คุณแน่ใจหรือไม่ที่จะลบรายการสั่งซื้อทั้งหมด? ข้อมูลนี้จะไม่สามารถกู้คืนได้!')">
                    <button type="submit" name="clear_orders" class="btn btn-outline-danger rounded-pill px-4">
                        ลบออเดอร์ทั้งหมด
                    </button>
                </form>
            </div>
            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fw-bold mb-1">ลบคำขอรับผ้าทั้งหมด</h6>
                    <p class="text-muted small mb-0">ลบรายการแจ้งให้ไปรับผ้าที่ค้างอยู่และที่ดำเนินการแล้วทั้งหมด</p>
                </div>
                <form method="post" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบคำขอรับผ้าทั้งหมด?')">
                    <button type="submit" name="clear_requests" class="btn btn-outline-danger rounded-pill px-4">
                        ลบคำขอทั้งหมด
                    </button>
                </form>
            </div>
            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fw-bold mb-1">ล้างโควตารายเดือนทั้งหมด</h6>
                    <p class="text-muted small mb-0">ลบจำนวนชิ้นคงเหลือของลูกค้ารายเดือนทุกคนให้กลายเป็น 0 (ล้างไฟล์ JSON)</p>
                    <p class="text-danger smaller mb-0" style="font-size: 0.75rem;">* ใช้สำหรับตัดจบโควตาเมื่อครบกำหนด 30 วันตามนโยบายร้าน</p>
                </div>
                <form method="post" onsubmit="return confirm('อันตราย! คุณแน่ใจหรือไม่ที่จะลบโควตารายเดือนของลูกค้าทุกคน? ยอดคงเหลือจะหายไปทั้งหมด!')">
                    <button type="submit" name="clear_monthly" class="btn btn-outline-danger rounded-pill px-4">
                        ล้างโควตาทั้งหมด
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
