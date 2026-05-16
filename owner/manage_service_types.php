<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

// กู้คืนค่าเริ่มต้น (กรณีเผลอลบข้อมูลในอนาคต)
if (isset($_POST['restore_defaults'])) {
    $conn->query("INSERT IGNORE INTO service_type (svType_id, svType_name, svType_behavior, svType_note) VALUES 
        ('T1', 'รายเดือน', 'monthly', 'หมวดหมู่หลักสำหรับหักโควตาผ้าสมาชิก'),
        ('T2', 'รายชิ้น', 'per_piece', 'หมวดหมู่หลักสำหรับเก็บเงินสดเพิ่ม')");
    header("Location: manage_service_types.php");
    exit;
}

// แก้ไขชื่อหมวดหมู่ (อนุญาตให้เปลี่ยนชื่อได้ แต่เปลี่ยน ID หรือพฤติกรรมไม่ได้ในระดับลึก)
if (isset($_POST['edit_type'])) {
    $id = $conn->real_escape_string($_POST['type_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $note = $conn->real_escape_string($_POST['note']);
    $conn->query("UPDATE service_type SET svType_name = '$name', svType_note = '$note' WHERE svType_id = '$id'");
    header("Location: manage_service_types.php");
    exit;
}

$res = $conn->query("SELECT * FROM service_type WHERE svType_id IN ('T1', 'T2') ORDER BY svType_id ASC");
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการระบบหมวดหมู่ - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&display=swap');
        body { background: #f1f5f9; font-family: 'Chakra Petch', sans-serif; }
        .main-content { margin-left: 280px; padding: 40px; }
        .card-core { border: none; border-radius: 24px; transition: all 0.3s ease; background: white; }
        .card-core:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .icon-box { width: 60px; height: 60px; border-radius: 18px; display: flex; align-items: center; justify-content: center; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h2 class="fw-bold text-dark mb-1">📂 จัดการระบบหมวดหมู่</h2>
                    <p class="text-muted mb-0">ระบบถูกออกแบบให้มี 2 หมวดหมู่หลักเพื่อความแม่นยำในการคำนวณเงิน</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="manage_services.php" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
                        <i class="fa fa-shirt me-2"></i> จัดการรายการบริการ (ราคา)
                    </a>
                </div>
            </div>

            <div class="row g-4 justify-content-center">
                <?php while($row = $res->fetch_assoc()): 
                    $is_monthly = ($row['svType_behavior'] == 'monthly');
                ?>
                <div class="col-md-6 col-lg-5">
                    <div class="card card-core shadow-sm h-100 border-top border-4 <?= $is_monthly ? 'border-primary' : 'border-success' ?>">
                        <div class="card-body p-4 text-center">
                            <div class="icon-box mx-auto mb-3 <?= $is_monthly ? 'bg-primary' : 'bg-success' ?> bg-opacity-10 <?= $is_monthly ? 'text-primary' : 'text-success' ?>">
                                <i class="fa <?= $is_monthly ? 'fa-calendar-check' : 'fa-hand-holding-dollar' ?> fa-2x"></i>
                            </div>
                            <h3 class="fw-bold text-dark mb-1"><?= $row['svType_name'] ?></h3>
                            <div class="badge rounded-pill mb-3 <?= $is_monthly ? 'bg-primary' : 'bg-success' ?> bg-opacity-10 <?= $is_monthly ? 'text-primary' : 'text-success' ?> px-3 py-2">
                                พฤติกรรม: <?= $is_monthly ? 'หักโควตารายเดือน' : 'เก็บเงินสดรายชิ้น' ?>
                            </div>
                            <p class="text-muted small mb-4 px-3"><?= $row['svType_note'] ?></p>
                            
                            <div class="d-flex gap-2">
                                <button class="btn btn-light rounded-pill flex-grow-1 fw-bold" data-bs-toggle="modal" data-bs-target="#editTypeModal"
                                    data-id="<?= $row['svType_id'] ?>" data-name="<?= $row['svType_name'] ?>" data-note="<?= $row['svType_note'] ?>">
                                    <i class="fa fa-edit me-2"></i> เปลี่ยนชื่อเรียก
                                </button>
                                <button class="btn btn-outline-danger rounded-pill px-4" disabled title="ไม่อนุญาตให้ลบเนื่องจากเป็นระบบหลัก">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <!-- ⚠️ กล่องแจ้งเตือนความสำคัญ -->
            <div class="mt-5 card border-0 rounded-4 shadow-sm overflow-hidden">
                <div class="row g-0">
                    <div class="col-md-2 bg-danger d-flex align-items-center justify-content-center p-4">
                        <i class="fa fa-shield-alt fa-4x text-white opacity-50"></i>
                    </div>
                    <div class="col-md-10">
                        <div class="card-body p-4">
                            <h5 class="fw-bold text-danger mb-3"><i class="fa fa-exclamation-triangle me-2"></i> ลบไม่ได้ๆๆๆๆๆๆๆๆๆๆๆๆๆๆๆๆๆๆๆๆๆๆๆ</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="small text-muted mb-0">
                                        <li class="mb-2"><b>มีผลต่อการคำนวณเงิน:</b> ระบบใช้ ID เหล่านี้แยกแยะว่างานไหนต้อง "เก็บเงินเลย" หรือ "ซักฟรีตามโควตา"</li>
                                        <li><b>ฐานข้อมูลเชื่อมโยงกัน:</b> รายการออเดอร์ในอดีตถูกผูกไว้กับหมวดหมู่นี้ การลบจะทำให้ประวัติข้อมูลเสียหาย</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="small text-muted mb-0">
                                        <li class="mb-2"><b>ความเสถียรของแอป:</b> แอปพลิเคชันฝั่งลูกค้าถูกโปรแกรมมาให้เรียกดูข้อมูลจาก 2 หมวดหมู่นี้เป็นหลัก</li>
                                        <li><b class="text-success">สิ่งที่ทำได้:</b> คุณสามารถกดปุ่ม "เปลี่ยนชื่อเรียก" เพื่อให้ตรงกับภาษาที่ร้านใช้ได้ตลอดเวลา</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal แก้ไข -->
    <div class="modal fade" id="editTypeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <form method="post">
                    <input type="hidden" name="type_id" id="edit_id">
                    <div class="modal-header border-0 px-4 pt-4">
                        <h5 class="modal-title fw-bold">📝 เปลี่ยนชื่อเรียกหมวดหมู่</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">ชื่อที่แสดงผลหน้าเว็บ</label>
                            <input type="text" name="name" id="edit_name" class="form-control form-control-lg rounded-3 border-2" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label small fw-bold text-muted">คำอธิบายเพิ่มเติม</label>
                            <textarea name="note" id="edit_note" class="form-control rounded-3" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="submit" name="edit_type" class="btn btn-primary rounded-pill w-100 py-3 fw-bold shadow">บันทึกการเปลี่ยนแปลง</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const editTypeModal = document.getElementById('editTypeModal');
        if (editTypeModal) {
            editTypeModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                document.getElementById('edit_id').value = button.getAttribute('data-id');
                document.getElementById('edit_name').value = button.getAttribute('data-name');
                document.getElementById('edit_note').value = button.getAttribute('data-note');
            });
        }
    </script>
</body>
</html>
