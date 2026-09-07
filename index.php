<?php
require_once 'config/config.php';
$title = 'Home | ' . APP_NAME;
$typesStmt = db()->prepare("SELECT rt.*, COUNT(r.id) room_count FROM room_types rt LEFT JOIN rooms r ON r.room_type_id=rt.id AND r.status='available' WHERE rt.status='active' GROUP BY rt.id ORDER BY rt.id");
$typesStmt->execute();
$types = $typesStmt->fetchAll();
require 'partials_header.php';
?>
<section class="hero"><div class="container"><h1>Find your perfect stay, simply.</h1><p>Search available rooms, compare facilities, reserve your room and manage your booking from one place.</p>
<form class="search-card" action="rooms.php" method="get" novalidate>
<div class="form-grid" style="grid-template-columns:1fr 1fr 1fr auto">
<div>
<label>Check-in</label>
<input type="date" name="check_in" min="<?=date('Y-m-d')?>" required>
</div>
<div>
<label>Check-out</label>
<input type="date" name="check_out" min="<?=date('Y-m-d', strtotime('+1 day'))?>" required>
</div>
<div>
<label>Guests</label>
<input type="number" name="guests" min="1" max="10" value="2" required>
</div>
<div>
<label style="opacity:0;user-select:none">Search</label>
<button class="btn orange" style="width:100%">Search Rooms</button>
</div>
</div>
</form>
</div></section>
<section class="section"><div class="container">
<h2>Stay your way</h2>
<p class="muted" style="margin-bottom:32px">Choose from comfortable rooms designed for different travel needs.</p>
<div class="cards">
<?php foreach($types as $t): ?>
<div class="card">
<img class="card-img" src="<?=e($t['image'] ?: 'https://via.placeholder.com/400x300/f0f0f0/999999?text=' . urlencode($t['name']))?>" alt="<?=e($t['name'])?>">
<div class="card-body">
<h3><?=e($t['name'])?></h3>
<p class="muted" style="min-height:72px"><?=e($t['description'] ?: 'Explore our ' . $t['name'] . ' rooms')?></p>
<div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
<p class="muted" style="margin-bottom:12px"><strong><?=$t['room_count']?></strong> room<?=$t['room_count']!=1?'s':''?> available</p>
<a class="btn orange" href="rooms.php?type=<?=$t['id']?>" style="width:100%">View Rooms</a>
</div>
</div>
</div>
<?php endforeach;?>
</div>
</div></section>
<?php require 'partials_footer.php'; ?>
