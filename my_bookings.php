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
$userNavActive = 'bookings';
require 'partials_user_nav.php';
?>
<?php
?>
<div class="page">
<div class="container">
<h1 style="margin-bottom:8px">My Bookings</h1>
<p class="muted" style="margin-bottom:32px">View and manage your hotel reservations</p>

<!-- Search & Filter Panel -->
<div class="panel" style="margin-bottom:24px">
<form method="get" action="my_bookings.php">
<div class="mb-filters">
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

<div class="mb-toolbar">
<div class="mb-count"><strong><?=count($rows)?></strong> booking<?=count($rows) == 1 ? '' : 's'?></div>
<div class="mb-view" role="group" aria-label="View">
<button type="button" data-view="grid" title="Grid view" aria-label="Grid view"><i class="fas fa-th-large"></i><span>Grid</span></button>
<button type="button" data-view="list" title="List view" aria-label="List view"><i class="fas fa-list"></i><span>List</span></button>
</div>
</div>

<div class="mb-cards mb-grid" id="mbCards">
<?php
$mbStatus = ['pending' => ['#fff4e0', '#b26a00', 'fa-hourglass-half', 'Pending'], 'confirmed' => ['#e6f6ec', '#1e7b3c', 'fa-check-circle', 'Confirmed'],
             'checked_in' => ['#e3effd', '#1d5fa8', 'fa-door-open', 'Checked in'], 'checked_out' => ['#eef0f3', '#4a5563', 'fa-door-closed', 'Completed'],
             'cancelled' => ['#fdeaea', '#b42318', 'fa-ban', 'Cancelled']];
$mbPay = ['paid' => ['#1e7b3c', 'fa-check-circle', 'Paid'], 'pending' => ['#b26a00', 'fa-clock', 'Payment pending'],
          'failed' => ['#b42318', 'fa-times-circle', 'Payment failed'], 'refunded' => ['#4a5563', 'fa-undo', 'Refunded']];
$today = new DateTime('today');
foreach($rows as $b):
    [$sbg, $sfg, $sicon, $slabel] = $mbStatus[$b['status']] ?? ['#eef0f3', '#4a5563', 'fa-circle', ucfirst($b['status'])];
    [$pfg, $picon, $plabel] = $mbPay[$b['payment_status']] ?? ['#4a5563', 'fa-circle', ucfirst($b['payment_status'])];
    $n = nights($b['check_in'], $b['check_out']);
    $ci = new DateTime($b['check_in']); $co = new DateTime($b['check_out']);
    $days = (int)$today->diff($ci)->format('%r%a');
    $activeStatus = in_array($b['status'], ['pending', 'confirmed'], true);
    $when = '';
    if ($b['status'] === 'checked_in') $when = 'Staying now';
    elseif ($activeStatus && $days > 1) $when = "In $days days";
    elseif ($activeStatus && $days === 1) $when = 'Tomorrow';
    elseif ($activeStatus && $days === 0) $when = 'Today';
    $canPay = $b['status'] === 'pending' && $b['payment_status'] !== 'paid';
    $canCancel = $activeStatus;
    $detailUrl = '/hotel/my_bookings/' . $b['booking_code'];
