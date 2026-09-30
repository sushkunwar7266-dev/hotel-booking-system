<?php
require_once 'config/config.php';

// Get current user
$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$in = $_GET['check_in'] ?? date('Y-m-d', strtotime('+1 day'));
$out = $_GET['check_out'] ?? date('Y-m-d', strtotime('+2 day'));
$guests = max(1, min(10, (int)($_GET['guests'] ?? 1)));

// Validate dates
$checkIn = strtotime($in);
$checkOut = strtotime($out);
$today = strtotime('today');

if($checkIn < $today) {
    $in = date('Y-m-d', strtotime('+1 day'));
}

if($checkOut <= $checkIn) {
    $out = date('Y-m-d', strtotime($in . ' +1 day'));
}

$s = db()->prepare("SELECT r.*,rt.name type_name FROM rooms r JOIN room_types rt ON rt.id=r.room_type_id WHERE r.id=?");
$s->execute([$id]);
$room = $s->fetch();

if(!$room) {
    flash('error', 'Room not found');
    redirect('rooms.php');
}

// Use room capacity if set, otherwise fallback to 2
$roomCapacity = !empty($room['capacity']) ? (int)$room['capacity'] : 2;

if($guests > $roomCapacity) {
    $guests = $roomCapacity;
}

// Show availability on the page instead of redirecting away
$roomOpen = $room['status'] === 'available';
$available = $roomOpen && is_room_available($id, $in, $out);

$n = nights($in, $out);
$total = $n * (float)$room['price'];
$title = $room['type_name'] . ' | ' . APP_NAME;

// Parse gallery images
$galleryImages = [];
if(!empty($room['gallery'])) {
    $galleryImages = json_decode($room['gallery'], true) ?? [];
}

require 'partials_header.php'; ?>
<div class="page">
<div class="container">
<div style="margin-bottom:20px">
<a href="rooms.php?check_in=<?=urlencode($in)?>&check_out=<?=urlencode($out)?>&guests=<?=$guests?>" style="color:var(--primary);text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:6px">
<i class="fas fa-arrow-left"></i> Back to rooms
</a>
</div>

<div class="room-detail-layout">
<div class="room-detail-left">
<!-- Main Image -->
<div class="room-image-container">
<img src="<?=e($room['image'])?>" alt="<?=e($room['type_name'])?>" class="room-detail-image" id="mainImage">
</div>

<!-- Gallery Images -->
<?php if(!empty($galleryImages)): ?>
<div style="margin-top:16px">
<h4 style="margin:0 0 12px;font-size:14px;font-weight:600;color:var(--muted);text-transform:uppercase">Gallery</h4>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px">
<div style="cursor:pointer;border:2px solid var(--accent);border-radius:8px;overflow:hidden" onclick="changeMainImage('<?=e($room['image'])?>')">
<img src="<?=e($room['image'])?>" style="width:100%;height:80px;object-fit:cover;display:block">
</div>
<?php foreach($galleryImages as $img): ?>
<div style="cursor:pointer;border:2px solid #e6e9ef;border-radius:8px;overflow:hidden" onclick="changeMainImage('<?=e($img)?>')" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='#e6e9ef'">
<img src="<?=e($img)?>" style="width:100%;height:80px;object-fit:cover;display:block">
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<div class="room-info-panel">
<div class="room-header">
<div>
<div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
<span class="room-badge">Room <?=e($room['room_number'])?></span>
<span class="room-badge" style="background:#e8f5e9;color:#2e7d32">
<i class="fas fa-users"></i> <?=$roomCapacity?> Guests
</span>
</div>
<h1 class="room-title"><?=e($room['type_name'])?></h1>
</div>
<div class="room-price-large">
<div class="price-amount">NPR <?=number_format((float)$room['price'])?></div>
<div class="price-label">per night</div>
</div>
</div>

<!-- Description -->
<p class="room-description"><?=!empty($room['description']) ? nl2br(e($room['description'])) : 'Comfortable and well-appointed room with modern amenities.'?></p>

