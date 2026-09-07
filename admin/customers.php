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
        COALESCE(SUM(CASE WHEN p.status='paid' THEN p.amount END), 0) as total_spent
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

require '../partials_header.php';
require 'partials_admin_nav.php';
?>

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

<div class="panel">
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
<div class="table-wrap">
<table class="table">
<thead>
<tr>
<th>Customer</th>
<th>Contact</th>
<th style="width:80px;text-align:center">Bookings</th>
<th style="width:100px;text-align:center">Spent</th>
<th style="width:80px;text-align:center">Status</th>
<th style="width:220px;text-align:center">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($customers as $c): ?>
<tr>
<td>
<div style="display:flex;align-items:center;gap:10px">
<div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px;flex-shrink:0">
<?=strtoupper(substr($c['name'], 0, 1))?>
</div>
<div style="min-width:0">
<strong style="font-size:14px;color:var(--dark);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=e($c['name'])?>"><?=e($c['name'])?></strong>
<span class="muted" style="font-size:11px">ID: <?=$c['id']?></span>
</div>
</div>
</td>
<td style="min-width:0">
<div style="font-size:13px">
<a href="mailto:<?=e($c['email'])?>" style="color:var(--primary);text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=e($c['email'])?>">
<i class="fas fa-envelope" style="margin-right:4px;font-size:11px"></i><?=e($c['email'])?>
</a>
<?php if($c['phone']): ?>
<a href="tel:<?=e($c['phone'])?>" style="color:var(--muted);text-decoration:none;font-size:12px;display:block;margin-top:2px">
<i class="fas fa-phone" style="margin-right:4px;font-size:10px"></i><?=e($c['phone'])?>
</a>
<?php endif; ?>
</div>
</td>
<td style="text-align:center">
<span style="background:#e3f2fd;color:#1976d2;padding:4px 10px;border-radius:10px;font-size:12px;font-weight:700;display:inline-block">
<?=$c['total_bookings']?>
</span>
</td>
<td style="text-align:center">
<strong style="color:var(--primary);font-size:13px;white-space:nowrap">NPR <?=number_format((float)$c['total_spent'])?></strong>
</td>
<td style="text-align:center">
<span class="badge <?=$c['status']==='active'?'confirmed':'cancelled'?>" style="padding:4px 10px;border-radius:10px;font-size:11px;font-weight:700;display:inline-block">
<?=ucfirst($c['status'])?>
</span>
</td>
<td>
<div style="display:flex;gap:6px;align-items:center;justify-content:center;flex-wrap:wrap">
<a href="bookings.php?customer=<?=$c['id']?>" class="btn small orange" style="display:inline-flex;align-items:center;gap:4px;padding:6px 10px;font-size:12px" title="View Bookings">
<i class="fas fa-eye"></i> View
</a>
<form method="post" style="margin:0;display:inline-block">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="toggle_status">
<input type="hidden" name="id" value="<?=$c['id']?>">
<input type="hidden" name="current_status" value="<?=$c['status']?>">
<button type="submit" class="btn small <?=$c['status']==='active'?'light':'orange'?>" style="display:inline-flex;align-items:center;gap:4px;padding:6px 10px;font-size:12px" onclick="return confirm('<?=$c['status']==='active'?'Deactivate':'Activate'?> this customer account?')" title="<?=$c['status']==='active'?'Deactivate':'Activate'?>">
<i class="fas fa-<?=$c['status']==='active'?'ban':'check'?>"></i>
</button>
</form>
<form method="post" style="margin:0;display:inline-block" onsubmit="return confirm('Are you sure you want to delete this customer?\n\nCustomer: <?=e($c['name'])?>\nEmail: <?=e($c['email'])?>\n\nThis action cannot be undone.')">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?=$c['id']?>">
<button type="submit" class="btn small danger" style="display:inline-flex;align-items:center;gap:4px;padding:6px 10px;font-size:12px" <?=$c['total_bookings']>0?'disabled title="Cannot delete - ' . $c['total_bookings'] . ' booking(s) exist"':''?> title="Delete">
<i class="fas fa-trash"></i>
</button>
</form>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
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
>
