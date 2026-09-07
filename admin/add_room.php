<?php 
require_once '../config/config.php';
require_admin();

// Check if editing existing room
$editMode = false;
$room = null;
if(isset($_GET['id'])) {
    $editMode = true;
    $roomId = (int)$_GET['id'];
    $stmt = db()->prepare("SELECT * FROM rooms WHERE id=?");
    $stmt->execute([$roomId]);
    $room = $stmt->fetch();
    if(!$room) {
        flash('error', 'Room not found');
        redirect('rooms.php');
    }
}

$title = ($editMode ? 'Edit Room' : 'Add New Room') . ' | ' . APP_NAME;

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $editMode = isset($_POST['room_id']) && !empty($_POST['room_id']);
    $roomId = $editMode ? (int)$_POST['room_id'] : 0;
    
    $roomNumber = trim($_POST['room_number']);
    $roomTypeId = (int)$_POST['room_type_id'];
    $price = (float)$_POST['price'];
    $capacity = (int)$_POST['capacity'];
    $description = trim($_POST['description'] ?? '');
    $amenities = trim($_POST['amenities'] ?? '');
    
    // Validation
    $errors = [];
    
    if(empty($roomNumber)) {
        $errors[] = 'Room number is required';
    } elseif(!preg_match('/^[A-Za-z0-9]{1,10}$/', $roomNumber)) {
        $errors[] = 'Room number must be alphanumeric (max 10 characters)';
    } else {
        // Check for duplicate room number (except when editing the same room)
        $checkSql = "SELECT id FROM rooms WHERE room_number=?";
        $checkParams = [$roomNumber];
        if($editMode) {
            $checkSql .= " AND id!=?";
            $checkParams[] = $roomId;
        }
        $checkStmt = db()->prepare($checkSql);
        $checkStmt->execute($checkParams);
        if($checkStmt->fetch()) {
            $errors[] = 'Room number "' . htmlspecialchars($roomNumber) . '" is already in use. Please choose a different number.';
        }
    }
    
    if($roomTypeId <= 0) {
        $errors[] = 'Invalid room type';
    }
    
    if($price <= 0) {
        $errors[] = 'Price must be greater than 0';
    } elseif($price > 999999.99) {
        $errors[] = 'Price is too high';
    }
    
    if($capacity <= 0) {
        $errors[] = 'Capacity must be at least 1';
    } elseif($capacity > 20) {
        $errors[] = 'Capacity cannot exceed 20 guests';
    }
    
    // Handle main image upload
    $mainImage = $editMode ? $_POST['existing_main_image'] : '';
    if(isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['main_image'];
        
        // Validate file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        if(!in_array($ext, $allowed)) {
            $errors[] = 'Main image must be: jpg, jpeg, png, webp, or gif';
        } elseif($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Main image must be less than 5MB';
        } else {
            // Validate MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if(!in_array($mimeType, $allowedMimes)) {
                $errors[] = 'Invalid image file type detected';
            } else {
                // Sanitize room number to prevent path traversal
                $safeRoomNumber = preg_replace('/[^a-zA-Z0-9]/', '', $roomNumber);
                $filename = 'room_' . $safeRoomNumber . '_main_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                
                // Ensure upload directory exists
                $uploadDir = '../uploads/rooms/';
                if(!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $destination = $uploadDir . $filename;
                
                // Verify destination is within allowed directory (prevent path traversal)
                $realUploadDir = realpath($uploadDir);
                $realDestination = realpath(dirname($destination)) . '/' . basename($destination);
                
                if(strpos($realDestination, $realUploadDir) !== 0) {
                    $errors[] = 'Invalid upload path';
                } elseif(move_uploaded_file($file['tmp_name'], $destination)) {
                    // Delete old image if editing
                    if($editMode && !empty($mainImage) && file_exists('..' . $mainImage)) {
                        @unlink('..' . $mainImage);
                    }
                    $mainImage = '/hotel/uploads/rooms/' . $filename;
                } else {
                    $errors[] = 'Failed to upload main image';
                }
            }
        }
    } elseif(!$editMode) {
        $errors[] = 'Main image is required';
    }
    
    // Handle gallery images upload
    $gallery = [];
    if($editMode && !empty($_POST['existing_gallery'])) {
        $gallery = json_decode($_POST['existing_gallery'], true) ?? [];
    }
    
    if(isset($_FILES['gallery']) && is_array($_FILES['gallery']['name'])) {
        $uploadDir = '../uploads/rooms/';
        if(!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $realUploadDir = realpath($uploadDir);
        
        foreach($_FILES['gallery']['name'] as $key => $name) {
            if($_FILES['gallery']['error'][$key] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['gallery']['tmp_name'][$key];
                $size = $_FILES['gallery']['size'][$key];
                
                // Validate extension
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                
                if(!in_array($ext, $allowed) || $size > 5 * 1024 * 1024) {
                    continue; // Skip invalid files
                }
                
                // Validate MIME type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if(!in_array($mimeType, $allowedMimes)) {
                    continue; // Skip invalid MIME types
                }
                
                // Sanitize filename
                $safeRoomNumber = preg_replace('/[^a-zA-Z0-9]/', '', $roomNumber);
                $filename = 'room_' . $safeRoomNumber . '_gallery_' . time() . '_' . $key . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destination = $uploadDir . $filename;
                
                // Verify destination is within allowed directory
                $realDestination = realpath(dirname($destination)) . '/' . basename($destination);
                if(strpos($realDestination, $realUploadDir) === 0) {
                    if(move_uploaded_file($tmpName, $destination)) {
                        $gallery[] = '/hotel/uploads/rooms/' . $filename;
                    }
                }
            }
        }
    }
    
    if(count($errors) > 0) {
        flash('error', implode("\n", $errors));
    } else {
        try {
            $galleryJson = !empty($gallery) ? json_encode(array_values($gallery)) : null;
            
            if($editMode) {
                // Update existing room
                $s = db()->prepare("UPDATE rooms SET room_number=?,room_type_id=?,price=?,capacity=?,image=?,gallery=?,description=?,amenities=? WHERE id=?");
                $s->execute([$roomNumber, $roomTypeId, $price, $capacity, $mainImage, $galleryJson, $description, $amenities, $roomId]);
                flash('success', 'Room updated successfully');
            } else {
                // Insert new room
                $s = db()->prepare("INSERT INTO rooms(room_number,room_type_id,price,capacity,image,gallery,description,amenities,status) VALUES(?,?,?,?,?,?,?,?,?)");
                $s->execute([$roomNumber, $roomTypeId, $price, $capacity, $mainImage, $galleryJson, $description, $amenities, 'available']);
                flash('success', 'Room added successfully');
            }
            
            redirect('rooms.php');
        } catch(PDOException $e) {
            // Log the actual error
            error_log('Room save error: ' . $e->getMessage());
            
            if(strpos($e->getMessage(), 'Duplicate entry') !== false) {
                flash('error', 'Room number already exists');
            } else {
                flash('error', 'Failed to save room. Please try again.');
            }
        }
    }
}

