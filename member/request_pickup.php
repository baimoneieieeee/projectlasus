<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit;
}

$user = $_SESSION['user'];
$m_id = $user['m_id'];

// ตรวจสอบโควตารับผ้า (ห้ามเกิน 4 ครั้ง)
$json_file = __DIR__ . '/../config/monthly_data.json';
$monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
$my_pickups = isset($monthly_data[$m_id]['pickups_used']) ? $monthly_data[$m_id]['pickups_used'] : 0;
$has_monthly = isset($monthly_data[$m_id]);

if (isset($_POST['request'])) {
    if ($has_monthly && $my_pickups >= 4) {
        $error = "ขออภัย คุณใช้สิทธิ์เรียกรับผ้าครบ 4 ครั้งในเดือนนี้แล้ว";
    } else {
        $rq_id = "RQ" . time();
        $date = date("Y-m-d H:i:s");
        $lat = $user['m_latitude'];
        $lng = $user['m_longitude'];
        $note = $conn->real_escape_string($_POST['note']);

        $sql = "INSERT INTO request (rq_id, m_id, rq_date, rq_latitude, rq_longitude, rq_status, rq_note) 
                VALUES ('$rq_id', '$m_id', '$date', '$lat', '$lng', 0, '$note')";
        
        if ($conn->query($sql)) {
            header("Location: home.php");
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แจ้งรับผ้า - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root { --primary: #3B82F6; --bg-light: #F1F5F9; }
        body { background: var(--bg-light); font-family: system-ui; }
        .card-custom { border-radius: 20px; border: none; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card card-custom p-4">
                    <h3 class="fw-bold text-center mb-4">🏠 แจ้งให้ร้านมารับผ้า</h3>
                    
                    <?php if($has_monthly && $my_pickups >= 4): ?>
                        <div class="text-center py-4">
                            <i class="fa fa-calendar-times fa-4x text-danger mb-3"></i>
                            <h5 class="fw-bold">สิทธิ์เรียกรับผ้าเดือนนี้หมดแล้ว</h5>
                            <p class="text-muted">คุณใช้สิทธิ์ครบ 4 ครั้งตามเงื่อนไขแพ็กเกจแล้วครับ</p>
                            <a href="home.php" class="btn btn-primary rounded-pill mt-3 px-4">กลับหน้าหลัก</a>
                        </div>
                    <?php else: ?>
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <p class="text-muted text-center">เราจะไปรับผ้าตามตำแหน่งที่คุณปักหมุดไว้</p>
                        <hr>
                        <form method="post">
                            <div class="mb-4">
                                <label class="form-label fw-bold">📍 ตำแหน่งของคุณ</label>
                                <div class="alert alert-info py-2 small">
                                    <strong>พิกัด:</strong> <?= $user['m_latitude'] ?>, <?= $user['m_longitude'] ?>
                                    <br><a href="set_location.php" class="text-decoration-none">เปลี่ยนตำแหน่ง</a>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold">📝 หมายเหตุเพิ่มเติม</label>
                                <textarea name="note" class="form-control" rows="3" placeholder="ระบุประเภทผ้า หรือจุดสังเกตที่อยู่..."></textarea>
                            </div>
                            <button type="submit" name="request" class="btn btn-primary btn-lg w-100 rounded-pill py-3">
                                <i class="fa fa-paper-plane me-2"></i> ยืนยันการแจ้งรับผ้า
                            </button>
                            <a href="home.php" class="btn btn-light btn-lg w-100 rounded-pill mt-3">ยกเลิก</a>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
