<?php
// Get pending booking count for notification badge
$pendingCount = get_pending_booking_count();
?>
<div class="admin-nav">
<div class="container">
<a href="index.php"><i class="fas fa-chart-line"></i> Dashboard</a>
<a href="bookings.php" style="position:relative">
<i class="fas fa-calendar-check"></i> Bookings
<?php if($pendingCount > 0): ?>
<span style="position:absolute;top:-6px;right:-10px;background:#ff5252;color:#fff;font-size:11px;font-weight:700;padding:2px 6px;border-radius:10px;min-width:20px;text-align:center"><?=$pendingCount?></span>
<?php endif; ?>
</a>
<a href="rooms.php"><i class="fas fa-bed"></i> Rooms</a>
<a href="room_types.php"><i class="fas fa-layer-group"></i> Room Types</a>
</div>
</div>