$types = db()->prepare("SELECT * FROM room_types ORDER BY name");
$types->execute();
$types = $types->fetchAll();

// Check if we need to pre-select a newly added room type
$newTypeId = isset($_GET['new_type_id']) ? (int)$_GET['new_type_id'] : 0;

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
<h1 style="margin:0 0 8px"><?=$editMode ? 'Edit Room' : 'Add New Room'?></h1>
<p class="muted"><?=$editMode ? 'Update room details and images' : 'Fill in the details to add a new room to inventory'?></p>
</div>
<a href="rooms.php" class="btn light" style="display:inline-flex;align-items:center;gap:8px">
<i class="fas fa-arrow-left"></i> Back to Rooms
</a>
</div>
</div>

<form method="post" enctype="multipart/form-data" novalidate>
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<?php if($editMode): ?>
<input type="hidden" name="room_id" value="<?=$room['id']?>">
<input type="hidden" name="existing_main_image" value="<?=e($room['image'])?>">
<input type="hidden" name="existing_gallery" value="<?=e($room['gallery'])?>">
<?php endif; ?>

<!-- Basic Information -->
<div class="panel" style="margin-bottom:24px">
<h3 style="margin:0 0 20px;color:var(--dark);font-size:18px">
<i class="fas fa-info-circle" style="color:var(--accent)"></i> Basic Information
</h3>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px">
<div>
<label style="display:block;margin-bottom:8px;font-weight:600;color:var(--dark)">
Room Number <span style="color:var(--danger)">*</span>
</label>
<input type="text" name="room_number" required maxlength="10" pattern="[A-Za-z0-9]{1,10}" placeholder="e.g. 101" style="width:100%;padding:12px;border:1px solid #d9dee7;border-radius:6px;font-size:14px" value="<?=e($editMode ? $room['room_number'] : ($_POST['room_number']??''))?>">
<small class="muted">Alphanumeric only, max 10 characters</small>
</div>
<div>
<div style="display:flex;gap:8px;align-items:end">
<div style="flex:1">
<label style="display:block;margin-bottom:8px;font-weight:600;color:var(--dark)">
Room Type <span style="color:var(--danger)">*</span>
</label>
<select name="room_type_id" id="roomTypeSelect" required style="width:100%;padding:12px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
<option value="">Select room type...</option>
<?php foreach($types as $t): ?>
<option value="<?=$t['id']?>" <?=($editMode ? $room['room_type_id'] : ($newTypeId ? $newTypeId : ($_POST['room_type_id']??'')))==$t['id']?'selected':''?>><?=e($t['name'])?></option>
<?php endforeach; ?>
<option value="add_new" style="font-weight:700;color:var(--accent)">+ Add New Room Type</option>
</select>
<small class="muted">Select the room category</small>
</div>
<button type="button" id="editTypeBtn" onclick="openEditRoomTypeModal()" style="display:none;padding:12px 16px;height:48px;background:#0066cc;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;font-weight:600;white-space:nowrap">
<i class="fas fa-edit"></i> Edit Type
</button>
</div>
</div>
<div>
<label style="display:block;margin-bottom:8px;font-weight:600;color:var(--dark)">
Price per Night (NPR) <span style="color:var(--danger)">*</span>
</label>
<input type="number" name="price" required min="1" max="999999" step="0.01" placeholder="4500" style="width:100%;padding:12px;border:1px solid #d9dee7;border-radius:6px;font-size:14px" value="<?=e($editMode ? $room['price'] : ($_POST['price']??''))?>">
<small class="muted">Room rate in Nepalese Rupees</small>
</div>
<div>
<label style="display:block;margin-bottom:8px;font-weight:600;color:var(--dark)">
Max Guests <span style="color:var(--danger)">*</span>
</label>
<input type="number" name="capacity" required min="1" max="20" placeholder="2" style="width:100%;padding:12px;border:1px solid #d9dee7;border-radius:6px;font-size:14px" value="<?=e($editMode ? $room['capacity'] : ($_POST['capacity']??'2'))?>">
<small class="muted">Maximum guest capacity</small>
</div>
</div>
</div>

