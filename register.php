<?php
session_start();
include __DIR__ . '/config/db.php';

$error = "";
$success = "";

if (isset($_POST['register'])) {
    $m_id = "MEM" . time();
    $name = $conn->real_escape_string($_POST['name']);
    $lastname = $conn->real_escape_string($_POST['lastname']);
    $email = $conn->real_escape_string($_POST['email']);
    $pass = $conn->real_escape_string($_POST['password']);
    $tel = $conn->real_escape_string($_POST['tel']);
    $address = $conn->real_escape_string($_POST['address']);

    // ตรวจสอบอีเมลซ้ำ
    $check = $conn->query("SELECT m_id FROM member WHERE m_mail = '$email'");
    if ($check->num_rows > 0) {
        $error = "อีเมลนี้ถูกใช้งานแล้ว";
    } else {
        $sql = "INSERT INTO member (m_id, m_name, m_lastname, m_password, m_mail, m_tel, m_address, m_latitude, m_longitude) 
                VALUES ('$m_id', '$name', '$lastname', '$pass', '$email', '$tel', '$address', 0, 0)";
        
        if ($conn->query($sql)) {
            $success = "สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ";
        } else {
            $error = "เกิดข้อผิดพลาด: " . $conn->error;
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>สมัครสมาชิก - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-1: #E6F4FF;
            --bg-2: #D9ECFF;
            --panel: #FFFFFF;
            --border: #C8D9EE;
            --text: #1E293B;
            --primary: #3B82F6;
            --radius: 16px;
            --shadow: 0 12px 28px rgba(30, 41, 59, .08);
        }
        body {
            background: linear-gradient(135deg, var(--bg-1), var(--bg-2));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card-reg {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 30px;
            max-width: 500px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="card-reg">
        <h2 class="h4 fw-bold mb-4">สมัครสมาชิก</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">ชื่อ</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">นามสกุล</label>
                    <input type="text" name="lastname" class="form-control" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">อีเมล</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">รหัสผ่าน</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">เบอร์โทรศัพท์</label>
                <input type="text" name="tel" class="form-control" maxlength="10">
            </div>
            <!-- <div class="mb-3">
                <label class="form-label">ที่อยู่</label>
                <textarea name="address" class="form-control" rows="2"></textarea>
            </div> -->
            <button type="submit" name="register" class="btn btn-primary w-100">สมัครสมาชิก</button>
            <div class="text-center mt-3">
                <a href="index.php" class="text-decoration-none">มีบัญชีอยู่แล้ว? เข้าสู่ระบบ</a>
            </div>
        </form>
    </div>
</body>
</html>
