<?php 
require_once '../config/config.php';
require_admin();
$title = 'Manage Room Types | ' . APP_NAME;

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'];
    
    if($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $newStatus = $_POST['status'] === 'active' ? 'inactive' : 'active';
        
        if($id > 0) {
            $s = db()->prepare("UPDATE room_types SET status=? WHERE id=?");
            $s->execute([$newStatus, $id]);
            flash('success', 'Room type status updated to ' . $newStatus);
        } else {
            flash('error', 'Invalid room type ID');
        }
    } elseif($action === 'delete') {
        $id = (int)$_POST['id'];
        
        if($id > 0) {
            // Check if room type has any rooms
            $roomCount = db()->prepare("SELECT COUNT(*) FROM rooms WHERE room_type_id=?");
            $roomCount->execute([$id]);
            $count = (int)$roomCount->fetchColumn();
            
            if($count > 0) {
                flash('error', "Cannot delete room type. There are $count room(s) using this type. Please delete or reassign the rooms first.");
            } else {
                // Get room type image to delete file
                $typeStmt = db()->prepare("SELECT image FROM room_types WHERE id=?");
                $typeStmt->execute([$id]);
                $type = $typeStmt->fetch();
                
                // Delete the room type from database
                $s = db()->prepare("DELETE FROM room_types WHERE id=?");
                $s->execute([$id]);
                
                // Delete image file if exists
                if(!empty($type['image']) && file_exists('..' . $type['image'])) {
                    @unlink('..' . $type['image']);
                }
                
                flash('success', 'Room type deleted successfully');
            }
        } else {
            flash('error', 'Invalid room type ID');
        }
    }
    
    redirect('room_types.php');
}

// Get filters from URL
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$sortBy = $_GET['sort'] ?? 'name_asc';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query with filters
$sql = "SELECT rt.*, COUNT(r.id) as room_count, SUM(r.status='available') as available_count,
               MIN(r.price) as min_price, MAX(r.price) as max_price 
        FROM room_types rt 
        LEFT JOIN rooms r ON r.room_type_id=rt.id 
        WHERE 1=1";

$params = [];

if($search !== '') {
    $sql .= " AND (rt.name LIKE ? OR rt.description LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam]);
}

if($statusFilter !== '') {
    $sql .= " AND rt.status = ?";
    $params[] = $statusFilter;
}

$sql .= " GROUP BY rt.id";

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM room_types WHERE 1=1";
$countParams = [];
if($search !== '') {
    $countSql .= " AND (name LIKE ? OR description LIKE ?)";
    $countParams = [$searchParam, $searchParam];
}
if($statusFilter !== '') {
    $countSql .= " AND status = ?";
    $countParams[] = $statusFilter;
}
$countStmt = db()->prepare($countSql);
$countStmt->execute($countParams);
$totalTypes = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalTypes / $perPage);

// Add sorting
switch($sortBy) {
    case 'name_desc':
        $sql .= " ORDER BY rt.name DESC";
        break;
    case 'rooms_desc':
        $sql .= " ORDER BY room_count DESC";
        break;
    case 'status':
        $sql .= " ORDER BY rt.status ASC, rt.name ASC";
        break;
    case 'name_asc':
    default:
        $sql .= " ORDER BY rt.name ASC";
        break;
}

$sql .= " LIMIT $perPage OFFSET $offset";

// Get all room types with room count
$stmt = db()->prepare($sql);
$stmt->execute($params);
$types = $stmt->fetchAll();

// Counts for the status tabs
$counts = ['' => 0];
foreach (db()->query("SELECT status, COUNT(*) c FROM room_types GROUP BY status") as $c) { $counts[$c['status']] = (int)$c['c']; $counts[''] += (int)$c['c']; }
$tabs = ['' => 'All Types', 'active' => 'Active', 'inactive' => 'Inactive'];
$tabUrl = function ($st) use ($search, $sortBy) {
    return 'room_types.php?' . http_build_query(array_filter(['status' => $st, 'search' => $search, 'sort' => $sortBy !== 'name_asc' ? $sortBy : '']));
};

