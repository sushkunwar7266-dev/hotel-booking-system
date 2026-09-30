<?php
require_once 'config/config.php';
$user = require_login();

$roomId = (int)($_GET['room_id'] ?? 0);
$in = $_GET['check_in'] ?? '';
$out = $_GET['check_out'] ?? '';
$guests = (int)($_GET['guests'] ?? 1);
$back = "room.php?id=$roomId&check_in=" . urlencode($in) . "&check_out=" . urlencode($out) . "&guests=$guests";

$s = db()->prepare("SELECT r.*, rt.name type_name, rt.capacity type_capacity FROM rooms r JOIN room_types rt ON rt.id=r.room_type_id WHERE r.id=? AND r.status='available'");
$s->execute([$roomId]);
$room = $s->fetch();
if (!$room) {
    flash('error', 'Room not found or unavailable');
    redirect('rooms.php');
}
$capacity = !empty($room['capacity']) ? (int)$room['capacity'] : 2;

// Validate the stay before showing checkout
$errors = [];
$ci = strtotime($in); $co = strtotime($out);
if (!$ci || !$co || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $in) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $out)) $errors[] = 'Please select valid check-in and check-out dates.';
elseif ($ci < strtotime('today')) $errors[] = 'Check-in date cannot be in the past.';
elseif ($co <= $ci) $errors[] = 'Check-out date must be after check-in date.';
elseif (($co - $ci) / 86400 > 30) $errors[] = 'Maximum booking duration is 30 nights.';
if ($guests < 1 || $guests > $capacity) $errors[] = "Guests must be between 1 and $capacity for this room.";
if (!$errors && !is_room_available($roomId, $in, $out)) $errors[] = 'This room is no longer available for the selected dates.';
if ($errors) {
    flash('error', implode("\n", $errors));
    redirect($back);
}

$n = nights($in, $out);
$price = (float)$room['price'];
$total = $n * $price;

// Re-fill the form after a failed submission
$old = $_SESSION['checkout_old'] ?? [];
unset($_SESSION['checkout_old']);
$val = fn($k, $d = '') => $old[$k] ?? $d;
$method = $val('payment_method', 'khalti') === 'hotel' ? 'hotel' : 'khalti';
$sandbox = strpos(KHALTI_BASE_URL, 'dev.khalti.com') !== false;

