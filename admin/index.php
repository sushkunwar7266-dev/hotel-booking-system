<?php 
require_once '../config/config.php';
$u = require_admin();
$title = 'Admin Dashboard | ' . APP_NAME;

// Fetch statistics with prepared statements
$roomsStmt = db()->prepare("SELECT COUNT(*) FROM rooms");
$roomsStmt->execute();
$bookingsStmt = db()->prepare("SELECT COUNT(*) FROM bookings");
$bookingsStmt->execute();
$customersStmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role='customer'");
$customersStmt->execute();
$revenueStmt = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'");
$revenueStmt->execute();
$roomTypesStmt = db()->prepare("SELECT COUNT(*) FROM room_types");
$roomTypesStmt->execute();

// Get detailed booking stats
$pendingBookingsStmt = db()->prepare("SELECT COUNT(*) FROM bookings WHERE status='pending'");
$pendingBookingsStmt->execute();
$confirmedBookingsStmt = db()->prepare("SELECT COUNT(*) FROM bookings WHERE status='confirmed'");
$confirmedBookingsStmt->execute();
$todayBookingsStmt = db()->prepare("SELECT COUNT(*) FROM bookings WHERE DATE(check_in) = CURDATE()");
$todayBookingsStmt->execute();

// Get detailed room stats
$availableRoomsStmt = db()->prepare("SELECT COUNT(*) FROM rooms WHERE status='available'");
$availableRoomsStmt->execute();
$occupiedRoomsStmt = db()->prepare("SELECT COUNT(DISTINCT room_id) FROM bookings WHERE status IN ('confirmed','checked_in') AND check_in <= CURDATE() AND check_out >= CURDATE()");
$occupiedRoomsStmt->execute();

$stats = [
    'rooms' => (int)$roomsStmt->fetchColumn(),
    'bookings' => (int)$bookingsStmt->fetchColumn(),
    'customers' => (int)$customersStmt->fetchColumn(),
    'revenue' => (float)$revenueStmt->fetchColumn(),
    'room_types' => (int)$roomTypesStmt->fetchColumn(),
    'pending_bookings' => (int)$pendingBookingsStmt->fetchColumn(),
    'confirmed_bookings' => (int)$confirmedBookingsStmt->fetchColumn(),
    'today_bookings' => (int)$todayBookingsStmt->fetchColumn(),
    'available_rooms' => (int)$availableRoomsStmt->fetchColumn(),
    'occupied_rooms' => (int)$occupiedRoomsStmt->fetchColumn()
];

$recentStmt = db()->prepare("SELECT b.*,u.name,r.room_number,rt.name type_name,p.status payment_status FROM bookings b JOIN users u ON u.id=b.user_id JOIN rooms r ON r.id=b.room_id JOIN room_types rt ON rt.id=r.room_type_id LEFT JOIN payments p ON p.booking_id=b.id ORDER BY b.created_at DESC LIMIT 10");
$recentStmt->execute();
$recent = $recentStmt->fetchAll();

require '../partials_header.php';
require 'partials_admin_nav.php';
?>

<div class="page" style="background:#f6f8fb">
<div class="container">
<div style="margin-bottom:32px">
<h1 style="margin:0 0 8px">Dashboard</h1>
<p class="muted">Overview of your hotel management system</p>
</div>

<div class="admin-stats-grid">
<div class="admin-stat-card">
<div class="stat-icon" style="background:#e3f2fd;color:#1976d2">
<i class="fas fa-bed"></i>
</div>
<div class="stat-content">
<div class="stat-label">Total Rooms</div>
<div class="stat-value"><?=$stats['rooms']?></div>
</div>
</div>

<div class="admin-stat-card">
<div class="stat-icon" style="background:#e8f5e9;color:#388e3c">
<i class="fas fa-calendar-check"></i>
</div>
<div class="stat-content">
<div class="stat-label">Total Bookings</div>
<div class="stat-value"><?=$stats['bookings']?></div>
</div>
</div>

<div class="admin-stat-card">
<div class="stat-icon" style="background:#fff3e0;color:#f57c00">
<i class="fas fa-users"></i>
</div>
<div class="stat-content">
<div class="stat-label">Customers</div>
<div class="stat-value"><?=$stats['customers']?></div>
</div>
</div>

<div class="admin-stat-card">
<div class="stat-icon" style="background:#e8f5e9;color:#2e7d32">
<i class="fas fa-rupee-sign"></i>
</div>
<div class="stat-content">
<div class="stat-label">Total Revenue</div>
<div class="stat-value">NPR <?=number_format($stats['revenue'])?></div>
</div>
</div>
</div>

