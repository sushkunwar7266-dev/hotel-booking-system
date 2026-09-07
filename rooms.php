<?php
require_once 'config/config.php';
$title = 'Rooms | ' . APP_NAME;

$in = $_GET['check_in'] ?? date('Y-m-d', strtotime('+1 day'));
$out = $_GET['check_out'] ?? date('Y-m-d', strtotime('+2 day'));
$guests = max(1, (int)($_GET['guests'] ?? 1));
$type = (int)($_GET['type'] ?? 0);
$max = (int)($_GET['max_price'] ?? 0);

// Validate dates
$checkIn = strtotime($in);
$checkOut = strtotime($out);
$today = strtotime('today');

if($checkIn < $today) {
    $in = date('Y-m-d', strtotime('+1 day'));
    $checkIn = strtotime($in);
}

if($checkOut <= $checkIn) {
    $out = date('Y-m-d', $checkIn + 86400);
}

// Validate guests
if($guests < 1) $guests = 1;
if($guests > 10) $guests = 10;

$sql = "SELECT r.*,rt.name type_name FROM rooms r JOIN room_types rt ON rt.id=r.room_type_id WHERE r.status='available' AND rt.status='active'";
$params = [];

// Filter by capacity
$sql .= " AND r.capacity>=?";
$params[] = $guests;
if($type) {
    $sql .= " AND r.room_type_id=?";
    $params[] = $type;
}
if($max > 0) {
    $sql .= " AND r.price<=?";
    $params[] = $max;
}
$sql .= " ORDER BY r.price ASC";

$rooms = db()->prepare($sql);
$rooms->execute($params);
$rooms = $rooms->fetchAll();
$rooms = array_values(array_filter($rooms, fn($r) => is_room_available((int)$r['id'], $in, $out)));

require 'partials_header.php'; ?>
<div class="page"><div class="container">

<form class="panel" method="get" novalidate style="margin-bottom:30px;padding:24px">
<h3 style="margin:0 0 20px">Search for available rooms</h3>
<div class="form-grid" style="grid-template-columns:1fr 1fr 1fr 1fr auto;align-items:end;gap:16px">
<div style="display:flex;flex-direction:column">
<label>Check-in</label>
<input type="date" name="check_in" value="<?=e($in)?>" required min="<?=date('Y-m-d')?>" style="height:48px;padding:13px 14px">
</div>
<div style="display:flex;flex-direction:column">
<label>Check-out</label>
<input type="date" name="check_out" value="<?=e($out)?>" required min="<?=date('Y-m-d', strtotime('+1 day'))?>" style="height:48px;padding:13px 14px">
</div>
<div style="display:flex;flex-direction:column">
<label>Guests</label>
<input type="number" name="guests" value="<?=$guests?>" min="1" max="10" required style="height:48px;padding:13px 14px">
</div>
<div style="display:flex;flex-direction:column">
<label>Max price (NPR)</label>
<input type="number" name="max_price" value="<?=$max?:''?>" min="0" placeholder="Any price" style="height:48px;padding:13px 14px">
</div>
<div style="display:flex;flex-direction:column">
<label style="opacity:0;user-select:none;margin-bottom:8px">Search</label>
<button class="btn orange" style="height:48px;padding:0 28px;white-space:nowrap;display:flex;align-items:center;justify-content:center">Search Rooms</button>
</div>
</div>
</form>

<h2 style="margin-bottom:20px"><?=count($rooms)?> rooms available</h2>
<div class="cards">
<?php foreach($rooms as $r): ?>
<div class="card">
<img class="card-img" src="<?=e($r['image'])?>" alt="<?=e($r['type_name'])?>">
<div class="card-body">
<h3 style="margin-bottom:4px"><?=e($r['type_name'])?></h3>
<p class="muted" style="margin-bottom:12px;font-size:14px">Room <?=e($r['room_number'])?></p>
<div class="chips">
<span class="chip"><i class="fas fa-users" style="margin-right:4px"></i> <?=!empty($r['capacity']) ? $r['capacity'] : 2?> guests</span>
<?php 
if(!empty($r['amenities'])) {
    $amenities = array_filter(array_map('trim', explode(',', $r['amenities'])));
    $displayAmenities = array_slice($amenities, 0, 2);
    foreach($displayAmenities as $amenity): 
        // Get icon for amenity
        $amenityLower = strtolower($amenity);
        $icon = 'check';
        if(strpos($amenityLower, 'wi-fi') !== false || strpos($amenityLower, 'wifi') !== false) $icon = 'wifi';
        elseif(strpos($amenityLower, 'air conditioning') !== false || strpos($amenityLower, 'ac') !== false) $icon = 'snowflake';
        elseif(strpos($amenityLower, 'tv') !== false) $icon = 'tv';
        elseif(strpos($amenityLower, 'mini bar') !== false) $icon = 'glass-martini-alt';
        elseif(strpos($amenityLower, 'breakfast') !== false) $icon = 'utensils';
        elseif(strpos($amenityLower, 'balcony') !== false) $icon = 'door-open';
        elseif(strpos($amenityLower, 'view') !== false) $icon = 'mountain';
?>
<span class="chip"><i class="fas fa-<?=$icon?>" style="margin-right:4px"></i> <?=e($amenity)?></span>
    <?php endforeach;
}
?>
</div>
<div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
<div class="price">NPR <?=number_format((float)$r['price'])?> <small style="font-size:14px;font-weight:600;color:var(--muted)">/ night</small></div>
<a class="btn orange" href="room.php?id=<?=$r['id']?>&check_in=<?=urlencode($in)?>&check_out=<?=urlencode($out)?>&guests=<?=$guests?>" style="width:100%">View & Book</a>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php if(!$rooms): ?><div class="alert" style="margin-top:25px;background:#fff3cd;color:#856404">No rooms available for selected dates and filters. Try different dates or adjust your search.</div><?php endif;?>
</div></div><?php require 'partials_footer.php'; ?>
