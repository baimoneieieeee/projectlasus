<?php
include 'config/db.php';
$conn->set_charset("utf8mb4");

// ดึงรายการ item ที่ยังไม่มีชื่อผ้า
$sql = "SELECT i.item_id, o.order_note 
        FROM item i 
        JOIN orders o ON i.order_id = o.order_id 
        WHERE i.item_name IS NULL OR i.item_name = ''";
$res = $conn->query($sql);

$count = 0;
while($row = $res->fetch_assoc()) {
    $note = $row['order_note'];
    $id = $row['item_id'];
    
    // พยายามดึงชื่อจากรูปแบบ "เสื้อยืด (10)"
    preg_match_all('/([^,\[\]:]+)\s\((\d+)\)/', $note, $matches);
    
    if(!empty($matches[1])) {
        // ใช้ชื่อแรกที่เจอเป็นค่าพื้นฐานสำหรับแถวที่ยังว่าง (เนื่องจากเป็นข้อมูลเก่า)
        $first_name = trim($matches[1][0]);
        $conn->query("UPDATE item SET item_name = '$first_name' WHERE item_id = '$id'");
        $count++;
    }
}

echo "เติมข้อมูลชื่อผ้าย้อนหลังสำเร็จ $count รายการ!";
?>