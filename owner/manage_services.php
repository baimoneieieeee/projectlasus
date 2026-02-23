<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

// จัดการการเพิ่มบริการ
if (isset($_POST['add_service'])) {
    $id = "SRV" . time();
    $name = $conn->real_escape_string($_POST['name']);
    $type_id = $conn->real_escape_string($_POST['type_id']);
    $price = $_POST['price'];

    $sql = "INSERT INTO service (service_id, service_name, svType_id, service_price) VALUES ('$id', '$name', '$type_id', '$price')";
    $conn->query($sql);
}

// จัดการการลบบริการ
if (isset($_GET['delete'])) {
    $id = $conn->real_escape_string($_GET['delete']);
    $conn->query("DELETE FROM service WHERE service_id = '$id'");
    header("Location: manage_services.php");
    exit;
}

// จัดการการแก้ไขบริการ
if (isset($_POST['edit_service'])) {
    $id = $conn->real_escape_string($_POST['service_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $type_id = $conn->real_escape_string($_POST['type_id']);
    $price = $_POST['price'];

    $sql = "UPDATE service SET service_name = '$name', svType_id = '$type_id', service_price = '$price' WHERE service_id = '$id'";
    $conn->query($sql);
    header("Location: manage_services.php");
    exit;
}

$res_types = $conn->query("SELECT * FROM service_type");
$types_array = [];
while($t = $res_types->fetch_assoc()) $types_array[] = $t;

$res_services = $conn->query("SELECT s.*, t.svType_name FROM service s JOIN service_type t ON s.svType_id = t.svType_id");
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการบริการ - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">จัดการบริการ</h2>
            <div class="d-flex gap-2">
                <a href="manage_service_types.php" class="btn btn-outline-primary rounded-pill">
                    <i class="fa fa-list me-2"></i> จัดการประเภท
                </a>
                <button class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                    <i class="fa fa-plus me-2"></i> เพิ่มบริการใหม่
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>ชื่อบริการ</th><th>ประเภท</th><th>ราคา</th><th class="text-center">จัดการ</th></tr>
                    </thead>
                    <tbody>
                        <?php if($res_services->num_rows > 0): ?>
                            <?php while($s = $res_services->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?= $s['service_name'] ?></td>
                                <td><?= $s['svType_name'] ?></td>
                                <td class="text-primary fw-bold">฿<?= number_format($s['service_price'], 2) ?></td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-sm rounded-circle" data-bs-toggle="dropdown"><i class="fa fa-ellipsis-v"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
                                            <li>
                                                <button class="dropdown-item" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editServiceModal" 
                                                        data-id="<?= $s['service_id'] ?>"
                                                        data-name="<?= $s['service_name'] ?>"
                                                        data-type="<?= $s['svType_id'] ?>"
                                                        data-price="<?= $s['service_price'] ?>">
                                                    <i class="fa fa-edit me-2"></i> แก้ไข
                                                </button>
                                            </li>
                                            <li><a class="dropdown-item text-danger" href="?delete=<?= $s['service_id'] ?>" onclick="return confirm('ยืนยันการลบ?')"><i class="fa fa-trash me-2"></i> ลบ</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">ยังไม่มีรายการบริการ</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal เพิ่มบริการ -->
    <div class="modal fade" id="addServiceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <form method="post">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold">➕ เพิ่มบริการใหม่</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ชื่อบริการ</label>
                            <input type="text" name="name" class="form-control rounded-3" placeholder="เช่น ซัก+อบ" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ประเภทบริการ</label>
                            <select name="type_id" class="form-select rounded-3" required>
                                <?php foreach($types_array as $t): ?>
                                    <option value="<?= $t['svType_id'] ?>"><?= $t['svType_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ราคา (บาท)</label>
                            <input type="number" step="0.01" name="price" class="form-control rounded-3" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="add_service" class="btn btn-primary rounded-pill px-4">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal แก้ไขบริการ -->
    <div class="modal fade" id="editServiceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <form method="post">
                    <input type="hidden" name="service_id" id="edit_id">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold">📝 แก้ไขบริการ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ชื่อบริการ</label>
                            <input type="text" name="name" id="edit_name" class="form-control rounded-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ประเภทบริการ</label>
                            <select name="type_id" id="edit_type" class="form-select rounded-3" required>
                                <?php foreach($types_array as $t): ?>
                                    <option value="<?= $t['svType_id'] ?>"><?= $t['svType_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ราคา (บาท)</label>
                            <input type="number" step="0.01" name="price" id="edit_price" class="form-control rounded-3" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="edit_service" class="btn btn-primary rounded-pill px-4">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const editServiceModal = document.getElementById('editServiceModal');
        if (editServiceModal) {
            editServiceModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                const type = button.getAttribute('data-type');
                const price = button.getAttribute('data-price');

                document.getElementById('edit_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_type').value = type;
                document.getElementById('edit_price').value = price;
            });
        }
    </script>
</body>
</html>