<!-- Amenities -->
<div class="room-amenities">
<h3 style="margin:0 0 16px;font-size:18px;color:var(--dark)"><i class="fas fa-check-circle" style="color:var(--accent);margin-right:8px"></i>Amenities</h3>
<div class="amenity-grid">
<?php 
if(!empty($room['amenities'])) {
    foreach(explode(',', $room['amenities']) as $a): 
        $amenity = trim($a);
        if(empty($amenity)) continue;
?>
<div class="amenity-item">
<i class="fas fa-check"></i>
<span><?=e($amenity)?></span>
</div>
<?php 
    endforeach;
} else {
    echo '<p class="muted" style="margin:0;grid-column:1/-1">No amenities specified</p>';
}
?>
</div>
</div>
</div>
</div>

<div class="room-detail-right">
<div class="booking-card">
<h3 style="margin:0 0 20px;font-size:20px">Reserve this room</h3>
<?php if(!$roomOpen): ?>
<div class="avail-box" style="display:flex;gap:10px;align-items:flex-start;background:#fdeaea;color:#b42318;border-radius:10px;padding:12px 14px;margin-bottom:18px;font-size:14px">
<i class="fas fa-tools" style="margin-top:3px"></i><span><strong>Currently unavailable.</strong> This room is under <?=e($room['status'])?> and cannot be booked right now.</span>
</div>
<?php elseif($available): ?>
<div class="avail-box" style="display:flex;gap:10px;align-items:flex-start;background:#e6f6ec;color:#1e7b3c;border-radius:10px;padding:12px 14px;margin-bottom:18px;font-size:14px">
<i class="fas fa-check-circle" style="margin-top:3px"></i><span><strong>Available</strong> for <?=date('M j', strtotime($in))?> to <?=date('M j, Y', strtotime($out))?>.</span>
</div>
<?php else: ?>
<div class="avail-box" style="display:flex;gap:10px;align-items:flex-start;background:#fff4e0;color:#b26a00;border-radius:10px;padding:12px 14px;margin-bottom:18px;font-size:14px">
<i class="fas fa-calendar-times" style="margin-top:3px"></i><span><strong>Booked</strong> for <?=date('M j', strtotime($in))?> to <?=date('M j, Y', strtotime($out))?>. Try different dates or <a href="rooms.php?check_in=<?=urlencode($in)?>&check_out=<?=urlencode($out)?>&guests=<?=$guests?>" style="color:inherit;font-weight:700">see other available rooms</a>.</span>
</div>
<?php endif; ?>

<form action="checkout.php" method="get" novalidate id="bookingForm">
<input type="hidden" name="room_id" value="<?=$id?>">

<div class="booking-summary" style="margin-bottom:24px">
<h4 style="margin:0 0 16px;font-size:16px;font-weight:700;color:var(--dark)"><i class="fas fa-calendar-alt" style="color:var(--accent);margin-right:8px"></i>Stay Dates</h4>
<div class="form-group" style="margin-bottom:16px">
<label>Check-in</label>
<input type="date" name="check_in" id="checkInDate" value="<?=e($in)?>" required min="<?=date('Y-m-d')?>" style="width:100%;padding:12px;border:1px solid #d9dee7;border-radius:9px">
</div>
<div class="form-group">
<label>Check-out</label>
<input type="date" name="check_out" id="checkOutDate" value="<?=e($out)?>" required min="<?=date('Y-m-d', strtotime('+1 day'))?>" style="width:100%;padding:12px;border:1px solid #d9dee7;border-radius:9px">
</div>
</div>

<div class="booking-info-summary" style="background:#f6f8fb;border-radius:12px;padding:16px;margin-bottom:20px">
<div class="booking-info-item" style="justify-content:space-between;padding:0;border:0">
<span><i class="fas fa-moon"></i> <span id="nightCount"><?=$n?></span> night<span id="nightPlural"><?=$n!=1?'s':''?></span></span>
<span><strong id="nightlyTotal">NPR <?=number_format((float)$room['price'] * $n)?></strong></span>
</div>
</div>

