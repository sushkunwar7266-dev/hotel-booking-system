<?php 
require_once '../config/config.php';
require_admin();
$title = 'Manage Bookings | ' . APP_NAME;

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'status';
    $id = (int)$_POST['id'];
    
    if($action === 'status') {
        $status = $_POST['status'];
        $allowed = ['pending', 'confirmed', 'cancelled', 'checked_in', 'checked_out'];
        if(in_array($status, $allowed, true)) {
            $s = db()->prepare("UPDATE bookings SET status=? WHERE id=?");
            $s->execute([$status, $id]);
            flash('success', 'Booking status updated successfully');
        }
    } elseif($action === 'payment') {
        $paymentStatus = $_POST['payment_status'];
        $allowedPayment = ['pending', 'paid', 'failed', 'refunded'];
        if(in_array($paymentStatus, $allowedPayment, true)) {
            $s = db()->prepare("UPDATE payments SET status=?, paid_at=? WHERE booking_id=?");
            $paidAt = $paymentStatus === 'paid' ? date('Y-m-d H:i:s') : null;
            $s->execute([$paymentStatus, $paidAt, $id]);
            flash('success', 'Payment status updated successfully');
        }
    }
    redirect('bookings.php');
}

// Get filters from URL
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$paymentFilter = $_GET['payment'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query with filters
$sql = "SELECT b.*,u.name,u.email,u.phone,r.room_number,rt.name type_name,p.status payment_status,p.id payment_id 
        FROM bookings b 
        JOIN users u ON u.id=b.user_id 
        JOIN rooms r ON r.id=b.room_id 
        JOIN room_types rt ON rt.id=r.room_type_id 
        LEFT JOIN payments p ON p.booking_id=b.id 
        WHERE 1=1";

$params = [];

if($search !== '') {
    $sql .= " AND (b.booking_code LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR rt.name LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

if($statusFilter !== '') {
    $sql .= " AND b.status = ?";
    $params[] = $statusFilter;
}

if($paymentFilter !== '') {
    $sql .= " AND p.status = ?";
    $params[] = $paymentFilter;
}

if($dateFrom !== '') {
    $sql .= " AND b.check_in >= ?";
    $params[] = $dateFrom;
}

if($dateTo !== '') {
    $sql .= " AND b.check_out <= ?";
    $params[] = $dateTo;
}

$sql .= " ORDER BY b.created_at DESC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

require '../partials_header.php';
require 'partials_admin_nav.php';
?>

<div class="page" style="background:#f6f8fb">
<div class="container">
<div style="margin-bottom:32px">
<h1 style="margin:0 0 8px">Booking Management</h1>
<p class="muted">Manage customer bookings and payment status</p>
</div>

<!-- Search & Filter Panel -->
<div class="panel" style="margin-bottom:24px">
<form method="get" action="bookings.php">
<div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr auto;gap:12px;align-items:end">
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-search"></i> Search
</label>
<input type="text" name="search" value="<?=e($search)?>" placeholder="Booking code, name, email, phone..." style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
</div>
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-calendar"></i> Status
</label>
<select name="status" style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
<option value="">All Status</option>
<option value="pending" <?=$statusFilter==='pending'?'selected':''?>>Pending</option>
<option value="confirmed" <?=$statusFilter==='confirmed'?'selected':''?>>Confirmed</option>
<option value="cancelled" <?=$statusFilter==='cancelled'?'selected':''?>>Cancelled</option>
<option value="checked_in" <?=$statusFilter==='checked_in'?'selected':''?>>Checked In</option>
<option value="checked_out" <?=$statusFilter==='checked_out'?'selected':''?>>Checked Out</option>
</select>
</div>
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-credit-card"></i> Payment
</label>
<select name="payment" style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
<option value="">All Payment</option>
<option value="pending" <?=$paymentFilter==='pending'?'selected':''?>>Pending</option>
<option value="paid" <?=$paymentFilter==='paid'?'selected':''?>>Paid</option>
<option value="failed" <?=$paymentFilter==='failed'?'selected':''?>>Failed</option>
<option value="refunded" <?=$paymentFilter==='refunded'?'selected':''?>>Refunded</option>
</select>
</div>
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-calendar-alt"></i> From
</label>
<input type="date" name="date_from" value="<?=e($dateFrom)?>" style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
</div>
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-calendar-alt"></i> To
</label>
<input type="date" name="date_to" value="<?=e($dateTo)?>" style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
</div>
<div style="display:flex;gap:8px">
<button type="submit" class="btn orange" style="padding:10px 20px;white-space:nowrap;height:44px;display:inline-flex;align-items:center;gap:8px">
<i class="fas fa-filter"></i> Filter
</button>
<?php if($search || $statusFilter || $paymentFilter || $dateFrom || $dateTo): ?>
<a href="bookings.php" class="btn light" style="padding:10px 16px;white-space:nowrap;height:44px;display:inline-flex;align-items:center;justify-content:center" title="Clear filters">
<i class="fas fa-times"></i>
</a>
<?php endif; ?>
</div>
</div>
</form>
</div>

<?php if($search || $statusFilter || $paymentFilter || $dateFrom || $dateTo): ?>
<div style="margin-bottom:16px;padding:12px 16px;background:#fff3cd;border:1px solid #ffc107;border-radius:6px;display:flex;align-items:center;justify-content:space-between">
<div style="display:flex;align-items:center;gap:8px;font-size:14px">
<i class="fas fa-info-circle" style="color:#856404"></i>
<span style="color:#856404"><strong><?=count($rows)?></strong> booking(s) found with applied filters</span>
</div>
</div>
<?php endif; ?>

<div class="panel">
<?php if(count($rows) === 0): ?>
<div style="text-align:center;padding:60px 20px">
<i class="fas fa-search" style="font-size:64px;color:#d9dee7;margin-bottom:20px"></i>
<h3 style="color:var(--muted);margin:0 0 12px">No bookings found</h3>
<p class="muted" style="margin-bottom:24px">Try adjusting your search or filter criteria</p>
<a href="bookings.php" class="btn light"><i class="fas fa-redo"></i> Clear Filters</a>
</div>
<?php else: ?>
<div class="table-wrap">
<table class="table">
<thead>
<tr>
<th>Room & Booking</th>
<th>Customer & Contact</th>
<th>Check-in / Check-out</th>
<th>Amount</th>
<th>Guests</th>
<th>Payment</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($rows as $b): ?>
<tr>
<td>
<div style="font-weight:700;margin-bottom:4px"><?=e($b['type_name'])?> <span class="muted">#<?=e($b['room_number'])?></span></div>
<small class="muted"><?=e($b['booking_code'])?></small>
</td>
<td>
<div style="font-weight:600;margin-bottom:4px"><?=e($b['name'])?></div>
<small class="muted"><i class="fas fa-phone" style="font-size:10px"></i> <?=e($b['phone']??'N/A')?></small>
</td>
<td>
<div style="font-size:13px">
<div><i class="fas fa-calendar-check" style="color:#388e3c;font-size:11px"></i> <?=date('M d, Y', strtotime($b['check_in']))?></div>
<div class="muted"><i class="fas fa-calendar-times" style="font-size:11px"></i> <?=date('M d, Y', strtotime($b['check_out']))?></div>
</div>
</td>
<td><strong style="color:var(--primary)">NPR <?=number_format((float)$b['total_amount'])?></strong></td>
<td style="text-align:center"><span style="background:#eef3f8;padding:4px 10px;border-radius:12px;font-weight:700"><?=$b['guests']?></span></td>
<td>
<form method="post" style="margin:0">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="payment">
<input type="hidden" name="id" value="<?=$b['id']?>">
<select name="payment_status" onchange="this.form.submit()" style="padding:6px 10px;border:1px solid #d9dee7;border-radius:6px;font-size:12px;font-weight:600">
<option value="pending" <?=$b['payment_status']==='pending'?'selected':''?>>Pending</option>
<option value="paid" <?=$b['payment_status']==='paid'?'selected':''?>>Paid</option>
<option value="failed" <?=$b['payment_status']==='failed'?'selected':''?>>Failed</option>
<option value="refunded" <?=$b['payment_status']==='refunded'?'selected':''?>>Refunded</option>
</select>
</form>
</td>
<td>
<form method="post" style="margin:0">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="status">
<input type="hidden" name="id" value="<?=$b['id']?>">
<select name="status" onchange="this.form.submit()" style="padding:6px 10px;border:1px solid #d9dee7;border-radius:6px;font-size:12px;font-weight:600">
<option value="pending" <?=$b['status']==='pending'?'selected':''?>>Pending</option>
<option value="confirmed" <?=$b['status']==='confirmed'?'selected':''?>>Confirmed</option>
<option value="cancelled" <?=$b['status']==='cancelled'?'selected':''?>>Cancelled</option>
<option value="checked_in" <?=$b['status']==='checked_in'?'selected':''?>>Checked In</option>
<option value="checked_out" <?=$b['status']==='checked_out'?'selected':''?>>Checked Out</option>
</select>
</form>
</td>
<td>
<button class="btn small orange" onclick="showDetails<?=$b['id']?>()" style="font-size:12px;padding:8px 14px;white-space:nowrap;display:inline-flex;align-items:center;gap:6px">
<i class="fas fa-eye"></i> Details
</button>
</td>
</tr>
<tr id="details-<?=$b['id']?>" style="display:none">
<td colspan="8">
<div style="background:#f6f8fb;padding:20px;border-radius:8px;margin:8px 0">
<h4 style="margin:0 0 16px;color:var(--dark)"><i class="fas fa-info-circle" style="color:var(--accent)"></i> Booking Details</h4>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px">
<div>
<h5 style="margin:0 0 12px;font-size:14px;color:var(--muted);text-transform:uppercase">Customer Information</h5>
<div style="display:flex;flex-direction:column;gap:8px">
<div><i class="fas fa-user" style="color:var(--accent);width:20px"></i> <strong>Name:</strong> <?=e($b['name'])?></div>
<div><i class="fas fa-envelope" style="color:var(--accent);width:20px"></i> <strong>Email:</strong> <?=e($b['email'])?></div>
<div><i class="fas fa-phone" style="color:var(--accent);width:20px"></i> <strong>Phone:</strong> <?=e($b['phone']??'Not provided')?></div>
</div>
</div>
<div>
<h5 style="margin:0 0 12px;font-size:14px;color:var(--muted);text-transform:uppercase">Booking Information</h5>
<div style="display:flex;flex-direction:column;gap:8px">
<div><i class="fas fa-calendar" style="color:var(--accent);width:20px"></i> <strong>Booking Date:</strong> <?=date('M d, Y H:i', strtotime($b['created_at']))?></div>
<div><i class="fas fa-moon" style="color:var(--accent);width:20px"></i> <strong>Nights:</strong> <?=nights($b['check_in'], $b['check_out'])?></div>
<div><i class="fas fa-users" style="color:var(--accent);width:20px"></i> <strong>Total Guests:</strong> <?=$b['guests']?></div>
</div>
</div>
<div>
<h5 style="margin:0 0 12px;font-size:14px;color:var(--muted);text-transform:uppercase">Special Requests</h5>
<div style="background:#fff;padding:12px;border-radius:6px;border:1px solid #e6e9ef;min-height:80px;font-size:14px;color:var(--dark)">
<?=nl2br(e($b['special_request'])?:'No special requests')?>
</div>
</div>
</div>
<button class="btn small" onclick="showDetails<?=$b['id']?>()" style="margin-top:16px;display:inline-flex;align-items:center;gap:6px">
<i class="fas fa-times"></i> Close
</button>
</div>
</td>
</tr>
<script>
function showDetails<?=$b['id']?>() {
    const row = document.getElementById('details-<?=$b['id']?>');
    row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}
</script>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>

</div>
</div>
<?php require 'partials_admin_footer.php'; ?>
