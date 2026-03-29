<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit;
}

$user = $_SESSION['user'];

// ดึงข้อมูลแพ็กเกจทั้งหมดจากฐานข้อมูล
$pkg_res = $conn->query("SELECT * FROM service WHERE service_quota > 0 ORDER BY service_price ASC");

if (isset($_POST['apply_monthly'])) {
    $service_id = $_POST['service_id'];
    $pkg_stmt = $conn->query("SELECT * FROM service WHERE service_id = '$service_id'");
    $pkg = $pkg_stmt->fetch_assoc();
    
    $m_id = $user['m_id'];
    $rq_id = "RQ" . time();
    $date = date("Y-m-d H:i:s");
    $price = $pkg['service_price'];
    $quota = $pkg['service_quota'];
    $pkg_name = $pkg['service_name'];
    
    // บันทึกที่อยู่ปัจจุบัน
    $m_info = $conn->query("SELECT m_address, m_latitude, m_longitude FROM member WHERE m_id = '$m_id'")->fetch_assoc();
    $lat = $m_info['m_latitude'] ?? 0;
    $lng = $m_info['m_longitude'] ?? 0;

    // สร้าง "คำขอสมัครสมาชิก" (Request) แทนออเดอร์
    $note = "สมัครแพ็กเกจ: $pkg_name ($quota ชิ้น) ราคา " . number_format($price) . " บาท";
    
    $sql = "INSERT INTO request (rq_id, m_id, rq_date, rq_latitude, rq_longitude, rq_status, rq_sum, rq_note) 
            VALUES ('$rq_id', '$m_id', '$date', '$lat', '$lng', 0, $price, '$note')";
    
    if ($conn->query($sql)) {
        echo "<script>alert('ส่งคำขอสมัครเรียบร้อยแล้ว! กรุณารอร้านค้ากดยืนยันการรับสมัครสมาชิกครับ'); window.location.href='home.php';</script>";
        exit;
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เลือกแพ็กเกจรายเดือน - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
    <div class="container">
        <h2 class="fw-bold text-center mb-5">🌟 เลือกแพ็กเกจรายเดือนที่เหมาะกับคุณ</h2>
        
        <div class="row justify-content-center g-4">
            <?php if($pkg_res && $pkg_res->num_rows > 0): ?>
                <?php while($pkg = $pkg_res->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100">
                        <h3 class="fw-bold mb-3"><?= htmlspecialchars($pkg['service_name']) ?></h3>
                        <div class="bg-primary text-white p-4 rounded-4 mb-4">
                            <h4 class="mb-1">ซักได้ <?= $pkg['service_quota'] ?> ชิ้น</h4>
                            <h2 class="fw-bold mb-0"><?= number_format($pkg['service_price']) ?> บาท</h2>
                            <p class="small mb-0">เฉลี่ยเพียงชิ้นละ <?= number_format($pkg['service_price'] / $pkg['service_quota'], 1) ?> บาท</p>
                        </div>
                        <ul class="text-start mb-4 text-muted small flex-grow-1">
                            <?php if(!empty($pkg['service_note'])): ?>
                                <?= nl2br(htmlspecialchars($pkg['service_note'])) ?>
                            <?php else: ?>
                                <li>ใช้ได้นาน 30 วัน</li>
                                <li>ซัก อบ รีด ได้ทุกประเภท</li>
                                <li>ไม่ต้องพกเงินสด</li>
                            <?php endif; ?>
                            <li class="text-danger fw-bold mt-2">สิทธิคงเหลือจะถูกตัดจบเมื่อครบ 30 วัน</li>
                        </ul>
                        <form method="post">
                            <input type="hidden" name="service_id" value="<?= $pkg['service_id'] ?>">
                            <button type="submit" name="apply_monthly" class="btn btn-primary btn-lg w-100 rounded-pill shadow-sm fw-bold">
                                สมัครแพ็กเกจนี้
                            </button>
                        </form>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <h4>ขออภัย ยังไม่มีแพ็กเกจเปิดให้บริการในขณะนี้</h4>
                    <a href="home.php" class="btn btn-secondary rounded-pill mt-3">กลับหน้าหลัก</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="text-center mt-4">
            <a href="home.php" class="btn btn-link text-decoration-none">ยกเลิกและกลับหน้าหลัก</a>
        </div>
    </div>
</body>
</html>
