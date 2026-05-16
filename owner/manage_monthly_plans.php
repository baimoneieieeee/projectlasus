<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

$settings_file = __DIR__ . '/../config/shop_settings.json';

// จัดการการเพิ่มแพ็กเกจ
if (isset($_POST['add_package'])) {
    $id = "SRV" . time();
    $name = $conn->real_escape_string($_POST['name']);
    $type_id = $conn->real_escape_string($_POST['type_id']);
    $price = (float)$_POST['price'];
    $quota = (int)$_POST['quota'];
    $note = $conn->real_escape_string($_POST['note']);

    if ($quota <= 0) $quota = 1;

    // บันทึกเงื่อนไขส่วนกลางด้วย
    $settings = ["membership_rounds" => $_POST['membership_rounds'], "membership_delivery" => $_POST['membership_delivery']];
    file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $sql = "INSERT INTO service (service_id, service_name, svType_id, service_price, service_quota, service_note) 
            VALUES ('$id', '$name', '$type_id', '$price', '$quota', '$note')";
    $conn->query($sql);
    header("Location: manage_monthly_plans.php");
    exit;
}

// จัดการการลบแพ็กเกจ
if (isset($_GET['delete'])) {
    $id = $conn->real_escape_string($_GET['delete']);
    $conn->query("DELETE FROM service WHERE service_id = '$id'");
    header("Location: manage_monthly_plans.php");
    exit;
}