<div class="form-group">
<label>Number of guests</label>
<input type="number" name="guests" value="<?=$guests?>" min="1" max="<?=$roomCapacity?>" required style="width:100%;padding:12px;border:1px solid #d9dee7;border-radius:9px">
<div class="form-hint">Maximum <?=$roomCapacity?> guests</div>
</div>

<div class="price-breakdown">
<div class="price-row price-total">
<span>Total Amount</span>
<span id="totalAmount">NPR <?=number_format($total)?></span>
</div>
</div>

<?php if($available): ?>
<button type="submit" class="btn orange" style="width:100%;padding:16px;font-size:16px;font-weight:700">
<i class="fas fa-arrow-right" style="margin-right:8px"></i>Continue to Checkout
</button>
<?php else: ?>
<button type="button" class="btn" disabled style="width:100%;padding:16px;font-size:16px;font-weight:700;background:#c5ccd6;cursor:not-allowed">
<i class="fas fa-ban" style="margin-right:8px"></i><?=$roomOpen ? 'Not available for these dates' : 'Currently unavailable'?>
</button>
<?php endif; ?>
<div class="form-hint" style="text-align:center;margin-top:8px">You won't be charged yet</div>
</form>
</div>
</div>
</div>
</div>
</div>

<script>
function changeMainImage(src) {
    document.getElementById('mainImage').src = src;
}

document.addEventListener('DOMContentLoaded', function() {
    const checkInInput = document.getElementById('checkInDate');
    const checkOutInput = document.getElementById('checkOutDate');
    const nightCount = document.getElementById('nightCount');
    const nightPlural = document.getElementById('nightPlural');
    const nightlyTotal = document.getElementById('nightlyTotal');
    const totalAmount = document.getElementById('totalAmount');
    const pricePerNight = <?=(float)$room['price']?>;
    
    function calculateNights() {
        const checkIn = new Date(checkInInput.value);
        const checkOut = new Date(checkOutInput.value);
        
        if (checkIn && checkOut && checkOut > checkIn) {
            const nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
            const total = nights * pricePerNight;
            
            nightCount.textContent = nights;
            nightPlural.textContent = nights !== 1 ? 's' : '';
            nightlyTotal.textContent = 'NPR ' + total.toLocaleString('en-NP');
            totalAmount.textContent = 'NPR ' + total.toLocaleString('en-NP');
        }
    }
    
    checkInInput.addEventListener('change', function() {
        const checkIn = new Date(this.value);
        const checkOut = new Date(checkOutInput.value);
        
        if (checkOut <= checkIn) {
            const newCheckOut = new Date(checkIn);
            newCheckOut.setDate(newCheckOut.getDate() + 1);
            checkOutInput.value = newCheckOut.toISOString().split('T')[0];
        }
        
        checkOutInput.min = new Date(checkIn.getTime() + 86400000).toISOString().split('T')[0];
        calculateNights();
    });
    
    checkOutInput.addEventListener('change', calculateNights);

    // Re-check availability for the new dates or guests
    function recheck() {
        if (!checkInInput.value || !checkOutInput.value || checkOutInput.value <= checkInInput.value) return;
        const g = document.querySelector('#bookingForm [name="guests"]');
        const box = document.querySelector('.avail-box');
        if (box) box.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Checking availability...</span>';
        location.href = 'room.php?id=<?=$id?>&check_in=' + checkInInput.value + '&check_out=' + checkOutInput.value + '&guests=' + (g ? g.value : 1);
    }
    checkInInput.addEventListener('change', recheck);
    checkOutInput.addEventListener('change', recheck);
    const guestsInput = document.querySelector('#bookingForm [name="guests"]');
    if (guestsInput) guestsInput.addEventListener('change', recheck);
});
</script>

<?php require 'partials_footer.php'; ?>
