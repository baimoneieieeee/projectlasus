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

    /* 📱 Mobile Top Navbar (Show only on small screens) */
    .mobile-top-nav {
        display: none;
        position: fixed; top: 0; left: 0; right: 0;
        height: 60px; background: white;
        border-bottom: 1px solid #e2e8f0;
        z-index: 999; align-items: center;
        padding: 0 15px; justify-content: space-between;
    }

    .sidebar { 
        width: 280px; height: 100vh; background: var(--sidebar-bg); 
        position: fixed; border-right: 1px solid #e2e8f0; 
        padding: 30px 20px; z-index: 1001; transition: 0.3s;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

    .nav-link { 
        color: var(--sidebar-color); padding: 12px 18px; 
        border-radius: 12px; margin-bottom: 5px; 
        display: flex; align-items: center; font-weight: 500;
        transition: 0.2s; border: 1px solid transparent;
        text-decoration: none;
    }

    .nav-link i { font-size: 1.2rem; width: 28px; }
    .nav-link:hover { background: #f1f5f9; color: #1e293b; }
    .nav-link.active { background: var(--sidebar-active-bg); color: var(--sidebar-active-color); font-weight: 700; border: 1px solid #dbeafe; }
    .nav-link.active i { color: var(--sidebar-active-color); }

    .main-content { margin-left: 280px; padding: 40px; min-height: 100vh; transition: 0.3s; }

    .shop-brand {
        display: flex; align-items: center; padding: 0 10px 30px 10px;
        border-bottom: 1px solid #f1f5f9; margin-bottom: 25px;
    }

    .shop-logo-circle {
        width: 40px; height: 40px; background: #2563eb; 
        border-radius: 10px; display: flex; align-items: center; 
        justify-content: center; color: white; margin-right: 12px;
    }

    /* Overlay for mobile */
    .sidebar-overlay {
        display: none;
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5); z-index: 1000;
    }

    @media (max-width: 991.98px) {
        .mobile-top-nav { display: flex; }
        .sidebar { transform: translateX(-100%); width: 260px; }
        .sidebar.show { transform: translateX(0); }
        .sidebar-overlay.show { display: block; }
        .main-content { margin-left: 0; padding: 80px 15px 30px 15px; }
    }
</style>

<!-- 📱 Mobile Navbar -->
<div class="mobile-top-nav">
    <button class="btn btn-light border shadow-sm rounded-3" onclick="toggleSidebar()">
        <i class="fa fa-bars text-primary"></i>
    </button>
    <div class="fw-bold text-dark small"><?= htmlspecialchars($shop_name) ?></div>
    <div class="shop-logo-circle ms-2" style="width: 32px; height: 32px;"><i class="fa fa-shirt" style="font-size: 0.8rem;"></i></div>
</div>

<!-- Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="sidebar" id="mainSidebar">
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
        <a class="nav-link <?= ($current_page == 'manage_monthly_plans.php') ? 'active' : '' ?>" href="manage_monthly_plans.php">
            <i class="fa-solid fa-box-archive"></i> จัดการแพ็กเกจ
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

<script>
    function toggleSidebar() {
        document.getElementById('mainSidebar').classList.toggle('show');
        document.getElementById('sidebarOverlay').classList.toggle('show');
    }
</script>