?>
<article class="mb-card<?=$b['status'] === 'cancelled' ? ' is-cancelled' : ''?>">
<a href="<?=e($detailUrl)?>" class="mb-media" aria-label="Booking <?=e($b['booking_code'])?>">
<?php if (!empty($b['room_image'])): ?><img src="<?=e($b['room_image'])?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?>
<i class="fas fa-bed mb-media-fallback"></i>
<span class="mb-status" style="background:<?=$sbg?>;color:<?=$sfg?>"><i class="fas <?=$sicon?>"></i> <?=$slabel?></span>
<?php if ($when): ?><span class="mb-when"><i class="fas fa-clock"></i> <?=$when?></span><?php endif; ?>
</a>
<div class="mb-body">
<div class="mb-head">
<div style="min-width:0">
<h3 class="mb-title"><?=e(rtrim($b['type_name'], '. '))?></h3>
<div class="mb-meta">Room <?=e($b['room_number'])?> &middot; <?=(int)$b['guests']?> guest<?=$b['guests'] == 1 ? '' : 's'?> &middot; <span class="mb-code"><?=e($b['booking_code'])?></span></div>
</div>
</div>
<div class="mb-dates">
<div><span>Check-in</span><strong><?=$ci->format('D, M j')?></strong><small><?=$ci->format('Y')?></small></div>
<div class="mb-nights"><i class="fas fa-moon"></i><?=$n?> night<?=$n == 1 ? '' : 's'?></div>
<div style="text-align:right"><span>Check-out</span><strong><?=$co->format('D, M j')?></strong><small><?=$co->format('Y')?></small></div>
</div>
</div>
<div class="mb-side">
<div class="mb-price">
<div>
<span class="mb-price-label">Total</span>
<strong>NPR <?=number_format((float)$b['total_amount'])?></strong>
</div>
<span class="mb-pay" style="color:<?=$pfg?>"><i class="fas <?=$picon?>"></i> <?=$plabel?></span>
</div>
<div class="mb-actions">
<?php if ($canPay): ?>
<a href="/hotel/payment.php?id=<?=$b['id']?>" class="mb-btn mb-khalti"><i class="fas fa-wallet"></i> Pay now</a>
<?php endif; ?>
<a href="<?=e($detailUrl)?>" class="mb-btn mb-primary"><i class="fas fa-file-invoice"></i> Details</a>
<div class="mb-more">
<a href="room.php?id=<?=$b['room_id']?>" class="mb-icon" title="View room" aria-label="View room"><i class="fas fa-eye"></i></a>
<?php if ($canCancel): ?>
<a href="cancel.php?id=<?=$b['id']?>" class="mb-icon mb-danger" title="Cancel booking" aria-label="Cancel booking" onclick="return confirm('Are you sure you want to cancel this booking?')"><i class="fas fa-times"></i></a>
<?php endif; ?>
</div>
</div>
</div>
</article>
<?php endforeach; ?>
</div>

