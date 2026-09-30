<?php 
require_once '../config/config.php';
require_admin();
$title = 'Manage Rooms | ' . APP_NAME;

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'];
    
    if($action === 'status') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        
        if(in_array($status, ['available', 'unavailable', 'maintenance'], true) && $id > 0) {
            // If changing to unavailable or maintenance, check for active bookings
            if($status !== 'available') {
                $activeBookings = db()->prepare("SELECT COUNT(*) FROM bookings 
                    WHERE room_id = ? 
                    AND status IN ('pending','confirmed','checked_in')
                    AND check_out >= CURDATE()");
                $activeBookings->execute([$id]);
                $activeCount = (int)$activeBookings->fetchColumn();
                
                if($activeCount > 0) {
                    flash('error', "Cannot change room status. There are $activeCount active booking(s) for this room. Please cancel or complete them first.");
                    redirect('rooms.php');
                    exit;
                }
            }
            
            $s = db()->prepare("UPDATE rooms SET status=? WHERE id=?");
            $s->execute([$status, $id]);
            flash('success', 'Room status updated');
        } else {
            flash('error', 'Invalid status update');
        }
    } elseif($action === 'delete') {
        $id = (int)$_POST['id'];
        
        if($id > 0) {
            // Check if room has any bookings
            $checkStmt = db()->prepare("SELECT COUNT(*) FROM bookings WHERE room_id=?");
            $checkStmt->execute([$id]);
            $bookingCount = (int)$checkStmt->fetchColumn();
            
            if($bookingCount > 0) {
                flash('error', 'Cannot delete room with existing bookings. Please cancel or complete all bookings first.');
            } else {
                // Get room images to delete files
                $roomStmt = db()->prepare("SELECT image, gallery FROM rooms WHERE id=?");
                $roomStmt->execute([$id]);
                $room = $roomStmt->fetch();
                
                // Delete the room from database
                $s = db()->prepare("DELETE FROM rooms WHERE id=?");
                $s->execute([$id]);
                
                // Delete image files
                if(!empty($room['image']) && file_exists('..' . $room['image'])) {
                    @unlink('..' . $room['image']);
                }
                
                if(!empty($room['gallery'])) {
                    $gallery = json_decode($room['gallery'], true) ?? [];
                    foreach($gallery as $img) {
                        if(!empty($img) && file_exists('..' . $img)) {
                            @unlink('..' . $img);
                        }
                    }
                }
                
                flash('success', 'Room deleted successfully');
            }
        } else {
            flash('error', 'Invalid room ID');
        }
    }
    
    redirect('rooms.php');
}

