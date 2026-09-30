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
            // Check if payment record exists
            $checkStmt = db()->prepare("SELECT id FROM payments WHERE booking_id=?");
            $checkStmt->execute([$id]);
            $paymentExists = $checkStmt->fetch();
            
            if($paymentExists) {
                // Update existing payment
                $paidAt = $paymentStatus === 'paid' ? date('Y-m-d H:i:s') : null;
                $s = db()->prepare("UPDATE payments SET status=?, paid_at=? WHERE booking_id=?");
                $s->execute([$paymentStatus, $paidAt, $id]);
            } else {
                // Create new payment record
                $bookingStmt = db()->prepare("SELECT total_amount FROM bookings WHERE id=?");
                $bookingStmt->execute([$id]);
                $booking = $bookingStmt->fetch();
                
                if($booking) {
                    $paidAt = $paymentStatus === 'paid' ? date('Y-m-d H:i:s') : null;
                    $insertStmt = db()->prepare("INSERT INTO payments (booking_id, amount, status, method, paid_at, created_at) VALUES (?, ?, ?, 'cash', ?, NOW())");
                    $insertStmt->execute([$id, $booking['total_amount'], $paymentStatus, $paidAt]);
                }
            }
            // Keep the booking's own payment status in sync with the payment record
            db()->prepare("UPDATE bookings SET payment_status=? WHERE id=?")->execute([$paymentStatus, $id]);
            flash('success', 'Payment status updated successfully');
        }
    }
    // Return to the booking details page when the change was made there
    $back = $_POST['return'] ?? '';
    redirect(preg_match('#^/hotel/admin/booking/[A-Za-z0-9]{4,30}$#', $back) ? $back : 'bookings.php');
}

