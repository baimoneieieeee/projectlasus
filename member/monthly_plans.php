<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit;
}

$user = $_SESSION['user'];

if (isset($_POST['apply_monthly'])) {
    $m_id = $user['m_id'];
    $order_id = "PKG" . time();
    $date = date("Y-m-d H:i:s");
    
    // สร้าง Order สำหรับแพ็กเกจรายเดือน (ยอด 800 บาท)
    // ใส่ Note พิเศษไว้เพื่อให้ระบบรู้ว่าเป็นออเดอร์รายเดือน
    $sql = "INSERT INTO orders (order_id, m_id, order_date, order_status, order_sum, order_note) 
            VALUES ('$order_id', '$m_id', '$date', 0, 800.00, 'MONTHLY_PACKAGE_800')";
    
    if ($conn->query($sql)) {
        // ส่งลูกค้าไปหน้าชำระเงิน (Process 7) ทันที
        header("Location: payment.php?id=$order_id");
        exit;
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>สมัครรายเดือน - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                    <h2 class="fw-bold mb-3">🌟 แพ็กเกจรายเดือน</h2>
                    <div class="bg-primary text-white p-4 rounded-4 mb-4">
                        <h4 class="mb-1">ซักได้ 80 ชิ้น</h4>
                        <h2 class="fw-bold mb-0">800 บาท</h2>
                        <p class="small mb-0">เฉลี่ยเพียงชิ้นละ 10 บาท</p>
                    </div>
                    <ul class="text-start mb-4 text-muted">
                        <li>ใช้ได้นาน 30 วัน</li>
                        <li>ซัก อบ รีด ได้ทุกประเภท</li>
                        <li>ไม่ต้องพกเงินสด</li>
                        <li class="text-danger fw-bold">เมื่อครบ 30 วัน สิทธิคงเหลือจะถูกตัดจบทั้งหมด แม้ผ้ายังไม่ครบจำนวน</li>
                    </ul>
                    <form method="post">
                        <button type="submit" name="apply_monthly" class="btn btn-primary btn-lg w-100 rounded-pill">
                            สมัครแพ็กเกจนี้
                        </button>
                    </form>
                    <a href="home.php" class="btn btn-link mt-2 text-decoration-none">กลับหน้าหลัก</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
