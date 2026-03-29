<?php
$current_page = basename($_SERVER['PHP_SELF']);
include_once __DIR__ . '/../config/db.php';
$shop_info = $conn->query("SELECT shop_name1 FROM shop LIMIT 1")->fetch_assoc();
$shop_name = $shop_info['shop_name1'] ?? 'Laundry Admin';
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;600;700&display=swap');
    
    :root {
        --sidebar-bg: #ffffff;
        --sidebar-color: #64748b;
        --sidebar-active-bg: #eff6ff;
        --sidebar-active-color: #2563eb;
    }

    body { font-family: 'Chakra Petch', sans-serif; background: #f8fafc; }

    .sidebar { 
        width: 280px; height: 100vh; background: var(--sidebar-bg); 
        position: fixed; border-right: 1px solid #e2e8f0; 
        padding: 30px 20px; z-index: 1000; transition: 0.3s;
        overflow-y: auto; /* เพิ่มแถบเลื่อนอัตโนมัติ */
        display: flex;
        flex-direction: column;
    }

    /* ปรับแต่งแถบเลื่อนให้ดูสวยงาม (สำหรับ Chrome/Edge) */
    .sidebar::-webkit-scrollbar {
        width: 5px;
    }
    .sidebar::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 10px;
    }
    .sidebar::-webkit-scrollbar-track {
        background: transparent;
    }

    .nav-link { 
        color: var(--sidebar-color); padding: 12px 18px; 
        border-radius: 12px; margin-bottom: 5px; 
        display: flex; align-items: center; font-weight: 500;
        transition: 0.2s; border: 1px solid transparent;
        text-decoration: none; /* กันขีดเส้นใต้ */
    }

    .nav-link i { font-size: 1.2rem; width: 28px; transition: 0.2s; }

    .nav-link:hover { 
        background: #f1f5f9; color: #1e293b; 
    }

    .nav-link.active { 
        background: var(--sidebar-active-bg); 
        color: var(--sidebar-active-color); 
        font-weight: 700;
        border: 1px solid #dbeafe;
    }

    .nav-link.active i { color: var(--sidebar-active-color); transform: scale(1.1); }

    .main-content { margin-left: 280px; padding: 40px; min-height: 100vh; }

    .shop-brand {
        display: flex; align-items: center; padding: 0 10px 30px 10px;
        border-bottom: 1px solid #f1f5f9; margin-bottom: 25px;
    }

    .shop-logo-circle {
        width: 45px; height: 45px; background: #2563eb; 
        border-radius: 12px; display: flex; align-items: center; 
        justify-content: center; color: white; margin-right: 15px;
        box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
    }

    @media (max-width: 991.98px) {
        .sidebar { transform: translateX(-100%); }
        .main-content { margin-left: 0; padding: 20px; }
    }
</style>

<div class="sidebar">
    <div class="shop-brand">
        <div class="shop-logo-circle"><i class="fa fa-shirt"></i></div>
        <div>
            <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($shop_name) ?></h6>
            <p class="small text-muted mb-0">ผู้ดูแลระบบ</p>
        </div>
    </div>

    <nav class="nav flex-column">
        <a class="nav-link <?= ($current_page == 'owner_home.php') ? 'active' : '' ?>" href="owner_home.php">
            <i class="fa-solid fa-house-chimney"></i> หน้าแรก
        </a>
        <a class="nav-link <?= ($current_page == 'manage_requests.php') ? 'active' : '' ?>" href="manage_requests.php">
            <i class="fa-solid fa-bell"></i> คำขอรับผ้า
        </a>
        <a class="nav-link <?= ($current_page == 'manage_orders.php' || $current_page == 'order_process.php') ? 'active' : '' ?>" href="manage_orders.php">
            <i class="fa-solid fa-receipt"></i> รายการสั่งซื้อ
        </a>
        <a class="nav-link <?= ($current_page == 'manage_monthly.php') ? 'active' : '' ?>" href="manage_monthly.php">
            <i class="fa-solid fa-star"></i> จัดการรายเดือน
        </a>
        <a class="nav-link <?= ($current_page == 'quick_order.php') ? 'active' : '' ?>" href="quick_order.php">
            <i class="fa-solid fa-plus-circle"></i> เปิดบิลซักด่วน
        </a>
        <a class="nav-link <?= ($current_page == 'reports.php') ? 'active' : '' ?>" href="reports.php">
            <i class="fa-solid fa-chart-pie"></i> รายงานสรุปผล
        </a>
        <hr class="my-3 opacity-50">
        <a class="nav-link <?= ($current_page == 'manage_services.php') ? 'active' : '' ?>" href="manage_services.php">
            <i class="fa-solid fa-tags"></i> จัดการบริการ
        </a>
        <a class="nav-link <?= ($current_page == 'manage_members.php') ? 'active' : '' ?>" href="manage_members.php">
            <i class="fa-solid fa-users"></i> ข้อมูลลูกค้า
        </a>
        <a class="nav-link <?= ($current_page == 'manage_shop.php') ? 'active' : '' ?>" href="manage_shop.php">
            <i class="fa-solid fa-gears"></i> ตั้งค่าร้านค้า
        </a>
        <a class="nav-link text-warning" href="manage_data.php">
            <i class="fa-solid fa-database"></i> จัดการข้อมูลระบบ
        </a>
        
        <div style="margin-top: auto; padding-top: 50px;">
            <a class="nav-link text-danger" href="../logout.php" onclick="return confirm('ยืนยันออกจากระบบ?')">
                <i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ
            </a>
        </div>
    </nav>
</div>