<!-- Images -->
<div class="panel" style="margin-bottom:24px">
<h3 style="margin:0 0 20px;color:var(--dark);font-size:18px">
<i class="fas fa-images" style="color:var(--accent)"></i> Images
</h3>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
<!-- Main Image Column -->
<div>
<label style="display:block;margin-bottom:8px;font-weight:600;color:var(--dark)">
Main Image <?=$editMode ? '' : '<span style="color:var(--danger)">*</span>'?>
</label>
<?php if($editMode && !empty($room['image'])): ?>
<div style="margin-bottom:12px">
<img src="<?=e($room['image'])?>" style="width:100%;max-width:300px;height:200px;object-fit:cover;border:2px solid #d9dee7;border-radius:8px;display:block">
<small class="muted" style="display:block;margin-top:8px">Current main image</small>
</div>
<?php endif; ?>
<input type="file" name="main_image" id="main_image" accept="image/jpeg,image/jpg,image/png,image/webp,image/gif" <?=$editMode?'':'required'?> style="width:100%;padding:12px;border:1px solid #d9dee7;border-radius:6px;font-size:14px" onchange="previewMainImage(this)">
<small class="muted" style="display:block;margin-top:8px"><?=$editMode ? 'Upload new to replace • ' : ''?>JPG, PNG, WEBP, GIF • Max 5MB</small>
<div id="main_preview" style="display:none;margin-top:12px">
<img id="main_preview_img" src="" style="width:100%;max-width:300px;height:200px;object-fit:cover;border:2px solid var(--accent);border-radius:8px;display:block">
<small class="muted" style="display:block;margin-top:8px;color:var(--accent);font-weight:600">New preview</small>
</div>
</div>

