<?php 
require_once '../config/config.php';
require_admin();
$title = 'Manage Customers | ' . APP_NAME;

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'];
    
    if($action === 'delete') {
        $id = (int)$_POST['id'];
        
        if($id > 0) {
            // Check if customer has any bookings
            $bookingCheckStmt = db()->prepare("SELECT COUNT(*) FROM bookings WHERE user_id=?");
            $bookingCheckStmt->execute([$id]);
            $bookingCount = (int)$bookingCheckStmt->fetchColumn();
            
            if($bookingCount > 0) {
                flash('error', "Cannot delete customer. There are $bookingCount booking(s) associated with this account. Please cancel or complete them first.");
            } else {
                // Delete the customer
                $s = db()->prepare("DELETE FROM users WHERE id=? AND role='customer'");
                $s->execute([$id]);
                flash('success', 'Customer deleted successfully');
            }
        } else {
            flash('error', 'Invalid customer ID');
        }
    } elseif($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $currentStatus = $_POST['current_status'];
        $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
        
        if($id > 0) {
            $s = db()->prepare("UPDATE users SET status=? WHERE id=? AND role='customer'");
            $s->execute([$newStatus, $id]);
            flash('success', 'Customer status updated to ' . $newStatus);
        } else {
            flash('error', 'Invalid customer ID');
        }
    }
    
    redirect('customers.php');
}

// Get filters from URL
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_desc';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query with filters
$sql = "SELECT u.*, 
        COUNT(DISTINCT b.id) as total_bookings,
        COUNT(DISTINCT CASE WHEN b.status='confirmed' THEN b.id END) as confirmed_bookings,
        COALESCE(SUM(CASE WHEN p.status='paid' THEN p.amount END), 0) as total_spent,
        COUNT(DISTINCT CASE WHEN b.status IN ('pending','confirmed','checked_in') AND b.check_out >= CURDATE() THEN b.id END) as active_bookings,
        MAX(b.created_at) as last_booking
        FROM users u
        LEFT JOIN bookings b ON b.user_id = u.id
        LEFT JOIN payments p ON p.booking_id = b.id
        WHERE u.role='customer'";

$params = [];

if($search !== '') {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if($statusFilter !== '') {
    $sql .= " AND u.status = ?";
    $params[] = $statusFilter;
}

$sql .= " GROUP BY u.id";

// Get total count for pagination (before adding LIMIT)
$countSql = "SELECT COUNT(DISTINCT u.id) FROM users u WHERE u.role='customer'";
$countParams = [];
if($search !== '') {
    $countSql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $countParams = [$searchParam, $searchParam, $searchParam];
}
if($statusFilter !== '') {
    $countSql .= " AND u.status = ?";
    $countParams[] = $statusFilter;
}
$countStmt = db()->prepare($countSql);
$countStmt->execute($countParams);
$totalCustomers = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalCustomers / $perPage);

// Add sorting
switch($sortBy) {
    case 'name_asc':
        $sql .= " ORDER BY u.name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY u.name DESC";
        break;
    case 'bookings_desc':
        $sql .= " ORDER BY total_bookings DESC";
        break;
    case 'spent_desc':
        $sql .= " ORDER BY total_spent DESC";
        break;
    case 'created_asc':
        $sql .= " ORDER BY u.created_at ASC";
        break;
    case 'created_desc':
    default:
        $sql .= " ORDER BY u.created_at DESC";
        break;
}

$sql .= " LIMIT $perPage OFFSET $offset";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Get total customer count for header
$totalStmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role='customer'");
$totalStmt->execute();
$totalCustomersAll = (int)$totalStmt->fetchColumn();

// Get active customer count
$activeStmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role='customer' AND status='active'");
$activeStmt->execute();
$activeCustomers = (int)$activeStmt->fetchColumn();

