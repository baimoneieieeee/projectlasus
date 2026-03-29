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
            <div>
                <h3 class="fw-bold mb-0">🛠 จัดการรายการบริการ (ราคา)</h3>
                <p class="text-muted small">ตั้งค่ารายการซักผ้าและราคาสำหรับหน้าร้าน</p>
            </div>
            <div class="d-flex gap-2">
                <a href="manage_service_types.php" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
                    <i class="fa fa-layer-group me-2"></i> จัดการหมวดหมู่
                </a>
                <button class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                    <i class="fa fa-plus-circle me-2"></i> เพิ่มบริการใหม่
                </button>
            </div>
        </div>

        <!-- 💡 คู่มือการตั้งค่าบริการ -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-primary bg-opacity-10 h-100">
                    <div class="fw-bold text-primary mb-2 small"><i class="fa fa-star me-1"></i> การตั้งค่า "แพ็กเกจสมาชิก"</div>
                    <p class="small text-muted mb-0">ให้ใส่ <b>จำนวนชิ้นโควตา</b> (เช่น 80) และตั้ง <b>ราคา</b> (เช่น 800) เพื่อให้ลูกค้าเห็นและกดสมัครได้</p>
                    <div class="mt-2 small text-primary fw-bold">ตัวอย่าง: แพ็กเกจ 80 ชิ้น ราคา 800 บาท</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-success bg-opacity-10 h-100">
                    <div class="fw-bold text-success mb-2 small"><i class="fa fa-tag me-1"></i> การตั้งค่า "ผ้าพิเศษ"</div>
                    <p class="small text-muted mb-0">ให้ใส่ <b>ราคาต่อชิ้น</b> (เช่น 150) และตั้งค่า <b>จำนวนโควตาเป็น 0</b> เพื่อคิดเงินแยกรายชิ้น</p>
                    <div class="mt-2 small text-success fw-bold">ตัวอย่าง: ผ้านวม 150 บาท / ชุดไหม 250 บาท</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Section 1: รายการสำหรับสมาชิกรรายเดือน (หักโควตา) -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle me-3">
                                <i class="fa fa-star fa-lg"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0">รายการหักโควตา</h5>
                                <span class="badge bg-warning text-dark rounded-pill mt-1">แพ็กเกจรายเดือน (ราคา 0฿)</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-borderless table-hover align-middle">
                                <tbody>
                                                            <?php 
                                                            $res_t1 = $conn->query("SELECT s.* FROM service s JOIN service_type t ON s.svType_id = t.svType_id WHERE t.svType_behavior = 'quota'");
                                                            if($res_t1->num_rows > 0): 
                                                                while($s = $res_t1->fetch_assoc()): 
                                                            ?>                                        <tr class="border-bottom border-light">
                                            <td class="py-3">
                                                <div class="fw-bold text-dark"><?= $s['service_name'] ?></div>
                                            </td>
                                            <td class="text-end py-3">
                                                <button class="btn btn-light btn-sm rounded-circle me-1 shadow-sm text-primary" data-bs-toggle="modal" data-bs-target="#editServiceModal" 
                                                        data-id="<?= $s['service_id'] ?>" data-name="<?= $s['service_name'] ?>" data-type="<?= $s['svType_id'] ?>" data-price="<?= $s['service_price'] ?>" data-quota="<?= $s['service_quota'] ?>" data-note="<?= htmlspecialchars($s['service_note']) ?>">
                                                    <i class="fa fa-edit"></i>
                                                </button>
                                                <a href="?delete=<?= $s['service_id'] ?>" class="btn btn-light btn-sm rounded-circle shadow-sm text-danger" onclick="return confirm('ยืนยันการลบ?')"><i class="fa fa-trash"></i></a>
                                            </td>
                                        </tr>
                                    <?php endwhile; else: ?>
                                        <tr><td colspan="2" class="text-center py-4 text-muted small bg-light rounded-3">ยังไม่มีรายการในหมวดนี้</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: รายการผ้าพิเศษ/รายครั้ง (จ่ายเงินสดเพิ่ม) -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                        <div class="d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle me-3">
                                <i class="fa fa-tag fa-lg"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0">รายการคิดเงินแยก</h5>
                                <span class="badge bg-success rounded-pill mt-1">ผ้าพิเศษ (จ่ายเงินสด)</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-borderless table-hover align-middle">
                                <tbody>
                                                            <?php 
                                                            $res_t2 = $conn->query("SELECT s.* FROM service s JOIN service_type t ON s.svType_id = t.svType_id WHERE t.svType_behavior = 'special'");
                                                            if($res_t2->num_rows > 0): 
                                                                while($s = $res_t2->fetch_assoc()): 
                                                            ?>                                        <tr class="border-bottom border-light">
                                            <td class="py-3">
                                                <div class="fw-bold text-dark"><?= $s['service_name'] ?></div>
                                                <div class="small fw-bold text-success mt-1">฿<?= number_format($s['service_price'], 2) ?> / ชิ้น</div>
                                            </td>
                                            <td class="text-end py-3">
                                                <button class="btn btn-light btn-sm rounded-circle me-1 shadow-sm text-primary" data-bs-toggle="modal" data-bs-target="#editServiceModal" 
                                                        data-id="<?= $s['service_id'] ?>" data-name="<?= $s['service_name'] ?>" data-type="<?= $s['svType_id'] ?>" data-price="<?= $s['service_price'] ?>" data-quota="<?= $s['service_quota'] ?>" data-note="<?= htmlspecialchars($s['service_note']) ?>">
                                                    <i class="fa fa-edit"></i>
                                                </button>
                                                <a href="?delete=<?= $s['service_id'] ?>" class="btn btn-light btn-sm rounded-circle shadow-sm text-danger" onclick="return confirm('ยืนยันการลบ?')"><i class="fa fa-trash"></i></a>
                                            </td>
                                        </tr>
                                    <?php endwhile; else: ?>
                                        <tr><td colspan="2" class="text-center py-4 text-muted small bg-light rounded-3">ยังไม่มีรายการในหมวดนี้</td></tr>
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
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-primary">จำนวนชิ้นโควตา (เฉพาะรายเดือน)</label>
                            <input type="number" name="quota" class="form-control rounded-3" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">รายละเอียดอื่นๆ (ให้ลูกค้าเห็น)</label>
                            <textarea name="note" class="form-control rounded-3" rows="4" placeholder="เช่น - ใช้ได้นาน 30 วัน&#10;- ซัก อบ รีด ได้ทุกประเภท&#10;- ส่งได้อาทิตย์ละไม่เกิน 20 ชิ้น"></textarea>
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
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-primary">จำนวนชิ้นโควตา (เฉพาะรายเดือน)</label>
                            <input type="number" name="quota" id="edit_quota" class="form-control rounded-3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">รายละเอียดอื่นๆ (ให้ลูกค้าเห็น)</label>
                            <textarea name="note" id="edit_note" class="form-control rounded-3" rows="4"></textarea>
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
                const quota = button.getAttribute('data-quota');
                const note = button.getAttribute('data-note');

                document.getElementById('edit_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_type').value = type;
                document.getElementById('edit_price').value = price;
                document.getElementById('edit_quota').value = quota;
                document.getElementById('edit_note').value = note;
            });
        }
    </script>
</body>
</html>