<!-- Gallery Images Column -->
<div>
<label style="display:block;margin-bottom:8px;font-weight:600;color:var(--dark)">
<i class="fas fa-th"></i> Gallery Images (Optional)
</label>
<?php if($editMode && !empty($room['gallery'])): 
$existingGallery = json_decode($room['gallery'], true) ?? [];
if(!empty($existingGallery)):
?>
<div style="margin-bottom:16px;padding:12px;background:#f6f8fb;border-radius:8px;border:1px solid #e6e9ef">
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:8px">
<?php foreach($existingGallery as $img): ?>
<img src="<?=e($img)?>" style="width:100%;height:80px;object-fit:cover;border:2px solid #d9dee7;border-radius:6px">
<?php endforeach; ?>
</div>
<small class="muted">Existing gallery (will be kept)</small>
</div>
<?php endif; endif; ?>
<div id="gallery-container" style="margin-bottom:12px">
<?php for($i=0; $i<3; $i++): ?>
<div class="gallery-item" style="margin-bottom:12px">
<input type="file" name="gallery[]" accept="image/jpeg,image/jpg,image/png,image/webp,image/gif" style="width:100%;padding:10px;border:1px solid #d9dee7;border-radius:6px;font-size:13px">
</div>
<?php endfor; ?>
</div>
<button type="button" class="btn light small" onclick="addGalleryInput()" style="display:inline-flex;align-items:center;gap:6px;font-size:13px">
<i class="fas fa-plus"></i> Add More
</button>
<small class="muted" style="display:block;margin-top:8px">Max 5MB each • JPG, PNG, WEBP, GIF</small>
</div>
</div>
</div>

<!-- Description & Amenities -->
<div class="panel" style="margin-bottom:24px">
<h3 style="margin:0 0 20px;color:var(--dark);font-size:18px">
<i class="fas fa-file-alt" style="color:var(--accent)"></i> Details & Amenities
</h3>
<div style="margin-bottom:24px">
<label style="display:block;margin-bottom:8px;font-weight:600;color:var(--dark)">
Description
</label>
<textarea name="description" rows="5" placeholder="Describe the room features, view, size, etc..." style="width:100%;padding:12px;border:1px solid #d9dee7;border-radius:6px;font-size:14px;resize:vertical"><?=e($editMode && isset($room['description']) ? $room['description'] : ($_POST['description']??''))?></textarea>
<small class="muted">Detailed room description for guests</small>
</div>
<div>
<label style="display:block;margin-bottom:8px;font-weight:600;color:var(--dark)">
Amenities
</label>
<textarea name="amenities" rows="3" placeholder="Wi-Fi, Air Conditioning, TV, Mini Bar, Room Service, Safe, Bathtub, etc..." style="width:100%;padding:12px;border:1px solid #d9dee7;border-radius:6px;font-size:14px;resize:vertical"><?=e($editMode && isset($room['amenities']) ? $room['amenities'] : ($_POST['amenities']??''))?></textarea>
<small class="muted">Comma-separated list of amenities</small>
</div>
</div>