// Get filters from URL
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$paymentFilter = $_GET['payment'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$customerFilter = (int)($_GET['customer'] ?? 0);

// Build query with filters
$sql = "SELECT b.*,u.name,u.email,u.phone,u.avatar,r.room_number,r.image room_image,rt.name type_name,
               (SELECT p.method FROM payments p WHERE p.booking_id=b.id ORDER BY (p.status='paid') DESC, p.id DESC LIMIT 1) pay_method
        FROM bookings b
        JOIN users u ON u.id=b.user_id
        JOIN rooms r ON r.id=b.room_id
        JOIN room_types rt ON rt.id=r.room_type_id
        WHERE 1=1";

$params = [];

if($search !== '') {
    $sql .= " AND (b.booking_code LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR rt.name LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

if($customerFilter > 0) {
    $sql .= " AND b.user_id = ?";
    $params[] = $customerFilter;
}

if($statusFilter !== '') {
    $sql .= " AND b.status = ?";
    $params[] = $statusFilter;
}

if($paymentFilter !== '') {
    $sql .= " AND b.payment_status = ?";
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

// Counts for the status tabs
$counts = ['' => 0];
foreach (db()->query("SELECT status, COUNT(*) c FROM bookings GROUP BY status") as $c) { $counts[$c['status']] = (int)$c['c']; $counts[''] += (int)$c['c']; }
$tabs = ['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'checked_in' => 'Checked In', 'checked_out' => 'Checked Out', 'cancelled' => 'Cancelled'];
$tabUrl = function ($st) use ($search, $paymentFilter, $dateFrom, $dateTo, $customerFilter) {
    return 'bookings.php?' . http_build_query(array_filter(['status' => $st, 'search' => $search, 'payment' => $paymentFilter, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'customer' => $customerFilter]));
};
$statusStyle = ['pending' => ['#fff4e0', '#b26a00', 'fa-hourglass-half'], 'confirmed' => ['#e6f6ec', '#1e7b3c', 'fa-check'],
                'checked_in' => ['#e3effd', '#1d5fa8', 'fa-door-open'], 'checked_out' => ['#eef0f3', '#4a5563', 'fa-door-closed'],
                'cancelled' => ['#fdeaea', '#b42318', 'fa-ban']];
$payStyle = ['paid' => ['#e6f6ec', '#1e7b3c'], 'pending' => ['#fff4e0', '#b26a00'], 'failed' => ['#fdeaea', '#b42318'], 'refunded' => ['#eef0f3', '#4a5563']];
$methodNames = ['khalti' => 'Khalti', 'cash' => 'Cash', 'demo' => 'Demo', 'esewa' => 'eSewa', 'card' => 'Card'];
$nextStep = ['pending' => ['confirmed', 'Confirm', 'fa-check', '#1e7b3c'], 'confirmed' => ['checked_in', 'Check in', 'fa-door-open', '#1d5fa8'],
             'checked_in' => ['checked_out', 'Check out', 'fa-door-closed', '#4a5563']];

require '../partials_header.php';
require 'partials_admin_nav.php';
?>

<style>
.bk-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.bk-tab{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;background:#fff;border:1px solid #e3e7ee;color:#475467;font-weight:600;font-size:13px;text-decoration:none;transition:.15s}
.bk-tab span{background:#eef1f6;color:#475467;border-radius:999px;padding:1px 8px;font-size:12px}
.bk-tab:hover{border-color:var(--primary);color:var(--primary)}
.bk-tab.active{background:var(--primary);border-color:var(--primary);color:#fff}
.bk-tab.active span{background:rgba(255,255,255,.2);color:#fff}
.bk-panel{padding:0!important;overflow:hidden}
.bk-scroll{overflow-x:auto}
.bk-table{width:100%;border-collapse:separate;border-spacing:0;min-width:900px}
.bk-table thead th{background:#f8fafc;color:#667085;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:12px 8px;border-bottom:1px solid #e6e9ef;text-align:left;white-space:nowrap}
.bk-table th:first-child,.bk-table td:first-child{padding-left:14px}.bk-table th:last-child,.bk-table td:last-child{padding-right:14px}
.bk-table td{padding:13px 8px;border-bottom:1px solid #f0f2f5;vertical-align:middle;font-size:14px}
.bk-row{cursor:pointer;transition:background .12s}
.bk-row:hover{background:#f8fafc}
.bk-row:last-child td{border-bottom:0}
.bk-code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-weight:700;color:var(--primary);text-decoration:none;font-size:13px}
.bk-code:hover{text-decoration:underline}
.bk-sub{color:#667085;font-size:12px;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:170px}
.bk-name{font-weight:600;color:#1d2939;white-space:nowrap}
.bk-guest{display:flex;align-items:center;gap:10px}
.bk-guest .bk-name{white-space:normal;max-width:125px;line-height:1.25}
.bk-sub{max-width:125px}
.bk-code{font-size:12px}
.bk-avatar{position:relative;overflow:hidden;width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#173b67,#2f5d95);color:#fff;font-size:12px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex:none}
.bk-avatar img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.bk-amount{font-weight:700;color:#1d2939;white-space:nowrap;margin-bottom:4px}
.bk-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap}
.bk-status{padding:5px 11px}
.bk-status i{font-size:10px}
.bk-actions{display:inline-flex;gap:6px;align-items:center;justify-content:flex-end}
.bk-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:8px;border:1px solid #d9dee7;background:#fff;font-size:12px;font-weight:600;color:#344054;text-decoration:none;cursor:pointer;white-space:nowrap;transition:.12s}
.bk-btn:hover{background:#f2f4f7}
.bk-btn-primary{background:var(--primary);border-color:var(--primary);color:#fff}
.bk-btn-primary:hover{background:#0f2b4d;color:#fff}
.bk-icon{width:32px;height:32px;padding:0;justify-content:center}
.bk-method{font-size:13px;font-weight:600;color:#344054;white-space:nowrap}
.bk-method i{color:#667085;margin-right:3px}
.bk-foot{padding:12px 16px;border-top:1px solid #e6e9ef;color:#667085;font-size:13px;background:#fcfcfd}
</style>
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
<input type="date" data-no-default name="date_from" value="<?=e($dateFrom)?>" style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
</div>
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-calendar-alt"></i> To
</label>
<input type="date" data-no-default name="date_to" value="<?=e($dateTo)?>" style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
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

<?php if($customerFilter > 0):
    $cn = db()->prepare("SELECT name FROM users WHERE id=?"); $cn->execute([$customerFilter]); $cName = $cn->fetchColumn(); ?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;background:#eef3f8;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:14px">
<span><i class="fas fa-user" style="color:var(--primary);margin-right:6px"></i>Showing bookings for <strong><?=e($cName ?: 'customer #' . $customerFilter)?></strong></span>
<a href="bookings.php" style="color:var(--primary);font-weight:600;text-decoration:none"><i class="fas fa-times"></i> Show all</a>
</div>
<?php endif; ?>
<div class="bk-tabs">
<?php foreach ($tabs as $k => $label): ?>
<a href="<?=e($tabUrl($k))?>" class="bk-tab <?=$statusFilter === $k ? 'active' : ''?>"><?=$label?> <span><?=$counts[$k] ?? 0?></span></a>
<?php endforeach; ?>
</div>

<div class="panel bk-panel">
<?php if(count($rows) === 0): ?>
<div style="text-align:center;padding:60px 20px">
<i class="fas fa-search" style="font-size:64px;color:#d9dee7;margin-bottom:20px"></i>
<h3 style="color:var(--muted);margin:0 0 12px">No bookings found</h3>
<p class="muted" style="margin-bottom:24px">Try adjusting your search or filter criteria</p>
<a href="bookings.php" class="btn light"><i class="fas fa-redo"></i> Clear Filters</a>
</div>
<?php else: ?>
<div class="bk-scroll">
<table class="bk-table">
<thead>
<tr>
<th>Booking</th>
<th>Guest</th>
<th>Room</th>
<th>Stay</th>
<th style="text-align:right">Amount</th>
<th>Payment</th>
<th>Method</th>
<th>Status</th>
<th style="text-align:right">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($rows as $b):
    $url = '/hotel/admin/booking/' . $b['booking_code'];
    [$sbg, $sfg, $sicon] = $statusStyle[$b['status']] ?? ['#eef0f3', '#4a5563', 'fa-circle'];
    [$pbg, $pfg] = $payStyle[$b['payment_status']] ?? ['#eef0f3', '#4a5563'];
    $words = array_slice(preg_split('/\s+/', trim($b['name'])), 0, 2);
    $initials = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), $words)));
    $nightsN = nights($b['check_in'], $b['check_out']);
    $sameYear = date('Y', strtotime($b['check_in'])) === date('Y', strtotime($b['check_out']));
    $roomType = rtrim($b['type_name'], '. ');
?>
<tr class="bk-row" data-href="<?=e($url)?>">
<td>
<a href="<?=e($url)?>" class="bk-code"><?=e($b['booking_code'])?></a>
<div class="bk-sub">Booked <?=date('M j, Y', strtotime($b['created_at']))?></div>
</td>
<td>
<div class="bk-guest">
<span class="bk-avatar"><?=e($initials ?: '?')?><?php if (!empty($b['avatar'])): ?><img src="<?=e($b['avatar'])?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?></span>
<div style="min-width:0">
<div class="bk-name"><?=e($b['name'])?></div>
<div class="bk-sub"><?=e($b['phone'] ?: $b['email'])?></div>
</div>
</div>
</td>
<td>
<div class="bk-name"><?=e($roomType)?></div>
<div class="bk-sub">Room <?=e($b['room_number'])?> &middot; <?=(int)$b['guests']?> guest<?=$b['guests'] == 1 ? '' : 's'?></div>
</td>
<td>
<div class="bk-name"><?=date('M j', strtotime($b['check_in']))?> <i class="fas fa-arrow-right" style="font-size:10px;color:#98a2b3;margin:0 2px"></i> <?=date('M j', strtotime($b['check_out']))?></div>
<div class="bk-sub"><?=$sameYear ? date('Y', strtotime($b['check_in'])) : date('Y', strtotime($b['check_in'])) . '–' . date('y', strtotime($b['check_out']))?> &middot; <?=$nightsN?> night<?=$nightsN == 1 ? '' : 's'?></div>
</td>
<td style="text-align:right">
<div class="bk-amount">NPR <?=number_format((float)$b['total_amount'])?></div>
</td>
<td>
<span class="bk-pill" style="background:<?=$pbg?>;color:<?=$pfg?>"><?=ucfirst($b['payment_status'])?></span>
</td>
<td>
<div class="bk-method"><?php if ($b['pay_method']): ?><i class="fas <?=$b['pay_method'] === 'khalti' ? 'fa-wallet' : ($b['pay_method'] === 'cash' ? 'fa-money-bill-wave' : 'fa-credit-card')?>" style="font-size:10px"></i> <?=e($methodNames[$b['pay_method']] ?? ucfirst($b['pay_method']))?><?php else: ?><i class="fas fa-hotel" style="font-size:10px"></i> At hotel<?php endif; ?></div>
</td>
<td>
<span class="bk-pill bk-status" style="background:<?=$sbg?>;color:<?=$sfg?>"><i class="fas <?=$sicon?>"></i> <?=ucwords(str_replace('_', ' ', $b['status']))?></span>
</td>
<td style="text-align:right">
<div class="bk-actions">
<?php if (isset($nextStep[$b['status']])): [$ns, $nl, $ni, $nc] = $nextStep[$b['status']]; ?>
<form method="post" style="margin:0">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="status">
<input type="hidden" name="id" value="<?=$b['id']?>">
<input type="hidden" name="status" value="<?=$ns?>">
<button class="bk-btn bk-icon" style="color:<?=$nc?>" title="<?=$nl?>" aria-label="<?=$nl?>"><i class="fas <?=$ni?>"></i></button>
</form>
<?php endif; ?>
<a href="<?=e($url)?>" class="bk-btn bk-btn-primary bk-icon" title="View details" aria-label="View details"><i class="fas fa-arrow-right"></i></a>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="bk-foot">Showing <strong><?=count($rows)?></strong> booking<?=count($rows) == 1 ? '' : 's'?><?=$statusFilter !== '' ? ' &middot; ' . e($tabs[$statusFilter] ?? '') : ''?></div>
<?php endif; ?>
</div>

</div>
</div>
<script>
// Click anywhere on a row to open the booking (except buttons, links and forms)
document.querySelectorAll('.bk-row').forEach(function (tr) {
    tr.addEventListener('click', function (e) {
        if (e.target.closest('a,button,form,select,input')) return;
        location.href = tr.dataset.href;
    });
});
</script>
<?php require 'partials_admin_footer.php'; ?>
