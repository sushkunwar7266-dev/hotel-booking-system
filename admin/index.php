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

$stats = [
    'rooms' => (int)$roomsStmt->fetchColumn(),
    'bookings' => (int)$bookingsStmt->fetchColumn(),
    'customers' => (int)$customersStmt->fetchColumn(),
    'revenue' => (float)$revenueStmt->fetchColumn()
];

$recentStmt = db()->prepare("SELECT b.*,u.name,r.room_number,rt.name type_name,p.status payment_status FROM bookings b JOIN users u ON u.id=b.user_id JOIN rooms r ON r.id=b.room_id JOIN room_types rt ON rt.id=r.room_type_id LEFT JOIN payments p ON p.booking_id=b.id ORDER BY b.created_at DESC LIMIT 10");
$recentStmt->execute();
$recent = $recentStmt->fetchAll();

require '../partials_header.php';
?>
<div class="admin-nav">
<div class="container">
<a href="index.php"><i class="fas fa-chart-line"></i> Dashboard</a>
<a href="rooms.php"><i class="fas fa-bed"></i> Rooms</a>
<a href="room_types.php"><i class="fas fa-layer-group"></i> Room Types</a>
<a href="bookings.php"><i class="fas fa-calendar-check"></i> Bookings</a>
</div>
</div>

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
<?php require '../partials_footer.php'; ?>