// Get filters from URL
$search = $_GET['search'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$priceMin = $_GET['price_min'] ?? '';
$priceMax = $_GET['price_max'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query with filters
$sql = "SELECT r.*,rt.name type_name 
        FROM rooms r 
        JOIN room_types rt ON rt.id=r.room_type_id 
        WHERE 1=1";

$params = [];

if($search !== '') {
    $sql .= " AND (r.room_number LIKE ? OR rt.name LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam]);
}

if($typeFilter !== '') {
    $sql .= " AND r.room_type_id = ?";
    $params[] = $typeFilter;
}

if($statusFilter !== '') {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}

if($priceMin !== '') {
    $sql .= " AND r.price >= ?";
    $params[] = (float)$priceMin;
}

if($priceMax !== '') {
    $sql .= " AND r.price <= ?";
    $params[] = (float)$priceMax;
}

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM rooms r JOIN room_types rt ON rt.id=r.room_type_id WHERE 1=1" . substr($sql, strpos($sql, 'WHERE 1=1') + 9, strpos($sql, 'ORDER BY') ? strpos($sql, 'ORDER BY') - strpos($sql, 'WHERE 1=1') - 9 : strlen($sql));
$countStmt = db()->prepare(str_replace([substr($sql, 7, strpos($sql, 'FROM rooms r') - 7), 'FROM rooms r JOIN room_types rt ON rt.id=r.room_type_id'], ['COUNT(*)', 'FROM rooms r JOIN room_types rt ON rt.id=r.room_type_id'], $sql));
$countStmt->execute($params);
$totalRooms = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRooms / $perPage);

$sql .= " ORDER BY CAST(r.room_number AS UNSIGNED), r.room_number LIMIT $perPage OFFSET $offset";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

$typesStmt = db()->prepare("SELECT * FROM room_types ORDER BY name");
$typesStmt->execute();
$types = $typesStmt->fetchAll();

// Counts for the status tabs
$counts = ['' => 0];
foreach (db()->query("SELECT status, COUNT(*) c FROM rooms GROUP BY status") as $c) { $counts[$c['status']] = (int)$c['c']; $counts[''] += (int)$c['c']; }
$tabs = ['' => 'All Rooms', 'available' => 'Available', 'unavailable' => 'Unavailable', 'maintenance' => 'Maintenance'];
$tabUrl = function ($st) use ($search, $typeFilter, $priceMin, $priceMax) {
    return 'rooms.php?' . http_build_query(array_filter(['status' => $st, 'search' => $search, 'type' => $typeFilter, 'price_min' => $priceMin, 'price_max' => $priceMax]));
};
$roomStatus = ['available' => ['#e6f6ec', '#1e7b3c'], 'unavailable' => ['#eef0f3', '#4a5563'], 'maintenance' => ['#fdeaea', '#b42318']];

require '../partials_header.php';
require 'partials_admin_nav.php';
?>

<style>
.rm-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.rm-tab{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;background:#fff;border:1px solid #e3e7ee;color:#475467;font-weight:600;font-size:13px;text-decoration:none;transition:.15s}
.rm-tab span{background:#eef1f6;color:#475467;border-radius:999px;padding:1px 8px;font-size:12px}
.rm-tab:hover{border-color:var(--primary);color:var(--primary)}
.rm-tab.active{background:var(--primary);border-color:var(--primary);color:#fff}
.rm-tab.active span{background:rgba(255,255,255,.2);color:#fff}
.rm-panel{padding:0!important;overflow:hidden}
.rm-scroll{overflow-x:auto}
.rm-table{width:100%;border-collapse:separate;border-spacing:0;min-width:860px}
.rm-table thead th{background:#f8fafc;color:#667085;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:12px 10px;border-bottom:1px solid #e6e9ef;text-align:left;white-space:nowrap}
.rm-table td{padding:12px 10px;border-bottom:1px solid #f0f2f5;vertical-align:middle;font-size:14px}
.rm-table th:first-child,.rm-table td:first-child{padding-left:16px}
.rm-table th:last-child,.rm-table td:last-child{padding-right:16px}
.rm-table tbody tr{transition:background .12s}
.rm-table tbody tr:hover{background:#f8fafc}
.rm-table tbody tr:last-child td{border-bottom:0}
.rm-room{display:flex;align-items:center;gap:12px}
.rm-thumb{position:relative;width:64px;height:48px;border-radius:8px;overflow:hidden;background:#eef1f6;flex:none;display:flex;align-items:center;justify-content:center;color:#98a2b3}
.rm-thumb img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.rm-num{font-weight:700;color:#1d2939;white-space:nowrap}
.rm-sub{color:#667085;font-size:12px;margin-top:2px;white-space:nowrap}
.rm-cap{font-size:13px;color:#344054;white-space:nowrap}
.rm-cap i{color:#98a2b3;margin-right:4px}
.rm-chips{display:flex;flex-wrap:wrap;gap:4px;max-width:260px}
.rm-chips span{background:#eef3f8;color:var(--primary);padding:3px 8px;border-radius:999px;font-size:11px;font-weight:600;white-space:nowrap}
.rm-chips span.more{background:#f2f4f7;color:#475467;cursor:help}
.rm-price{font-weight:700;color:#1d2939;white-space:nowrap}
.rm-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap}
.rm-link{font-size:13px;font-weight:600;color:var(--primary);text-decoration:none;white-space:nowrap}
.rm-link:hover{text-decoration:underline}
.rm-status{appearance:none;-webkit-appearance:none;border:0;border-radius:999px;padding:6px 28px 6px 12px;font-size:12px;font-weight:700;cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23667085'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center}
.rm-status:focus{outline:2px solid rgba(23,59,103,.25);outline-offset:1px}
.rm-actions{display:inline-flex;gap:6px;align-items:center;justify-content:flex-end}
.rm-btn{width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;border:1px solid #d9dee7;background:#fff;color:#344054;font-size:13px;cursor:pointer;text-decoration:none;transition:.12s}
.rm-btn:hover{background:#f2f4f7;color:var(--primary)}
.rm-danger{color:#b42318}
.rm-danger:hover{background:#fdeaea;color:#b42318;border-color:#f5c2c0}
.rm-foot{padding:12px 16px;border-top:1px solid #e6e9ef;color:#667085;font-size:13px;background:#fcfcfd}
</style>
<div class="page" style="background:#f6f8fb">
<div class="container">
<div style="margin-bottom:32px">
<h1 style="margin:0 0 8px">Room Inventory</h1>
<p class="muted">Manage rooms and availability status</p>
</div>

<!-- Search & Filter Panel -->
<div class="panel" style="margin-bottom:24px">
<form method="get" action="rooms.php">
<div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr auto;gap:12px;align-items:end">
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-search"></i> Search
</label>
<input type="text" name="search" value="<?=e($search)?>" placeholder="Room number or type..." style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
</div>
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-door-open"></i> Type
</label>
<select name="type" style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
<option value="">All Types</option>
<?php foreach($types as $t): ?>
<option value="<?=$t['id']?>" <?=$typeFilter==$t['id']?'selected':''?>><?=e($t['name'])?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-toggle-on"></i> Status
</label>
<select name="status" style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
<option value="">All Status</option>
<option value="available" <?=$statusFilter==='available'?'selected':''?>>Available</option>
<option value="unavailable" <?=$statusFilter==='unavailable'?'selected':''?>>Unavailable</option>
<option value="maintenance" <?=$statusFilter==='maintenance'?'selected':''?>>Maintenance</option>
</select>
</div>
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-coins"></i> Min Price
</label>
<input type="number" name="price_min" value="<?=e($priceMin)?>" placeholder="0" min="0" step="100" style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
</div>
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-coins"></i> Max Price
</label>
<input type="number" name="price_max" value="<?=e($priceMax)?>" placeholder="99999" min="0" step="100" style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
</div>
<div style="display:flex;gap:8px">
<button type="submit" class="btn orange" style="padding:10px 20px;white-space:nowrap;height:44px;display:inline-flex;align-items:center;gap:8px">
<i class="fas fa-filter"></i> Filter
</button>
<?php if($search || $typeFilter || $statusFilter || $priceMin || $priceMax): ?>
<a href="rooms.php" class="btn light" style="padding:10px 16px;white-space:nowrap;height:44px;display:inline-flex;align-items:center;justify-content:center" title="Clear filters">
<i class="fas fa-times"></i>
</a>
<?php endif; ?>
</div>
</div>
</form>
</div>

<?php if($search || $typeFilter || $statusFilter || $priceMin || $priceMax): ?>
<div style="margin-bottom:16px;padding:12px 16px;background:#fff3cd;border:1px solid #ffc107;border-radius:6px;display:flex;align-items:center;justify-content:space-between">
<div style="display:flex;align-items:center;gap:8px;font-size:14px">
<i class="fas fa-info-circle" style="color:#856404"></i>
<span style="color:#856404"><strong><?=count($rooms)?></strong> room(s) found with applied filters</span>
</div>
</div>
<?php endif; ?>

<div class="rm-tabs">
<?php foreach ($tabs as $k => $label): ?>
<a href="<?=e($tabUrl($k))?>" class="rm-tab <?=$statusFilter === $k ? 'active' : ''?>"><?=$label?> <span><?=$counts[$k] ?? 0?></span></a>
<?php endforeach; ?>
</div>

<div class="panel rm-panel">
<?php if(count($rooms) === 0): ?>
<div style="text-align:center;padding:60px 20px">
<i class="fas fa-search" style="font-size:64px;color:#d9dee7;margin-bottom:20px"></i>
<h3 style="color:var(--muted);margin:0 0 12px">No rooms found</h3>
<p class="muted" style="margin-bottom:24px">Try adjusting your search or filter criteria</p>
<a href="rooms.php" class="btn light"><i class="fas fa-redo"></i> Clear Filters</a>
</div>
<?php else: ?>
<div class="rm-scroll">
<table class="rm-table">
<thead>
<tr>
<th>Room</th>
<th>Capacity</th>
<th style="text-align:right">Price / night</th>
<th>Bookings</th>
<th>Status</th>
<th style="text-align:right">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($rooms as $r):
    [$rbg, $rfg] = $roomStatus[$r['status']] ?? ['#eef0f3', '#4a5563'];
    $amen = array_values(array_filter(array_map('trim', explode(',', (string)$r['amenities']))));
    $cap = (int)($r['capacity'] ?: $r['type_capacity']);
?>
<tr>
<td>
<div class="rm-room">
<div class="rm-thumb">
<?php if($r['image']): ?><img src="<?=e($r['image'])?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?>
<i class="fas fa-bed"></i>
</div>
<div style="min-width:0">
<div class="rm-num">Room <?=e($r['room_number'])?></div>
<div class="rm-sub"><?=e(rtrim($r['type_name'], '. '))?></div>
</div>
</div>
</td>
<td><span class="rm-cap"><i class="fas fa-user-friends"></i> <?=$cap?> guest<?=$cap == 1 ? '' : 's'?></span></td>
<td style="text-align:right"><span class="rm-price">NPR <?=number_format((float)$r['price'])?></span></td>
<td>
<?php if ($r['occupied']): ?><span class="rm-pill" style="background:#e3effd;color:#1d5fa8"><i class="fas fa-door-open"></i> Occupied</span>
<?php elseif ($r['upcoming']): ?><a class="rm-link" href="bookings.php?search=<?=urlencode($r['room_number'])?>"><?=$r['upcoming']?> upcoming</a>
<?php else: ?><span class="rm-sub">No bookings</span><?php endif; ?>
</td>
<td>
<form method="post" style="margin:0">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="status">
<input type="hidden" name="id" value="<?=$r['id']?>">
<select name="status" class="rm-status" style="background-color:<?=$rbg?>;color:<?=$rfg?>" data-current="<?=e($r['status'])?>" onchange="if(confirm('Change room <?=e($r['room_number'])?> to ' + this.value + '?')) this.form.submit(); else this.value = this.dataset.current;">
<option value="available" <?=$r['status']==='available'?'selected':''?>>Available</option>
<option value="unavailable" <?=$r['status']==='unavailable'?'selected':''?>>Unavailable</option>
<option value="maintenance" <?=$r['status']==='maintenance'?'selected':''?>>Maintenance</option>
</select>
</form>
</td>
<td style="text-align:right">
<div class="rm-actions">
<a href="/hotel/room.php?id=<?=$r['id']?>" target="_blank" class="rm-btn" title="View on website" aria-label="View on website"><i class="fas fa-external-link-alt"></i></a>
<a href="add_room.php?id=<?=$r['id']?>" class="rm-btn" title="Edit room" aria-label="Edit room"><i class="fas fa-pen"></i></a>
<form method="post" style="margin:0" onsubmit="return confirm('Delete room <?=e($r['room_number'])?> (<?=e($r['type_name'])?>)?\n\nThis action cannot be undone.')">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?=$r['id']?>">
<button type="submit" class="rm-btn rm-danger" title="Delete room" aria-label="Delete room"><i class="fas fa-trash"></i></button>
</form>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="rm-foot">Showing <strong><?=$offset + 1?>&ndash;<?=$offset + count($rooms)?></strong> of <strong><?=$totalRooms?></strong> rooms</div>
<?php endif; ?>
</div>

<?php if($totalPages > 1): ?>
<!-- Pagination -->
<div style="margin-top:24px;display:flex;justify-content:center;align-items:center;gap:8px">
<?php
$queryParams = $_GET;
unset($queryParams['page']);
$baseUrl = 'rooms.php?' . http_build_query($queryParams);
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
Page <?=$page?> of <?=$totalPages?> (<?=$totalRooms?> total)
</span>
</div>
<?php endif; ?>

</div>
</div>

<!-- Floating Action Button -->
<a href="add_room.php" class="fab-button" title="Add New Room">
<i class="fas fa-plus"></i>
</a>

<?php require 'partials_admin_footer.php'; ?>
