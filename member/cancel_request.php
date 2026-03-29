<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit;
}

if (isset($_GET['id'])) {
    $rq_id = $conn->real_escape_string($_GET['id']);
    $m_id = $_SESSION['user']['m_id'];

    // ลบเฉพาะคำขอที่เป็นของตัวเองและยังไม่ได้ถูกยืนยัน (status 0)
    $sql = "DELETE FROM request WHERE rq_id = '$rq_id' AND m_id = '$m_id' AND rq_status = 0";
    if ($conn->query($sql)) {
        header("Location: home.php?msg=cancelled");
    } else {
        header("Location: home.php?error=fail");
    }
}
?>