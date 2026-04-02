<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

$shop_id = $_SESSION['user']['shop_id'];

if (isset($_POST['update_shop'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $address = $conn->real_escape_string($_POST['address']);
    $tel = $conn->real_escape_string($_POST['tel']);
    $mail = $conn->real_escape_string($_POST['mail']);
    $pass = $conn->real_escape_string($_POST['password']);
    $lat = $conn->real_escape_string($_POST['lat']);
    $lng = $conn->real_escape_string($_POST['lng']);
    
    // ตั้งค่าค่าส่งและระยะทาง (ฉบับย่อ)
    $dist1 = (int)$_POST['dist1'];
    $dist2 = (int)$_POST['dist2'];
    $rate = (int)$_POST['rate'];
    $rate_far = (int)$_POST['rate_far'];

    if (!empty($_FILES['logo']['tmp_name'])) { move_uploaded_file($_FILES['logo']['tmp_name'], 'logo.png'); }
    if (!empty($_FILES['qrcode']['tmp_name'])) {
        $qrcode = addslashes(file_get_contents($_FILES['qrcode']['tmp_name']));
        $conn->query("UPDATE shop SET shop_QRcode = '$qrcode' WHERE shop_id = '$shop_id'");
    }

    $sql = "UPDATE shop SET 
            shop_name1 = '$name', shop_address = '$address', shop_tel = '$tel', shop_mail = '$mail', 
            shop_password = '$pass', shop_latitude = '$lat', shop_longitude = '$lng',
            shop_dist_1 = '$dist1', shop_dist_2 = '$dist2', shop_rate = '$rate', shop_rate_far = '$rate_far'
            WHERE shop_id = '$shop_id'";
    
    if ($conn->query($sql)) {
        $_SESSION['user']['shop_name1'] = $name;
        $success = "อัปเดตข้อมูลร้านค้าเรียบร้อยแล้ว";
    }
}

$shop = $conn->query("SELECT * FROM shop WHERE shop_id = '$shop_id'")->fetch_assoc();
$default_lat = $shop['shop_latitude'] ?: 13.7563;
$default_lng = $shop['shop_longitude'] ?: 100.5018;
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ตั้งค่าร้านค้า - Laundry Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { background: #F8FAFC; font-family: system-ui; }
        .card-custom { border-radius: 16px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        #map { height: 350px; border-radius: 12px; border: 1px solid #E2E8F0; z-index: 1; }
        .main-content { margin-left: 260px; padding: 30px; }
        .fee-input { border: none; background: #F1F5F9; text-align: center; font-weight: bold; width: 70px; border-radius: 8px; padding: 4px; }
        @media (max-width: 991.98px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <h2 class="fw-bold mb-4">ตั้งค่าร้านค้า</h2>
        <?php if(isset($success)): ?>
            <div class="alert alert-success rounded-4 border-0 mb-4 shadow-sm" role="alert"><?= $success ?></div>
        <?php endif; ?>

        <div class="card card-custom p-4 mb-4">
            <form method="post" enctype="multipart/form-data">
                <div class="row g-4">
                    <div class="col-md-8"><label class="form-label small fw-bold">ชื่อร้านค้า</label><input type="text" name="name" class="form-control rounded-pill" value="<?= $shop['shop_name1'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label small fw-bold">เบอร์โทรศัพท์</label><input type="text" name="tel" class="form-control rounded-pill" value="<?= $shop['shop_tel'] ?>"></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">อีเมลเข้าใช้งาน</label><input type="email" name="mail" class="form-control rounded-pill" value="<?= $shop['shop_mail'] ?>" required></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">รหัสผ่าน</label><input type="text" name="password" class="form-control rounded-pill" value="<?= $shop['shop_password'] ?>" required></div>

                    <!-- นโยบายค่าส่งแบบย่อ -->
                    <div class="col-12">
                        <div class="p-4 bg-white border rounded-4 shadow-sm">
                            <h6 class="fw-bold mb-3 text-primary"><i class="fa fa-truck me-2"></i>นโยบายค่าจัดส่ง (2 ช่วง)</h6>
                            <div class="table-responsive">
                                <table class="table table-borderless align-middle mb-0 text-center">
                                    <thead class="text-muted small"><tr><th>ระดับระยะทาง</th><th>ระยะทางสูงสุด (กม.)</th><th>ค่าบริการ (บาท)</th></tr></thead>
                                    <tbody>
                                        <tr>
                                            <td class="fw-bold text-success">ระยะใกล้ (ส่งฟรี)</td>
                                            <td>0 ถึง <input type="number" name="dist1" class="fee-input" value="<?= $shop['shop_dist_1'] ?>"></td>
                                            <td class="text-success fw-bold">ฟรี</td>
                                        </tr>
                                        <tr class="border-top">
                                            <td class="fw-bold text-primary">ระยะปกติ (ราคามาตรฐาน)</td>
                                            <td>เกิน <span class="fw-bold text-primary"><?= $shop['shop_dist_1'] ?></span> กม. ขึ้นไป</td>
                                            <td><input type="number" name="rate" class="fee-input text-primary" value="<?= $shop['shop_rate'] ?>"></td>
                                        </tr>
                                        <!-- Hidden inputs for legacy variables to avoid DB errors or breaking code -->
                                        <input type="hidden" name="dist2" value="<?= $shop['shop_dist_2'] ?>">
                                        <input type="
                                        hidden" name="rate_far" value="<?= $shop['shop_rate_far'] ?>">

                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold mb-2">📍 ตำแหน่งร้านบนแผนที่</label>
                        <div id="map"></div>
                        <input type="hidden" name="lat" id="lat" value="<?= $default_lat ?>"><input type="hidden" name="lng" id="lng" value="<?= $default_lng ?>">
                    </div>

                    <div class="col-12"><label class="form-label small fw-bold">ที่อยู่ร้านค้า (ข้อความ)</label><textarea name="address" class="form-control rounded-4" rows="2"><?= $shop['shop_address'] ?></textarea></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">คิวอาร์โค้ด</label><input type="file" name="qrcode" class="form-control mb-2"><?php if($shop['shop_QRcode']): ?><img src="data:image/jpeg;base64,<?= base64_encode($shop['shop_QRcode']) ?>" class="img-thumbnail" style="max-height: 80px;"><?php endif; ?></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">โลโก้</label><input type="file" name="logo" class="form-control mb-2"><?php if(file_exists('logo.png')): ?><img src="logo.png?v=<?= time() ?>" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;"><?php endif; ?></div>
                </div>
                <div class="text-end mt-4"><button type="submit" name="update_shop" class="btn btn-primary rounded-pill px-5 fw-bold shadow">บันทึกข้อมูล</button></div>
            </form>
        </div>
    </div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map').setView([<?= $default_lat ?>, <?= $default_lng ?>], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        var marker = L.marker([<?= $default_lat ?>, <?= $default_lng ?>], { draggable: true }).addTo(map);
        marker.on('dragend', function() { document.getElementById('lat').value = marker.getLatLng().lat; document.getElementById('lng').value = marker.getLatLng().lng; });
        map.on('click', function(e) { marker.setLatLng(e.latlng); document.getElementById('lat').value = e.latlng.lat; document.getElementById('lng').value = e.latlng.lng; });
    </script>
</body>
</html>