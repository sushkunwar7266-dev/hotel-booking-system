<?php
// Get pending booking count for notification badge
$pendingCount = get_pending_booking_count();
?>
<!-- Mobile Sidebar Toggle -->
<button class="admin-mobile-toggle">
    <i class="fas fa-bars"></i>
</button>
<div class="admin-sidebar-overlay"></div>

<!-- Admin Sidebar -->
<div class="admin-sidebar">
    <div class="admin-sidebar-header">
        <i class="fas fa-user-shield"></i>
        <span>Admin Panel</span>
    </div>
    <nav class="admin-sidebar-nav">
        <a href="index.php" class="admin-nav-item">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>
        <a href="bookings.php" class="admin-nav-item">
            <i class="fas fa-calendar-check"></i>
            <span>Bookings</span>
            <?php if($pendingCount > 0): ?>
            <span class="admin-badge"><?=$pendingCount?></span>
            <?php endif; ?>
        </a>
        <div class="admin-nav-group">
            <a href="rooms.php" class="admin-nav-item">
                <i class="fas fa-bed"></i>
                <span>Rooms</span>
            </a>
            <a href="add_room.php" class="admin-quick-add" title="Add New Room">
                <i class="fas fa-plus"></i>
            </a>
        </div>
        <div class="admin-nav-group">
            <a href="room_types.php" class="admin-nav-item">
                <i class="fas fa-layer-group"></i>
                <span>Room Types</span>
            </a>
            <a href="room_types.php" class="admin-quick-add" title="Add New Room Type">
                <i class="fas fa-plus"></i>
            </a>
        </div>
        <a href="customers.php" class="admin-nav-item">
            <i class="fas fa-users"></i>
            <span>Customers</span>
        </a>
        <a href="../index.php" class="admin-nav-item">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
    </nav>
    <div class="admin-sidebar-footer">
        <a href="../logout.php" class="admin-nav-item logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- Desktop Toggle Button (outside sidebar) -->
<button class="admin-sidebar-toggle" title="Toggle Sidebar">
    <i class="fas fa-chevron-right"></i>
</button>

<!-- Admin Content Wrapper -->
<div class="admin-content-wrapper">
