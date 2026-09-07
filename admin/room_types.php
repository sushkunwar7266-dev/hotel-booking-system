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

// Get all room types with room count
$stmt = db()->prepare("SELECT rt.*, COUNT(r.id) as room_count FROM room_types rt LEFT JOIN rooms r ON r.room_type_id=rt.id GROUP BY rt.id ORDER BY rt.name");
$stmt->execute();
$types = $stmt->fetchAll();

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
<h1 style="margin:0 0 8px">Room Type Management</h1>
<p class="muted">Manage room categories and their details</p>
</div>
<button onclick="openAddModal()" class="btn orange" style="display:inline-flex;align-items:center;gap:8px">
<i class="fas fa-plus"></i> Add New Room Type
</button>
</div>
</div>

<div class="panel">
<?php if(count($types) === 0): ?>
<div style="text-align:center;padding:60px 20px">
<i class="fas fa-layer-group" style="font-size:64px;color:#d9dee7;margin-bottom:20px"></i>
<h3 style="color:var(--muted);margin:0 0 12px">No room types yet</h3>
<p class="muted" style="margin-bottom:24px">Create your first room type to get started</p>
<button onclick="openAddModal()" class="btn orange"><i class="fas fa-plus" style="margin-right:8px"></i>Add Room Type</button>
</div>
<?php else: ?>
<div class="table-wrap">
<table class="table">
<thead>
<tr>
<th style="width:80px">Image</th>
<th>Room Type Name</th>
<th>Description</th>
<th style="width:120px;text-align:center">Rooms</th>
<th style="width:120px;text-align:center">Status</th>
<th style="width:240px;text-align:center">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($types as $t): ?>
<tr>
<td>
<?php if($t['image']): ?>
<img src="<?=e($t['image'])?>" alt="<?=e($t['name'])?>" style="width:60px;height:60px;object-fit:cover;border-radius:6px;display:block">
<?php else: ?>
<div style="width:60px;height:60px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center">
<i class="fas fa-image" style="font-size:20px;color:#999"></i>
</div>
<?php endif; ?>
</td>
<td>
<strong style="font-size:16px;color:var(--dark)"><?=e($t['name'])?></strong>
</td>
<td>
<span class="muted" style="display:block;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=e($t['description'] ?: 'No description provided')?>">
<?=e($t['description'] ?: 'No description provided')?>
</span>
</td>
<td style="text-align:center">
<span style="background:#e8f5e9;color:#2e7d32;padding:6px 12px;border-radius:12px;font-size:13px;font-weight:700;display:inline-block">
<i class="fas fa-door-open"></i> <?=$t['room_count']?>
</span>
</td>
<td style="text-align:center">
<span class="badge <?=$t['status']==='active'?'confirmed':'cancelled'?>" style="padding:6px 12px;border-radius:12px;font-size:12px;font-weight:700;display:inline-block">
<?=$t['status']==='active'?'Active':'Inactive'?>
</span>
</td>
<td>
<div style="display:flex;gap:8px;justify-content:center">
<form method="post" style="margin:0;display:inline-block">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="toggle_status">
<input type="hidden" name="id" value="<?=$t['id']?>">
<input type="hidden" name="status" value="<?=$t['status']?>">
<button type="submit" class="btn small <?=$t['status']==='active'?'light':'orange'?>" style="display:inline-flex;align-items:center;gap:6px" onclick="return confirm('<?=$t['status']==='active'?'Disable':'Enable'?> this room type?')">
<i class="fas fa-<?=$t['status']==='active'?'toggle-off':'toggle-on'?>"></i> <?=$t['status']==='active'?'Disable':'Enable'?>
</button>
</form>
<button onclick='editRoomType(<?=json_encode($t)?>)' class="btn small orange" style="display:inline-flex;align-items:center;gap:6px">
<i class="fas fa-edit"></i> Edit
</button>
<form method="post" style="margin:0;display:inline-block" onsubmit="return confirm('Are you sure you want to delete this room type?\n\nThis will permanently remove:\n- Room type: <?=e($t['name'])?>\n- Associated image\n\nThis action cannot be undone!')">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?=$t['id']?>">
<button type="submit" class="btn small danger" style="display:inline-flex;align-items:center;gap:6px" <?=$t['room_count']>0?'disabled title="Cannot delete - ' . $t['room_count'] . ' room(s) exist"':''?>>
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

<?php require '../partials_footer.php'; ?>