<!-- Action Buttons -->
<div class="panel" style="display:flex;justify-content:space-between;align-items:center;padding:20px 24px">
<div>
<p class="muted" style="margin:0;font-size:14px">
<?=$editMode ? 'Save changes to update the room information' : 'All fields marked with * are required'?>
</p>
</div>
<div style="display:flex;gap:12px">
<a href="rooms.php" class="btn light" style="display:inline-flex;align-items:center;gap:8px">
<i class="fas fa-times"></i> Cancel
</a>
<button type="submit" class="btn orange" style="display:inline-flex;align-items:center;gap:8px;padding:12px 24px;font-weight:600">
<i class="fas fa-save"></i> <?=$editMode ? 'Update Room' : 'Add Room'?>
</button>
</div>
</div>

</form>

</div>
</div>

<!-- Add/Edit Room Type Modal -->
<div id="addRoomTypeModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
<div style="background:#fff;border-radius:12px;padding:32px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
<h3 id="roomTypeModalTitle" style="margin:0;font-size:20px;color:var(--dark)">
<i class="fas fa-plus-circle" style="color:var(--accent);margin-right:8px"></i>Add New Room Type
</h3>
<button onclick="closeRoomTypeModal()" style="background:none;border:none;font-size:24px;color:var(--muted);cursor:pointer;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center">
<i class="fas fa-times"></i>
</button>
</div>
<form id="addRoomTypeForm" method="post" action="add_room_type.php" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="redirect_back" value="1">
<input type="hidden" name="type_id" id="editTypeId" value="">
<input type="hidden" name="existing_image" id="existingTypeImage" value="">
<div style="margin-bottom:20px">
<label style="display:block;margin-bottom:8px;font-weight:600;color:var(--dark)">
Room Type Name <span style="color:var(--danger)">*</span>
</label>
<input type="text" name="name" id="roomTypeName" required maxlength="100" placeholder="e.g. Deluxe Suite" style="width:100%;padding:12px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
<small class="muted">Unique name for this room type</small>
</div>
<div style="margin-bottom:20px">
<label style="display:block;margin-bottom:8px;font-weight:600;color:var(--dark)">
Description
</label>
<textarea name="description" id="roomTypeDescription" rows="3" placeholder="Brief description of this room type..." style="width:100%;padding:12px;border:1px solid #d9dee7;border-radius:6px;font-size:14px;resize:vertical"></textarea>
<small class="muted">Description displayed on homepage</small>
</div>
<div id="currentImagePreview" style="display:none;margin-bottom:12px">
<label style="display:block;margin-bottom:8px;font-weight:600;color:var(--dark)">Current Image</label>
<img id="currentTypeImage" src="" style="width:100%;max-width:300px;height:150px;object-fit:cover;border:2px solid #d9dee7;border-radius:8px">
</div>
<div style="margin-bottom:20px">
<label style="display:block;margin-bottom:8px;font-weight:600;color:var(--dark)">
<span id="imageLabel">Room Type Image</span>
</label>
<input type="file" name="image" id="roomTypeImage" accept="image/jpeg,image/jpg,image/png,image/webp,image/gif" style="width:100%;padding:12px;border:1px solid #d9dee7;border-radius:6px;font-size:14px">
<small class="muted" id="imageHint">Image displayed on homepage • JPG, PNG, WEBP, GIF • Max 5MB</small>
</div>
<div style="display:flex;gap:12px;justify-content:flex-end">
<button type="button" onclick="closeRoomTypeModal()" class="btn light" style="display:inline-flex;align-items:center;gap:8px">
<i class="fas fa-times"></i> Cancel
</button>
<button type="submit" class="btn orange" id="roomTypeSubmitBtn" style="display:inline-flex;align-items:center;gap:8px">
<i class="fas fa-plus"></i> <span id="submitBtnText">Add Room Type</span>
</button>
</div>
</form>
</div>
</div>

<script>
function previewMainImage(input) {
    const preview = document.getElementById('main_preview');
    const previewImg = document.getElementById('main_preview_img');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
    }
}

