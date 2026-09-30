<?php
// Customer account sidebar (same look as the admin sidebar)
// Set $userNavActive before including: 'bookings' | 'rooms'
$navUser = current_user();
$userNavActive = $userNavActive ?? '';
$upStmt = db()->prepare("SELECT COUNT(*) FROM bookings WHERE user_id=? AND status IN ('pending','confirmed','checked_in') AND check_out >= CURDATE()");
$upStmt->execute([$navUser['id']]);
$upcomingCount = (int)$upStmt->fetchColumn();
$navWords = array_slice(preg_split('/\s+/', trim($navUser['name'])), 0, 2);
$navInitials = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), $navWords)));
?>
<!-- Mobile Sidebar Toggle -->
<button class="admin-mobile-toggle" aria-label="Open menu">
    <i class="fas fa-bars"></i>
</button>
<div class="admin-sidebar-overlay"></div>

<!-- Customer Sidebar -->
<div class="admin-sidebar user-sidebar">
    <a href="/hotel/index.php" class="admin-sidebar-header" style="text-decoration:none;color:inherit">
        <i class="fas fa-hotel"></i>
        <span>Stay<b style="color:var(--accent)">Ease</b></span>
    </a>
    <a href="/hotel/profile/personal" class="user-card" title="My profile" style="text-decoration:none">
        <span class="user-avatar"><?php if (!empty($navUser['avatar'])): ?><img src="<?=e($navUser['avatar'])?>" alt=""><?php else: ?><?=e($navInitials ?: '?')?><?php endif; ?></span>
        <div class="user-meta">
            <strong><?=e($navUser['name'])?></strong>
            <small><?=e($navUser['email'])?></small>
        </div>
    </a>
    <nav class="admin-sidebar-nav">
        <a href="/hotel/my_bookings.php" class="admin-nav-item <?=$userNavActive === 'bookings' ? 'active' : ''?>">
            <i class="fas fa-calendar-check"></i>
            <span>My Bookings</span>
            <?php if($upcomingCount > 0): ?>
            <span class="admin-badge" title="Upcoming bookings" style="background:#28a745"><?=$upcomingCount?></span>
            <?php endif; ?>
        </a>
        <a href="/hotel/rooms.php" class="admin-nav-item <?=$userNavActive === 'rooms' ? 'active' : ''?>">
            <i class="fas fa-bed"></i>
            <span>Book a Room</span>
        </a>
        <a href="/hotel/profile/personal" class="admin-nav-item <?=$userNavActive === 'profile-personal' ? 'active' : ''?>">
            <i class="fas fa-user"></i>
            <span>Personal details</span>
        </a>
        <a href="/hotel/profile/contact" class="admin-nav-item <?=$userNavActive === 'profile-contact' ? 'active' : ''?>">
            <i class="fas fa-address-book"></i>
            <span>Contact details</span>
        </a>
        <a href="/hotel/profile/password" class="admin-nav-item <?=$userNavActive === 'profile-password' ? 'active' : ''?>">
            <i class="fas fa-lock"></i>
            <span>Password</span>
        </a>
        <a href="/hotel/profile/account" class="admin-nav-item <?=$userNavActive === 'profile-account' ? 'active' : ''?>">
            <i class="fas fa-id-card"></i>
            <span>Account</span>
        </a>
        <a href="/hotel/index.php" class="admin-nav-item">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <?php if($navUser['role'] === 'admin'): ?>
        <a href="/hotel/admin/index.php" class="admin-nav-item">
            <i class="fas fa-user-shield"></i>
            <span>Admin Panel</span>
        </a>
        <?php endif; ?>
    </nav>
    <div class="admin-sidebar-footer">
        <a href="/hotel/logout.php" class="admin-nav-item logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- Desktop Toggle Button -->
<button class="admin-sidebar-toggle" title="Toggle Sidebar">
    <i class="fas fa-chevron-right"></i>
</button>

<style>
.user-card{display:flex;align-items:center;gap:10px;margin:14px 14px 6px;padding:12px;border-radius:12px;background:rgba(255,255,255,.06)}
.user-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;display:block}
.user-avatar{overflow:hidden;width:38px;height:38px;border-radius:50%;background:var(--accent);color:#fff;font-weight:700;font-size:13px;display:inline-flex;align-items:center;justify-content:center;flex:none}
.user-meta{min-width:0;display:flex;flex-direction:column;color:#fff}
.user-meta strong{font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-meta small{font-size:11px;color:rgba(255,255,255,.6);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.admin-sidebar.collapsed .user-card{padding:6px;justify-content:center;margin:12px 8px 6px}
.admin-sidebar.collapsed .user-meta{display:none}
.user-nav-label{padding:14px 20px 6px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.4)}
.admin-sidebar.collapsed .user-nav-label{padding:10px 0 4px;text-align:center}
.admin-sidebar.collapsed .user-nav-label span{display:none}
.admin-sidebar.collapsed .user-nav-label:after{content:"";display:block;height:1px;background:rgba(255,255,255,.1);margin:0 16px}
.admin-sidebar-nav{overflow-y:auto}
@media (max-width:768px){.admin-content-wrapper .page{padding-top:76px}}
</style>

<!-- Page Content Wrapper -->
<div class="admin-content-wrapper">
