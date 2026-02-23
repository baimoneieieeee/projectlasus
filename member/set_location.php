<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit;
}

if (isset($_POST['set_location'])) {
    $lat = $_POST['latitude'];
    $lng = $_POST['longitude'];
    $m_id = $_SESSION['user']['m_id'];

    $sql = "UPDATE member SET m_latitude = '$lat', m_longitude = '$lng' WHERE m_id = '$m_id'";
    if ($conn->query($sql)) {
        $_SESSION['user']['m_latitude'] = $lat;
        $_SESSION['user']['m_longitude'] = $lng;
        header("Location: home.php");
        exit;
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ตั้งค่าตำแหน่ง - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map { height: 400px; border-radius: 12px; margin-bottom: 20px; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body p-4 text-center">
                        <h2 class="h4 fw-bold mb-4">📍 ตั้งค่าตำแหน่งของคุณ</h2>
                        <p class="text-muted">กรุณาเลือกตำแหน่งที่ต้องการให้ร้านไปรับผ้าบนแผนที่</p>
                        <div id="map"></div>
                        <form method="post">
                            <input type="hidden" name="latitude" id="lat" value="13.7563">
                            <input type="hidden" name="longitude" id="lng" value="100.5018">
                            <button type="submit" name="set_location" class="btn btn-primary btn-lg w-100 rounded-3">บันทึกตำแหน่ง</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map').setView([13.7563, 100.5018], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        var marker = L.marker([13.7563, 100.5018], {draggable: true}).addTo(map);
        marker.on('dragend', function(e) {
            var pos = marker.getLatLng();
            document.getElementById('lat').value = pos.lat;
            document.getElementById('lng').value = pos.lng;
        });
        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            document.getElementById('lat').value = e.latlng.lat;
            document.getElementById('lng').value = e.latlng.lng;
        });
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                var lat = position.coords.latitude;
                var lng = position.coords.longitude;
                map.setView([lat, lng], 15);
                marker.setLatLng([lat, lng]);
                document.getElementById('lat').value = lat;
                document.getElementById('lng').value = lng;
            });
        }
    </script>
</body>
</html>
