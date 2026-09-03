<?php
require_once 'config/config.php';
$title='Home | '.APP_NAME;
$types=db()->query("SELECT rt.*, COUNT(r.id) room_count FROM room_types rt LEFT JOIN rooms r ON r.room_type_id=rt.id AND r.status='available' GROUP BY rt.id ORDER BY rt.id")->fetchAll();
require 'partials_header.php';
?>
<section class="hero"><div class="container"><h1>Find your perfect stay, simply.</h1><p>Search available rooms, compare facilities, reserve your room and manage your booking from one place.</p>
<form class="search-card" action="rooms.php" method="get"><div class="form-grid">
<div><label>Check-in</label><input type="date" name="check_in" min="<?= date('Y-m-d',strtotime('+1 day')) ?>" required></div>
<div><label>Check-out</label><input type="date" name="check_out" min="<?= date('Y-m-d',strtotime('+2 day')) ?>" required></div>
<div><label>Guests</label><input type="number" name="guests" min="1" value="2" required></div>
<div style="display:flex;align-items:end"><button class="btn orange" style="width:100%">Search Rooms</button></div>
</div></form></div></section>
<section class="section"><div class="container"><h2>Stay your way</h2><p class="muted">Choose from comfortable rooms designed for different travel needs.</p>
<div class="cards"><?php foreach($types as $t): ?><div class="card"><div class="card-body"><h3><?=e($t['name'])?></h3><p class="muted"><?=e($t['description'])?></p><div class="chips"><?php foreach(array_filter(array_map('trim',explode(',',$t['amenities']))) as $a): ?><span class="chip"><?=e($a)?></span><?php endforeach;?></div><a class="btn" href="rooms.php?type=<?=$t['id']?>">View Rooms</a></div></div><?php endforeach;?></div></div></section>
<?php require 'partials_footer.php'; ?>
