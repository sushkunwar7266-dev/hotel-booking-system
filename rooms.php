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

$sql = "SELECT r.*,rt.name type_name,rt.capacity,rt.amenities FROM rooms r JOIN room_types rt ON rt.id=r.room_type_id WHERE r.status='available' AND rt.capacity>=?";
$params = [$guests];
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
<div class="page"><div class="container"><h1>Available Rooms</h1><p class="muted"><?=e($in)?> → <?=e($out)?> · <?=$guests?> guest(s)</p>
<form class="panel" method="get" novalidate>
<div class="form-grid">
<div><label>Check-in</label><input type="date" name="check_in" value="<?=e($in)?>" required min="<?=date('Y-m-d')?>"><div class="form-hint">Today or later</div></div>
<div><label>Check-out</label><input type="date" name="check_out" value="<?=e($out)?>" required min="<?=date('Y-m-d', strtotime('+1 day'))?>"><div class="form-hint">After check-in</div></div>
<div><label>Guests</label><input type="number" name="guests" value="<?=$guests?>" min="1" max="10" required><div class="form-hint">1-10 guests</div></div>
<div><label>Max price</label><input type="number" name="max_price" value="<?=$max?:''?>" min="0" placeholder="Any"><div class="form-hint">Optional</div></div>
</div><br><button class="btn orange">Search Rooms</button>
</form>
<div class="cards">
<?php foreach($rooms as $r): ?>
<div class="card"><img class="card-img" src="<?=e($r['image'])?>" alt="<?=e($r['type_name'])?>"><div class="card-body"><h3><?=e($r['type_name'])?> · Room <?=e($r['room_number'])?></h3><p class="muted"><?=e($r['amenities'])?></p><div class="chips"><span class="chip">Capacity: <?=$r['capacity']?></span></div><div class="price">NPR <?=number_format((float)$r['price'])?> <small>/ night</small></div><br><a class="btn orange" href="room.php?id=<?=$r['id']?>&check_in=<?=urlencode($in)?>&check_out=<?=urlencode($out)?>&guests=<?=$guests?>">View & Book</a></div></div>
<?php endforeach; ?>
</div>
<?php if(!$rooms): ?><div class="alert" style="margin-top:25px;background:#fff3cd;color:#856404">No rooms available for selected dates and filters. Try different dates or adjust your search.</div><?php endif;?>
</div></div><?php require 'partials_footer.php'; ?>
