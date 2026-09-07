<?php
require_once 'config/config.php';

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

$s = db()->prepare("SELECT r.*,rt.name type_name,rt.description,rt.capacity,rt.amenities FROM rooms r JOIN room_types rt ON rt.id=r.room_type_id WHERE r.id=?");
$s->execute([$id]);
$room = $s->fetch();

if(!$room) {
    flash('error', 'Room not found');
    redirect('rooms.php');
}

if($guests > $room['capacity']) {
    $guests = $room['capacity'];
}

if(!is_room_available($id, $in, $out)) {
    flash('error', 'This room is not available for the selected dates');
    redirect('rooms.php');
}

$n = nights($in, $out);
$total = $n * (float)$room['price'];
$title = $room['type_name'] . ' | ' . APP_NAME;

require 'partials_header.php'; ?>
<div class="page"><div class="container"><div class="room-layout"><img src="<?=e($room['image'])?>" alt="<?=e($room['type_name'])?>">
<div class="panel"><h1><?=e($room['type_name'])?></h1><p><?=e($room['description'])?></p>
<div class="chips">
<span class="chip">Capacity: <?=$room['capacity']?> guests</span>
<?php foreach(explode(',', $room['amenities']) as $a): ?>
<span class="chip"><?=e(trim($a))?></span>
<?php endforeach;?>
</div>
<h3>Room <?=e($room['room_number'])?></h3>
<div class="price">NPR <?=number_format((float)$room['price'])?> <small>/ night</small></div>
<hr>
<form action="book.php" method="post" novalidate>
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="room_id" value="<?=$id?>">
<p><label>Check-in</label><input type="date" name="check_in" value="<?=e($in)?>" required min="<?=date('Y-m-d')?>" readonly style="background:#f5f5f5"></p>
<p><label>Check-out</label><input type="date" name="check_out" value="<?=e($out)?>" required readonly style="background:#f5f5f5"></p>
<p><label>Number of guests</label><input type="number" name="guests" value="<?=$guests?>" min="1" max="<?=$room['capacity']?>" required><div class="form-hint">Max capacity: <?=$room['capacity']?></div></p>
<p><label>Special request (optional)</label><textarea name="special_request" placeholder="Any special requirements?" maxlength="500"></textarea><div class="form-hint">Max 500 characters</div></p>
<div class="alert success" style="margin:15px 0"><strong><?=$n?> night(s)</strong><br>Total: <strong>NPR <?=number_format($total)?></strong></div>
<button class="btn orange" style="width:100%">Reserve & Continue to Payment</button>
</form>
<p class="muted" style="margin-top:15px;text-align:center"><a href="rooms.php?check_in=<?=urlencode($in)?>&check_out=<?=urlencode($out)?>&guests=<?=$guests?>">← Change dates or room</a></p>
</div></div></div></div><?php require 'partials_footer.php'; ?>
