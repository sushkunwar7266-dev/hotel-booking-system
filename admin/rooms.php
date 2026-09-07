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

$sql .= " ORDER BY r.room_number";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

$typesStmt = db()->prepare("SELECT * FROM room_types ORDER BY name");
$typesStmt->execute();
$types = $typesStmt->fetchAll();

require '../partials_header.php';
?>
<div class="admin-nav">
<div class="container">
<a href="index.php"><i class="fas fa-chart-line"></i> Dashboard</a>
<a href="rooms.php"><i class="fas fa-bed"></i> Rooms</a>
<a href="room_types.php"><i class="fas fa-layer-group"></i> Room Types</a>
<a href="bookings.php"><i class="fas fa-calendar-check"></i> Bookings</a>
</div>
</div>

<div class="page" style="background:#f6f8fb">
<div class="container">
<div style="margin-bottom:32px">
<div style="display:flex;align-items:center;justify-content:space-between">
<div>
<h1 style="margin:0 0 8px">Room Inventory</h1>
<p class="muted">Manage rooms and availability status</p>
</div>
<a href="add_room.php" class="btn orange" style="display:inline-flex;align-items:center;gap:8px">
<i class="fas fa-plus"></i> Add New Room
</a>
</div>
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

<div class="panel">
<?php if(count($rooms) === 0): ?>
<div style="text-align:center;padding:60px 20px">
<i class="fas fa-search" style="font-size:64px;color:#d9dee7;margin-bottom:20px"></i>
<h3 style="color:var(--muted);margin:0 0 12px">No rooms found</h3>
<p class="muted" style="margin-bottom:24px">Try adjusting your search or filter criteria</p>
<a href="rooms.php" class="btn light"><i class="fas fa-redo"></i> Clear Filters</a>
</div>
<?php else: ?>
<div class="table-wrap">
<table class="table">
<thead>
<tr>
<th style="width:80px">Image</th>
<th>Room Number</th>
<th>Type</th>
<th>Price/Night</th>
<th style="width:140px;text-align:center">Status</th>
<th style="width:340px;text-align:center">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($rooms as $r): ?>
<tr>
<td>
<?php if($r['image']): ?>
<img src="<?=e($r['image'])?>" alt="Room <?=e($r['room_number'])?>" style="width:60px;height:60px;object-fit:cover;border-radius:6px;display:block">
<?php else: ?>
<div style="width:60px;height:60px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center">
<i class="fas fa-image" style="font-size:20px;color:#999"></i>
</div>
<?php endif; ?>
</td>
<td><strong style="font-size:16px;color:var(--dark)"><?=e($r['room_number'])?></strong></td>
<td><span class="muted"><?=e($r['type_name'])?></span></td>
<td><strong style="color:var(--primary);font-size:15px">NPR <?=number_format((float)$r['price'])?></strong></td>
<td style="text-align:center">
<span class="badge <?=$r['status']==='available'?'confirmed':''?>" style="padding:6px 12px;border-radius:12px;font-size:12px;font-weight:700;display:inline-block">
<?=ucfirst($r['status'])?>
</span>
</td>
<td>
<div style="display:flex;gap:8px;align-items:center;justify-content:center">
<form method="post" style="margin:0;display:inline-block">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="status">
<input type="hidden" name="id" value="<?=$r['id']?>">
<select name="status" onchange="if(confirm('Change room status to ' + this.value + '?')) this.form.submit(); else this.selectedIndex = <?=array_search($r['status'], ['available','unavailable','maintenance'])?>;" style="padding:8px 12px;border:1px solid #d9dee7;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer">
<option value="available" <?=$r['status']==='available'?'selected':''?>>Available</option>
<option value="unavailable" <?=$r['status']==='unavailable'?'selected':''?>>Unavailable</option>
<option value="maintenance" <?=$r['status']==='maintenance'?'selected':''?>>Maintenance</option>
</select>
</form>
<a href="add_room.php?id=<?=$r['id']?>" class="btn small orange" style="display:inline-flex;align-items:center;gap:6px">
<i class="fas fa-edit"></i> Edit
</a>
<form method="post" style="margin:0;display:inline-block" onsubmit="return confirm('Are you sure you want to delete this room?\n\nRoom: <?=e($r['room_number'])?>\nType: <?=e($r['type_name'])?>\n\nThis action cannot be undone.')">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?=$r['id']?>">
<button type="submit" class="btn small danger" style="display:inline-flex;align-items:center;gap:6px">
<i class="fas fa-trash"></i> Delete
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

</div>
</div>
<?php require '../partials_footer.php'; ?>
