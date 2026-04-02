<?php
include 'config/db.php';
$res = $conn->query("SELECT service_id, service_name FROM service");
while ($row = $res->fetch_assoc()) {
    echo $row['service_id'] . ": " . $row['service_name'] . "\n";
}
?>