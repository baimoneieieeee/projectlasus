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
    $quota = (int)$_POST['quota'];
    $note = $conn->real_escape_string($_POST['note']);

    $sql = "INSERT INTO service (service_id, service_name, svType_id, service_price, service_quota, service_note) 
            VALUES ('$id', '$name', '$type_id', '$price', '$quota', '$note')";
    $conn->query($sql);
    header("Location: manage_services.php");
    exit;
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
    $quota = (int)$_POST['quota'];
    $note = $conn->real_escape_string($_POST['note']);

    $sql = "UPDATE service SET service_name = '$name', svType_id = '$type_id', service_price = '$price', service_quota = '$quota', service_note = '$note' WHERE service_id = '$id'";
    $conn->query($sql);
    header("Location: manage_services.php");
    exit;
}

$res_types = $conn->query("SELECT * FROM service_type ORDER BY svType_behavior DESC");
$types_array = [];
while($t = $res_types->fetch_assoc()) $types_array[] = $t;

// ดึงข้อมูลบริการแยกตามหมวดหมู่หลัก 2 อัน (กรองเอาเฉพาะรายการซักผ้าจริงๆ ไม่เอาตัวแพ็กเกจ)
$res_monthly = $conn->query("SELECT s.*, t.svType_name FROM service s JOIN service_type t ON s.svType_id = t.svType_id WHERE t.svType_behavior = 'monthly' AND s.service_quota = 0 ORDER BY s.service_id DESC");
$res_per_piece = $conn->query("SELECT s.*, t.svType_name FROM service s JOIN service_type t ON s.svType_id = t.svType_id WHERE t.svType_behavior = 'per_piece' AND s.service_quota = 0 ORDER BY s.service_price ASC");
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการรายการบริการ - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&display=swap');
        body { background: #f8fafc; font-family: 'Chakra Petch', sans-serif; }
        .main-content { margin-left: 280px; padding: 40px; }
        .card-custom { border: none; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: white; margin-bottom: 30px; }
        .group-header-monthly { border-bottom: 3px solid #2563eb; padding-bottom: 10px; margin-bottom: 20px; color: #2563eb; display: flex; align-items: center; }
        .group-header-per-piece { border-bottom: 3px solid #10b981; padding-bottom: 10px; margin-bottom: 20px; color: #10b981; display: flex; align-items: center; }
        .badge-package { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .badge-item { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">🛠 จัดการรายการบริการ</h2>
                    <p class="text-muted">กำหนดรายการซักและราคาขายของร้าน</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="manage_service_types.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
                        <i class="fa fa-layer-group me-2"></i> หมวดหมู่
                    </a>
                    <button class="btn btn-primary rounded-pill px-4 shadow fw-bold" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                        <i class="fa fa-plus me-2"></i> เพิ่มรายการใหม่
                    </button>
                </div>
            </div>

            <div class="row g-4">
                <!-- 📦 กลุ่มที่ 1: รายการในหมวด "รายเดือน" -->
                <div class="col-lg-6">
                    <div class="group-header-monthly">
                        <i class="fa fa-calendar-check fa-lg me-3"></i>
                        <h4 class="fw-bold mb-0">รายการกลุ่ม: รายเดือน</h4>
                    </div>
                    <div class="card card-custom p-3">
                        <div class="alert alert-primary bg-primary bg-opacity-10 border-0 rounded-4 smaller text-primary mb-3" style="font-size: 0.85rem;">
                            <i class="fa fa-info-circle me-1"></i> รายการในกลุ่มนี้จะใช้สำหรับ <b>หักาผ้ารายเดือนว่ามีบริการอะไรบ้าง</b> หรือ <b>แพ็กเกจรายเดือนมีอะไรบ้าง</b> สมาชิก
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <tbody>
                                    <?php if($res_monthly->num_rows > 0): while($s = $res_monthly->fetch_assoc()): 
                                        $is_package = ($s['service_quota'] > 0);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3 fs-4"><?= $is_package ? '📦' : '👕' ?></div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?= $s['service_name'] ?></div>
                                                    <?php if($is_package): ?>
                                                        <span class="badge badge-package rounded-pill small">แพ็กเกจ: <?= $s['service_quota'] ?> ชิ้น</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-item rounded-pill small text-muted">หักโควตาผ้า</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold">฿<?= number_format($s['service_price'], 0) ?></td>
                                        <td class="text-end">
                                            <button class="btn btn-light btn-sm rounded-circle text-primary" data-bs-toggle="modal" data-bs-target="#editServiceModal" 
                                                    data-id="<?= $s['service_id'] ?>" data-name="<?= $s['service_name'] ?>" data-type="<?= $s['svType_id'] ?>" data-price="<?= $s['service_price'] ?>" data-quota="<?= $s['service_quota'] ?>" data-note="<?= htmlspecialchars($s['service_note']) ?>">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr><td class="text-center py-4 text-muted">ยังไม่มีรายการ</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 💰 กลุ่มที่ 2: รายการในหมวด "รายชิ้น" -->
                <div class="col-lg-6">
                    <div class="group-header-per-piece">
                        <i class="fa fa-hand-holding-dollar fa-lg me-3"></i>
                        <h4 class="fw-bold mb-0">รายการกลุ่ม: รายชิ้น</h4>
                    </div>
                    <div class="card card-custom p-3">
                        <div class="alert alert-success bg-success bg-opacity-10 border-0 rounded-4 smaller text-success mb-3" style="font-size: 0.85rem;">
                            <i class="fa fa-info-circle me-1"></i> รายการในกลุ่มนี้จะใช้สำหรับ <b>เก็บเงินสดเพิ่ม</b> (เช่น ผ้าพิเศษ, ผ้านวม)
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <tbody>
                                    <?php if($res_per_piece->num_rows > 0): while($s = $res_per_piece->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3 fs-4">💰</div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?= $s['service_name'] ?></div>
                                                    <span class="badge badge-item rounded-pill small">เก็บเงินรายครั้ง</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold text-success">฿<?= number_format($s['service_price'], 0) ?></td>
                                        <td class="text-end">
                                            <button class="btn btn-light btn-sm rounded-circle text-primary" data-bs-toggle="modal" data-bs-target="#editServiceModal" 
                                                    data-id="<?= $s['service_id'] ?>" data-name="<?= $s['service_name'] ?>" data-type="<?= $s['svType_id'] ?>" data-price="<?= $s['service_price'] ?>" data-quota="<?= $s['service_quota'] ?>" data-note="<?= htmlspecialchars($s['service_note']) ?>">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr><td class="text-center py-4 text-muted">ยังไม่มีรายการ</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal เพิ่มบริการ -->
    <div class="modal fade" id="addServiceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <form method="post">
                    <div class="modal-header border-0 px-4 pt-4">
                        <h5 class="modal-title fw-bold">➕ เพิ่มบริการใหม่</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4 pt-0">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ชื่อรายการ (เช่น ซักเสื้อยืด หรือ แพ็กเกจ 80 ชิ้น)</label>
                            <input type="text" name="name" class="form-control rounded-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-primary">ใส่ไว้ในหมวดหมู่ไหน?</label>
                            <select name="type_id" id="add_type_id" class="form-select rounded-3" required onchange="toggleQuota('add')">
                                <?php foreach($types_array as $t): ?>
                                    <option value="<?= $t['svType_id'] ?>" data-behavior="<?= $t['svType_behavior'] ?>">
                                        <?= $t['svType_name'] ?> (<?= $t['svType_behavior'] == 'monthly' ? 'หักรายเดือน' : 'เก็บเงินรายครั้ง' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6" id="add_price_container">
                                <label class="form-label small fw-bold">ราคาขาย (บาท)</label>
                                <input type="number" name="price" id="add_price" class="form-control rounded-3" value="0">
                            </div>
                            <div class="col-6" id="add_quota_container">
                                <label class="form-label small fw-bold text-primary">จำนวนโควตา (ถ้ามี)</label>
                                <input type="number" name="quota" id="add_quota" class="form-control rounded-3" value="0">
                                <div class="smaller text-muted mt-1" style="font-size: 0.7rem;">ใส่ตัวเลขถ้าเป็น "แพ็กเกจ" ถ้าเป็นซักทั่วไปใส่ 0</div>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label small fw-bold">รายละเอียด (ลูกค้าเห็นในแอป)</label>
                            <textarea name="note" class="form-control rounded-3" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="submit" name="add_service" class="btn btn-primary rounded-pill w-100 py-3 fw-bold shadow">บันทึกข้อมูลรายการ</button>
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
                    <div class="modal-header border-0 px-4 pt-4">
                        <h5 class="modal-title fw-bold">📝 แก้ไขรายการ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4 pt-0">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">ชื่อรายการ</label>
                            <input type="text" name="name" id="edit_name" class="form-control rounded-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-primary">เปลี่ยนหมวดหมู่</label>
                            <select name="type_id" id="edit_type" class="form-select rounded-3" onchange="toggleQuota('edit')">
                                <?php foreach($types_array as $t): ?>
                                    <option value="<?= $t['svType_id'] ?>" data-behavior="<?= $t['svType_behavior'] ?>"><?= $t['svType_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6" id="edit_price_container">
                                <label class="form-label small fw-bold">ราคา</label>
                                <input type="number" name="price" id="edit_price" class="form-control rounded-3" required>
                            </div>
                            <div class="col-6" id="edit_quota_container">
                                <label class="form-label small fw-bold">โควตา</label>
                                <input type="number" name="quota" id="edit_quota" class="form-control rounded-3">
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label small fw-bold">รายละเอียด</label>
                            <textarea name="note" id="edit_note" class="form-control rounded-3" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0 d-flex gap-2">
                        <a href="#" id="delete_link" class="btn btn-outline-danger rounded-pill px-4 fw-bold" onclick="return confirm('ยืนยันการลบรายการนี้?')"><i class="fa fa-trash"></i></a>
                        <button type="submit" name="edit_service" class="btn btn-primary rounded-pill flex-grow-1 py-3 fw-bold shadow">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleQuota(mode) {
            const select = document.getElementById(mode === 'add' ? 'add_type_id' : 'edit_type');
            const quotaContainer = document.getElementById(mode === 'add' ? 'add_quota_container' : 'edit_quota_container');
            const priceContainer = document.getElementById(mode === 'add' ? 'add_price_container' : 'edit_price_container');
            const behavior = select.options[select.selectedIndex].getAttribute('data-behavior');
            
            if (behavior === 'monthly') {
                // รายเดือน (บริการซักปกติ): ซ่อนทั้งราคาและโควตา
                quotaContainer.style.display = 'none';
                priceContainer.style.display = 'none';
                
                if(mode === 'add') {
                    document.getElementById('add_price').value = 0;
                    document.getElementById('add_quota').value = 0;
                } else {
                    document.getElementById('edit_price').value = 0;
                    document.getElementById('edit_quota').value = 0;
                }
            } else {
                // รายชิ้น (เก็บเงินสดเพิ่ม): โชว์เฉพาะราคา ซ่อนโควตา
                quotaContainer.style.display = 'none';
                priceContainer.style.display = 'block';
                
                if(mode === 'add') {
                    document.getElementById('add_quota').value = 0;
                } else {
                    document.getElementById('edit_quota').value = 0;
                }
            }
        }

        const editServiceModal = document.getElementById('editServiceModal');
        if (editServiceModal) {
            editServiceModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_name').value = button.getAttribute('data-name');
                document.getElementById('edit_type').value = button.getAttribute('data-type');
                document.getElementById('edit_price').value = button.getAttribute('data-price');
                document.getElementById('edit_quota').value = button.getAttribute('data-quota');
                document.getElementById('edit_note').value = button.getAttribute('data-note');
                document.getElementById('delete_link').href = '?delete=' + id;
                
                // เรียกใช้งาน toggleQuota ทันทีที่เปิด Modal แก้ไข
                toggleQuota('edit');
            });
        }

        const addServiceModal = document.getElementById('addServiceModal');
        if (addServiceModal) {
            addServiceModal.addEventListener('show.bs.modal', () => {
                toggleQuota('add');
            });
        }
    </script>
</body>
</html>
