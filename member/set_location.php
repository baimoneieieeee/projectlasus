<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit;
}

$user = $_SESSION['user'];
$m_id = $user['m_id'];

// ดึงข้อมูลชื่อร้านค้า พิกัด และนโยบายค่าส่ง
$shop_info = $conn->query("SELECT * FROM shop LIMIT 1")->fetch_assoc();
$shop_name = $shop_info['shop_name1'] ?? 'Laundry System';
$shop_lat = (float)($shop_info['shop_latitude'] ?? 13.7563);
$shop_lng = (float)($shop_info['shop_longitude'] ?? 100.5018);

// นโยบายค่าส่ง (ดึงค่า Dynamic จาก DB)
$d1 = (int)($shop_info['shop_dist_1'] ?? 10);
$d2 = (int)($shop_info['shop_dist_2'] ?? 20);
$f2 = (int)($shop_info['shop_rate'] ?? 30);
$f3 = (int)($shop_info['shop_rate_far'] ?? 0);

if (isset($_POST['set_location'])) {
    $lat = $conn->real_escape_string($_POST['latitude']);
    $lng = $conn->real_escape_string($_POST['longitude']);
    $sql = "UPDATE member SET m_latitude = '$lat', m_longitude = '$lng' WHERE m_id = '$m_id'";
    if ($conn->query($sql)) {
        $_SESSION['user']['m_latitude'] = $lat;
        $_SESSION['user']['m_longitude'] = $lng;
        header("Location: home.php");
        exit;
    }
}

