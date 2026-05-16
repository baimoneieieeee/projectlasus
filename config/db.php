<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "laundry_system";

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลไม่ได้: " . $conn->connect_error);
}
