<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$code = $_GET['code'] ?? '';
if (!preg_match('/^[A-Za-z0-9]{4,30}$/', $code)) {
    flash('error', 'Booking not found');
    redirect('/hotel/admin/bookings.php');
}

$s = db()->prepare("SELECT b.*, r.room_number, r.image room_image, r.amenities room_amenities, r.price room_price, r.status room_status,
                           rt.name type_name, rt.capacity type_capacity, u.name user_name, u.email user_email, u.phone user_phone, u.created_at user_since
                    FROM bookings b JOIN rooms r ON r.id=b.room_id JOIN room_types rt ON rt.id=r.room_type_id JOIN users u ON u.id=b.user_id
                    WHERE b.booking_code=?");
$s->execute([$code]);
$b = $s->fetch();
if (!$b) {
    flash('error', 'Booking not found');
    redirect('/hotel/admin/bookings.php');
}

$ps = db()->prepare("SELECT * FROM payments WHERE booking_id=? ORDER BY id DESC");
$ps->execute([$b['id']]);
$payments = $ps->fetchAll();
$paid = null;
foreach ($payments as $row) { if ($row['status'] === 'paid') { $paid = $row; break; } }
$latest = $paid ?? ($payments[0] ?? null);

$cs = db()->prepare("SELECT COUNT(*) total, SUM(status NOT IN ('cancelled')) active FROM bookings WHERE user_id=?");
$cs->execute([$b['user_id']]);
$custStats = $cs->fetch();

// Contact details and special request are stored together
$contact = ['name' => $b['user_name'], 'email' => $b['user_email'], 'phone' => $b['user_phone'] ?? ''];
$request = (string)$b['special_request'];
if (preg_match('/^Contact: (.*)\nEmail: (.*)\nPhone: (.*?)(?:\n\nSpecial Request: (.*))?$/s', $request, $m)) {
    $contact = ['name' => $m[1], 'email' => $m[2], 'phone' => $m[3]];
    $request = $m[4] ?? '';
}

$n = nights($b['check_in'], $b['check_out']);
$rate = $n > 0 ? (float)$b['total_amount'] / $n : (float)$b['room_price'];
$isPaid = $b['payment_status'] === 'paid';
$methodNames = ['khalti' => 'Khalti', 'cash' => 'Cash', 'demo' => 'Demo', 'esewa' => 'eSewa', 'card' => 'Card'];
$method = $latest ? ($methodNames[$latest['method']] ?? ucfirst($latest['method'])) : 'Pay at Hotel (not yet paid)';
$colors = ['pending' => ['#fff4e0', '#b26a00'], 'confirmed' => ['#e6f6ec', '#1e7b3c'], 'checked_in' => ['#e3effd', '#1d5fa8'],
           'checked_out' => ['#eef0f3', '#4a5563'], 'cancelled' => ['#fdeaea', '#b42318'], 'paid' => ['#e6f6ec', '#1e7b3c'],
           'failed' => ['#fdeaea', '#b42318'], 'refunded' => ['#eef0f3', '#4a5563'], 'maintenance' => ['#fdeaea', '#b42318'],
           'available' => ['#e6f6ec', '#1e7b3c'], 'unavailable' => ['#eef0f3', '#4a5563']];
$badge = function (string $st, ?string $label = null) use ($colors) {
    [$bg, $fg] = $colors[$st] ?? ['#eef0f3', '#4a5563'];
    return '<span class="badge-pill" style="background:' . $bg . ';color:' . $fg . '">' . e($label ?? ucwords(str_replace('_', ' ', $st))) . '</span>';
};
$self = '/hotel/admin/booking/' . $b['booking_code'];

// Next sensible actions for the front desk
$quick = [
    'pending' => [['confirmed', 'Confirm Booking', 'fa-check', '#28a745'], ['cancelled', 'Cancel', 'fa-times', '#dc3545']],
    'confirmed' => [['checked_in', 'Check In Guest', 'fa-door-open', '#1d5fa8'], ['cancelled', 'Cancel', 'fa-times', '#dc3545']],
    'checked_in' => [['checked_out', 'Check Out Guest', 'fa-door-closed', '#4a5563']],
][$b['status']] ?? [];

// Progress through the booking life cycle
$steps = ['pending' => 'Booked', 'confirmed' => 'Confirmed', 'checked_in' => 'Checked in', 'checked_out' => 'Checked out'];
$order = array_keys($steps);
$current = array_search($b['status'], $order, true);

$title = 'Booking ' . $b['booking_code'] . ' | Admin | ' . APP_NAME;
require __DIR__ . '/../partials_header.php';
require __DIR__ . '/partials_admin_nav.php';
?>
<style>
.bd-grid{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:24px;align-items:start}
.bd-card{background:#fff;border:1px solid #e6e9ef;border-radius:14px;padding:22px;box-shadow:0 2px 8px rgba(0,0,0,.04);margin-bottom:20px}
.bd-card h3{margin:0 0 16px;font-size:17px;display:flex;align-items:center;gap:8px}
.bd-card h3 i{color:var(--accent)}
.badge-pill{display:inline-block;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700}
.kv{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px 20px}
.kv-2col{grid-template-columns:repeat(2,minmax(0,1fr))!important}
.kv div span{display:block;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;margin-bottom:3px}
.kv div strong{font-size:15px;word-break:break-word}
.room-box{display:grid;grid-template-columns:200px minmax(0,1fr);gap:18px}
.room-box img{width:100%;height:140px;object-fit:cover;border-radius:10px;background:#eef3f8}
.chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.chips span{background:#eef3f8;color:var(--primary);padding:5px 10px;border-radius:16px;font-size:12px;font-weight:600}
.receipt{font-size:14px}
.r-row{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 0;border-bottom:1px dashed #e6e9ef}
.r-row span:first-child{color:var(--muted)}
.r-total{display:flex;justify-content:space-between;padding:12px 0 2px;font-weight:800;font-size:18px;color:var(--primary)}
.paid-stamp{border:2px solid #28a745;color:#28a745;border-radius:8px;padding:4px 10px;font-weight:800;letter-spacing:.1em;display:inline-block;transform:rotate(-4deg)}
.ctrl label{display:block;font-size:12px;color:var(--muted);text-transform:uppercase;margin-bottom:4px}
.ctrl select{width:100%;padding:10px;border:1px solid #d9dee7;border-radius:8px;font-weight:600}
.ptable{width:100%;border-collapse:collapse;font-size:13px}
.ptable th,.ptable td{padding:8px 6px;border-bottom:1px solid #eef0f3;text-align:left}
.ptable th{color:var(--muted);font-size:11px;text-transform:uppercase}
.timeline{display:flex;justify-content:space-between;position:relative;margin:6px 0 4px}
.timeline:before{content:"";position:absolute;top:14px;left:12%;right:12%;height:3px;background:#e6e9ef}
.tl{position:relative;text-align:center;flex:1;font-size:12px;color:var(--muted)}
.tl i{width:30px;height:30px;border-radius:50%;background:#e6e9ef;color:#fff;display:inline-flex;align-items:center;justify-content:center;margin-bottom:6px;position:relative}
.tl.done i{background:#28a745}.tl.done{color:var(--dark);font-weight:600}
.print-only{display:none}
.bd-grid>*{min-width:0}
@media (max-width:560px){.kv{grid-template-columns:1fr}.bd-card{padding:16px}}
@media (max-width:1000px){.bd-grid{grid-template-columns:1fr}.kv{grid-template-columns:repeat(2,minmax(0,1fr))}.room-box{grid-template-columns:1fr}}
@media print{
  nav,footer,.admin-sidebar,.admin-mobile-toggle,.admin-sidebar-toggle,.admin-sidebar-overlay,.no-print,.toast-container{display:none!important}
  .admin-content-wrapper{margin-left:0!important}
  .print-only{display:block!important}
  body{background:#fff;font-size:11px;margin:0;padding:0}
  .page{padding:0!important;background:#fff!important;margin:0}
  .admin-content,.main-content,.container{margin:0!important;padding:12mm 15mm!important;max-width:none!important}
  .bd-grid{display:block!important;grid-template-columns:1fr!important}
  .bd-grid>*{width:100%!important;margin-bottom:8px!important;float:none!important}
  .bd-grid aside{display:block!important;width:100%!important}
  .room-box{display:block!important}
  .room-box img,.chips{display:none!important}
  .bd-card{box-shadow:none;border:1px solid #ddd;padding:8px;margin-bottom:8px;break-inside:avoid;page-break-inside:avoid}
  .bd-card h3{font-size:12px;margin:0 0 6px;border-bottom:1px solid #333;padding-bottom:3px;font-weight:700}
  .bd-card h3 i{font-size:11px}
  .kv,.kv-2col{grid-template-columns:repeat(2,1fr)!important;gap:4px 8px!important}
  .kv div span{font-size:8px;margin-bottom:1px;letter-spacing:.02em}
  .kv div strong{font-size:10px;line-height:1.3}
  .timeline{display:none!important}
  .receipt{font-size:10px}
  .receipt h3{margin-top:8px!important}
  .r-row{padding:4px 0;border-bottom:1px dashed #ccc;font-size:10px}
  .r-row span:first-child{font-size:10px}
  .r-row strong{font-size:10px}
  .r-total{font-size:13px;padding:6px 0 2px;border-top:2px solid #333;margin-top:4px}
  .paid-stamp{font-size:11px;padding:2px 6px;border-width:1.5px}
  .ptable{display:none!important}
  h1{font-size:16px!important;margin:2px 0 4px!important}
  .room-box>div{margin-top:0!important}
  .room-box>div>div:first-child{font-size:11px!important}
  .room-box>div>div:nth-child(2){font-size:9px!important;margin:2px 0!important}
  .badge-pill{font-size:8px;padding:2px 6px}
  div[style*="flex"][style*="space-between"]{margin-bottom:6px!important}
  div[style*="flex"][style*="space-between"] h1{margin-bottom:3px!important}
  div[style*="flex"][style*="space-between"]>div:first-child>div:first-child{font-size:10px!important}
  div[style*="background:#fff8ec"]{padding:6px!important;font-size:9px!important}
}
</style>

<div class="page" style="background:#f6f8fb">
<div class="container">

<div class="no-print" style="margin-bottom:14px">
<a href="/hotel/admin/bookings.php" style="color:var(--primary);text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:6px"><i class="fas fa-arrow-left"></i> Back to Bookings</a>
</div>

<div class="print-only" style="text-align:center;margin-bottom:12px;padding-bottom:8px;border-bottom:2px solid #333">
<div style="font-size:18px;font-weight:800;color:#173b67">StayEase Hotel</div>
<div style="color:#667085;font-size:11px;margin-top:2px">Kathmandu, Nepal · +977 980-0000000</div>
<h2 style="margin:6px 0 0;font-size:16px;color:#111">BOOKING RECEIPT</h2>
<div style="font-size:10px;color:#667085;margin-top:2px"><?=e($b['booking_code'])?> · <?=date('M j, Y')?></div>
</div>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px">
<div>
<div style="color:var(--muted);font-size:14px">Booking code</div>
<h1 style="margin:2px 0 8px;font-size:28px"><?=e($b['booking_code'])?></h1>
<div style="display:flex;gap:8px;flex-wrap:wrap"><?=$badge($b['status'])?> <?=$badge($b['payment_status'], 'Payment: ' . ucfirst($b['payment_status']))?></div>
</div>
<div class="no-print" style="display:flex;gap:10px;flex-wrap:wrap">
<?php foreach ($quick as [$st, $label, $icon, $color]): ?>
<form method="post" action="/hotel/admin/bookings.php" style="margin:0" <?=$st === 'cancelled' ? 'onsubmit="return confirm(\'Cancel this booking?\')"' : ''?>>
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="status">
<input type="hidden" name="id" value="<?=$b['id']?>">
<input type="hidden" name="status" value="<?=$st?>">
<input type="hidden" name="return" value="<?=e($self)?>">
<button class="btn" style="background:<?=$color?>;color:#fff;gap:8px"><i class="fas <?=$icon?>"></i> <?=$label?></button>
</form>
<?php endforeach; ?>
<button type="button" class="btn light" onclick="window.print()" style="gap:8px"><i class="fas fa-print"></i> <?=$isPaid ? 'Print Receipt' : 'Print Invoice'?></button>
</div>
</div>

<?php if ($b['status'] !== 'cancelled'): ?>
<div class="bd-card">
<div class="timeline">
<?php foreach ($steps as $k => $label): $done = $current !== false && array_search($k, $order, true) <= $current; ?>
<div class="tl <?=$done ? 'done' : ''?>"><i class="fas fa-<?=$done ? 'check' : 'circle'?>" style="font-size:<?=$done ? '13px' : '8px'?>"></i><br><?=$label?></div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<div class="bd-grid">
<div>
<div class="bd-card">
<h3><i class="fas fa-calendar-alt"></i> Stay Details</h3>
<div class="kv kv-2col">
<div><span>Check-in</span><strong><?=date('M j, Y', strtotime($b['check_in']))?></strong></div>
<div><span>Check-out</span><strong><?=date('M j, Y', strtotime($b['check_out']))?></strong></div>
<div><span>Nights</span><strong><?=$n?> night<?=$n == 1 ? '' : 's'?></strong></div>
<div><span>Booked on</span><strong><?=date('M j, Y', strtotime($b['created_at']))?></strong></div>
</div>
<?php if ($request !== ''): ?>
<div style="margin-top:14px"><span style="font-size:11px;color:var(--muted);text-transform:uppercase">Special request</span>
<div style="background:#fff8ec;border-left:3px solid var(--accent);border-radius:6px;padding:10px;margin-top:4px;font-size:13px;white-space:pre-line"><?=e($request)?></div></div>
<?php endif; ?>
</div>

<div class="bd-card">
<h3><i class="fas fa-user"></i> Guest Information</h3>
<div class="kv kv-2col">
<div><span>Name</span><strong><?=e($contact['name'])?></strong></div>
<div><span>Email</span><strong><?=e($contact['email'])?></strong></div>
<div><span>Phone</span><strong><?=e($contact['phone'] ?: '-')?></strong></div>
<div><span>Guests</span><strong><?=(int)$b['guests']?> person<?=$b['guests'] == 1 ? '' : 's'?></strong></div>
</div>
</div>

<div class="bd-card">
<h3><i class="fas fa-bed"></i> Room</h3>
<div class="room-box">
<?php if (!empty($b['room_image'])): ?><img src="<?=e($b['room_image'])?>" alt=""><?php endif; ?>
<div>
<div style="font-size:16px;font-weight:700"><?=e($b['type_name'])?> · Room <?=e($b['room_number'])?></div>
<div style="color:var(--muted);margin:4px 0">NPR <?=number_format((float)$b['room_price'])?> / night · Up to <?=(int)$b['type_capacity']?> guests</div>
<?php if (!empty($b['room_amenities'])): ?>
<div class="chips no-print"><?php foreach (array_filter(array_map('trim', explode(',', $b['room_amenities']))) as $a): ?><span><?=e($a)?></span><?php endforeach; ?></div>
<?php endif; ?>
</div>
</div>
</div>

<div class="bd-card no-print">
<h3><i class="fas fa-history"></i> Payment History</h3>
<?php if (!$payments): ?>
<p class="muted" style="margin:0">No payment records yet.</p>
<?php else: ?>
<div style="overflow-x:auto">
<table class="ptable">
<tr><th>#</th><th>Method</th><th>Amount</th><th>Status</th><th>Transaction ID</th><th>Date</th></tr>
<?php foreach ($payments as $pr): ?>
<tr>
<td><?=$pr['id']?></td>
<td><?=e($methodNames[$pr['method']] ?? $pr['method'])?></td>
<td>NPR <?=number_format((float)$pr['amount'], 2)?></td>
<td><?=$badge($pr['status'])?></td>
<td style="font-family:monospace;font-size:12px"><?=e($pr['transaction_id'] ?: '-')?></td>
<td><?=date('M j, Y g:i A', strtotime($pr['paid_at'] ?? $pr['created_at']))?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>
</div>
</div>

<aside>
<div class="bd-card no-print ctrl">
<h3><i class="fas fa-sliders-h"></i> Manage</h3>
<form method="post" action="/hotel/admin/bookings.php" style="margin-bottom:14px">
<input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="status">
<input type="hidden" name="id" value="<?=$b['id']?>"><input type="hidden" name="return" value="<?=e($self)?>">
<label>Booking status</label>
<select name="status" onchange="this.form.submit()">
<?php foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed', 'checked_in' => 'Checked In', 'checked_out' => 'Checked Out', 'cancelled' => 'Cancelled'] as $k => $v): ?>
<option value="<?=$k?>" <?=$b['status'] === $k ? 'selected' : ''?>><?=$v?></option>
<?php endforeach; ?>
</select>
</form>
<form method="post" action="/hotel/admin/bookings.php">
<input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="payment">
<input type="hidden" name="id" value="<?=$b['id']?>"><input type="hidden" name="return" value="<?=e($self)?>">
<label>Payment status</label>
<select name="payment_status" onchange="this.form.submit()">
<?php foreach (['pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed', 'refunded' => 'Refunded'] as $k => $v): ?>
<option value="<?=$k?>" <?=$b['payment_status'] === $k ? 'selected' : ''?>><?=$v?></option>
<?php endforeach; ?>
</select>
<div class="form-hint">Marking an unpaid booking as paid records a cash payment.</div>
</form>
</div>

<div class="bd-card receipt">
<h3 style="justify-content:space-between"><span style="display:flex;gap:8px;align-items:center"><i class="fas fa-receipt"></i> Payment Summary</span><?php if ($isPaid): ?><span class="paid-stamp">PAID</span><?php endif; ?></h3>
<div class="r-row"><span><?=e($b['type_name'])?>, Room <?=e($b['room_number'])?></span><span></span></div>
<div class="r-row"><span>NPR <?=number_format($rate, 2)?> × <?=$n?> night<?=$n != 1 ? 's' : ''?></span><strong>NPR <?=number_format((float)$b['total_amount'], 2)?></strong></div>
<div class="r-total"><span>Total Amount</span><span>NPR <?=number_format((float)$b['total_amount'], 2)?></span></div>
<div style="margin-top:14px;padding-top:14px;border-top:1px solid #e6e9ef">
<div class="r-row" style="border:none;padding:4px 0"><span>Payment Method</span><strong><?=e($method)?></strong></div>
<div class="r-row" style="border:none;padding:4px 0"><span>Payment Status</span><?=$badge($b['payment_status'])?></div>
<?php if ($latest && $latest['paid_at']): ?>
<div class="r-row" style="border:none;padding:4px 0"><span>Paid on</span><strong><?=date('M j, Y g:i A', strtotime($latest['paid_at']))?></strong></div>
<?php endif; ?>
</div>
</div>
</aside>
</div>
</div>
</div>
<script>
// Keep "Bookings" highlighted in the sidebar on this page
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.admin-nav-item[href="/hotel/admin/bookings.php"]').forEach(function (a) { a.classList.add('active'); });
});
</script>
<?php require __DIR__ . '/partials_admin_footer.php'; ?>
