<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

if (isset($_POST['add_type'])) {
    $id = "TYP" . time();
    $name = $conn->real_escape_string($_POST['name']);
    $note = $conn->real_escape_string($_POST['note']);
    $conn->query("INSERT INTO service_type (svType_id, svType_name, svType_note) VALUES ('$id', '$name', '$note')");
}

if (isset($_GET['delete'])) {
    $id = $conn->real_escape_string($_GET['delete']);
    $conn->query("DELETE FROM service_type WHERE svType_id = '$id'");
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
    <style>
        :root { --primary: #2563EB; --bg: #F8FAFC; }
        body { background: var(--bg); font-family: 'Segoe UI', sans-serif; }
        .sidebar { width: 260px; height: 100vh; background: #fff; position: fixed; border-right: 1px solid #E2E8F0; padding: 20px; }
        .main-content { margin-left: 260px; padding: 30px; }
        .nav-link { color: #64748B; padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; }
        .nav-link.active { background: #EFF6FF; color: var(--primary); font-weight: 600; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h4 class="fw-bold text-primary mb-4 px-3">🧺 Shop Admin</h4>
        <nav class="nav flex-column">
            <a class="nav-link" href="owner_home.php"><i class="fa fa-home me-2"></i> หน้าแรก</a>
            <a class="nav-link" href="manage_requests.php"><i class="fa fa-bell me-2"></i> คำขอรับผ้า</a>
            <a class="nav-link" href="manage_orders.php"><i class="fa fa-file-invoice me-2"></i> รายการสั่งซื้อ</a>
            <a class="nav-link active" href="manage_services.php"><i class="fa fa-tags me-2"></i> จัดการบริการ</a>
            <a class="nav-link" href="manage_members.php"><i class="fa fa-users me-2"></i> ข้อมูลลูกค้า</a>
            <a class="nav-link" href="manage_shop.php"><i class="fa fa-cog me-2"></i> ตั้งค่าร้านค้า</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">จัดการประเภทบริการ</h2>
            <button class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#addTypeModal">
                <i class="fa fa-plus me-2"></i> เพิ่มประเภทบริการใหม่
            </button>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light"><tr><th>ID</th><th>ชื่อประเภท</th><th>หมายเหตุ</th><th>จัดการ</th></tr></thead>
                    <tbody>
                        <?php while($row = $res->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['svType_id'] ?></td>
                            <td class="fw-bold"><?= $row['svType_name'] ?></td>
                            <td class="small text-muted"><?= $row['svType_note'] ?: '-' ?></td>
                            <td>
                                <a href="?delete=<?= $row['svType_id'] ?>" class="text-danger" onclick="return confirm('ยืนยัน?')"><i class="fa fa-trash"></i></a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