<style>
.mb-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 14px}
.mb-count{color:#667085;font-size:14px}
.mb-count strong{color:#1d2939}
.mb-view{display:inline-flex;background:#fff;border:1px solid #e3e7ee;border-radius:10px;padding:3px}
.mb-view button{display:inline-flex;align-items:center;gap:6px;border:0;background:transparent;color:#667085;font-weight:600;font-size:13px;padding:7px 12px;border-radius:8px;cursor:pointer}
.mb-view button.active{background:var(--primary);color:#fff}
.mb-cards{display:grid;gap:18px}
.mb-grid{grid-template-columns:repeat(auto-fill,minmax(300px,1fr))}
.mb-card{background:#fff;border:1px solid #e6e9ef;border-radius:16px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 1px 3px rgba(16,24,40,.04);transition:box-shadow .2s,transform .2s}
.mb-card:hover{box-shadow:0 10px 24px rgba(16,24,40,.08);transform:translateY(-2px)}
.mb-card.is-cancelled .mb-media img{filter:grayscale(.7) opacity(.8)}
.mb-media{position:relative;display:block;aspect-ratio:16/9;background:#eef1f6;overflow:hidden}
.mb-media img{position:absolute;inset:0;z-index:1;width:100%;height:100%;object-fit:cover;transition:transform .4s}
.mb-card:hover .mb-media img{transform:scale(1.04)}
.mb-media-fallback{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:28px;color:#c5ccd6}
.mb-media:after{content:"";position:absolute;inset:auto 0 0 0;height:45%;background:linear-gradient(transparent,rgba(0,0,0,.35));pointer-events:none;z-index:1}
.mb-status{position:absolute;top:12px;left:12px;z-index:2;display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border-radius:999px;font-size:12px;font-weight:700;box-shadow:0 2px 6px rgba(0,0,0,.1)}
.mb-when{position:absolute;bottom:12px;left:12px;z-index:2;color:#fff;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:6px;text-shadow:0 1px 2px rgba(0,0,0,.4)}
.mb-body{padding:16px 18px 0;flex:1}
.mb-title{margin:0;font-size:18px;color:#1d2939;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mb-meta{color:#667085;font-size:13px;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mb-code{font-family:ui-monospace,Consolas,monospace;font-size:12px}
.mb-dates{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:8px;margin:14px 0 0;padding:12px 14px;background:#f8fafc;border-radius:12px}
.mb-dates span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#98a2b3;font-weight:700}
.mb-dates strong{display:block;font-size:14px;color:#1d2939;margin-top:2px;white-space:nowrap}
.mb-dates small{color:#98a2b3;font-size:11px}
.mb-nights{display:flex;flex-direction:column;align-items:center;gap:3px;font-size:11px;font-weight:700;color:#667085;white-space:nowrap}
.mb-nights i{color:var(--accent)}
.mb-side{padding:14px 18px 16px}
.mb-price{display:flex;justify-content:space-between;align-items:flex-end;gap:10px;margin-bottom:12px}
.mb-price-label{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#98a2b3;font-weight:700}
.mb-price strong{font-size:20px;color:var(--primary);white-space:nowrap}
.mb-pay{font-size:12px;font-weight:700;white-space:nowrap;display:inline-flex;align-items:center;gap:5px}
.mb-actions{display:flex;gap:8px;align-items:center}
.mb-btn{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:10px 12px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;white-space:nowrap;transition:.15s}
.mb-primary{background:var(--primary);color:#fff}
.mb-primary:hover{background:#0f2b4d;color:#fff}
.mb-khalti{background:#5C2D91;color:#fff}
.mb-khalti:hover{background:#4a2377;color:#fff}
.mb-more{display:flex;gap:6px}
.mb-icon{width:38px;height:38px;display:inline-flex;align-items:center;justify-content:center;border-radius:10px;border:1px solid #e3e7ee;color:#475467;text-decoration:none;transition:.15s}
.mb-icon:hover{background:#f2f4f7;color:var(--primary)}
.mb-danger:hover{background:#fdeaea;color:#b42318;border-color:#f5c2c0}
/* List view */
.mb-list .mb-price{flex-direction:column;align-items:flex-start;gap:4px}
.mb-list{grid-template-columns:1fr;gap:12px}
.mb-list .mb-card{flex-direction:row;align-items:stretch}
.mb-list .mb-card:hover{transform:none}
.mb-list .mb-media{aspect-ratio:auto;width:220px;flex:none}
.mb-list .mb-body{padding:16px 18px;display:flex;flex-direction:column;justify-content:center;min-width:0}
.mb-list .mb-dates{margin-top:12px;max-width:380px}
.mb-list .mb-side{width:300px;flex:none;border-left:1px solid #f0f2f5;display:flex;flex-direction:column;justify-content:center}
@media (max-width:900px){
  .mb-list .mb-card{flex-direction:column}
  .mb-list .mb-media{width:100%;aspect-ratio:16/7}
  .mb-list .mb-side{width:auto;border-left:0;border-top:1px solid #f0f2f5}
  .mb-list .mb-dates{max-width:none}
}
@media (max-width:520px){.mb-view span{display:none}}
.mb-filters{display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:12px;align-items:end}
@media (max-width:900px){.mb-filters{grid-template-columns:1fr 1fr}.mb-filters>div:first-child{grid-column:1/-1}}
@media (max-width:520px){.mb-filters{grid-template-columns:1fr}}
</style>
<script>
(function () {
    var box = document.getElementById('mbCards');
    var btns = document.querySelectorAll('.mb-view button');
    function setView(v) {
        box.classList.toggle('mb-grid', v === 'grid');
        box.classList.toggle('mb-list', v === 'list');
        btns.forEach(function (b) { b.classList.toggle('active', b.dataset.view === v); b.setAttribute('aria-pressed', b.dataset.view === v); });
        try { localStorage.setItem('myBookingsView', v); } catch (e) {}
    }
    var saved = 'grid';
    try { saved = localStorage.getItem('myBookingsView') || 'grid'; } catch (e) {}
    setView(saved === 'list' ? 'list' : 'grid');
    btns.forEach(function (b) { b.addEventListener('click', function () { setView(b.dataset.view); }); });
})();
</script>
<?php endif; ?>

</div>
</div>
<?php require 'partials_user_footer.php'; ?>