function addGalleryInput() {
    const container = document.getElementById('gallery-container');
    const div = document.createElement('div');
    div.className = 'gallery-item';
    div.style.marginBottom = '12px';
    div.style.display = 'flex';
    div.style.gap = '8px';
    div.innerHTML = `
        <input type="file" name="gallery[]" accept="image/jpeg,image/jpg,image/png,image/webp,image/gif" style="flex:1;padding:10px;border:1px solid #d9dee7;border-radius:6px;font-size:13px">
        <button type="button" class="btn danger small" onclick="this.parentElement.remove()" style="padding:10px 14px;display:inline-flex;align-items:center;justify-content:center">
            <i class="fas fa-trash"></i>
        </button>
    `;
    container.appendChild(div);
}

// Room Type Modal Functions
const roomTypes = <?=json_encode($types)?>;

function openEditRoomTypeModal() {
    openRoomTypeModal(true);
}

function openRoomTypeModal(isEdit = false) {
    const modal = document.getElementById('addRoomTypeModal');
    const title = document.getElementById('roomTypeModalTitle');
    const submitText = document.getElementById('submitBtnText');
    const imageLabel = document.getElementById('imageLabel');
    const imageHint = document.getElementById('imageHint');
    const select = document.getElementById('roomTypeSelect');
    
    if(isEdit) {
        const selectedId = select.value;
        
        if(!selectedId || selectedId === 'add_new') {
            alert('Please select a room type to edit first');
            return;
        }
        
        const roomType = roomTypes.find(t => t.id == selectedId);
        if(!roomType) {
            alert('Room type not found');
            return;
        }
        
        // Populate form with existing data
        document.getElementById('roomTypeName').value = roomType.name;
        document.getElementById('roomTypeDescription').value = roomType.description || '';
        document.getElementById('editTypeId').value = roomType.id;
        document.getElementById('existingTypeImage').value = roomType.image || '';
        
        // Show current image if exists
        if(roomType.image) {
            document.getElementById('currentTypeImage').src = roomType.image;
            document.getElementById('currentImagePreview').style.display = 'block';
        } else {
            document.getElementById('currentImagePreview').style.display = 'none';
        }
        
        // Update UI text
        title.innerHTML = '<i class="fas fa-edit" style="color:#0066cc;margin-right:8px"></i>Edit Room Type';
        submitText.textContent = 'Update Room Type';
        imageLabel.textContent = 'Room Type Image (Upload new to replace)';
        imageHint.textContent = 'Upload new image to replace existing • JPG, PNG, WEBP, GIF • Max 5MB';
    } else {
        // Add mode - reset form
        document.getElementById('addRoomTypeForm').reset();
        document.getElementById('editTypeId').value = '';
        document.getElementById('existingTypeImage').value = '';
        document.getElementById('currentImagePreview').style.display = 'none';
        
        // Update UI text
        title.innerHTML = '<i class="fas fa-plus-circle" style="color:var(--accent);margin-right:8px"></i>Add New Room Type';
        submitText.textContent = 'Add Room Type';
        imageLabel.textContent = 'Room Type Image';
        imageHint.textContent = 'Image displayed on homepage • JPG, PNG, WEBP, GIF • Max 5MB';
        
        // Reset dropdown
        select.value = '';
    }
    
    modal.style.display = 'flex';
}

function closeRoomTypeModal() {
    document.getElementById('addRoomTypeModal').style.display = 'none';
}

// Handle room type dropdown change
document.addEventListener('DOMContentLoaded', function() {
    const roomTypeSelect = document.getElementById('roomTypeSelect');
    const editBtn = document.getElementById('editTypeBtn');
    
    function updateEditButton() {
        const selectedValue = roomTypeSelect.value;
        if(selectedValue && selectedValue !== 'add_new' && selectedValue !== '') {
            editBtn.style.display = 'block';
        } else {
            editBtn.style.display = 'none';
        }
    }
    
    roomTypeSelect.addEventListener('change', function() {
        if(this.value === 'add_new') {
            openRoomTypeModal(false);
            this.value = ''; // Reset dropdown after opening modal
        } else {
            updateEditButton();
        }
    });
    
    // Initial check
    updateEditButton();
    
    // Close modal on outside click
    document.getElementById('addRoomTypeModal').addEventListener('click', function(e) {
        if(e.target === this) {
            closeRoomTypeModal();
        }
    });
});
</script>

<?php require '../partials_footer.php'; ?>
