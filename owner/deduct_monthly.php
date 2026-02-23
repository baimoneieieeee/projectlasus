<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

if (isset($_POST['use_quota'])) {
    $order_id = $_POST['order_id'];
    $m_id = $_POST['m_id'];
    
    // คำนวณจำนวนชิ้นทั้งหมดในออเดอร์นี้
    $sql_items = "SELECT SUM(item_amount) as total FROM item WHERE order_id = '$order_id'";
    $res = $conn->query($sql_items);
    $total_items = (int)$res->fetch_assoc()['total'];

    // อ่านข้อมูล JSON
    $json_file = __DIR__ . '/../config/monthly_data.json';
    $monthly_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];

    if (isset($monthly_data[$m_id]) && $monthly_data[$m_id]['quota'] >= $total_items) {
        // หักโควตา
        $monthly_data[$m_id]['quota'] -= $total_items;
        $monthly_data[$m_id]['updated_at'] = date("Y-m-d H:i:s");
        file_put_contents($json_file, json_encode($monthly_data, JSON_PRETTY_PRINT));

        // อัปเดตสถานะออเดอร์เป็น "จ่ายเงินแล้ว" และยอดเป็น 0
        $conn->query("UPDATE orders SET order_status = 3, order_sum = 0, order_note = 'จ่ายด้วยโควตารายเดือน ($total_items ชิ้น)' WHERE order_id = '$order_id'");
        
        header("Location: order_process.php?id=$order_id");
        exit;
    } else {
        echo "<script>alert('โควตาไม่เพียงพอ! (ต้องการ $total_items ชิ้น)'); window.history.back();</script>";
    }
}
?>