$default_lat = (float)($user['m_latitude'] && $user['m_latitude'] != 0 ? $user['m_latitude'] : $shop_lat);
$default_lng = (float)($user['m_longitude'] && $user['m_longitude'] != 0 ? $user['m_longitude'] : $shop_lng);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ปักหมุดที่อยู่ - <?= htmlspecialchars($shop_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root { --primary: #3B82F6; --bg-light: #F8FAFC; }
        body { background: var(--bg-light); font-family: 'Sarabun', system-ui, sans-serif; }
        .navbar { background: white; border-bottom: 1px solid #E2E8F0; }
        .card-custom { border-radius: 24px; border: none; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); overflow: hidden; }
        #map { height: 450px; width: 100%; z-index: 1; }
        .map-container { position: relative; border-radius: 16px; overflow: hidden; border: 1px solid #E2E8F0; }
        .distance-badge { position: absolute; top: 10px; left: 10px; z-index: 1000; background: rgba(255, 255, 255, 0.95); padding: 8px 15px; border-radius: 999px; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: 2px solid var(--primary); color: #1E40AF; display: flex; align-items: center; min-width: 250px; }
        .btn-locate { position: absolute; top: 10px; right: 10px; z-index: 1000; background: white; border: none; box-shadow: 0 2px 5px rgba(0,0,0,0.2); width: 40px; height: 40px; border-radius: 8px; color: var(--primary); transition: 0.2s; }
        .btn-locate:hover { background: var(--primary); color: white; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center fw-bold text-primary" href="home.php">
                <?php if(file_exists('../owner/logo.png')): ?><img src="../owner/logo.png?v=<?= time() ?>" class="rounded-circle" style="width: 45px; height: 45px; object-fit: cover; margin-right: 12px; border: 2px solid #3B82F6;"><?php endif; ?>
                <span class="fs-5"><?= htmlspecialchars($shop_name) ?></span>
            </a>
            <div class="ms-auto"><a href="home.php" class="btn btn-light rounded-pill px-4 btn-sm fw-bold">กลับ</a></div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="card card-custom shadow-lg p-0">
                    <div class="p-4 bg-white">
                        <h4 class="fw-bold mb-1">📍 ปักหมุดที่อยู่รับ-ส่งผ้า</h4>
                        <div class="map-container mb-3">
                            <div class="distance-badge" id="distance-info"><i class="fa fa-route me-2"></i> คำนวณระยะทาง...</div>
                            <button type="button" class="btn-locate" onclick="getMyLocation()"><i class="fa fa-crosshairs"></i></button>
                            <div id="map"></div>
                        </div>

                        <!-- ตารางเรทราคาที่ดึงจาก DB -->
                        <div class="p-3 bg-light rounded-4 border shadow-sm">
                            <h6 class="fw-bold mb-2 small text-primary"><i class="fa fa-info-circle me-2"></i>นโยบายค่าจัดส่งตามระยะทาง</h6>
                            <div class="row g-2 text-center">
                                <div class="col-4">
                                    <div class="p-2 bg-white rounded-3 shadow-sm border-top border-success border-3">
                                        <div class="small fw-bold text-success mb-1">ระยะใกล้</div>
                                        <div class="smaller text-muted" style="font-size: 0.65rem;">0-<?= $d1 ?> กม.</div>
                                        <div class="fw-bold h6 mb-0 text-success">ฟรี</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 bg-white rounded-3 shadow-sm border-top border-primary border-3">
                                        <div class="small fw-bold text-primary mb-1">ระยะกลาง</div>
                                        <div class="smaller text-muted" style="font-size: 0.65rem;"><?= $d1 ?>-<?= $d2 ?> กม.</div>
                                        <div class="fw-bold h6 mb-0 text-primary"><?= $f2 ?>.-</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 bg-white rounded-3 shadow-sm border-top border-danger border-3">
                                        <div class="small fw-bold text-danger mb-1">ระยะไกล</div>
                                        <div class="smaller text-muted" style="font-size: 0.65rem;">> <?= $d2 ?> กม.</div>
                                        <div class="fw-bold h6 mb-0 text-danger">ตามตกลง</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ส่วนข้อมูลติดต่อร้านค้า -->
                        <div class="mt-4 p-3 bg-white rounded-4 border shadow-sm">
                            <h6 class="fw-bold mb-3 text-dark"><i class="fa fa-store me-2 text-primary"></i>ข้อมูลติดต่อร้านค้า</h6>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3">
                                            <i class="fa fa-phone text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="smaller text-muted">เบอร์โทรศัพท์</div>
                                            <div class="fw-bold small"><?= htmlspecialchars($shop_info['shop_tel'] ?? '-') ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3">
                                            <i class="fa fa-envelope text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="smaller text-muted">อีเมล</div>
                                            <div class="fw-bold small"><?= htmlspecialchars($shop_info['shop_mail'] ?? '-') ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex align-items-start">
                                        <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3">
                                            <i class="fa fa-map-marker-alt text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="smaller text-muted">ที่อยู่ร้านค้า</div>
                                            <div class="small"><?= nl2br(htmlspecialchars($shop_info['shop_address'] ?? '-')) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 border-top bg-white">
                        <form method="post">
                            <input type="hidden" name="latitude" id="lat" value="<?= $default_lat ?>"><input type="hidden" name="longitude" id="lng" value="<?= $default_lng ?>">
                            <div class="row align-items-center">
                                <div class="col-md-7 mb-3 mb-md-0"><div class="text-muted small"><i class="fa fa-user me-2"></i>ตำแหน่งคุณ: <strong id="display-lat"><?= $default_lat ?></strong>, <strong id="display-lng"><?= $default_lng ?></strong></div></div>
                                <div class="col-md-5"><button type="submit" name="set_location" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold shadow-sm">บันทึกตำแหน่งนี้</button></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var sLat = <?= $shop_lat ?>, sLng = <?= $shop_lng ?>;
        var d1 = <?= $d1 ?>, d2 = <?= $d2 ?>, f2 = <?= $f2 ?>, f3 = <?= $f3 ?>;
        
        var map = L.map('map', { zoomControl: false }).setView([<?= $default_lat ?>, <?= $default_lng ?>], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // หมุดตำแหน่งร้านค้า (สีแดง/ส้ม)
        var shopIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });
        L.marker([sLat, sLng], {icon: shopIcon}).addTo(map).bindPopup("<b>ตำแหน่งร้าน: " + <?= json_encode($shop_name) ?> + "</b>").openPopup();

        // หมุดตำแหน่งของคุณ (สีน้ำเงิน - เลื่อนได้)
        var marker = L.marker([<?= $default_lat ?>, <?= $default_lng ?>], { draggable: true }).addTo(map);

        function calcDist(la1, lo1, la2, lo2) {
            var R = 6371;
            var dLa = (la2 - la1) * Math.PI / 180, dLo = (lo2 - lo1) * Math.PI / 180;
            var a = Math.sin(dLa/2) * Math.sin(dLa/2) + Math.cos(la1*Math.PI/180)*Math.cos(la2*Math.PI/180)*Math.sin(dLo/2)*Math.sin(dLo/2);
            return R * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)));
        }

        function updateUI(la, lo) {
            document.getElementById('lat').value = la; document.getElementById('lng').value = lo;
            document.getElementById('display-lat').innerText = la.toFixed(6); document.getElementById('display-lng').innerText = lo.toFixed(6);
       
            var dist = calcDist(sLat, sLng, la, lo);
            var fT = "ฟรี", bC = "bg-primary", distName = "ระยะใกล้";
            
            if (dist > d2) { fT = "ติดต่อร้านค้า"; bC = "bg-danger"; distName = "ระยะไกล"; }
            else if (dist > d1) { fT = f2 + " บาท"; bC = "bg-warning text-dark"; distName = "ระยะกลาง"; }


            document.getElementById('distance-info').innerHTML = 
                '<i class="fa fa-route me-2"></i> ' + distName + ': ' + dist.toFixed(2) + ' กม. <span class="ms-2 badge rounded-pill ' + bC + '">ค่าส่ง: ' + fT + '</span>';
        }

        marker.on('drag', function(e) { updateUI(e.target.getLatLng().lat, e.target.getLatLng().lng); });
        map.on('click', function(e) { marker.setLatLng(e.latlng); updateUI(e.latlng.lat, e.latlng.lng); });
        function getMyLocation() { navigator.geolocation.getCurrentPosition(function(p) { map.setView([p.coords.latitude, p.coords.longitude], 17); marker.setLatLng([p.coords.latitude, p.coords.longitude]); updateUI(p.coords.latitude, p.coords.longitude); }); }
        updateUI(<?= $default_lat ?>, <?= $default_lng ?>);
    </script>
</body>
</html>