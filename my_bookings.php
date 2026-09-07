<?php
require_once 'config/config.php';
$u = require_login();

// Get filters from URL
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$paymentFilter = $_GET['payment'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';

// Build query with filters
$sql = "SELECT b.*,r.room_number,r.id room_id,r.image room_image,rt.name type_name 
        FROM bookings b 
        JOIN rooms r ON r.id=b.room_id 
        JOIN room_types rt ON rt.id=r.room_type_id 
        WHERE b.user_id=?";

$params = [$u['id']];

if($search !== '') {
    $sql .= " AND (b.booking_code LIKE ? OR rt.name LIKE ? OR r.room_number LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if($statusFilter !== '') {
    $sql .= " AND b.status = ?";
    $params[] = $statusFilter;
}

if($paymentFilter !== '') {
    $sql .= " AND b.payment_status = ?";
    $params[] = $paymentFilter;
}

// Add sorting
switch($sortBy) {
    case 'oldest':
        $sql .= " ORDER BY b.created_at ASC";
        break;
    case 'checkin_asc':
        $sql .= " ORDER BY b.check_in ASC";
        break;
    case 'checkin_desc':
        $sql .= " ORDER BY b.check_in DESC";
        break;
    case 'amount_asc':
        $sql .= " ORDER BY b.total_amount ASC";
        break;
    case 'amount_desc':
        $sql .= " ORDER BY b.total_amount DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY b.created_at DESC";
        break;
}

$s = db()->prepare($sql);
$s->execute($params);
$rows = $s->fetchAll();
$title = 'My Bookings | ' . APP_NAME;
require 'partials_header.php';
?>
<div class="page">
<div class="container">
<h1 style="margin-bottom:8px">My Bookings</h1>
<p class="muted" style="margin-bottom:32px">View and manage your hotel reservations</p>

<!-- Search & Filter Panel -->
<div class="panel" style="margin-bottom:24px">
<form method="get" action="my_bookings.php">
<div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:12px;align-items:end">
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-search"></i> Search
</label>
<input type="text" name="search" value="<?=e($search)?>" placeholder="Booking code, room type, or room number..." style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
</div>
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-info-circle"></i> Status
</label>
<select name="status" style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
<option value="">All Status</option>
<option value="pending" <?=$statusFilter==='pending'?'selected':''?>>Pending</option>
<option value="confirmed" <?=$statusFilter==='confirmed'?'selected':''?>>Confirmed</option>
<option value="checked_in" <?=$statusFilter==='checked_in'?'selected':''?>>Checked In</option>
<option value="checked_out" <?=$statusFilter==='checked_out'?'selected':''?>>Checked Out</option>
<option value="cancelled" <?=$statusFilter==='cancelled'?'selected':''?>>Cancelled</option>
</select>
</div>
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-credit-card"></i> Payment
</label>
<select name="payment" style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
<option value="">All Payments</option>
<option value="pending" <?=$paymentFilter==='pending'?'selected':''?>>Pending</option>
<option value="paid" <?=$paymentFilter==='paid'?'selected':''?>>Paid</option>
<option value="failed" <?=$paymentFilter==='failed'?'selected':''?>>Failed</option>
<option value="refunded" <?=$paymentFilter==='refunded'?'selected':''?>>Refunded</option>
</select>
</div>
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-sort"></i> Sort By
</label>
<select name="sort" style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
<option value="newest" <?=$sortBy==='newest'?'selected':''?>>Newest First</option>
<option value="oldest" <?=$sortBy==='oldest'?'selected':''?>>Oldest First</option>
<option value="checkin_asc" <?=$sortBy==='checkin_asc'?'selected':''?>>Check-in (Earliest)</option>
<option value="checkin_desc" <?=$sortBy==='checkin_desc'?'selected':''?>>Check-in (Latest)</option>
<option value="amount_desc" <?=$sortBy==='amount_desc'?'selected':''?>>Amount (High-Low)</option>
<option value="amount_asc" <?=$sortBy==='amount_asc'?'selected':''?>>Amount (Low-High)</option>
</select>
</div>
<div style="display:flex;gap:8px">
<button type="submit" class="btn orange" style="padding:10px 20px;white-space:nowrap;height:44px;display:inline-flex;align-items:center;gap:8px">
<i class="fas fa-filter"></i> Filter
</button>
<?php if($search || $statusFilter || $paymentFilter || $sortBy !== 'newest'): ?>
<a href="my_bookings.php" class="btn light" style="padding:10px 16px;white-space:nowrap;height:44px;display:inline-flex;align-items:center;justify-content:center" title="Clear filters">
<i class="fas fa-times"></i>
</a>
<?php endif; ?>
</div>
</div>
</form>
</div>

<?php if($search || $statusFilter || $paymentFilter): ?>
<div style="margin-bottom:16px;padding:12px 16px;background:#fff3cd;border:1px solid #ffc107;border-radius:6px;display:flex;align-items:center;justify-content:space-between">
<div style="display:flex;align-items:center;gap:8px;font-size:14px">
<i class="fas fa-info-circle" style="color:#856404"></i>
<span style="color:#856404"><strong><?=count($rows)?></strong> booking(s) found with applied filters</span>
</div>
</div>
<?php endif; ?>

<?php if(count($rows) === 0): ?>
<div class="panel" style="text-align:center;padding:60px 20px">
<i class="fas fa-calendar-times" style="font-size:64px;color:#d9dee7;margin-bottom:20px"></i>
<h3 style="color:var(--muted);margin:0 0 12px">
<?php if($search || $statusFilter || $paymentFilter): ?>
No bookings found
<?php else: ?>
No bookings yet
<?php endif; ?>
</h3>
<p class="muted" style="margin-bottom:24px">
<?php if($search || $statusFilter || $paymentFilter): ?>
Try adjusting your search or filter criteria
<?php else: ?>
Start exploring our rooms and make your first reservation
<?php endif; ?>
</p>
<?php if($search || $statusFilter || $paymentFilter): ?>
<a href="my_bookings.php" class="btn light"><i class="fas fa-redo"></i> Clear Filters</a>
<?php else: ?>
<a href="rooms.php" class="btn orange"><i class="fas fa-search" style="margin-right:8px"></i>Browse Rooms</a>
<?php endif; ?>
</div>
<?php else: ?>

<div class="bookings-grid">
<?php foreach($rows as $b): 
$statusColors = [
    'pending' => 'warning',
    'confirmed' => 'success',
    'cancelled' => 'danger',
    'checked_in' => 'info',
    'checked_out' => 'secondary'
];
$statusColor = $statusColors[$b['status']] ?? 'secondary';
?>
<div class="booking-card-item">
<div class="booking-card-image">
<img src="<?=e($b['room_image'])?>" alt="<?=e($b['type_name'])?>">
<span class="booking-status-badge badge-<?=$statusColor?>"><?=ucfirst($b['status'])?></span>
</div>
<div class="booking-card-content">
<div class="booking-card-header">
<div>
<h3 style="margin:0 0 6px;font-size:20px"><?=e($b['type_name'])?></h3>
<p class="muted" style="margin:0;font-size:14px">Room <?=e($b['room_number'])?> • <?=e($b['booking_code'])?></p>
</div>
</div>
<div class="booking-card-details">
<div class="detail-row">
<div class="detail-item">
<i class="fas fa-calendar-alt"></i>
<div>
<div class="detail-label">Check-in</div>
<div class="detail-value"><?=date('M d, Y', strtotime($b['check_in']))?></div>
</div>
</div>
<div class="detail-item">
<i class="fas fa-calendar-check"></i>
<div>
<div class="detail-label">Check-out</div>
<div class="detail-value"><?=date('M d, Y', strtotime($b['check_out']))?></div>
</div>
</div>
</div>
<div class="detail-row">
<div class="detail-item">
<i class="fas fa-users"></i>
<div>
<div class="detail-label">Guests</div>
<div class="detail-value"><?=$b['guests']?> guest<?=$b['guests']!=1?'s':''?></div>
</div>
</div>
<div class="detail-item">
<i class="fas fa-moon"></i>
<div>
<div class="detail-label">Nights</div>
<div class="detail-value"><?=nights($b['check_in'], $b['check_out'])?> night<?=nights($b['check_in'], $b['check_out'])!=1?'s':''?></div>
</div>
</div>
</div>
</div>
<div class="booking-card-footer">
<div class="booking-price">
<div class="price-label">Total Amount</div>
<div class="price-value">NPR <?=number_format((float)$b['total_amount'])?></div>
</div>
<div class="booking-actions">
<a href="room.php?id=<?=$b['room_id']?>&check_in=<?=urlencode($b['check_in'])?>&check_out=<?=urlencode($b['check_out'])?>&guests=<?=$b['guests']?>" class="btn light" style="display:inline-flex;align-items:center;gap:6px">
<i class="fas fa-eye"></i> View Room
</a>
<?php if(in_array($b['status'], ['pending', 'confirmed'])): ?>
<a href="cancel.php?id=<?=$b['id']?>" class="btn danger small" onclick="return confirm('Are you sure you want to cancel this booking?')" style="display:inline-flex;align-items:center;gap:6px">
<i class="fas fa-times"></i> Cancel
</a>
<?php endif;?>
</div>
</div>
<div class="payment-info" style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:#f6f8fb;border-top:1px solid #e6e9ef">
<div style="display:flex;align-items:center;gap:8px">
<?php
$paymentColors = [
    'pending' => '#ffc107',
    'paid' => '#28a745',
    'failed' => '#dc3545',
    'refunded' => '#6c757d'
];
$paymentColor = $paymentColors[$b['payment_status']] ?? '#6c757d';
?>
<i class="fas fa-<?=$b['payment_status']==='paid'?'check-circle':'clock'?>" style="color:<?=$paymentColor?>"></i>
<span>Payment: <strong style="color:<?=$paymentColor?>"><?=ucfirst($b['payment_status'])?></strong></span>
</div>
<?php if($b['payment_status'] === 'pending' && $b['status'] === 'pending'): ?>
<span class="muted" style="font-size:12px">Awaiting admin confirmation</span>
<?php endif; ?>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

</div>
</div>
<?php require 'partials_footer.php'; ?>