$title = 'Checkout | ' . APP_NAME;
require 'partials_header.php';
?>
<style>
.checkout-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:28px;align-items:start}
.co-card{background:#fff;border:1px solid #e6e9ef;border-radius:14px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,.04);margin-bottom:20px}
.co-card h3{margin:0 0 18px;font-size:18px;display:flex;align-items:center;gap:10px}
.co-card h3 .step{width:28px;height:28px;border-radius:50%;background:var(--primary);color:#fff;font-size:14px;display:inline-flex;align-items:center;justify-content:center}
.co-card input,.co-card textarea{width:100%;padding:12px;border:1px solid #d9dee7;border-radius:9px;font-family:inherit;font-size:14px;box-sizing:border-box}
.co-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.pm{display:flex;align-items:center;gap:14px;padding:16px;border:2px solid #d9dee7;border-radius:12px;cursor:pointer;margin-bottom:12px;transition:.15s}
.pm input{width:18px;height:18px;flex:none}
.pm.active.khalti{border-color:#5C2D91;background:#f8f4fc}
.pm.active.hotel{border-color:var(--accent);background:#fff7ef}
.pm-logo{width:46px;height:46px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex:none}
.summary{position:sticky;top:90px}
.summary img{width:100%;height:190px;object-fit:cover;border-radius:10px;margin-bottom:14px;background:#eef3f8}
.sum-row{display:flex;justify-content:space-between;gap:10px;padding:9px 0;border-bottom:1px dashed #e6e9ef;font-size:14px}
.sum-row span:first-child{color:var(--muted)}
.sum-total{display:flex;justify-content:space-between;padding:14px 0 4px;font-size:20px;font-weight:800;color:var(--primary)}
.steps{display:flex;gap:10px;align-items:center;margin-bottom:24px;font-size:14px;color:var(--muted);flex-wrap:wrap}
.steps b{color:var(--primary)}
@media (max-width:900px){.checkout-grid{grid-template-columns:1fr}.summary{position:static}.co-row{grid-template-columns:1fr}}
</style>
<div class="page"><div class="container">
<a href="<?=e($back)?>" style="color:var(--primary);text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:6px;margin-bottom:14px"><i class="fas fa-arrow-left"></i> Back to room</a>
<h1 style="margin:0 0 8px">Checkout</h1>
<div class="steps"><span><i class="fas fa-check-circle" style="color:#28a745"></i> Select room</span><i class="fas fa-chevron-right"></i><b>Details &amp; payment</b><i class="fas fa-chevron-right"></i><span>Confirmation</span></div>

<form action="book.php" method="post" novalidate id="checkoutForm">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="room_id" value="<?=$roomId?>">
<input type="hidden" name="check_in" value="<?=e($in)?>">
<input type="hidden" name="check_out" value="<?=e($out)?>">
<input type="hidden" name="guests" value="<?=$guests?>">

<div class="checkout-grid">
<div>
<div class="co-card">
<h3><span class="step">1</span> Contact Information</h3>
<div class="form-group">
<label>Full Name</label>
<input type="text" name="contact_name" value="<?=e($val('contact_name', $user['name']))?>" required minlength="3" maxlength="120">
</div>
<div class="co-row">
<div class="form-group">
<label>Email</label>
<input type="email" name="contact_email" value="<?=e($val('contact_email', $user['email']))?>" required maxlength="190">
</div>
<div class="form-group">
<label>Phone Number</label>
<input type="tel" name="contact_phone" value="<?=e($val('contact_phone', $user['phone'] ?? ''))?>" required pattern="[0-9]{10}" maxlength="10" placeholder="9800000000">
<div class="form-hint">10 digit number</div>
</div>
</div>
<div class="form-group" style="margin-bottom:0">
<label>Special requests <span style="font-weight:400;color:var(--muted)">(Optional)</span></label>
<textarea name="special_request" maxlength="500" rows="3" placeholder="Early check-in, extra bed, airport pickup..." style="resize:vertical"><?=e($val('special_request'))?></textarea>
<div class="form-hint">Maximum 500 characters</div>
</div>
</div>

<div class="co-card">
<h3><span class="step">2</span> Payment Method</h3>
<label class="pm khalti <?=$method==='khalti'?'active':''?>">
<input type="radio" name="payment_method" value="khalti" <?=$method==='khalti'?'checked':''?> style="accent-color:#5C2D91">
<span class="pm-logo" style="background:#5C2D91;color:#fff"><i class="fas fa-wallet"></i></span>
<span style="display:flex;flex-direction:column;gap:3px">
<strong style="color:#5C2D91">Pay with Khalti</strong>
<span style="font-size:13px;color:var(--muted)">Khalti wallet, e-banking, mobile banking, ConnectIPS or card. Your booking is confirmed as soon as the payment is verified.</span>
</span>
</label>
<label class="pm hotel <?=$method==='hotel'?'active':''?>">
<input type="radio" name="payment_method" value="hotel" <?=$method==='hotel'?'checked':''?> style="accent-color:var(--accent)">
<span class="pm-logo" style="background:#fff1e3;color:var(--accent)"><i class="fas fa-hotel"></i></span>
<span style="display:flex;flex-direction:column;gap:3px">
<strong>Pay at Hotel</strong>
<span style="font-size:13px;color:var(--muted)">Pay in cash on arrival. Your booking stays pending until the hotel confirms it.</span>
</span>
</label>
<?php if ($sandbox): ?>
<div class="form-hint"><i class="fas fa-flask"></i> Khalti sandbox mode: use Khalti ID 9800000005, MPIN 1111, OTP 987654. No real money is charged.</div>
<?php endif; ?>
</div>
</div>

<aside class="summary">
<div class="co-card">
<h3><i class="fas fa-receipt" style="color:var(--accent)"></i> Booking Summary</h3>
<?php if (!empty($room['image'])): ?><img src="<?=e($room['image'])?>" alt="<?=e($room['type_name'])?>"><?php endif; ?>
<div style="font-size:18px;font-weight:700"><?=e($room['type_name'])?></div>
<div style="color:var(--muted);font-size:14px;margin-bottom:10px">Room <?=e($room['room_number'])?></div>
<div class="sum-row"><span><i class="fas fa-sign-in-alt"></i> Check-in</span><strong><?=date('D, M j, Y', $ci)?></strong></div>
<div class="sum-row"><span><i class="fas fa-sign-out-alt"></i> Check-out</span><strong><?=date('D, M j, Y', $co)?></strong></div>
<div class="sum-row"><span><i class="fas fa-moon"></i> Nights</span><strong><?=$n?></strong></div>
<div class="sum-row"><span><i class="fas fa-users"></i> Guests</span><strong><?=$guests?></strong></div>
<div class="sum-row"><span>NPR <?=number_format($price, 2)?> × <?=$n?> night<?=$n!=1?'s':''?></span><strong>NPR <?=number_format($total, 2)?></strong></div>
<div class="sum-total"><span>Total</span><span>NPR <?=number_format($total, 2)?></span></div>
<a href="<?=e($back)?>" style="font-size:13px;color:var(--primary)">Change dates or guests</a>
<button type="submit" class="btn orange" style="width:100%;padding:16px;font-size:16px;margin-top:18px">
<i class="fas fa-lock" style="margin-right:8px"></i><span id="payBtnText"><?=$method==='khalti' ? 'Pay NPR '.number_format($total, 2).' with Khalti' : 'Confirm Booking'?></span>
</button>
<div class="form-hint" style="text-align:center;margin-top:10px">By confirming you agree to the hotel's booking and cancellation policy.</div>
</div>
</aside>
</div>
</form>
</div></div>
<script>
document.querySelectorAll('input[name="payment_method"]').forEach(function (r) {
    r.addEventListener('change', function () {
        document.querySelectorAll('.pm').forEach(function (l) { l.classList.toggle('active', l.querySelector('input').checked); });
        document.getElementById('payBtnText').textContent = this.value === 'khalti'
            ? <?=js('Pay NPR ' . number_format($total, 2) . ' with Khalti')?> : 'Confirm Booking';
    });
});
</script>
<?php require 'partials_footer.php'; ?>
