<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    .sidebar { width: 260px; height: 100vh; background: #fff; position: fixed; border-right: 1px solid #E2E8F0; padding: 20px; z-index: 1000; }
    .nav-link { color: #64748B; padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; transition: 0.2s; }
    .nav-link.active { background: #EFF6FF; color: #2563EB; font-weight: 600; }
    .nav-link:hover:not(.active) { background: #F1F5F9; color: #1E293B; }
    .main-content { margin-left: 260px; padding: 30px; min-height: 100vh; }
    @media (max-width: 991.98px) {
        .sidebar { width: 100%; height: auto; position: relative; border-right: none; border-bottom: 1px solid #E2E8F0; }
        .main-content { margin-left: 0; }
    }
</style>

<div class="sidebar">
    <h4 class="fw-bold text-primary mb-4 px-3">🧺 Shop Admin</h4>
    <nav class="nav flex-column">
        <a class="nav-link <?= ($current_page == 'owner_home.php') ? 'active' : '' ?>" href="owner_home.php">
            <i class="fa fa-home me-2"></i> หน้าแรก
        </a>
        <a class="nav-link <?= ($current_page == 'manage_requests.php') ? 'active' : '' ?>" href="manage_requests.php">
            <i class="fa fa-bell me-2"></i> คำขอรับผ้า
        </a>
        <a class="nav-link <?= ($current_page == 'manage_orders.php') ? 'active' : '' ?>" href="manage_orders.php">
            <i class="fa fa-file-invoice me-2"></i> รายการสั่งซื้อ
        </a>
        <a class="nav-link <?= ($current_page == 'reports.php') ? 'active' : '' ?>" href="reports.php">
            <i class="fa fa-chart-bar me-2"></i> รายงาน
        </a>
        <a class="nav-link <?= ($current_page == 'manage_services.php' || $current_page == 'manage_service_types.php') ? 'active' : '' ?>" href="manage_services.php">
            <i class="fa fa-tags me-2"></i> จัดการบริการ
        </a>
        <a class="nav-link <?= ($current_page == 'manage_members.php') ? 'active' : '' ?>" href="manage_members.php">
            <i class="fa fa-users me-2"></i> ข้อมูลลูกค้า
        </a>
        <a class="nav-link <?= ($current_page == 'manage_monthly.php') ? 'active' : '' ?>" href="manage_monthly.php">
            <i class="fa fa-star me-2"></i> โควตารายเดือน
        </a>
        <a class="nav-link <?= ($current_page == 'manage_shop.php') ? 'active' : '' ?>" href="manage_shop.php">
            <i class="fa fa-cog me-2"></i> ตั้งค่าร้านค้า
        </a>
        <hr>
        <a class="nav-link text-danger" href="../logout.php">
            <i class="fa fa-sign-out-alt me-2"></i> ออกจากระบบ
        </a>
    </nav>
</div>
