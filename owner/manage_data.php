<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

$message = "";
$error = "";

// ฟังก์ชันตรวจสอบรหัสผ่านเจ้าของร้าน
function verifyOwnerPassword($conn, $input_pass) {
    $shop_res = $conn->query("SELECT shop_password FROM shop LIMIT 1");
    $shop = $shop_res->fetch_assoc();
    return ($input_pass === $shop['shop_password']);
}

if (isset($_POST['confirm_action'])) {
    $action = $_POST['action_type'];
    $password = $_POST['owner_password'];

    if (!verifyOwnerPassword($conn, $password)) {
        $error = "❌ รหัสผ่านไม่ถูกต้อง! ไม่สามารถดำเนินการได้";
    } else {
        // ดำเนินการตามประเภทการลบ
        if ($action === 'reset_quotas') {
            $json_file = __DIR__ . '/../config/monthly_data.json';
            $monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
            foreach ($monthly_data as $m_id => $data) {
                $res = $conn->query("SELECT order_note FROM orders WHERE m_id = '$m_id' AND order_note LIKE 'MONTHLY_PACKAGE_%' ORDER BY order_date DESC LIMIT 1");
                if ($row = $res->fetch_assoc()) {
                    preg_match('/MONTHLY_PACKAGE_(\d+)/', $row['order_note'], $matches);
                    $quota = isset($matches[1]) ? (int)$matches[1] : 80;
                    $monthly_data[$m_id]['quota'] = $quota;
                } else { $monthly_data[$m_id]['quota'] = 80; }
                $monthly_data[$m_id]['pickups_used'] = 0;
                $monthly_data[$m_id]['updated_at'] = date("Y-m-d H:i:s");
            }
            file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));
            $message = "✅ รีเซ็ตโควตาสมาชิกทุกคนเรียบร้อยแล้ว";
        } 
        elseif ($action === 'clear_monthly') {
            $json_file = __DIR__ . '/../config/monthly_data.json';
            // ลบโควตาของทุกคนทิ้งจริงๆ โดยการส่งค่า {} เข้าไป
            file_put_contents($json_file, json_encode(new stdClass()));
            $message = "✅ ลบสถานะรายเดือนของสมาชิกทุกคนเรียบร้อยแล้ว (ทุกคนกลับเป็นสมาชิกทั่วไป)";
        }
        elseif ($action === 'delete_orders') {
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            $conn->query("TRUNCATE item");
            $conn->query("TRUNCATE payment");
            $conn->query("TRUNCATE delivery");
            $conn->query("TRUNCATE orders");
            $conn->query("TRUNCATE request");
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
            $message = "✅ ล้างประวัติงานซักทั้งหมดเรียบร้อยแล้ว";
        }
        elseif ($action === 'delete_members') {
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            $conn->query("TRUNCATE member");
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
            $json_file = __DIR__ . '/../config/monthly_data.json';
            file_put_contents($json_file, json_encode(new stdClass()));
            $message = "✅ ลบข้อมูลสมาชิกและโควตาทั้งหมดเรียบร้อยแล้ว (กรุณาออกจากระบบเพื่อเริ่มใหม่)";
        }
        elseif ($action === 'reset_all') {
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            $conn->query("TRUNCATE item");
            $conn->query("TRUNCATE payment");
            $conn->query("TRUNCATE delivery");
            $conn->query("TRUNCATE orders");
            $conn->query("TRUNCATE request");
            $conn->query("TRUNCATE member");
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
            $json_file = __DIR__ . '/../config/monthly_data.json';
            file_put_contents($json_file, json_encode(new stdClass(), JSON_PRETTY_PRINT));
            $message = "🔥 รีเซ็ตระบบใหม่ทั้งหมดเรียบร้อยแล้ว (ข้อมูลว่างเปล่า)";
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการข้อมูลระบบ - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-light">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fw-bold mb-4"><i class="fa fa-shield-halved text-warning me-2"></i> จัดการข้อมูลระบบ (Security Mode)</h2>

            <!-- 🛑 กล่องคำเตือนอันตรายสูงสุด -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-danger bg-opacity-10 border-start border-danger border-5">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-danger"><i class="fa fa-exclamation-triangle me-2"></i> พื้นที่ควบคุมพิเศษ (คำเตือนด้านความปลอดภัย)</h5>
                    <p class="mb-0 text-dark fw-bold">ระบบในหน้านี้มีไว้สำหรับการ "ล้างข้อมูล" เท่านั้น! ไม่แนะนำให้กดเล่นหากไม่จำเป็นจริงๆ</p>
                    <ul class="mb-0 mt-2 small text-danger fw-bold">
                        <li>ข้อมูลที่ถูกลบไปแล้ว <span class="text-decoration-underline">จะไม่สามารถกู้คืนกลับมาได้</span> แม้แต่เจ้าของร้านเอง</li>
                        <li>กรุณาตรวจสอบให้แน่ใจ 100% ก่อนกรอกรหัสผ่านเพื่อยืนยันการลบ</li>
                    </ul>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4"><?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4"><?= $error ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- 1. รีเซ็ตเดือนใหม่ -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                        <h5 class="fw-bold text-primary"><i class="fa fa-calendar-plus me-2"></i> รีเซ็ตโควตารายเดือน</h5>
                        <div class="alert alert-light border-0 small py-2">
                            <b>ผลกระทบ:</b> สมาชิกทุกคนจะกลับมามีโควตาผ้าเต็ม (ตามแพ็กเกจ) ทันที ประวัติการซักเก่าจะยังอยู่
                        </div>
                        <p class="text-muted small">แนะนำให้ทำเฉพาะเมื่อขึ้นเดือนใหม่เท่านั้น</p>
                        <button class="btn btn-primary rounded-pill w-100 fw-bold mt-auto py-2" onclick="openConfirmModal('reset_quotas', 'รีเซ็ตโควตาสมาชิกทุกคน')">รีเซ็ตเดือนใหม่</button>
                    </div>
                </div>

                <!-- 1.1 ลบสถานะรายเดือน -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 h-100 border-start border-warning border-4">
                        <h5 class="fw-bold text-warning"><i class="fa fa-user-times me-2"></i> ลบสถานะรายเดือน</h5>
                        <div class="alert alert-light border-0 small py-2">
                            <b>ผลกระทบ:</b> ยกเลิกการเป็นสมาชิกรายเดือนของทุกคน (โควตาหายเกลี้ยง) แต่รายชื่อลูกค้ายังอยู่
                        </div>
                        <p class="text-muted small">ใช้เมื่อต้องการให้ทุกคนหมดอายุสมาชิกพร้อมกัน</p>
                        <button class="btn btn-warning text-dark rounded-pill w-100 fw-bold mt-auto py-2" onclick="openConfirmModal('clear_monthly', 'ลบสถานะรายเดือนของทุกคน (ยกเลิกโควตา)')">ลบโควตารายเดือน</button>
                    </div>
                </div>

                <!-- 2. ล้างประวัติงานซัก -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                        <h5 class="fw-bold text-info"><i class="fa fa-broom me-2"></i> ล้างประวัติงานซัก</h5>
                        <div class="alert alert-light border-0 small py-2">
                            <b>ผลกระทบ:</b> รายการสั่งซื้อและสลิปโอนเงินทั้งหมดจะหายไป ลูกค้าจะมองไม่เห็นประวัติการซักเดิม
                        </div>
                        <p class="text-muted small">ใช้เมื่อต้องการล้างข้อมูลออเดอร์เก่าๆ เพื่อลดภาระระบบ</p>
                        <button class="btn btn-info text-white rounded-pill w-100 fw-bold mt-auto py-2" onclick="openConfirmModal('delete_orders', 'ลบประวัติงานซักทั้งหมด')">ล้างประวัติออเดอร์</button>
                    </div>
                </div>

                <!-- 3. ล้างข้อมูลสมาชิก -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                        <h5 class="fw-bold text-danger"><i class="fa fa-user-slash me-2"></i> ลบข้อมูลสมาชิกทั้งหมด</h5>
                        <div class="alert alert-danger bg-opacity-10 border-0 small py-2">
                            <b>ผลกระทบ:</b> รายชื่อลูกค้าจะหายไปทั้งหมด ลูกค้าจะล็อกอินไม่ได้ และต้องสมัครสมาชิกใหม่ทุกคน
                        </div>
                        <p class="text-muted small">ห้ามกดหากยังมีสมาชิกที่ค้างโควตาผ้าอยู่ในระบบ</p>
                        <button class="btn btn-danger rounded-pill w-100 fw-bold mt-auto py-2" onclick="openConfirmModal('delete_members', 'ลบข้อมูลสมาชิกทุกคน')">ลบสมาชิกทั้งหมด</button>
                    </div>
                </div>

                <!-- 4. รีเซ็ตโรงงาน -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 h-100 border-start border-danger border-5 bg-dark">
                        <h5 class="fw-bold text-white"><i class="fa fa-fire me-2"></i> ล้างข้อมูลทุกอย่าง (Reset All)</h5>
                        <div class="alert alert-light bg-opacity-10 border-0 small py-2 text-white">
                            <b>ผลกระทบสูงสุด:</b> ข้อมูลทุกอย่างจะว่างเปล่า เหมือนเพิ่งเปิดร้านวันแรก (ยกเว้นราคางานซัก)
                        </div>
                        <p class="text-white-50 small">ใช้เฉพาะกรณีต้องการล้างระบบเพื่อเริ่มกิจการใหม่เท่านั้น</p>
                        <button class="btn btn-outline-light rounded-pill w-100 fw-bold mt-auto py-2" onclick="openConfirmModal('reset_all', 'ลบทุกอย่างในระบบ (Reset System)')">ล้างข้อมูลทุกอย่าง</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Security Modal ยืนยันด้วยรหัสผ่าน -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <form method="post">
                    <input type="hidden" name="action_type" id="action_type">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-danger"><i class="fa fa-lock me-2"></i> ยืนยันรหัสผ่านเจ้าของร้าน</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4 text-center">
                        <p class="mb-3 text-muted">คุณกำลังจะดำเนินการ: <br><strong id="action_label" class="text-dark"></strong></p>
                        <div class="mb-4">
                            <label class="form-label small fw-bold">กรุณากรอกรหัสผ่านร้านค้าเพื่อยืนยัน</label>
                            <input type="password" name="owner_password" class="form-control form-control-lg rounded-3 text-center" placeholder="รหัสผ่านของคุณ" required>
                        </div>
                        <div class="alert alert-danger p-2 small border-0 rounded-3 mb-0">
                            <i class="fa fa-exclamation-triangle me-1"></i> การกระทำนี้ไม่สามารถกู้คืนข้อมูลได้!
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="submit" name="confirm_action" class="btn btn-danger btn-lg w-100 rounded-pill fw-bold py-3">
                            <i class="fa fa-check-circle me-1"></i> ยืนยันการลบข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const myModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        function openConfirmModal(type, label) {
            document.getElementById('action_type').value = type;
            document.getElementById('action_label').innerText = label;
            myModal.show();
        }
    </script>
</body>
</html>