require '../partials_header.php';
require 'partials_admin_nav.php';
?>

<style>
.rt-tabs{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:16px}
.rt-tab{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;background:#fff;border:1px solid #e3e7ee;color:#475467;font-weight:600;font-size:13px;text-decoration:none;transition:.15s}
.rt-tab span{background:#eef1f6;color:#475467;border-radius:999px;padding:1px 8px;font-size:12px}
.rt-tab:hover{border-color:var(--primary);color:var(--primary)}
.rt-tab.active{background:var(--primary);border-color:var(--primary);color:#fff}
.rt-tab.active span{background:rgba(255,255,255,.2);color:#fff}
.rt-panel{padding:0!important;overflow:hidden}
.rt-scroll{overflow-x:auto}
.rt-table{width:100%;border-collapse:separate;border-spacing:0;min-width:860px}
.rt-table thead th{background:#f8fafc;color:#667085;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:12px 10px;border-bottom:1px solid #e6e9ef;text-align:left;white-space:nowrap}
.rt-table td{padding:14px 10px;border-bottom:1px solid #f0f2f5;vertical-align:middle;font-size:14px}
.rt-table th:first-child,.rt-table td:first-child{padding-left:16px}
.rt-table th:last-child,.rt-table td:last-child{padding-right:16px}
.rt-table tbody tr{transition:background .12s}
.rt-table tbody tr:hover{background:#f8fafc}
.rt-table tbody tr:last-child td{border-bottom:0}
.rt-type{display:flex;align-items:center;gap:14px}
.rt-thumb{position:relative;width:72px;height:54px;border-radius:9px;overflow:hidden;background:#eef1f6;flex:none;display:flex;align-items:center;justify-content:center;color:#98a2b3}
.rt-thumb img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.rt-name{font-weight:700;color:#1d2939;font-size:15px}
.rt-desc{color:#667085;font-size:12.5px;margin-top:3px;line-height:1.4;max-width:340px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.rt-sub{color:#667085;font-size:12px;white-space:nowrap}
.rt-cap{font-size:13px;color:#344054;white-space:nowrap}
.rt-cap i{color:#98a2b3;margin-right:4px}
.rt-price{font-weight:700;color:#1d2939;white-space:nowrap}
.rt-rooms{display:grid;grid-template-columns:auto;gap:4px;text-decoration:none;color:#1d2939;font-size:13px;min-width:96px}
.rt-rooms:hover strong{color:var(--primary);text-decoration:underline}
.rt-bar{display:block;height:5px;border-radius:99px;background:#eef1f6;overflow:hidden;width:96px}
.rt-bar span{display:block;height:100%;background:#28a745;border-radius:99px}
.rt-switch{display:inline-flex;align-items:center;gap:8px;border:0;background:#eef0f3;color:#4a5563;border-radius:999px;padding:4px 12px 4px 4px;font-size:12px;font-weight:700;cursor:pointer;transition:.15s}
.rt-switch .knob{width:26px;height:16px;border-radius:99px;background:#c5ccd6;position:relative;transition:.15s}
.rt-switch .knob:after{content:"";position:absolute;top:2px;left:2px;width:12px;height:12px;border-radius:50%;background:#fff;transition:.15s}
.rt-switch.on{background:#e6f6ec;color:#1e7b3c}
.rt-switch.on .knob{background:#28a745}
.rt-switch.on .knob:after{left:12px}
.rt-actions{display:inline-flex;gap:6px;align-items:center;justify-content:flex-end}
.rt-btn{width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;border:1px solid #d9dee7;background:#fff;color:#344054;font-size:13px;cursor:pointer;text-decoration:none;transition:.12s}
.rt-btn:hover{background:#f2f4f7;color:var(--primary)}
.rt-btn:disabled{opacity:.4;cursor:not-allowed}
.rt-danger{color:#b42318}
.rt-danger:not(:disabled):hover{background:#fdeaea;color:#b42318;border-color:#f5c2c0}
.rt-foot{padding:12px 16px;border-top:1px solid #e6e9ef;color:#667085;font-size:13px;background:#fcfcfd}
</style>
<div class="page" style="background:#f6f8fb">
<div class="container">
<div style="margin-bottom:32px">
<h1 style="margin:0 0 8px">Room Type Management</h1>
<p class="muted">Manage room categories and their details</p>
</div>

<!-- Search & Filter Panel -->
<div class="panel" style="margin-bottom:24px">
<form method="get" action="room_types.php">
<div style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;align-items:end">
<div>
<label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:var(--dark)">
<i class="fas fa-search"></i> Search
</label>
<input type="text" name="search" value="<?=e($search)?>" placeholder="Name or description..." style="width:100%;padding:10px 14px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
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
<option value="name_asc" <?=$sortBy==='name_asc'?'selected':''?>>Name (A-Z)</option>
<option value="name_desc" <?=$sortBy==='name_desc'?'selected':''?>>Name (Z-A)</option>
<option value="rooms_desc" <?=$sortBy==='rooms_desc'?'selected':''?>>Most Rooms</option>
<option value="status" <?=$sortBy==='status'?'selected':''?>>Status</option>
</select>
</div>
<div style="display:flex;gap:8px">
<button type="submit" class="btn orange" style="padding:10px 20px;white-space:nowrap;height:44px;display:inline-flex;align-items:center;gap:8px">
<i class="fas fa-filter"></i> Filter
</button>
<?php if($search || $statusFilter || $sortBy !== 'name_asc'): ?>
<a href="room_types.php" class="btn light" style="padding:10px 16px;white-space:nowrap;height:44px;display:inline-flex;align-items:center;justify-content:center" title="Clear filters">
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
<span style="color:#856404"><strong><?=count($types)?></strong> room type(s) found with applied filters</span>
</div>
</div>
<?php endif; ?>

<div class="rt-tabs">
<?php foreach ($tabs as $k => $label): ?>
<a href="<?=e($tabUrl($k))?>" class="rt-tab <?=$statusFilter === $k ? 'active' : ''?>"><?=$label?> <span><?=$counts[$k] ?? 0?></span></a>
<?php endforeach; ?>
<button type="button" onclick="openAddModal()" class="btn orange" style="margin-left:auto;gap:8px;padding:8px 16px"><i class="fas fa-plus"></i> Add Room Type</button>
</div>

<div class="panel rt-panel">
<?php if(count($types) === 0): ?>
<div style="text-align:center;padding:60px 20px">
<i class="fas fa-layer-group" style="font-size:64px;color:#d9dee7;margin-bottom:20px"></i>
<h3 style="color:var(--muted);margin:0 0 12px">No room types found</h3>
<p class="muted" style="margin-bottom:24px">
<?php if($search || $statusFilter): ?>
Try adjusting your search or filter criteria
<?php else: ?>
Create your first room type to get started
<?php endif; ?>
</p>
<?php if($search || $statusFilter): ?>
<a href="room_types.php" class="btn light"><i class="fas fa-redo"></i> Clear Filters</a>
<?php else: ?>
<button onclick="openAddModal()" class="btn orange"><i class="fas fa-plus" style="margin-right:8px"></i>Add Room Type</button>
<?php endif; ?>
</div>
<?php else: ?>
<div class="rt-scroll">
<table class="rt-table">
<thead>
<tr>
<th>Room Type</th>
<th>Capacity</th>
<th style="text-align:right">Price / night</th>
<th>Rooms</th>
<th>Status</th>
<th style="text-align:right">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($types as $t):
    $active = $t['status'] === 'active';
    $rc = (int)$t['room_count']; $ac = (int)$t['available_count'];
?>
<tr>
<td>
<div class="rt-type">
<div class="rt-thumb">
<?php if($t['image']): ?><img src="<?=e($t['image'])?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?>
<i class="fas fa-layer-group"></i>
</div>
<div style="min-width:0">
<div class="rt-name"><?=e(rtrim($t['name'], '. '))?></div>
<div class="rt-desc" title="<?=e($t['description'] ?: '')?>"><?=e($t['description'] ?: 'No description provided')?></div>
</div>
</div>
</td>
<td><span class="rt-cap"><i class="fas fa-user-friends"></i> Up to <?=(int)$t['capacity']?></span></td>
<td style="text-align:right">
<?php if ($rc): ?>
<span class="rt-price">NPR <?=number_format((float)$t['min_price'])?><?=$t['min_price'] != $t['max_price'] ? '&ndash;' . number_format((float)$t['max_price']) : ''?></span>
<?php else: ?><span class="rt-sub">&ndash;</span><?php endif; ?>
</td>
<td>
<?php if ($rc): ?>
<a class="rt-rooms" href="rooms.php?type=<?=$t['id']?>" title="View rooms of this type">
<span><strong><?=$rc?></strong> room<?=$rc == 1 ? "" : "s"?></span>
<span class="rt-bar"><span style="width:<?=round($ac / $rc * 100)?>%"></span></span>
<span class="rt-sub"><?=$ac?> available</span>
</a>
<?php else: ?>
<span class="rt-sub">No rooms yet</span>
<?php endif; ?>
</td>
<td>
<form method="post" style="margin:0" onsubmit="return confirm('<?=$active ? 'Disable' : 'Enable'?> this room type?<?=$active ? '\\nIt will be hidden from customers.' : ''?>')">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="toggle_status">
<input type="hidden" name="id" value="<?=$t['id']?>">
<input type="hidden" name="status" value="<?=$t['status']?>">
<button type="submit" class="rt-switch <?=$active ? 'on' : ''?>" title="<?=$active ? 'Hide from customers' : 'Show to customers'?>">
<span class="knob"></span><?=$active ? 'Active' : 'Inactive'?>
</button>
</form>
</td>
<td style="text-align:right">
<div class="rt-actions">
<a href="rooms.php?type=<?=$t['id']?>" class="rt-btn" title="View rooms" aria-label="View rooms"><i class="fas fa-bed"></i></a>
<button type="button" class="rt-btn" title="Edit room type" aria-label="Edit room type" data-type="<?=e(json_encode($t))?>" onclick="editRoomType(JSON.parse(this.dataset.type))"><i class="fas fa-pen"></i></button>
<form method="post" style="margin:0" onsubmit="return confirm('Delete room type <?=e(addslashes($t['name']))?>?\n\nThis cannot be undone.')">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?=$t['id']?>">
<button type="submit" class="rt-btn rt-danger" aria-label="Delete room type" <?=$rc > 0 ? 'disabled title="Cannot delete: ' . $rc . ' room(s) use this type"' : 'title="Delete room type"'?>><i class="fas fa-trash"></i></button>
</form>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="rt-foot">Showing <strong><?=count($types)?></strong> of <strong><?=$totalTypes ?? count($types)?></strong> room types</div>
<?php endif; ?>
</div>

<?php if($totalPages > 1): ?>
<!-- Pagination -->
<div style="margin-top:24px;display:flex;justify-content:center;align-items:center;gap:8px">
<?php
$queryParams = $_GET;
unset($queryParams['page']);
$baseUrl = 'room_types.php?' . http_build_query($queryParams);
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
Page <?=$page?> of <?=$totalPages?> (<?=$totalTypes?> total)
</span>
</div>
<?php endif; ?>

</div>
</div>

<!-- Floating Action Button -->
<button onclick="openAddModal()" class="fab-button" title="Add Room Type">
<i class="fas fa-plus"></i>
</button>

<!-- Add/Edit Room Type Modal -->
<div id="roomTypeModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
<div style="background:#fff;border-radius:12px;padding:32px;max-width:700px;width:90%;max-height:90vh;overflow-y:auto">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
<h3 id="modalTitle" style="margin:0;font-size:20px;color:var(--dark)">
<i class="fas fa-plus-circle" style="color:var(--accent);margin-right:8px"></i>Add New Room Type
</h3>
<button onclick="closeModal()" style="background:none;border:none;font-size:24px;color:var(--muted);cursor:pointer;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center">
<i class="fas fa-times"></i>
</button>
</div>
<form id="roomTypeForm" method="post" action="add_room_type.php" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="type_id" id="typeId" value="">
<input type="hidden" name="existing_image" id="existingImage" value="">
<div style="margin-bottom:20px">
<label style="display:block;margin-bottom:8px;font-weight:600;color:var(--dark)">
Room Type Name <span style="color:var(--danger)">*</span>
</label>
<input type="text" name="name" id="typeName" required maxlength="100" placeholder="e.g. Deluxe Suite, Executive Room" style="width:100%;padding:12px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
<small class="muted">Unique name for this room type</small>
</div>
<div style="margin-bottom:20px">
<label style="display:block;margin-bottom:8px;font-weight:600;color:var(--dark)">
Description
</label>
<textarea name="description" id="typeDescription" rows="4" placeholder="Brief description of this room type that will appear on the homepage..." style="width:100%;padding:12px;border:1px solid #d9dee7;border-radius:6px;font-size:14px;resize:vertical"></textarea>
<small class="muted">This description is displayed on the homepage</small>
</div>
<div id="currentImagePreview" style="display:none;margin-bottom:16px">
<label style="display:block;margin-bottom:8px;font-weight:600;color:var(--dark)">Current Image</label>
<img id="currentImage" src="" style="width:100%;max-width:400px;height:200px;object-fit:cover;border:2px solid #d9dee7;border-radius:8px">
</div>
<div style="margin-bottom:20px">
<label style="display:block;margin-bottom:8px;font-weight:600;color:var(--dark)">
<span id="imageLabel">Room Type Image</span>
</label>
<input type="file" name="image" id="typeImage" accept="image/jpeg,image/jpg,image/png,image/webp,image/gif" style="width:100%;padding:12px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
<small class="muted" id="imageHint">Image displayed on homepage • JPG, PNG, WEBP, GIF • Max 5MB</small>
</div>
<div style="display:flex;gap:12px;justify-content:flex-end">
<button type="button" onclick="closeModal()" class="btn light" style="display:inline-flex;align-items:center;gap:8px">
<i class="fas fa-times"></i> Cancel
</button>
<button type="submit" class="btn orange" style="display:inline-flex;align-items:center;gap:8px">
<i class="fas fa-save"></i> <span id="submitBtnText">Add Room Type</span>
</button>
</div>
</form>
</div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle" style="color:var(--accent);margin-right:8px"></i>Add New Room Type';
    document.getElementById('submitBtnText').textContent = 'Add Room Type';
    document.getElementById('imageLabel').textContent = 'Room Type Image';
    document.getElementById('imageHint').textContent = 'Image displayed on homepage • JPG, PNG, WEBP, GIF • Max 5MB';
    document.getElementById('roomTypeForm').reset();
    document.getElementById('typeId').value = '';
    document.getElementById('existingImage').value = '';
    document.getElementById('currentImagePreview').style.display = 'none';
    document.getElementById('roomTypeModal').style.display = 'flex';
}

function editRoomType(type) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit" style="color:#0066cc;margin-right:8px"></i>Edit Room Type';
    document.getElementById('submitBtnText').textContent = 'Update Room Type';
    document.getElementById('imageLabel').textContent = 'Room Type Image (Upload new to replace)';
    document.getElementById('imageHint').textContent = 'Upload new image to replace existing • JPG, PNG, WEBP, GIF • Max 5MB';
    
    document.getElementById('typeName').value = type.name;
    document.getElementById('typeDescription').value = type.description || '';
    document.getElementById('typeId').value = type.id;
    document.getElementById('existingImage').value = type.image || '';
    
    if(type.image) {
        document.getElementById('currentImage').src = type.image;
        document.getElementById('currentImagePreview').style.display = 'block';
    } else {
        document.getElementById('currentImagePreview').style.display = 'none';
    }
    
    document.getElementById('roomTypeModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('roomTypeModal').style.display = 'none';
}

// Close modal on outside click
document.getElementById('roomTypeModal').addEventListener('click', function(e) {
    if(e.target === this) {
        closeModal();
    }
});
</script>

<?php require 'partials_admin_footer.php'; ?>