$counts = ['' => $totalCustomersAll, 'active' => $activeCustomers, 'inactive' => $totalCustomersAll - $activeCustomers];
$tabs = ['' => 'All Customers', 'active' => 'Active', 'inactive' => 'Inactive'];
$tabUrl = function ($st) use ($search, $sortBy) {
    return 'customers.php?' . http_build_query(array_filter(['status' => $st, 'search' => $search, 'sort' => $sortBy !== 'created_desc' ? $sortBy : '']));
};
$avatarColors = ['#173b67', '#7c3aed', '#0e7490', '#b45309', '#be185d', '#15803d', '#4338ca'];

require '../partials_header.php';
require 'partials_admin_nav.php';
?>

<style>
.cu-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.cu-tab{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;background:#fff;border:1px solid #e3e7ee;color:#475467;font-weight:600;font-size:13px;text-decoration:none;transition:.15s}
.cu-tab span{background:#eef1f6;color:#475467;border-radius:999px;padding:1px 8px;font-size:12px}
.cu-tab:hover{border-color:var(--primary);color:var(--primary)}
.cu-tab.active{background:var(--primary);border-color:var(--primary);color:#fff}
.cu-tab.active span{background:rgba(255,255,255,.2);color:#fff}
.cu-panel{padding:0!important;overflow:hidden}
.cu-scroll{overflow-x:auto}
.cu-table{width:100%;border-collapse:separate;border-spacing:0;min-width:880px}
.cu-table thead th{background:#f8fafc;color:#667085;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:12px 8px;border-bottom:1px solid #e6e9ef;text-align:left;white-space:nowrap}
.cu-table td{padding:13px 8px;border-bottom:1px solid #f0f2f5;vertical-align:middle;font-size:14px}
.cu-table th:first-child,.cu-table td:first-child{padding-left:16px}
.cu-table th:last-child,.cu-table td:last-child{padding-right:16px}
.cu-table tbody tr{transition:background .12s}
.cu-table tbody tr:hover{background:#f8fafc}
.cu-table tbody tr:last-child td{border-bottom:0}
.cu-avatar img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.cu-person{display:flex;align-items:center;gap:12px}
.cu-avatar{position:relative;overflow:hidden;width:38px;height:38px;border-radius:50%;color:#fff;font-size:13px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex:none}
.cu-name{font-weight:700;color:#1d2939;white-space:nowrap;max-width:180px;overflow:hidden;text-overflow:ellipsis}
.cu-sub{color:#667085;font-size:12px;margin-top:2px;white-space:nowrap}
.cu-contact{display:flex;align-items:center;gap:7px;font-size:13px;color:#344054;text-decoration:none;max-width:210px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cu-contact i{font-size:11px;color:#98a2b3;width:12px}
.cu-contact:hover{color:var(--primary)}
.cu-muted{color:#667085;margin-top:3px;font-size:12px}
.cu-link{font-size:13px;color:#1d2939;text-decoration:none;white-space:nowrap}
.cu-link:hover{color:var(--primary);text-decoration:underline}
.cu-money{font-weight:700;color:#1d2939;white-space:nowrap}
.cu-date{font-size:13px;color:#344054;white-space:nowrap}
.cu-switch{display:inline-flex;align-items:center;gap:8px;border:0;background:#eef0f3;color:#4a5563;border-radius:999px;padding:4px 12px 4px 4px;font-size:12px;font-weight:700;cursor:pointer;transition:.15s}
.cu-switch .knob{width:26px;height:16px;border-radius:99px;background:#c5ccd6;position:relative;transition:.15s}
.cu-switch .knob:after{content:"";position:absolute;top:2px;left:2px;width:12px;height:12px;border-radius:50%;background:#fff;transition:.15s}
.cu-switch.on{background:#e6f6ec;color:#1e7b3c}
.cu-switch.on .knob{background:#28a745}
.cu-switch.on .knob:after{left:12px}
.cu-actions{display:inline-flex;gap:6px;align-items:center;justify-content:flex-end}
.cu-btn{width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;border:1px solid #d9dee7;background:#fff;color:#344054;font-size:13px;cursor:pointer;text-decoration:none;transition:.12s}
.cu-btn:hover{background:#f2f4f7;color:var(--primary)}
.cu-btn:disabled{opacity:.4;cursor:not-allowed}
.cu-danger{color:#b42318}
.cu-danger:not(:disabled):hover{background:#fdeaea;color:#b42318;border-color:#f5c2c0}
.cu-foot{padding:12px 16px;border-top:1px solid #e6e9ef;color:#667085;font-size:13px;background:#fcfcfd}
</style>
<div class="page" style="background:#f6f8fb">
<div class="container">
<div style="margin-bottom:32px">
<div style="display:flex;align-items:center;justify-content:space-between">
<div>
<h1 style="margin:0 0 8px">Customer Management</h1>
<p class="muted">View and manage customer accounts</p>
</div>
<div style="display:flex;gap:16px;align-items:center">
<div style="text-align:right">
<div style="font-size:24px;font-weight:700;color:var(--primary)"><?=$totalCustomersAll?></div>
<div style="font-size:12px;color:var(--muted)">Total Customers</div>
</div>
</div>
</div>
</div>

<!-- Search & Filter Panel -->
<div class="panel" style="margin-bottom:24px">
<form method="get" action="customers.php">
<div style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;align-items:end">
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-search"></i> Search
</label>
<input type="text" name="search" value="<?=e($search)?>" placeholder="Name, email, or phone..." style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
</div>
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-toggle-on"></i> Status
</label>
<select name="status" style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
<option value="">All Status</option>
<option value="active" <?=$statusFilter==='active'?'selected':''?>>Active</option>
<option value="inactive" <?=$statusFilter==='inactive'?'selected':''?>>Inactive</option>
</select>
</div>
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-sort"></i> Sort By
</label>
<select name="sort" style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
<option value="created_desc" <?=$sortBy==='created_desc'?'selected':''?>>Newest First</option>
<option value="created_asc" <?=$sortBy==='created_asc'?'selected':''?>>Oldest First</option>
<option value="name_asc" <?=$sortBy==='name_asc'?'selected':''?>>Name (A-Z)</option>
<option value="name_desc" <?=$sortBy==='name_desc'?'selected':''?>>Name (Z-A)</option>
<option value="bookings_desc" <?=$sortBy==='bookings_desc'?'selected':''?>>Most Bookings</option>
<option value="spent_desc" <?=$sortBy==='spent_desc'?'selected':''?>>Highest Spending</option>
</select>
</div>
<div style="display:flex;gap:8px">
<button type="submit" class="btn orange" style="padding:10px 20px;white-space:nowrap;height:44px;display:inline-flex;align-items:center;gap:8px">
<i class="fas fa-filter"></i> Filter
</button>
<?php if($search || $statusFilter || $sortBy !== 'created_desc'): ?>
<a href="customers.php" class="btn light" style="padding:10px 16px;white-space:nowrap;height:44px;display:inline-flex;align-items:center;justify-content:center" title="Clear filters">
<i class="fas fa-times"></i>
</a>
<?php endif; ?>
</div>
</div>
</form>
</div>

<?php if($search || $statusFilter): ?>
<div style="margin-bottom:16px;padding:12px 16px;background:#fff3cd;border:1px solid #ffc107;border-radius:6px;display:flex;align-items:center;justify-content:space-between">
<div style="display:flex;align-items:center;gap:8px;font-size:14px">
<i class="fas fa-info-circle" style="color:#856404"></i>
<span style="color:#856404"><strong><?=count($customers)?></strong> customer(s) found with applied filters</span>
</div>
</div>
<?php endif; ?>

<div class="cu-tabs">
<?php foreach ($tabs as $k => $label): ?>
<a href="<?=e($tabUrl($k))?>" class="cu-tab <?=$statusFilter === $k ? 'active' : ''?>"><?=$label?> <span><?=$counts[$k]?></span></a>
<?php endforeach; ?>
</div>

<div class="panel cu-panel">
<?php if(count($customers) === 0): ?>
<div style="text-align:center;padding:60px 20px">
<i class="fas fa-users" style="font-size:64px;color:#d9dee7;margin-bottom:20px"></i>
<h3 style="color:var(--muted);margin:0 0 12px">No customers found</h3>
<p class="muted" style="margin-bottom:24px">
<?php if($search || $statusFilter): ?>
Try adjusting your search or filter criteria
<?php else: ?>
Customers will appear here once they register
<?php endif; ?>
</p>
<?php if($search || $statusFilter): ?>
<a href="customers.php" class="btn light"><i class="fas fa-redo"></i> Clear Filters</a>
<?php endif; ?>
</div>
<?php else: ?>
<div class="cu-scroll">
<table class="cu-table">
<thead>
<tr>
<th>Customer</th>
<th>Contact</th>
<th>Bookings</th>
<th style="text-align:right">Total paid</th>
<th>Last booking</th>
<th>Status</th>
<th style="text-align:right">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($customers as $c):
    $active = $c['status'] === 'active';
    $words = array_slice(preg_split('/\s+/', trim($c['name'])), 0, 2);
    $initials = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), $words)));
    $color = $avatarColors[$c['id'] % count($avatarColors)];
    $tb = (int)$c['total_bookings']; $ab = (int)$c['active_bookings'];
?>
<tr>
<td>
<div class="cu-person">
<span class="cu-avatar" style="background:<?=$color?>"><?=e($initials ?: '?')?><?php if (!empty($c['avatar'])): ?><img src="<?=e($c['avatar'])?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?></span>
<div style="min-width:0">
<div class="cu-name"><?=e($c['name'])?></div>
<div class="cu-sub">Joined <?=date('M Y', strtotime($c['created_at']))?> &middot; #<?=$c['id']?></div>
</div>
</div>
</td>
<td>
<a class="cu-contact" href="mailto:<?=e($c['email'])?>" title="<?=e($c['email'])?>"><i class="fas fa-envelope"></i><?=e($c['email'])?></a>
<?php if($c['phone']): ?><a class="cu-contact cu-muted" href="tel:<?=e($c['phone'])?>"><i class="fas fa-phone"></i><?=e($c['phone'])?></a><?php endif; ?>
</td>
<td>
<?php if ($tb): ?>
<a class="cu-link" href="bookings.php?customer=<?=$c['id']?>"><strong><?=$tb?></strong> booking<?=$tb == 1 ? '' : 's'?></a>
<div class="cu-sub"><?=$ab ? '<span style="color:#1e7b3c;font-weight:600">' . $ab . ' upcoming</span>' : 'None upcoming'?></div>
<?php else: ?><span class="cu-sub">No bookings yet</span><?php endif; ?>
</td>
<td style="text-align:right"><span class="cu-money"><?=$c['total_spent'] > 0 ? 'NPR ' . number_format((float)$c['total_spent']) : '<span class="cu-sub">&ndash;</span>'?></span></td>
<td><?=$c['last_booking'] ? '<span class="cu-date">' . date('M j, Y', strtotime($c['last_booking'])) . '</span>' : '<span class="cu-sub">&ndash;</span>'?></td>
<td>
<form method="post" style="margin:0" onsubmit="return confirm('<?=$active ? 'Deactivate' : 'Activate'?> <?=e(addslashes($c['name']))?>?<?=$active ? '\nThey will not be able to log in.' : ''?>')">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="toggle_status">
<input type="hidden" name="id" value="<?=$c['id']?>">
<input type="hidden" name="current_status" value="<?=$c['status']?>">
<button type="submit" class="cu-switch <?=$active ? 'on' : ''?>" title="<?=$active ? 'Deactivate account' : 'Activate account'?>"><span class="knob"></span><?=$active ? 'Active' : 'Inactive'?></button>
</form>
</td>
<td style="text-align:right">
<div class="cu-actions">
<a href="bookings.php?customer=<?=$c['id']?>" class="cu-btn" title="View bookings" aria-label="View bookings"><i class="fas fa-calendar-check"></i></a>
<a href="mailto:<?=e($c['email'])?>" class="cu-btn" title="Send email" aria-label="Send email"><i class="fas fa-envelope"></i></a>
<form method="post" style="margin:0" onsubmit="return confirm('Delete customer <?=e(addslashes($c['name']))?>?\n\nThis action cannot be undone.')">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?=$c['id']?>">
<button type="submit" class="cu-btn cu-danger" aria-label="Delete customer" <?=$tb > 0 ? 'disabled title="Cannot delete: ' . $tb . ' booking(s) exist"' : 'title="Delete customer"'?>><i class="fas fa-trash"></i></button>
</form>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="cu-foot">Showing <strong><?=$offset + 1?>&ndash;<?=$offset + count($customers)?></strong> of <strong><?=$totalCustomers?></strong> customers</div>
<?php endif; ?>
</div>

<?php if($totalPages > 1): ?>
<!-- Pagination -->
<div style="margin-top:24px;display:flex;justify-content:center;align-items:center;gap:8px">
<?php
$queryParams = $_GET;
unset($queryParams['page']);
$baseUrl = 'customers.php?' . http_build_query($queryParams);
$separator = $queryParams ? '&' : '';
?>

<?php if($page > 1): ?>
<a href="<?=$baseUrl . $separator?>page=1" class="btn light small" style="display:inline-flex;align-items:center">
<i class="fas fa-angle-double-left"></i>
</a>
<a href="<?=$baseUrl . $separator?>page=<?=$page-1?>" class="btn light small" style="display:inline-flex;align-items:center">
<i class="fas fa-angle-left"></i>
</a>
<?php else: ?>
<button class="btn light small" disabled style="display:inline-flex;align-items:center;opacity:0.5">
<i class="fas fa-angle-double-left"></i>
</button>
<button class="btn light small" disabled style="display:inline-flex;align-items:center;opacity:0.5">
<i class="fas fa-angle-left"></i>
</button>
<?php endif; ?>

<?php
$startPage = max(1, $page - 2);
$endPage = min($totalPages, $page + 2);

for($i = $startPage; $i <= $endPage; $i++):
?>
<a href="<?=$baseUrl . $separator?>page=<?=$i?>" class="btn small <?=$i === $page ? 'orange' : 'light'?>" style="min-width:40px">
<?=$i?>
</a>
<?php endfor; ?>

<?php if($page < $totalPages): ?>
<a href="<?=$baseUrl . $separator?>page=<?=$page+1?>" class="btn light small" style="display:inline-flex;align-items:center">
<i class="fas fa-angle-right"></i>
</a>
<a href="<?=$baseUrl . $separator?>page=<?=$totalPages?>" class="btn light small" style="display:inline-flex;align-items:center">
<i class="fas fa-angle-double-right"></i>
</a>
<?php else: ?>
<button class="btn light small" disabled style="display:inline-flex;align-items:center;opacity:0.5">
<i class="fas fa-angle-right"></i>
</button>
<button class="btn light small" disabled style="display:inline-flex;align-items:center;opacity:0.5">
<i class="fas fa-angle-double-right"></i>
</button>
<?php endif; ?>

<span style="margin-left:16px;color:var(--muted);font-size:14px">
Page <?=$page?> of <?=$totalPages?> (<?=$totalCustomers?> total)
</span>
</div>
<?php endif; ?>

</div>
</div>
<?php require 'partials_admin_footer.php'; ?>
