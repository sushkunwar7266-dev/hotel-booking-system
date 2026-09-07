<?php 
require_once '../config/config.php';
require_admin();
$title = 'Manage Rooms | ' . APP_NAME;

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'];
    
    if($action === 'add') {
        $roomNumber = trim($_POST['room_number']);
        $roomTypeId = (int)$_POST['room_type_id'];
        $price = (float)$_POST['price'];
        $image = trim($_POST['image']);
        
        // Validation
        $errors = [];
        
        if(empty($roomNumber)) {
            $errors[] = 'Room number is required';
        } elseif(!preg_match('/^[A-Za-z0-9]{1,10}$/', $roomNumber)) {
            $errors[] = 'Room number must be alphanumeric (max 10 characters)';
        }
        
        if($roomTypeId <= 0) {
            $errors[] = 'Invalid room type';
        }
        
        if($price <= 0) {
            $errors[] = 'Price must be greater than 0';
        } elseif($price > 999999.99) {
            $errors[] = 'Price is too high';
        }
        
        if(!empty($image) && !filter_var($image, FILTER_VALIDATE_URL)) {
            $errors[] = 'Image must be a valid URL';
        }
        
        if(count($errors) > 0) {
            flash('error', implode('<br>', $errors));
        } else {
            try {
                $s = db()->prepare("INSERT INTO rooms(room_number,room_type_id,price,image,status) VALUES(?,?,?,?,?)");
                $s->execute([$roomNumber, $roomTypeId, $price, $image, 'available']);
                flash('success', 'Room added successfully');
            } catch(PDOException $e) {
                if(strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    flash('error', 'Room number already exists');
                } else {
                    flash('error', 'Failed to add room');
                }
            }
        }
    } elseif($action === 'status') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        
        if(in_array($status, ['available', 'maintenance'], true) && $id > 0) {
            $s = db()->prepare("UPDATE rooms SET status=? WHERE id=?");
            $s->execute([$status, $id]);
            flash('success', 'Room status updated');
        } else {
            flash('error', 'Invalid status update');
        }
    }
    
    redirect('rooms.php');
}

$types = db()->query("SELECT * FROM room_types")->fetchAll();
$rooms = db()->query("SELECT r.*,rt.name type_name FROM rooms r JOIN room_types rt ON rt.id=r.room_type_id ORDER BY r.room_number")->fetchAll();
require '../partials_header.php';
?>
<div class="admin-nav"><div class="container"><strong>Admin Panel</strong><a href="index.php">Dashboard</a><a href="rooms.php">Rooms</a><a href="bookings.php">Bookings</a></div></div>
<div class="page"><div class="container"><h1>Room Inventory</h1>
<div class="panel"><h2>Add Room</h2>
<form method="post" novalidate>
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="action" value="add">
<div class="form-grid">
<div><label>Room No.</label><input name="room_number" required maxlength="10" pattern="[A-Za-z0-9]{1,10}" placeholder="e.g. 101"><div class="form-hint">Alphanumeric only</div></div>
<div><label>Type</label><select name="room_type_id" required><?php foreach($types as $t):?><option value="<?=$t['id']?>"><?=e($t['name'])?></option><?php endforeach;?></select></div>
<div><label>Price/night</label><input type="number" name="price" required min="1" max="999999" step="0.01" placeholder="4500"><div class="form-hint">In NPR</div></div>
<div><label>Image URL</label><input name="image" type="url" placeholder="https://..."><div class="form-hint">Optional</div></div>
</div><br>
<button class="btn orange">Add Room</button>
</form>
</div><br>
<div class="panel table-wrap"><table class="table"><tr><th>Room</th><th>Type</th><th>Price</th><th>Status</th><th>Change</th></tr>
<?php foreach($rooms as $r):?>
<tr><td><?=e($r['room_number'])?></td><td><?=e($r['type_name'])?></td><td>NPR <?=number_format((float)$r['price'])?></td><td><span class="badge <?=$r['status']==='available'?'confirmed':''?>"><?=e($r['status'])?></span></td>
<td><form method="post"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=$r['id']?>">
<select name="status" onchange="this.form.submit()"><option value="available" <?=$r['status']==='available'?'selected':''?>>Available</option><option value="maintenance" <?=$r['status']==='maintenance'?'selected':''?>>Maintenance</option></select>
</form></td></tr>
<?php endforeach;?>
</table></div>
</div></div><?php require '../partials_footer.php'; ?>
