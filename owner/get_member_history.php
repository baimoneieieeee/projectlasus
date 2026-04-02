<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    die(json_encode([]));
}

$m_id = $conn->real_escape_string($_GET['m_id'] ?? '');

if (empty($m_id)) {
    die(json_encode([]));
}

$sql = "SELECT order_id, order_date, order_status, order_sum, order_note 
        FROM orders 
        WHERE m_id = '$m_id' 
        ORDER BY order_date DESC";
$res = $conn->query($sql);

$history = [];
while ($row = $res->fetch_assoc()) {
    $row['date_fmt'] = date('d/m/Y', strtotime($row['order_date']));
    $row['time_fmt'] = date('H:i', strtotime($row['order_date']));
    $history[] = $row;
}

header('Content-Type: application/json');
echo json_encode($history);
?>