// จัดการการแก้ไขแพ็กเกจ
if (isset($_POST['edit_package'])) {
    $id = $conn->real_escape_string($_POST['service_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $type_id = $conn->real_escape_string($_POST['type_id']);
    $price = (float)$_POST['price'];
    $quota = (int)$_POST['quota'];
    $note = $conn->real_escape_string($_POST['note']);

    // บันทึกเงื่อนไขส่วนกลางด้วย
    $settings = ["membership_rounds" => $_POST['membership_rounds'], "membership_delivery" => $_POST['membership_delivery']];
    file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $sql = "UPDATE service SET service_name = '$name', svType_id = '$type_id', service_price = '$price', service_quota = '$quota', service_note = '$note' WHERE service_id = '$id'";
    $conn->query($sql);
    header("Location: manage_monthly_plans.php");
    exit;
}

$global_settings = file_exists($settings_file) ? json_decode(file_get_contents($settings_file), true) : [];

// ดึงข้อมูล
$res_types = $conn->query("SELECT * FROM service_type WHERE svType_behavior = 'monthly' ORDER BY svType_name ASC");
$types_array = [];
while($t = $res_types->fetch_assoc()) $types_array[] = $t;

$res_packages = $conn->query("SELECT s.*, t.svType_name FROM service s JOIN service_type t ON s.svType_id = t.svType_id WHERE s.service_quota > 0 ORDER BY s.service_price ASC");
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการแพ็กเกจ - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&display=swap');
        :root { --primary: #2563eb; --bg: #f8fafc; }
        body { background: var(--bg); font-family: 'Chakra Petch', sans-serif; color: #1e293b; }
        .main-content { margin-left: 280px; padding: 40px; }
        
        /* 🛠️ Admin Section */
        .admin-section { background: white; border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .admin-label { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px; display: block; }
        
        /* 📦 Package Preview Card */
        .preview-label { background: #334155; color: white; padding: 2px 10px; border-radius: 6px; font-size: 0.65rem; font-weight: 600; display: inline-block; margin-bottom: 10px; }
        .pkg-card { transition: 0.3s; border: none; overflow: hidden; border-radius: 24px; background: white; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); position: relative; }
        .pkg-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .quota-circle { background: rgba(255,255,255,0.2); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; }
        .smaller { font-size: 0.85rem; }
        
        /* ⚡ Quick Actions Overlay */
        .pkg-actions { position: absolute; top: 15px; right: 15px; z-index: 10; display: flex; gap: 5px; }
        .btn-action { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: white; color: #64748b; border: 1px solid #e2e8f0; transition: 0.2s; }
        .btn-action:hover { background: #f1f5f9; color: var(--primary); }
        .btn-action.delete:hover { color: #ef4444; }

        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-0">🌟 จัดการแพ็กเกจรายเดือน</h2>
                    <p class="text-muted small">ตั้งค่าเงื่อนไขส่วนกลางและแพ็กเกจที่คุณต้องการเปิดขาย</p>
                </div>
                <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addPackageModal">
                    <i class="fa fa-plus me-1"></i> เพิ่มแพ็กเกจใหม่
                </button>
            </div>

            <?php if(isset($success_msg)): ?>
                <div class="alert alert-success border-0 rounded-4 shadow-sm mb-4"><?= $success_msg ?></div>
            <?php endif; ?>

            <!-- 📦 Packages Grid -->
            <div class="mb-3 d-flex align-items-center gap-2">
                <span class="fw-bold"><i class="fa fa-eye me-1"></i> ตัวอย่างหน้าจอที่ลูกค้าจะเห็น</span>
                <hr class="flex-grow-1 opacity-25">
            </div>

            <?php
            $cond_rounds = $global_settings['membership_rounds'] ?? 'ให้บริการ 4 รอบ/เดือน (สัปดาห์ละ 1 ครั้ง)';
            $cond_delivery = $global_settings['membership_delivery'] ?? 'บริการรับ-ส่งฟรี ในระยะทางที่กำหนด';
            $shop_info = $conn->query("SELECT shop_extra_price FROM shop LIMIT 1")->fetch_assoc();
            $extra_price = (int)($shop_info['shop_extra_price'] ?? 10);
            ?>
            <div class="row g-4">
                <?php if($res_packages->num_rows > 0): while($pkg = $res_packages->fetch_assoc()): ?>
                <div class="col-xl-4 col-md-6">
                    <div class="pkg-card p-4 text-center h-100">
                        <!-- Admin Actions -->
                        <div class="pkg-actions">
                            <button class="btn-action shadow-sm" title="แก้ไข" data-bs-toggle="modal" data-bs-target="#editPackageModal" 
                                    data-id="<?= $pkg['service_id'] ?>" data-name="<?= $pkg['service_name'] ?>" data-type="<?= $pkg['svType_id'] ?>" data-price="<?= $pkg['service_price'] ?>" data-quota="<?= $pkg['service_quota'] ?>" data-note="<?= htmlspecialchars($pkg['service_note']) ?>">
                                <i class="fa fa-edit"></i>
                            </button>
                            <a href="?delete=<?= $pkg['service_id'] ?>" class="btn-action delete shadow-sm" title="ลบ" onclick="return confirm('ยืนยันการลบแพ็กเกจนี้?')">
                                <i class="fa fa-trash"></i>
                            </a>
                        </div>

                        <span class="preview-label">PREVIEW</span>
                        <h3 class="fw-bold mb-3 text-primary"><?= htmlspecialchars($pkg['service_name']) ?></h3>
                        <div class="bg-primary text-white p-4 rounded-4 mb-4 shadow-sm">
                            <div class="quota-circle"><i class="fa fa-shirt fa-lg"></i></div>
                            <h4 class="mb-1">โควตา <?= $pkg['service_quota'] ?> ชิ้น</h4>
                            <h2 class="fw-bold mb-0">฿<?= number_format($pkg['service_price']) ?></h2>
                            <p class="smaller opacity-75 mb-0">เฉลี่ยชิ้นละ <?= number_format($pkg['service_price'] / $pkg['service_quota'], 1) ?> บาท</p>
                        </div>
                        
                        <div class="text-start mb-0">
                            <h6 class="fw-bold small text-dark mb-3"><i class="fa fa-info-circle me-2 text-primary"></i>เงื่อนไขการใช้งาน:</h6>
                            <ul class="list-unstyled d-flex flex-column gap-2 smaller text-muted">
                                <li class="d-flex align-items-start text-start">
                                    <i class="fa fa-sync text-primary me-2 mt-1"></i>
                                    <span><b><?= htmlspecialchars($cond_rounds) ?></b></span>
                                </li>
                                <li class="d-flex align-items-start text-start">
                                    <i class="fa fa-box-open text-primary me-2 mt-1"></i>
                                    <span>จำนวนผ้าสะสมรวม <b><?= $pkg['service_quota'] ?> ชิ้น</b></span>
                                </li>
                                <li class="d-flex align-items-start text-start">
                                    <i class="fa fa-exclamation-circle text-danger me-2 mt-1"></i>
                                    <span>หากส่งซักเกินโควตา คิดเพิ่มชิ้นละ <b><?= $extra_price ?> บาท</b></span>
                                </li>
                                <li class="d-flex align-items-start text-start">
                                    <i class="fa fa-truck text-success me-2 mt-1"></i>
                                    <span><b><?= htmlspecialchars($cond_delivery) ?></b></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endwhile; else: ?>
                <div class="col-12 text-center py-5">
                    <div class="p-5 bg-white rounded-5 border border-dashed text-muted">
                        <i class="fa fa-box-open fa-3x mb-3 opacity-25"></i>
                        <h5>ยังไม่มีแพ็กเกจรายเดือน</h5>
                        <p>เริ่มสร้างแพ็กเกจแรกเพื่อดึงดูดลูกค้าได้เลยครับ</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Modal เพิ่มแพ็กเกจ -->
    <div class="modal fade" id="addPackageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <form method="post">
                    <div class="modal-header border-0 px-4 pt-4">
                        <h5 class="modal-title fw-bold fs-4">➕ เพิ่มแพ็กเกจใหม่</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4 pt-0">
                        <div class="mb-4">
                            <label class="form-label small fw-bold">ชื่อแพ็กเกจ (ลูกค้าเห็นอันแรก)</label>
                            <input type="text" name="name" class="form-control rounded-3 py-2" placeholder="เช่น รายเดือนปกติ (80 ชิ้น)" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-primary">เลือกหมวดหมู่</label>
                            <select name="type_id" class="form-select rounded-3 py-2" required>
                                <?php foreach($types_array as $t): ?>
                                    <option value="<?= $t['svType_id'] ?>"><?= $t['svType_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <label class="form-label small fw-bold">ราคาขาย (บาท)</label>
                                <input type="number" name="price" class="form-control rounded-3 py-2" placeholder="800" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-primary">โควตา (จำนวนชิ้น)</label>
                                <input type="number" name="quota" class="form-control rounded-3 py-2" value="80" required>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label small fw-bold">รายละเอียด/เงื่อนไขพิเศษ</label>
                            <textarea name="note" class="form-control rounded-3" rows="3" placeholder="เช่น แถมซักตุ๊กตาฟรี 1 ตัว"></textarea>
                        </div>

                        <hr class="my-4 opacity-25">
                        <span class="admin-label"><i class="fa fa-gears me-1"></i> ตั้งค่ากฎส่วนกลาง (มีผลทุกแพ็กเกจ)</span>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">รอบการให้บริการ</label>
                            <input type="text" name="membership_rounds" class="form-control border-0 bg-light rounded-3" value="<?= htmlspecialchars($global_settings['membership_rounds'] ?? 'ให้บริการ 4 รอบ/เดือน (สัปดาห์ละ 1 ครั้ง)') ?>" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label small fw-bold">การจัดส่ง</label>
                            <input type="text" name="membership_delivery" class="form-control border-0 bg-light rounded-3" value="<?= htmlspecialchars($global_settings['membership_delivery'] ?? 'บริการรับ-ส่งฟรี ในระยะทางที่กำหนด') ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="submit" name="add_package" class="btn btn-primary rounded-pill w-100 py-3 fw-bold shadow">สร้างแพ็กเกจและบันทึกกฎ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal แก้ไขแพ็กเกจ -->
    <div class="modal fade" id="editPackageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <form method="post">
                    <input type="hidden" name="service_id" id="edit_id">
                    <div class="modal-header border-0 px-4 pt-4">
                        <h5 class="modal-title fw-bold fs-4">📝 แก้ไขข้อมูลแพ็กเกจ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4 pt-0">
                        <div class="mb-4">
                            <label class="form-label small fw-bold">ชื่อแพ็กเกจ</label>
                            <input type="text" name="name" id="edit_name" class="form-control rounded-3 py-2" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold">เปลี่ยนหมวดหมู่</label>
                            <select name="type_id" id="edit_type" class="form-select rounded-3 py-2">
                                <?php foreach($types_array as $t): ?>
                                    <option value="<?= $t['svType_id'] ?>"><?= $t['svType_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <label class="form-label small fw-bold">ราคาขาย</label>
                                <input type="number" name="price" id="edit_price" class="form-control rounded-3 py-2" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">โควตา (ชิ้น)</label>
                                <input type="number" name="quota" id="edit_quota" class="form-control rounded-3 py-2" required>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label small fw-bold">รายละเอียด</label>
                            <textarea name="note" id="edit_note" class="form-control rounded-3" rows="3"></textarea>
                        </div>

                        <hr class="my-4 opacity-25">
                        <span class="admin-label"><i class="fa fa-gears me-1"></i> ตั้งค่ากฎส่วนกลาง (มีผลทุกแพ็กเกจ)</span>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">รอบการให้บริการ</label>
                            <input type="text" name="membership_rounds" class="form-control border-0 bg-light rounded-3" value="<?= htmlspecialchars($global_settings['membership_rounds'] ?? 'ให้บริการ 4 รอบ/เดือน (สัปดาห์ละ 1 ครั้ง)') ?>" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label small fw-bold">การจัดส่ง</label>
                            <input type="text" name="membership_delivery" class="form-control border-0 bg-light rounded-3" value="<?= htmlspecialchars($global_settings['membership_delivery'] ?? 'บริการรับ-ส่งฟรี ในระยะทางที่กำหนด') ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="submit" name="edit_package" class="btn btn-primary rounded-pill w-100 py-3 fw-bold shadow">บันทึกการแก้ไขและกฎส่วนกลาง</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const editPackageModal = document.getElementById('editPackageModal');
        if (editPackageModal) {
            editPackageModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_name').value = button.getAttribute('data-name');
                document.getElementById('edit_type').value = button.getAttribute('data-type');
                document.getElementById('edit_price').value = button.getAttribute('data-price');
                document.getElementById('edit_quota').value = button.getAttribute('data-quota');
                document.getElementById('edit_note').value = button.getAttribute('data-note');
            });
        }
    </script>
</body>
</html>