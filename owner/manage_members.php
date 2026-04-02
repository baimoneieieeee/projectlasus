<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

// 1. จัดการการลบ
if (isset($_GET['delete'])) {
    $id = $conn->real_escape_string($_GET['delete']);
    $conn->query("DELETE FROM member WHERE m_id = '$id'");
    header("Location: manage_members.php?success=ลบข้อมูลเรียบร้อยแล้ว");
    exit;
}

// 2. จัดการการเพิ่มลูกค้าใหม่
if (isset($_POST['add_member'])) {
    $m_id = "MEM" . time();
    $name = $conn->real_escape_string($_POST['name']);
    $last = $conn->real_escape_string($_POST['lastname']);
    $mail = $conn->real_escape_string($_POST['mail']);
    $tel = $conn->real_escape_string($_POST['tel']);
    $pass = $conn->real_escape_string($_POST['password']);
    $addr = $conn->real_escape_string($_POST['address']);

    $sql = "INSERT INTO member (m_id, m_name, m_lastname, m_mail, m_tel, m_password, m_address) 
            VALUES ('$m_id', '$name', '$last', '$mail', '$tel', '$pass', '$addr')";
    if ($conn->query($sql)) {
        header("Location: manage_members.php?success=เพิ่มลูกค้าใหม่เรียบร้อยแล้ว");
        exit;
    }
}

