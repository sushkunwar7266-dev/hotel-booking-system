<?php
/**
 * Khalti return_url. Khalti redirects here (GET) with pidx, status, transaction_id, etc.
 * The query string is never trusted: the payment is confirmed only through the lookup API.
 */
require_once 'config/config.php';
$user = require_login();

$pidx = trim($_GET['pidx'] ?? '');
if ($pidx === '' || !preg_match('/^[A-Za-z0-9]{10,64}$/', $pidx)) {
    flash('error', 'Invalid payment response.');
    redirect('my_bookings.php');
}

// The pidx must belong to a Khalti payment for one of this user's bookings
$s = db()->prepare("SELECT p.id pay_id, p.status pay_status, b.id booking_id, b.total_amount, b.status booking_status
                    FROM payments p JOIN bookings b ON b.id = p.booking_id
                    WHERE p.pidx = ? AND p.method = 'khalti' AND b.user_id = ?");
$s->execute([$pidx, $user['id']]);
$pay = $s->fetch();
if (!$pay) {
    flash('error', 'Payment not found.');
    redirect('my_bookings.php');
}
if ($pay['pay_status'] === 'paid') {
    flash('success', 'Payment already verified. Your booking is confirmed.');
    redirect('my_bookings.php');
}

$res = khalti_request('epayment/lookup/', ['pidx' => $pidx]);
$data = $res['body'] ?? [];
$status = $data['status'] ?? '';
$expectedPaisa = (int)round((float)$pay['total_amount'] * 100);

if ($status === 'Completed') {
    if ((int)($data['total_amount'] ?? 0) !== $expectedPaisa) {
        error_log("Khalti amount mismatch for pidx $pidx: expected $expectedPaisa, got " . json_encode($data));
        flash('error', 'Payment amount mismatch. Please contact the hotel with your booking code.');
        redirect('my_bookings.php');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE payments SET status='paid', transaction_id=?, paid_at=NOW() WHERE id=? AND status<>'paid'")
            ->execute([$data['transaction_id'] ?? null, $pay['pay_id']]);
        $pdo->prepare("UPDATE bookings SET payment_status='paid', status=IF(status='pending','confirmed',status) WHERE id=?")
            ->execute([$pay['booking_id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('Khalti confirm failed: ' . $e->getMessage());
        flash('error', 'Payment received but the booking could not be updated. Please contact the hotel.');
        redirect('my_bookings.php');
    }
    flash('success', 'Payment successful via Khalti. Your booking is confirmed.');
} elseif (in_array($status, ['User canceled', 'Expired'], true)) {
    db()->prepare("UPDATE payments SET status='failed' WHERE id=? AND status='pending'")->execute([$pay['pay_id']]);
    flash('error', $status === 'Expired' ? 'The Khalti payment link expired. Please try again.' : 'Payment was cancelled. You can try again from My Bookings.');
} elseif (in_array($status, ['Pending', 'Initiated'], true)) {
    flash('error', 'Your Khalti payment is still pending. Please check again later or contact the hotel.');
} elseif ($status === 'Refunded' || $status === 'Partially Refunded') {
    db()->prepare("UPDATE payments SET status='refunded' WHERE id=?")->execute([$pay['pay_id']]);
    flash('error', 'This Khalti payment was refunded.');
} else {
    error_log("Khalti lookup unexpected response for pidx $pidx: HTTP {$res['code']} " . json_encode($data));
    flash('error', 'Could not verify the payment with Khalti. Please contact the hotel.');
}
redirect('my_bookings.php');
