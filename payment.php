<?php
require_once 'config/config.php';
$user = require_login();
$id = (int)($_GET['id'] ?? 0);

$s = db()->prepare("SELECT b.*, r.room_number, rt.name type_name FROM bookings b JOIN rooms r ON r.id=b.room_id JOIN room_types rt ON rt.id=r.room_type_id WHERE b.id=? AND b.user_id=?");
$s->execute([$id, $user['id']]);
$b = $s->fetch();
if (!$b) {
    flash('error', 'Booking not found');
    redirect('my_bookings.php');
}

$payable = $b['status'] === 'pending' && $b['payment_status'] !== 'paid';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$payable) {
        flash('error', 'This booking has already been processed.');
        redirect('my_bookings.php');
    }

    $url = khalti_start($id, $user, $err);
    if (!$url) {
        flash('error', $err);
        redirect("payment.php?id=$id");
    }
    header('Location: ' . $url);
    exit;
}

$title = 'Payment | ' . APP_NAME;
require 'partials_header.php';
?>
<div class="page"><div class="panel auth">
<h2>Complete Payment</h2>
<p>Booking <strong><?=e($b['booking_code'])?></strong></p>
<p><?=e($b['type_name'])?> · Room <?=e($b['room_number'])?></p>
<p><?=e($b['check_in'])?> → <?=e($b['check_out'])?> · <?=nights($b['check_in'], $b['check_out'])?> night(s)</p>
<h2>NPR <?=number_format((float)$b['total_amount'], 2)?></h2>
<?php if ($payable): ?>
<form method="post">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<button class="btn" style="width:100%;background:#5C2D91;color:#fff;display:inline-flex;align-items:center;justify-content:center;gap:8px">
<i class="fas fa-wallet"></i> Pay with Khalti
</button>
</form>
<p class="muted" style="font-size:12px;margin-top:10px">You will be redirected to Khalti to complete the payment securely. Your booking is confirmed once Khalti verifies the payment.</p>
<?php if (strpos(KHALTI_BASE_URL, 'dev.khalti.com') !== false): ?>
<div class="alert success" style="font-size:13px">Sandbox mode: use Khalti ID 9800000000 to 9800000005, MPIN 1111, OTP 987654. No real money is charged.</div>
<?php endif; ?>
<?php else: ?>
<div class="alert success">This booking is <?=e($b['status'])?> and payment is <?=e($b['payment_status'])?>. No payment is needed.</div>
<?php endif; ?>
<a href="my_bookings.php" class="btn light" style="width:100%;margin-top:10px;text-align:center">Back to My Bookings</a>
</div></div>
<?php require 'partials_footer.php'; ?>
