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

// ดึงข้อมูลร้านค้าเพื่อดูราคาซักเกิน
$shop_info = $conn->query("SELECT shop_extra_price FROM shop LIMIT 1")->fetch_assoc();
$extra_price = (int)($shop_info['shop_extra_price'] ?? 10);

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&display=swap');
        body { background: #F1F5F9; font-family: 'Chakra Petch', sans-serif; }
        .pkg-card { transition: 0.3s; border: none; overflow: hidden; border-radius: 24px; }
        .pkg-card:hover { transform: translateY(-10px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .quota-circle { background: rgba(255,255,255,0.2); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; }
        .smaller { font-size: 0.85rem; }
    </style>
</head>
<body class="py-5">
    <div class="container">
        <h2 class="fw-bold text-center mb-2">🌟 เลือกแพ็กเกจที่ใช่สำหรับคุณ</h2>
        <p class="text-center text-muted mb-5">ประหยัดกว่า คุ้มค่ากว่า ด้วยบริการรายเดือนมืออาชีพ</p>
        
        <div class="row justify-content-center g-4">
            <?php if($pkg_res && $pkg_res->num_rows > 0): ?>
                <?php while($pkg = $pkg_res->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="card pkg-card shadow-sm p-4 text-center h-100">
                        <h3 class="fw-bold mb-3 text-primary"><?= htmlspecialchars($pkg['service_name']) ?></h3>
                        <div class="bg-primary text-white p-4 rounded-4 mb-4 shadow-sm">
                            <div class="quota-circle"><i class="fa fa-shirt fa-lg"></i></div>
                            <h4 class="mb-1">โควตา <?= $pkg['service_quota'] ?> ชิ้น</h4>
                            <h2 class="fw-bold mb-0">฿<?= number_format($pkg['service_price']) ?></h2>
                            <p class="smaller opacity-75 mb-0">เฉลี่ยชิ้นละ <?= number_format($pkg['service_price'] / $pkg['service_quota'], 1) ?> บาท</p>
                        </div>
                        
                        <div class="text-start mb-4 flex-grow-1">
                            <h6 class="fw-bold small text-dark mb-3"><i class="fa fa-info-circle me-2 text-primary"></i>เงื่อนไขการใช้งาน:</h6>
                            <ul class="list-unstyled d-flex flex-column gap-2 smaller text-muted">
                                <!-- <li class="d-flex align-items-start">
                                    <i class="fa fa-check-circle text-success me-2 mt-1"></i>
                                    <span>ระยะเวลาการใช้งาน <b>30 วัน</b></span>
                                </li> -->
                                <li class="d-flex align-items-start">
                                    <i class="fa fa-sync text-primary me-2 mt-1"></i>
                                    <span>ให้บริการ <b>4 รอบ/เดือน</b> (สัปดาห์ละ 1 ครั้ง)</span>
                                </li>
                                <li class="d-flex align-items-start">
                                    <i class="fa fa-box-open text-primary me-2 mt-1"></i>
                                    <span>จำนวนผ้าสะสมรวม <b><?= $pkg['service_quota'] ?> ชิ้น</b></span>
                                </li>
                                <li class="d-flex align-items-start">
                                    <i class="fa fa-exclamation-circle text-danger me-2 mt-1"></i>
                                    <span>หากส่งซักเกินโควตา คิดเพิ่มชิ้นละ <b><?= $extra_price ?> บาท</b></span>
                                </li>
                                <li class="d-flex align-items-start">
                                    <i class="fa fa-truck text-success me-2 mt-1"></i>
                                    <span><b>บริการรับ-ส่งฟรี</b> ในระยะทางที่กำหนด</span>
                                </li>
                            </ul>
                        </div>

                        <form method="post">
                            <input type="hidden" name="service_id" value="<?= $pkg['service_id'] ?>">
                            <button type="submit" name="apply_monthly" class="btn btn-primary btn-lg w-100 rounded-pill shadow border-0 fw-bold py-3">
                                สมัครแพ็กเกจนี้
                            </button>
                        </form>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <i class="fa fa-box-open fa-4x text-light mb-3"></i>
                    <h4>ขออภัย ยังไม่มีแพ็กเกจเปิดให้บริการในขณะนี้</h4>
                    <a href="home.php" class="btn btn-secondary rounded-pill mt-3">กลับหน้าหลัก</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="text-center mt-5">
            <a href="home.php" class="btn btn-link text-decoration-none text-muted small">
                <i class="fa fa-arrow-left me-1"></i> ยกเลิกและกลับหน้าหลัก
            </a>
        </div>
    </div>
</body>
</html>