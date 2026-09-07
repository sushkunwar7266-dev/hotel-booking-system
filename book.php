<?php
require_once 'config/config.php';
$user = require_login();

if($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('rooms.php');
verify_csrf();

$roomId = (int)$_POST['room_id'];
$in = $_POST['check_in'];
$out = $_POST['check_out'];
$guests = (int)$_POST['guests'];
$special = trim($_POST['special_request'] ?? '');

// Validation
$errors = [];

// Date validation
if(empty($in) || empty($out)) {
    $errors[] = 'Check-in and check-out dates are required';
} else {
    $checkIn = strtotime($in);
    $checkOut = strtotime($out);
    $today = strtotime('today');
    
    if($checkIn < $today) {
        $errors[] = 'Check-in date cannot be in the past';
    }
    
    if($checkOut <= $checkIn) {
        $errors[] = 'Check-out date must be after check-in date';
    }
    
    $daysDiff = ($checkOut - $checkIn) / (60 * 60 * 24);
    if($daysDiff > 30) {
        $errors[] = 'Maximum booking duration is 30 days';
    }
    
    if($daysDiff < 1) {
        $errors[] = 'Minimum booking duration is 1 night';
    }
}

// Guests validation
if($guests < 1) {
    $errors[] = 'At least 1 guest is required';
} elseif($guests > 10) {
    $errors[] = 'Maximum 10 guests allowed';
}

// Room validation
if($roomId <= 0) {
    $errors[] = 'Invalid room selection';
}

// Special request validation
if(strlen($special) > 500) {
    $errors[] = 'Special request must not exceed 500 characters';
}

if(count($errors) > 0) {
    flash('error', implode('<br>', $errors));
    redirect('rooms.php');
}

$n = nights($in, $out);
$s = db()->prepare("SELECT r.*, rt.capacity FROM rooms r JOIN room_types rt ON rt.id=r.room_type_id WHERE r.id=? AND r.status='available'");
$s->execute([$roomId]);
$room = $s->fetch();

if(!$room) {
    flash('error', 'Room not found or unavailable');
    redirect('rooms.php');
}

if($guests > $room['capacity']) {
    flash('error', 'Number of guests exceeds room capacity (' . $room['capacity'] . ')');
    redirect('rooms.php?check_in=' . urlencode($in) . '&check_out=' . urlencode($out));
}

if(!is_room_available($roomId, $in, $out)) {
    flash('error', 'Room is no longer available for these dates');
    redirect('rooms.php?check_in=' . urlencode($in) . '&check_out=' . urlencode($out));
}

$pdo = db();
$pdo->beginTransaction();

try {
    $code = 'BK' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
    $total = $n * (float)$room['price'];
    
    $s = $pdo->prepare("INSERT INTO bookings(booking_code,user_id,room_id,check_in,check_out,guests,total_amount,status,special_request) VALUES(?,?,?,?,?,?,?,?,?)");
    $s->execute([$code, $user['id'], $roomId, $in, $out, $guests, $total, 'pending', $special]);
    
    $bid = (int)$pdo->lastInsertId();
    
    $s = $pdo->prepare("INSERT INTO payments(booking_id,amount,method,status) VALUES(?,?,?,?)");
    $s->execute([$bid, $total, 'demo', 'pending']);
    
    $pdo->commit();
    redirect("payment.php?id=$bid");
} catch(Throwable $e) {
    $pdo->rollBack();
    flash('error', 'Could not create booking. Please try again.');
    redirect('rooms.php');
}