<!-- Summary Cards Section -->
<div style="margin-bottom:32px">
<h2 style="margin:0 0 20px;font-size:20px;color:var(--dark)">Quick Summary</h2>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px">
<!-- Bookings Summary Card -->
<div class="panel" style="padding:24px">
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
<h3 style="margin:0;font-size:18px;color:var(--dark)">
<i class="fas fa-calendar-check" style="color:#388e3c;margin-right:8px"></i>Bookings
</h3>
<a href="bookings.php" class="btn small orange">View All</a>
</div>
<div style="display:flex;flex-direction:column;gap:12px">
<div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#f6f8fb;border-radius:8px">
<span style="color:var(--muted);font-size:14px">Total Bookings</span>
<strong style="color:var(--dark);font-size:18px"><?=$stats['bookings']?></strong>
</div>
<div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#fff3e0;border-radius:8px">
<span style="color:#e65100;font-size:14px">Pending</span>
<strong style="color:#e65100;font-size:18px"><?=$stats['pending_bookings']?></strong>
</div>
<div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#e8f5e9;border-radius:8px">
<span style="color:#2e7d32;font-size:14px">Confirmed</span>
<strong style="color:#2e7d32;font-size:18px"><?=$stats['confirmed_bookings']?></strong>
</div>
<div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#e3f2fd;border-radius:8px">
<span style="color:#1976d2;font-size:14px">Check-in Today</span>
<strong style="color:#1976d2;font-size:18px"><?=$stats['today_bookings']?></strong>
</div>
</div>
</div>

<!-- Rooms Summary Card -->
<div class="panel" style="padding:24px">
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
<h3 style="margin:0;font-size:18px;color:var(--dark)">
<i class="fas fa-bed" style="color:#1976d2;margin-right:8px"></i>Rooms
</h3>
<a href="rooms.php" class="btn small orange">View All</a>
</div>
<div style="display:flex;flex-direction:column;gap:12px">
<div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#f6f8fb;border-radius:8px">
<span style="color:var(--muted);font-size:14px">Total Rooms</span>
<strong style="color:var(--dark);font-size:18px"><?=$stats['rooms']?></strong>
</div>
<div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#e8f5e9;border-radius:8px">
<span style="color:#2e7d32;font-size:14px">Available</span>
<strong style="color:#2e7d32;font-size:18px"><?=$stats['available_rooms']?></strong>
</div>
<div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#ffebee;border-radius:8px">
<span style="color:#c62828;font-size:14px">Occupied</span>
<strong style="color:#c62828;font-size:18px"><?=$stats['occupied_rooms']?></strong>
</div>
<div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#f3e5f5;border-radius:8px">
<span style="color:#7b1fa2;font-size:14px">Occupancy Rate</span>
<strong style="color:#7b1fa2;font-size:18px"><?=$stats['rooms']>0?round(($stats['occupied_rooms']/$stats['rooms'])*100):0?>%</strong>
</div>
</div>
</div>

<!-- Room Types Summary Card -->
<div class="panel" style="padding:24px">
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
<h3 style="margin:0;font-size:18px;color:var(--dark)">
<i class="fas fa-layer-group" style="color:#f57c00;margin-right:8px"></i>Room Types
</h3>
<a href="room_types.php" class="btn small orange">View All</a>
</div>
<div style="display:flex;flex-direction:column;gap:12px">
<div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#f6f8fb;border-radius:8px">
<span style="color:var(--muted);font-size:14px">Total Types</span>
<strong style="color:var(--dark);font-size:18px"><?=$stats['room_types']?></strong>
</div>
<div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#fff3e0;border-radius:8px">
<span style="color:#e65100;font-size:14px">Active Types</span>
<strong style="color:#e65100;font-size:18px"><?=$stats['room_types']?></strong>
</div>
<div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#e1f5fe;border-radius:8px">
<span style="color:#0277bd;font-size:14px">Avg Rooms/Type</span>
<strong style="color:#0277bd;font-size:18px"><?=$stats['room_types']>0?round($stats['rooms']/$stats['room_types'],1):0?></strong>
</div>
<div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#f1f8e9;border-radius:8px">
<span style="color:#558b2f;font-size:14px">Revenue</span>
<strong style="color:#558b2f;font-size:16px">NPR <?=number_format($stats['revenue'])?></strong>
</div>
</div>
</div>
</div>
</div>

<div class="panel" style="margin-top:32px">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
<h2 style="margin:0"><i class="fas fa-clock" style="color:var(--accent);margin-right:8px"></i>Recent Bookings</h2>
<a href="bookings.php" class="btn light small">View All</a>
</div>
<div class="table-wrap">
<table class="table">
<thead>
<tr>
<th>Booking Code</th>
<th>Guest</th>
<th>Room</th>
<th>Dates</th>
<th>Amount</th>
<th>Status</th>
<th>Payment</th>
</tr>
</thead>
<tbody>
<?php foreach($recent as $b): ?>
<tr>
<td><strong><?=e($b['booking_code'])?></strong></td>
<td><?=e($b['name'])?></td>
<td><?=e($b['type_name'])?> <span class="muted">#<?=e($b['room_number'])?></span></td>
<td><?=date('M d', strtotime($b['check_in']))?> → <?=date('M d, Y', strtotime($b['check_out']))?></td>
<td><strong>NPR <?=number_format((float)$b['total_amount'])?></strong></td>
<td><span class="badge <?=e($b['status'])?>"><?=e($b['status'])?></span></td>
<td><span class="badge <?=e($b['payment_status']??'pending')?>"><?=e($b['payment_status']??'pending')?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

</div>
</div>
<?php require 'partials_admin_footer.php'; ?>
