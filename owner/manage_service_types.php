<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

// กู้คืนค่าเริ่มต้น (T1, T2)
if (isset($_POST['restore_defaults'])) {
    $conn->query("INSERT IGNORE INTO service_type (svType_id, svType_name, svType_behavior, svType_note) VALUES 
        ('T1', 'ซักปกติ (หักโควตา)', 'quota', 'สำหรับรายการที่หักจากจำนวนชิ้นในแพ็กเกจสมาชิก'),
        ('T2', 'ผ้าพิเศษ/รายครั้ง', 'special', 'สำหรับรายการที่ต้องคิดเงินสดเพิ่มแยกจากรายเดือน')");
    header("Location: manage_service_types.php");
    exit;
}

// เพิ่มประเภทบริการ
if (isset($_POST['add_type'])) {
    $id = "TYP" . time();
    $name = $conn->real_escape_string($_POST['name']);
    $behavior = $_POST['behavior'];
    $note = $conn->real_escape_string($_POST['note']);
    $conn->query("INSERT INTO service_type (svType_id, svType_name, svType_behavior, svType_note) VALUES ('$id', '$name', '$behavior', '$note')");
    header("Location: manage_service_types.php");
    exit;
}

// ลบประเภทบริการ
if (isset($_GET['delete'])) {
    $id = $conn->real_escape_string($_GET['delete']);
    if ($id === 'T1' || $id === 'T2') {
        $error = "ไม่สามารถลบประเภทหลัก (T1, T2) ได้";
    } else {
        $check = $conn->query("SELECT COUNT(*) FROM service WHERE svType_id = '$id'")->fetch_row()[0];
        if ($check > 0) {
            $error = "ไม่สามารถลบได้ เนื่องจากมีบริการที่ใช้งานประเภทนี้อยู่";
        } else {
            $conn->query("DELETE FROM service_type WHERE svType_id = '$id'");
            header("Location: manage_service_types.php");
            exit;
        }
    }
}

// แก้ไขประเภทบริการ
if (isset($_POST['edit_type'])) {
    $id = $conn->real_escape_string($_POST['type_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $behavior = $_POST['behavior'];
    $note = $conn->real_escape_string($_POST['note']);
    $conn->query("UPDATE service_type SET svType_name = '$name', svType_behavior = '$behavior', svType_note = '$note' WHERE svType_id = '$id'");
    header("Location: manage_service_types.php");
    exit;
}

$res = $conn->query("SELECT * FROM service_type");
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการประเภทบริการ - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-light">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">📂 จัดการประเภทบริการ (หมวดหมู่)</h2>
            <div class="d-flex gap-2">
                <form method="post" onsubmit="return confirm('ยืนยันการกู้คืนประเภทพื้นฐาน?')">
                    <button type="submit" name="restore_defaults" class="btn btn-outline-warning rounded-pill px-4">
                        <i class="fa fa-sync-alt me-2"></i> กู้คืนค่าเริ่มต้น
                    </button>
                </form>
                <a href="manage_services.php" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="fa fa-arrow-left me-2"></i> กลับหน้าบริการ
                </a>
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addTypeModal">
                    <i class="fa fa-plus me-2"></i> เพิ่มประเภทใหม่
                </button>
            </div>
        </div>

        <!-- 💡 คู่มือการตั้งค่าประเภทบริการ -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-primary bg-opacity-10 h-100">
                    <div class="fw-bold text-primary mb-2"><i class="fa fa-info-circle me-1"></i> 1. แบบหักโควตา (รายเดือน)</div>
                    <p class="small text-muted mb-0">ใช้สำหรับ <b>ผ้าปกติ</b> ในแพ็กเกจที่ลูกค้าจ่ายเหมามาแล้ว ระบบจะนำจำนวนชิ้นไปหักออกจากโควตา (เช่น 80 ชิ้น) โดยไม่เก็บเงินเพิ่ม</p>
                    <div class="mt-2 small"><b>ตัวอย่าง:</b> ซักปกติ, อบผ้าทั่วไป, รีดผ้าทั่วไป</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-success bg-opacity-10 h-100">
                    <div class="fw-bold text-success mb-2"><i class="fa fa-tag me-1"></i> 2. แบบเก็บเงินสด (รายครั้ง/ผ้าพิเศษ)</div>
                    <p class="small text-muted mb-0">ใช้สำหรับ <b>ผ้าพิเศษ</b> ที่ต้องคิดเงินเพิ่มแยกต่างหากเป็นรายชิ้น แม้ลูกค้าจะเป็นสมาชิกรายเดือนก็ตาม</p>
                    <div class="mt-2 small"><b>ตัวอย่าง:</b> ผ้านวม, ชุดไหม, ชุดราชการ, ซักรองเท้า</div>
                </div>
            </div>
        </div>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4"><?= $error ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 py-3" style="width: 80px;">ID</th>
                            <th class="py-3">ชื่อประเภทบริการ</th>
                            <th class="py-3" style="width: 200px;">พฤติกรรมการคิดเงิน</th>
                            <th class="py-3">หมายเหตุเพิ่มเติม</th>
                            <th class="text-center py-3" style="width: 100px;">แก้ไข</th>
                            <th class="text-center py-3" style="width: 100px;">ลบข้อมูล</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $res->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4"><code><?= $row['svType_id'] ?></code></td>
                            <td>
                                <div class="fw-bold text-dark"><?= $row['svType_name'] ?></div>
                            </td>
                            <td>
                                <?php if($row['svType_behavior'] == 'quota'): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3 py-2">
                                        <i class="fa fa-star me-1"></i> หักโควตา (รายเดือน)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-2">
                                        <i class="fa fa-tag me-1"></i> จ่ายเงินสด (รายครั้ง)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="text-muted small"><?= $row['svType_note'] ?: '-' ?></span></td>
                            <td class="text-center">
                                <button class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editTypeModal"
                                        data-id="<?= $row['svType_id'] ?>"
                                        data-name="<?= $row['svType_name'] ?>"
                                        data-behavior="<?= $row['svType_behavior'] ?>"
                                        data-note="<?= $row['svType_note'] ?>">
                                    <i class="fa fa-edit me-1"></i> แก้ไข
                                </button>
                            </td>
                            <td class="text-center border-start bg-light bg-opacity-10">
                                <?php if($row['svType_id'] !== 'T1' && $row['svType_id'] !== 'T2'): ?>
                                    <a href="?delete=<?= $row['svType_id'] ?>" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-sm" onclick="return confirm('ยืนยันการลบประเภทนี้?')">
                                        <i class="fa fa-trash me-1"></i> ลบ
                                    </a>
                                <?php else: ?>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border-0"><i class="fa fa-lock me-1"></i> ระบบล็อก</span>
                                        <div class="mt-1">ห้ามลบ: รหัสหลักของระบบ</div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <!-- 💡 หมายเหตุสำหรับผู้ดูแลร้าน -->
            <div class="card-footer bg-light border-0 p-4">
                <div class="d-flex">
                    <div class="text-primary me-3">
                        <i class="fa fa-info-circle fa-2x"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-2">ทำไมบางรายการถึงกด "ลบ" ไม่ได้?</h6>
                        <p class="small text-muted mb-3">รายการที่มีคำว่า <b>"ระบบล็อก"</b> คือหมวดหมู่หลักที่โปรแกรมใช้แยกแยะงานซักครับ เพื่อป้องกันระบบทำงานผิดพลาด จึงไม่อนุญาตให้ลบทิ้งครับ</p>
                        
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="bg-white p-2 rounded-3 border-start border-success border-4 shadow-sm">
                                    <div class="small fw-bold text-success"><i class="fa fa-check-circle me-1"></i> สิ่งที่ทำได้</div>
                                    <div style="font-size: 0.75rem;" class="text-muted">กดปุ่ม <b>"แก้ไข"</b> เพื่อเปลี่ยนชื่อให้เป็นแบบที่ร้านต้องการได้เลยครับ (เช่น เปลี่ยนจาก 'ซักผ้า' เป็น 'งานซักทั่วไป')</div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="bg-white p-2 rounded-3 border-start border-danger border-4 shadow-sm">
                                    <div class="small fw-bold text-danger"><i class="fa fa-times-circle me-1"></i> สิ่งที่ห้ามทำ</div>
                                    <div style="font-size: 0.75rem;" class="text-muted">ห้ามลบหมวดหมู่นี้ทิ้ง เพราะจะทำให้โปรแกรมคำนวณเงินและหักแต้มสมาชิกไม่ได้ครับ</div>
                                </div>
                            </div>
                        </div>
                        <p class="mt-3 mb-0 fw-bold text-primary" style="font-size: 0.8rem;">สรุปง่ายๆ: "ห้ามลบช่องนี้ แต่เปลี่ยนชื่อเรียกได้ตามใจชอบครับ"</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal เพิ่มประเภท -->
    <div class="modal fade" id="addTypeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <form method="post">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold">➕ เพิ่มประเภทใหม่</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ชื่อประเภท</label>
                            <input type="text" name="name" class="form-control rounded-3" placeholder="เช่น ซักแห้ง" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-primary">การคิดเงิน (พฤติกรรมระบบ)</label>
                            <select name="behavior" class="form-select rounded-3" required>
                                <option value="quota">หักโควตา (แบบรายเดือน)</option>
                                <option value="special" selected>เก็บเงินสดเพิ่ม (แบบรายครั้ง)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">หมายเหตุ</label>
                            <textarea name="note" class="form-control rounded-3" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" name="add_type" class="btn btn-primary rounded-pill w-100 py-2 fw-bold">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal แก้ไขประเภท -->
    <div class="modal fade" id="editTypeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <form method="post">
                    <input type="hidden" name="type_id" id="edit_type_id">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold">📝 แก้ไขประเภทบริการ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ชื่อประเภท</label>
                            <input type="text" name="name" id="edit_type_name" class="form-control rounded-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-primary">การคิดเงิน (พฤติกรรมระบบ)</label>
                            <select name="behavior" id="edit_type_behavior" class="form-select rounded-3" required>
                                <option value="quota">หักโควตา (แบบรายเดือน)</option>
                                <option value="special">เก็บเงินสดเพิ่ม (แบบรายครั้ง)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">หมายเหตุ</label>
                            <textarea name="note" id="edit_type_note" class="form-control rounded-3" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" name="edit_type" class="btn btn-primary rounded-pill w-100 py-2 fw-bold">บันทึกการแก้ไข</button>
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
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                const behavior = button.getAttribute('data-behavior');
                const note = button.getAttribute('data-note');

                document.getElementById('edit_type_id').value = id;
                document.getElementById('edit_type_name').value = name;
                document.getElementById('edit_type_behavior').value = behavior;
                document.getElementById('edit_type_note').value = note;
            });
        }
    </script>
</body>
</html>
