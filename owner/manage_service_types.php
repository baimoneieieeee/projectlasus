<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

// เพิ่มประเภทบริการ
if (isset($_POST['add_type'])) {
    $id = "TYP" . time();
    $name = $conn->real_escape_string($_POST['name']);
    $note = $conn->real_escape_string($_POST['note']);
    $conn->query("INSERT INTO service_type (svType_id, svType_name, svType_note) VALUES ('$id', '$name', '$note')");
    header("Location: manage_service_types.php");
    exit;
}

// ลบประเภทบริการ
if (isset($_GET['delete'])) {
    $id = $conn->real_escape_string($_GET['delete']);
    // ตรวจสอบก่อนว่ามีการใช้งานในตาราง service หรือไม่
    $check = $conn->query("SELECT COUNT(*) FROM service WHERE svType_id = '$id'")->fetch_row()[0];
    if ($check > 0) {
        $error = "ไม่สามารถลบได้ เนื่องจากมีบริการที่ใช้งานประเภทนี้อยู่";
    } else {
        $conn->query("DELETE FROM service_type WHERE svType_id = '$id'");
        header("Location: manage_service_types.php");
        exit;
    }
}

// แก้ไขประเภทบริการ
if (isset($_POST['edit_type'])) {
    $id = $conn->real_escape_string($_POST['type_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $note = $conn->real_escape_string($_POST['note']);
    $conn->query("UPDATE service_type SET svType_name = '$name', svType_note = '$note' WHERE svType_id = '$id'");
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
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">จัดการประเภทบริการ</h2>
            <div class="d-flex gap-2">
                <a href="manage_services.php" class="btn btn-outline-secondary rounded-pill">
                    <i class="fa fa-arrow-left me-2"></i> กลับหน้าบริการ
                </a>
                <button class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#addTypeModal">
                    <i class="fa fa-plus me-2"></i> เพิ่มประเภทใหม่
                </button>
            </div>
        </div>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4"><?= $error ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light"><tr><th>ID</th><th>ชื่อประเภท</th><th>หมายเหตุ</th><th class="text-center">จัดการ</th></tr></thead>
                    <tbody>
                        <?php while($row = $res->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['svType_id'] ?></td>
                            <td class="fw-bold"><?= $row['svType_name'] ?></td>
                            <td class="small text-muted"><?= $row['svType_note'] ?: '-' ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-light rounded-pill px-3 me-1" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editTypeModal"
                                        data-id="<?= $row['svType_id'] ?>"
                                        data-name="<?= $row['svType_name'] ?>"
                                        data-note="<?= $row['svType_note'] ?>">
                                    <i class="fa fa-edit me-1"></i> แก้ไข
                                </button>
                                <a href="?delete=<?= $row['svType_id'] ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('ยืนยัน?')">
                                    <i class="fa fa-trash me-1"></i> ลบ
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
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
                            <input type="text" name="name" class="form-control rounded-3" placeholder="เช่น ซักผ้า, อบผ้า, รีดผ้า" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">หมายเหตุ</label>
                            <textarea name="note" class="form-control rounded-3" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" name="add_type" class="btn btn-primary rounded-pill w-100">บันทึก</button>
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
                            <label class="form-label small fw-bold">หมายเหตุ</label>
                            <textarea name="note" id="edit_type_note" class="form-control rounded-3" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" name="edit_type" class="btn btn-primary rounded-pill w-100">บันทึกการแก้ไข</button>
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
                const note = button.getAttribute('data-note');

                document.getElementById('edit_type_id').value = id;
                document.getElementById('edit_type_name').value = name;
                document.getElementById('edit_type_note').value = note;
            });
        }
    </script>
</body>
</html>