// 3. จัดการการแก้ไขข้อมูล
if (isset($_POST['edit_member'])) {
    $m_id = $conn->real_escape_string($_POST['m_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $last = $conn->real_escape_string($_POST['lastname']);
    $mail = $conn->real_escape_string($_POST['mail']);
    $tel = $conn->real_escape_string($_POST['tel']);
    $pass = $conn->real_escape_string($_POST['password']);
    $addr = $conn->real_escape_string($_POST['address']);

    $sql = "UPDATE member SET 
            m_name = '$name', m_lastname = '$last', m_mail = '$mail', 
            m_tel = '$tel', m_password = '$pass', m_address = '$addr' 
            WHERE m_id = '$m_id'";
    if ($conn->query($sql)) {
        header("Location: manage_members.php?success=แก้ไขข้อมูลเรียบร้อยแล้ว");
        exit;
    }
}

// การค้นหา
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where = $search ? "WHERE m_name LIKE '%$search%' OR m_lastname LIKE '%$search%' OR m_mail LIKE '%$search%' OR m_tel LIKE '%$search%'" : "";
$res = $conn->query("SELECT * FROM member $where ORDER BY m_id DESC");
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการข้อมูลลูกค้า - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background: #F8FAFC; font-family: system-ui; }
        .card-custom { border-radius: 16px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .search-input { border-radius: 50px 0 0 50px; border: 1px solid #E2E8F0; padding-left: 20px; }
        .search-btn { border-radius: 0 50px 50px 0; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold mb-0">จัดการลูกค้า</h2>
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fa fa-user-plus me-2"></i> เพิ่มลูกค้าใหม่
                </button>
            </div>

        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 mb-4 shadow-sm" role="alert">
                <?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ค้นหา -->
        <div class="card card-custom p-3 mb-4">
            <form method="get" class="d-flex">
                <div class="input-group">
                    <input type="text" name="search" class="form-control search-input" placeholder="ค้นหาชื่อ, อีเมล, เบอร์โทร..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-primary search-btn px-4" type="submit"><i class="fa fa-search"></i></button>
                </div>
                <?php if($search): ?><a href="manage_members.php" class="btn btn-link text-muted ms-2">ล้าง</a><?php endif; ?>
            </form>
        </div>

        <div class="card card-custom p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light text-muted small">
                        <tr><th>ชื่อ-นามสกุล</th><th>การติดต่อ</th><th>สถานะสมาชิก</th><th>สมัครเมื่อ</th><th>จัดการ</th></tr>
                    </thead>
                    <tbody>
                        <?php 
                        // อ่านข้อมูล JSON มาเตรียมไว้
                        $json_file = __DIR__ . '/../config/monthly_data.json';
                        $monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];

                        while($row = $res->fetch_assoc()): 
                            $m_id = $row['m_id'];
                            $has_quota = (isset($monthly_data[$m_id]) && isset($monthly_data[$m_id]['quota']));
                            $quota_left = $has_quota ? $monthly_data[$m_id]['quota'] : 0;
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($row['m_name'].' '.$row['m_lastname']) ?></div>
                                <div class="small text-muted"><?= $row['m_id'] ?></div>
                            </td>
                            <td>
                                <div class="small"><i class="fa fa-envelope me-1 text-muted"></i> <?= htmlspecialchars($row['m_mail']) ?></div>
                                <div class="small"><i class="fa fa-phone me-1 text-muted"></i> <?= htmlspecialchars($row['m_tel']) ?></div>
                            </td>
                            <td>
                                <?php if($has_quota): ?>
                                    <div class="badge bg-warning text-dark border-0 rounded-pill px-3 py-2 shadow-sm">
                                        <i class="fa fa-star me-1 text-primary"></i> <b><?= $quota_left ?></b> ชิ้นคงเหลือ
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border rounded-pill px-3 py-2">ลูกค้าทั่วไป</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small fw-bold text-dark"><?= date('d/m/Y', strtotime($row['m_created_at'] ?: 'now')) ?></div>
                                <div class="smaller text-muted" style="font-size: 0.7rem;"><?= date('H:i', strtotime($row['m_created_at'] ?: 'now')) ?> น.</div>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-info" onclick="viewHistory('<?= $m_id ?>', '<?= htmlspecialchars($row['m_name'].' '.$row['m_lastname']) ?>')" title="ดูประวัติการซัก"><i class="fa fa-list"></i></button>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editMember(<?= htmlspecialchars(json_encode($row)) ?>)" data-bs-toggle="modal" data-bs-target="#editModal"><i class="fa fa-edit"></i></button>
                                    <a href="manage_orders.php?m_id=<?= $row['m_id'] ?>" class="btn btn-sm btn-outline-success"><i class="fa fa-receipt"></i></a>
                                    <a href="?delete=<?= $row['m_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('ยืนยันการลบ?')"><i class="fa fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal เพิ่มลูกค้า -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0"><h5 class="modal-title fw-bold">เพิ่มลูกค้าใหม่</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label small fw-bold">ชื่อ</label><input type="text" name="name" class="form-control rounded-pill" required></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">นามสกุล</label><input type="text" name="lastname" class="form-control rounded-pill" required></div>
                        <div class="col-12"><label class="form-label small fw-bold">อีเมล</label><input type="email" name="mail" class="form-control rounded-pill" required></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">เบอร์โทร</label><input type="text" name="tel" class="form-control rounded-pill" maxlength="10"></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">รหัสผ่าน</label><input type="text" name="password" class="form-control rounded-pill" value="1234" required></div>
                        <div class="col-12"><label class="form-label small fw-bold">ที่อยู่</label><textarea name="address" class="form-control rounded-4" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer border-0"><button type="submit" name="add_member" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">บันทึกข้อมูล</button></div>
            </form>
        </div>
    </div>

    <!-- Modal แก้ไขลูกค้า -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" class="modal-content border-0 rounded-4 shadow">
                <input type="hidden" name="m_id" id="edit_id">
                <div class="modal-header border-0"><h5 class="modal-title fw-bold">แก้ไขข้อมูลลูกค้า</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label small fw-bold">ชื่อ</label><input type="text" name="name" id="edit_name" class="form-control rounded-pill" required></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">นามสกุล</label><input type="text" name="lastname" id="edit_last" class="form-control rounded-pill" required></div>
                        <div class="col-12"><label class="form-label small fw-bold">อีเมล</label><input type="email" name="mail" id="edit_mail" class="form-control rounded-pill" required></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">เบอร์โทร</label><input type="text" name="tel" id="edit_tel" class="form-control rounded-pill"></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">รหัสผ่าน</label><input type="text" name="password" id="edit_pass" class="form-control rounded-pill" required></div>
                        <div class="col-12"><label class="form-label small fw-bold">ที่อยู่</label><textarea name="address" id="edit_addr" class="form-control rounded-4" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer border-0"><button type="submit" name="edit_member" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">บันทึกการแก้ไข</button></div>
            </form>
        </div>
    </div>

    <!-- Modal ประวัติการซัก -->
    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0 bg-light rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fa fa-history me-2 text-primary"></i>ประวัติการซัก: <span id="hist_m_name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-white sticky-top shadow-sm small fw-bold">
                                <tr><th class="ps-4">วันที่</th><th>เลขออเดอร์</th><th>สถานะ</th><th>ยอดรวม</th><th>บันทึก</th></tr>
                            </thead>
                            <tbody id="history_body">
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-0 small text-muted">แสดงประวัติออเดอร์ทั้งหมดของลูกค้า</div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const histModal = new bootstrap.Modal(document.getElementById('historyModal'));

        function viewHistory(m_id, name) {
            document.getElementById('hist_m_name').innerText = name;
            document.getElementById('history_body').innerHTML = '<tr><td colspan="5" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>';
            histModal.show();

            fetch('get_member_history.php?m_id=' + m_id)
                .then(res => res.json())
                .then(data => {
                    let html = '';
                    if(data.length === 0) {
                        html = '<tr><td colspan="5" class="text-center py-5 text-muted">ยังไม่มีประวัติการสั่งซื้อ</td></tr>';
                    } else {
                        data.forEach(ord => {
                            let s_text = ['รอรับผ้า','รอชำระ','กำลังซัก','เตรียมส่ง','สำเร็จ'][ord.order_status] || 'ไม่ระบุ';
                            let s_color = ['dark','warning','info','primary','success'][ord.order_status] || 'secondary';
                            html += `
                                <tr>
                                    <td class="ps-4 small">
                                        <div class="fw-bold text-dark">${ord.date_fmt}</div>
                                        <div class="smaller text-muted">${ord.time_fmt} น.</div>
                                    </td>
                                    <td class="small fw-bold text-primary">${ord.order_id}</td>
                                    <td><span class="badge bg-${s_color} bg-opacity-10 text-${s_color} border border-${s_color} border-opacity-25 rounded-pill small">${s_text}</span></td>
                                    <td class="fw-bold text-dark">฿${parseFloat(ord.order_sum).toLocaleString()}</td>
                                    <td class="small text-muted" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${ord.order_note}">${ord.order_note || '-'}</td>
                                </tr>
                            `;
                        });
                    }
                    document.getElementById('history_body').innerHTML = html;
                });
        }

        function editMember(data) {
            document.getElementById('edit_id').value = data.m_id;
            document.getElementById('edit_name').value = data.m_name;
            document.getElementById('edit_last').value = data.m_lastname;
            document.getElementById('edit_mail').value = data.m_mail;
            document.getElementById('edit_tel').value = data.m_tel;
            document.getElementById('edit_pass').value = data.m_password;
            document.getElementById('edit_addr').value = data.m_address;
        }
    </script>
</body>
</html>