<?php
require_once 'config/config.php';
$u = require_login();
$s = db()->prepare("SELECT b.*,r.room_number,r.id room_id,r.image room_image,rt.name type_name FROM bookings b JOIN rooms r ON r.id=b.room_id JOIN room_types rt ON rt.id=r.room_type_id WHERE b.user_id=? ORDER BY b.created_at DESC");
$s->execute([$u['id']]);
$rows = $s->fetchAll();
$title = 'My Bookings | ' . APP_NAME;
require 'partials_header.php';
?>
<div class="page">
<div class="container">
<h1 style="margin-bottom:8px">My Bookings</h1>
<p class="muted" style="margin-bottom:32px">View and manage your hotel reservations</p>

<?php if(count($rows) === 0): ?>
<div class="panel" style="text-align:center;padding:60px 20px">
<i class="fas fa-calendar-times" style="font-size:64px;color:#d9dee7;margin-bottom:20px"></i>
<h3 style="color:var(--muted);margin:0 0 12px">No bookings yet</h3>
<p class="muted" style="margin-bottom:24px">Start exploring our rooms and make your first reservation</p>
<a href="rooms.php" class="btn orange"><i class="fas fa-search" style="margin-right:8px"></i>Browse Rooms</a>
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
