<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit;
}

$m_id = $_SESSION['user']['m_id'];

// ดึงข้อมูลล่าสุดจากฐานข้อมูล
$sql = "SELECT * FROM member WHERE m_id = '$m_id'";
$res = $conn->query($sql);
$user_data = $res->fetch_assoc();

if (isset($_POST['update_profile'])) {
    $name = $conn->real_escape_string($_POST['m_name']);
    $lastname = $conn->real_escape_string($_POST['m_lastname']);
    $tel = $conn->real_escape_string($_POST['m_tel']);
    $address = $conn->real_escape_string($_POST['m_address']);

    $sql_update = "UPDATE member SET 
                   m_name = '$name', 
                   m_lastname = '$lastname', 
                   m_tel = '$tel', 
                   m_address = '$address' 
                   WHERE m_id = '$m_id'";

    if ($conn->query($sql_update)) {
        // อัปเดตข้อมูลในเซสชันด้วย
        $_SESSION['user']['m_name'] = $name;
        $_SESSION['user']['m_lastname'] = $lastname;
        $_SESSION['user']['m_tel'] = $tel;
        $_SESSION['user']['m_address'] = $address;
        $success = "อัปเดตข้อมูลโปรไฟล์เรียบร้อยแล้ว";
        
        // โหลดข้อมูลใหม่เพื่อแสดงในฟอร์ม
        $res = $conn->query($sql);
        $user_data = $res->fetch_assoc();
    } else {
        $error = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . $conn->error;
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แก้ไขข้อมูลส่วนตัว - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg sticky-top bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="home.php">👕 Laundry</a>
            <a href="home.php" class="btn btn-outline-secondary btn-sm">กลับหน้าหลัก</a>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h4 class="fw-bold mb-4 text-center">📝 แก้ไขข้อมูลส่วนตัว</h4>

                    <?php if(isset($success)): ?>
                        <div class="alert alert-success border-0 rounded-4 mb-4"><?= $success ?></div>
                    <?php endif; ?>
                    <?php if(isset($error)): ?>
                        <div class="alert alert-danger border-0 rounded-4 mb-4"><?= $error ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">ชื่อ</label>
                                <input type="text" name="m_name" class="form-control rounded-pill" value="<?= htmlspecialchars($user_data['m_name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">นามสกุล</label>
                                <input type="text" name="m_lastname" class="form-control rounded-pill" value="<?= htmlspecialchars($user_data['m_lastname']) ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">เบอร์โทรศัพท์</label>
                                <input type="text" name="m_tel" class="form-control rounded-pill" value="<?= htmlspecialchars($user_data['m_tel']) ?>" required maxlength="10">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">ที่อยู่สำหรับรับ-ส่งผ้า (เพิ่มรายละเอียดเพื่อการจัดส่งที่เร็วขึ้น)</label>
                                <textarea name="m_address" class="form-control rounded-4" rows="4" required><?= htmlspecialchars($user_data['m_address']) ?></textarea>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-info border-0 rounded-4 py-2 small d-flex align-items-center">
                                    <i class="fa fa-map-marker-alt me-2 text-danger"></i>
                                    <span>พิกัดปัจจุบัน: <?= $user_data['m_latitude'] ?>, <?= $user_data['m_longitude'] ?></span>
                                    <a href="set_location.php" class="ms-auto btn btn-sm btn-light rounded-pill">แก้ไขพิกัด</a>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" name="update_profile" class="btn btn-primary btn-lg w-100 rounded-pill">
                                <i class="fa fa-save me-2"></i> บันทึกข้อมูล
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
