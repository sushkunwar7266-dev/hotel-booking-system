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

// Contact information
$contactName = trim($_POST['contact_name'] ?? '');
$contactEmail = trim($_POST['contact_email'] ?? '');
$contactPhone = trim($_POST['contact_phone'] ?? '');

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

// Contact information validation
if(empty($contactName)) {
    $errors[] = 'Contact name is required';
} elseif(strlen($contactName) < 3) {
    $errors[] = 'Contact name must be at least 3 characters';
}

if(empty($contactEmail)) {
    $errors[] = 'Contact email is required';
} elseif(!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
}

if(empty($contactPhone)) {
    $errors[] = 'Contact phone is required';
} elseif(!preg_match('/^[0-9]{10}$/', $contactPhone)) {
    $errors[] = 'Phone number must be exactly 10 digits';
}

// Special request validation
if(strlen($special) > 500) {
    $errors[] = 'Special request must not exceed 500 characters';
}

if(count($errors) > 0) {
    flash('error', implode("\n", $errors));
    redirect("room.php?id=$roomId&check_in=" . urlencode($in) . "&check_out=" . urlencode($out) . "&guests=$guests");
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
    redirect("room.php?id=$roomId&check_in=" . urlencode($in) . "&check_out=" . urlencode($out) . "&guests=$guests");
}

if(!is_room_available($roomId, $in, $out)) {
    flash('error', 'Room is no longer available for these dates');
    redirect('rooms.php?check_in=' . urlencode($in) . '&check_out=' . urlencode($out));
}

$pdo = db();
$pdo->beginTransaction();

try {
    // Lock the room row to prevent concurrent bookings (prevents race conditions)
    $lockStmt = $pdo->prepare("SELECT id, status FROM rooms WHERE id = ? FOR UPDATE");
    $lockStmt->execute([$roomId]);
    $lockedRoom = $lockStmt->fetch();
    
    if(!$lockedRoom) {
        throw new Exception('Room not found');
    }
    
    // Re-check room status inside transaction
    if($lockedRoom['status'] !== 'available') {
        throw new Exception('Room is no longer available');
    }
    
    // Re-check availability inside transaction with locked room
    $conflictCheck = $pdo->prepare("SELECT COUNT(*) FROM bookings 
        WHERE room_id = ? 
        AND status IN ('pending','confirmed','checked_in')
        AND check_in < ? 
        AND check_out > ?
        FOR UPDATE");
    $conflictCheck->execute([$roomId, $out, $in]);
    
    if((int)$conflictCheck->fetchColumn() > 0) {
        throw new Exception('Room is no longer available for these dates');
    }
    
    // Check for duplicate bookings (idempotency - prevent double clicks)
    $dupeCheck = $pdo->prepare("SELECT COUNT(*) FROM bookings 
        WHERE user_id = ? 
        AND room_id = ? 
        AND check_in = ? 
        AND check_out = ?
        AND status IN ('pending','confirmed')
        AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $dupeCheck->execute([$user['id'], $roomId, $in, $out]);
    
    if((int)$dupeCheck->fetchColumn() > 0) {
        throw new Exception('You have already made this booking. Please check your bookings page.');
    }
    
    $code = 'BK' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
    $total = $n * (float)$room['price'];
    
    // Store contact information in special_request field
    $bookingNotes = "Contact: $contactName\nEmail: $contactEmail\nPhone: $contactPhone";
    if($special) {
        $bookingNotes .= "\n\nSpecial Request: $special";
    }
    
    $s = $pdo->prepare("INSERT INTO bookings(booking_code,user_id,room_id,check_in,check_out,guests,total_amount,status,payment_status,special_request) VALUES(?,?,?,?,?,?,?,?,?,?)");
    $s->execute([$code, $user['id'], $roomId, $in, $out, $guests, $total, 'pending', 'pending', $bookingNotes]);
    
    $bid = (int)$pdo->lastInsertId();
    
    $pdo->commit();
    flash('success', "Booking request submitted! Your booking code is: $code. Please wait for admin confirmation.");
    redirect('my_bookings.php');
} catch(Throwable $e) {
    $pdo->rollBack();
    $errorMsg = $e->getMessage();
    if(strpos($errorMsg, 'already made this booking') !== false || 
       strpos($errorMsg, 'no longer available') !== false || 
       strpos($errorMsg, 'not found') !== false) {
        flash('error', $errorMsg);
    } else {
        flash('error', 'Could not create booking. Please try again.');
    }
    redirect("room.php?id=$roomId&check_in=" . urlencode($in) . "&check_out=" . urlencode($out) . "&guests=$guests");
}
