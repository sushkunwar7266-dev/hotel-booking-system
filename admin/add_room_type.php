<?php
require_once '../config/config.php';
require_admin();

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('rooms.php');
}

verify_csrf();

$name = trim($_POST['name']);
$description = trim($_POST['description'] ?? '');
$redirectBack = isset($_POST['redirect_back']);
$typeId = !empty($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
$existingImage = $_POST['existing_image'] ?? '';
$isEdit = $typeId > 0;

// Validation
$errors = [];

if(empty($name)) {
    $errors[] = 'Room type name is required';
} elseif(strlen($name) < 3) {
    $errors[] = 'Room type name must be at least 3 characters';
} elseif(strlen($name) > 100) {
    $errors[] = 'Room type name is too long (max 100 characters)';
}

// Handle image upload
$imagePath = $existingImage; // Keep existing image by default
if(isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    
    // Validate file extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    
    if(!in_array($ext, $allowed)) {
        $errors[] = 'Image must be: jpg, jpeg, png, webp, or gif';
    } elseif($file['size'] > 5 * 1024 * 1024) {
        $errors[] = 'Image must be less than 5MB';
    } else {
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if(!in_array($mimeType, $allowedMimes)) {
            $errors[] = 'Invalid image file type detected';
        } else {
            // Sanitize filename
            $safeRoomType = preg_replace('/[^a-zA-Z0-9]/', '_', $name);
            $filename = 'roomtype_' . $safeRoomType . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            
            // Ensure upload directory exists
            $uploadDir = '../uploads/room_types/';
            if(!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $destination = $uploadDir . $filename;
            
            // Verify destination is within allowed directory
            $realUploadDir = realpath($uploadDir);
            $realDestination = realpath(dirname($destination)) . '/' . basename($destination);
            
            if(strpos($realDestination, $realUploadDir) !== 0) {
                $errors[] = 'Invalid upload path';
            } elseif(move_uploaded_file($file['tmp_name'], $destination)) {
                // Delete old image if editing and had an image
                if($isEdit && !empty($existingImage) && file_exists('..' . $existingImage)) {
                    @unlink('..' . $existingImage);
                }
                $imagePath = '/hotel/uploads/room_types/' . $filename;
            } else {
                $errors[] = 'Failed to upload image';
            }
        }
    }
}

if(count($errors) > 0) {
    flash('error', implode("\n", $errors));
    if($redirectBack) {
        redirect('add_room.php');
    } else {
        redirect('rooms.php');
    }
}

try {
    if($isEdit) {
        // Update existing room type
        $s = db()->prepare("UPDATE room_types SET name=?, description=?, image=? WHERE id=?");
        $s->execute([$name, $description, $imagePath, $typeId]);
        flash('success', 'Room type "' . htmlspecialchars($name) . '" updated successfully');
        
        if($redirectBack) {
            redirect('add_room.php?new_type_id=' . $typeId);
        } else {
            redirect('room_types.php');
        }
    } else {
        // Check if room type already exists (case-insensitive)
        $s = db()->prepare("SELECT id, name FROM room_types WHERE LOWER(name)=LOWER(?)");
        $s->execute([$name]);
        $existing = $s->fetch();
        if($existing) {
            flash('error', 'Room type "' . htmlspecialchars($existing['name']) . '" already exists. Please use a different name.');
            if($redirectBack) {
                redirect('add_room.php');
            } else {
                redirect('room_types.php');
            }
        }
        
        // Insert new room type with description and image
        $s = db()->prepare("INSERT INTO room_types(name,description,image,capacity) VALUES(?,?,?,?)");
        $s->execute([$name, $description, $imagePath, 2]);
        
        $newTypeId = db()->lastInsertId();
        
        flash('success', 'Room type "' . htmlspecialchars($name) . '" added successfully');
        
        if($redirectBack) {
            // Redirect back to add_room with the new type selected
            redirect('add_room.php?new_type_id=' . $newTypeId);
        } else {
            redirect('room_types.php');
        }
    }
    
} catch(PDOException $e) {
    // Log the actual error
    error_log('Room type save error: ' . $e->getMessage());
    flash('error', 'Failed to save room type. Please try again.');
    if($redirectBack) {
        redirect('add_room.php');
    } else {
        redirect('room_types.php');
    }
}
