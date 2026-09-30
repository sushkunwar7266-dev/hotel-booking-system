<?php
require_once 'config/config.php';
$user = require_login();

$s = db()->prepare("SELECT * FROM users WHERE id=?");
$s->execute([$user['id']]);
$me = $s->fetch();

$errors = [];
$section = '';
$tabs = [
    'personal' => ['Personal details', 'Your name appears on bookings and receipts.', 'fa-user'],
    'contact'  => ['Contact details', 'Used for booking updates. Your email is also your login.', 'fa-address-book'],
    'password' => ['Password', 'Keep your account secure with a strong password.', 'fa-lock'],
    'account'  => ['Account', 'Information about your StayEase account.', 'fa-id-card'],
];
$tab = $_GET['tab'] ?? 'personal';
if (!isset($tabs[$tab])) $tab = 'personal';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $section = $_POST['section'] ?? '';
    $back = '/hotel/profile/' . (isset($tabs[$_POST['tab'] ?? '']) ? $_POST['tab'] : 'personal');
    $avatarDir = __DIR__ . '/uploads/avatars/';
    // Delete a stored avatar file, only if it is inside uploads/avatars
    $removeAvatarFile = function (?string $path) use ($avatarDir) {
        if (!$path || !str_starts_with($path, '/hotel/uploads/avatars/')) return;
        $file = $avatarDir . basename($path);
        if (is_file($file)) @unlink($file);
    };

    if ($section === 'avatar') {
        $f = $_FILES['avatar'] ?? null;
        $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $msg = null;
        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) $msg = 'Choose a photo to upload';
        elseif ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > 2 * 1024 * 1024) $msg = 'Photo must be 2 MB or smaller';
        elseif ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) $msg = 'Upload failed. Please try again.';
        else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            $info = @getimagesize($f['tmp_name']);
            if (!isset($types[$mime]) || !$info || !isset($types[$info['mime']])) $msg = 'Use a JPG, PNG or WEBP image';
            elseif ($info[0] < 64 || $info[1] < 64) $msg = 'Photo must be at least 64 × 64 pixels';
            elseif ($info[0] > 6000 || $info[1] > 6000) $msg = 'Photo is too large (max 6000 × 6000 pixels)';
        }
        if ($msg) {
            flash('error', $msg);
            redirect($back);
        }
        if (!is_dir($avatarDir)) mkdir($avatarDir, 0755, true);
        // Name and extension come from the server, never from the uploaded file name
        $name = 'user_' . (int)$me['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $types[$mime];
        if (!move_uploaded_file($f['tmp_name'], $avatarDir . $name)) {
            flash('error', 'Could not save the photo. Please try again.');
            redirect($back);
        }
        db()->prepare("UPDATE users SET avatar=? WHERE id=?")->execute(['/hotel/uploads/avatars/' . $name, $me['id']]);
        $removeAvatarFile($me['avatar'] ?? null);
        flash('success', 'Profile photo updated');
        redirect($back);
    } elseif ($section === 'avatar_remove') {
        $removeAvatarFile($me['avatar'] ?? null);
        db()->prepare("UPDATE users SET avatar=NULL WHERE id=?")->execute([$me['id']]);
        flash('success', 'Profile photo removed');
        redirect($back);
    } elseif ($section === 'personal') {
        $name = trim($_POST['name'] ?? '');
        $dob = trim($_POST['date_of_birth'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');

        if ($name === '') $errors['name'] = 'Name is required';
        elseif (mb_strlen($name) < 3) $errors['name'] = 'Name must be at least 3 characters';
        elseif (mb_strlen($name) > 120) $errors['name'] = 'Name must not exceed 120 characters';

        if ($dob !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $dob);
            if (!$d || $d->format('Y-m-d') !== $dob) $errors['date_of_birth'] = 'Enter a valid date';
            elseif ($d > new DateTime('-16 years')) $errors['date_of_birth'] = 'You must be at least 16 years old';
            elseif ($d < new DateTime('-120 years')) $errors['date_of_birth'] = 'Enter a valid date of birth';
        }
        if ($gender !== '' && !in_array($gender, ['male', 'female', 'other'], true)) $errors['gender'] = 'Select a valid option';
        if (mb_strlen($address) > 255) $errors['address'] = 'Address must not exceed 255 characters';
        if (mb_strlen($city) > 100) $errors['city'] = 'City must not exceed 100 characters';
        if (mb_strlen($country) > 100) $errors['country'] = 'Country must not exceed 100 characters';

        if (!$errors) {
            db()->prepare("UPDATE users SET name=?, date_of_birth=?, gender=?, address=?, city=?, country=? WHERE id=?")
                ->execute([$name, $dob ?: null, $gender ?: null, $address ?: null, $city ?: null, $country ?: null, $me['id']]);
            flash('success', 'Personal details updated');
            redirect('/hotel/profile/personal');
        }
    } elseif ($section === 'contact') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $currentPass = $_POST['current_password_contact'] ?? '';
        $emailChanged = $email !== strtolower($me['email']);

        if ($email === '') $errors['email'] = 'Email is required';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email format';
        elseif (strlen($email) > 190) $errors['email'] = 'Email must not exceed 190 characters';
        elseif ($emailChanged) {
            $chk = db()->prepare("SELECT id FROM users WHERE email=? AND id<>?");
            $chk->execute([$email, $me['id']]);
            if ($chk->fetch()) $errors['email'] = 'This email is already used by another account';
        }
        if ($phone !== '' && !preg_match('/^[0-9]{10}$/', $phone)) $errors['phone'] = 'Phone number must be exactly 10 digits';
        // Changing the login email needs the current password
        if ($emailChanged && !$errors && !password_verify($currentPass, $me['password'])) {
            $errors['current_password_contact'] = 'Enter your current password to change your email';
        }

        if (!$errors) {
            db()->prepare("UPDATE users SET email=?, phone=? WHERE id=?")->execute([$email, $phone ?: null, $me['id']]);
            flash('success', $emailChanged ? 'Contact details updated. Use your new email next time you log in.' : 'Contact details updated');
            redirect('/hotel/profile/contact');
        }
    } elseif ($section === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $me['password'])) $errors['current_password'] = 'Current password is incorrect';
        if (strlen($new) < 6) $errors['new_password'] = 'Password must be at least 6 characters';
        elseif (strlen($new) > 255) $errors['new_password'] = 'Password is too long';
        elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/[0-9]/', $new)) $errors['new_password'] = 'Use at least one uppercase letter, one lowercase letter and one number';
        elseif (password_verify($new, $me['password'])) $errors['new_password'] = 'New password must be different from the current one';
        if (!isset($errors['new_password']) && $new !== $confirm) $errors['confirm_password'] = 'Passwords do not match';

        if (!$errors) {
            db()->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('success', 'Password changed successfully');
            redirect('/hotel/profile/password');
        }
    }

    if ($errors) { flash('error', 'Please fix the highlighted fields.'); if (isset($tabs[$section])) $tab = $section; }
}

// Values to show: submitted values after an error, otherwise saved ones
$v = function ($field) use ($me, $section, $errors) {
    if ($errors && array_key_exists($field, $_POST) && !str_contains($field, 'password')) return $_POST[$field];
    return $me[$field] ?? '';
};
$err = fn($f) => isset($errors[$f]) ? '<div class="pf-error"><i class="fas fa-exclamation-circle"></i> ' . e($errors[$f]) . '</div>' : '';
$cls = fn($f) => isset($errors[$f]) ? ' pf-invalid' : '';

$filled = count(array_filter([$me['name'], $me['email'], $me['phone'], $me['date_of_birth'], $me['gender'], $me['address'], $me['city'], $me['country'], $me['avatar'] ?? null]));
$completion = (int)round($filled / 9 * 100);
$words = array_slice(preg_split('/\s+/', trim($me['name'])), 0, 2);
$initials = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), $words)));

$title = $tabs[$tab][0] . ' | ' . APP_NAME;
require 'partials_header.php';
$userNavActive = 'profile-' . $tab;
require 'partials_user_nav.php';
?>
<style>
.pf-wrap{max-width:880px;margin:0 auto}
.pf-head{margin-bottom:24px}
.pf-head h1{margin:0 0 6px}
.pf-user{display:flex;align-items:center;gap:18px;flex-wrap:wrap}
.pf-avatar-upload{position:relative;cursor:pointer;overflow:hidden;width:84px!important;height:84px!important;font-size:28px!important}
.pf-avatar-upload img{width:100%;height:100%;object-fit:cover;display:block}
.pf-cam{position:absolute;inset:auto 0 0 0;height:30px;background:rgba(0,0,0,.55);color:#fff;font-size:13px;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .15s}
.pf-avatar-upload:hover .pf-cam,.pf-avatar-upload:focus-within .pf-cam{opacity:1}
.pf-avatar-upload.uploading{opacity:.6;pointer-events:none}
.pf-photo-actions{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:8px}
.pf-link{border:0;background:none;padding:0;color:var(--primary);font-weight:600;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.pf-link:hover{text-decoration:underline}
.pf-link-danger{color:#b42318}
.pf-photo-hint{font-size:12px;color:var(--muted)}
.pf-avatar{width:64px;height:64px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;flex:none;box-shadow:0 0 0 4px #fff,0 0 0 5px var(--border)}
.pf-user h2{margin:0;font-size:20px;color:var(--dark)}
.pf-user .muted{font-size:14px;margin-top:3px}
.pf-complete{margin-left:auto;min-width:200px}
.pf-complete .row{display:flex;justify-content:space-between;font-size:13px;color:var(--muted);margin-bottom:6px}
.pf-complete .row strong{color:var(--dark)}
.pf-bar{height:8px;background:#eef1f6;border-radius:99px;overflow:hidden}
.pf-bar span{display:block;height:100%;background:var(--accent);border-radius:99px}
.pf-card{padding:0!important;margin-bottom:20px;scroll-margin-top:20px;overflow:hidden}
.pf-card-head{display:flex;align-items:center;gap:12px;padding:18px 22px;border-bottom:1px solid var(--border)}
.pf-card-icon{width:38px;height:38px;border-radius:10px;background:#fff1e3;color:var(--accent);display:inline-flex;align-items:center;justify-content:center;flex:none}
.pf-card-head h3{margin:0;font-size:17px;color:var(--dark)}
.pf-card-head p{margin:2px 0 0;font-size:13px;color:var(--muted)}
.pf-card-body{padding:22px}
.pf-fields{display:grid;grid-template-columns:1fr 1fr;gap:16px 18px}
.pf-fields .full{grid-column:1/-1}
.pf-field label{display:block;font-weight:600;font-size:13px;color:var(--dark);margin-bottom:6px}
.pf-field label small{font-weight:400;color:var(--muted)}
.pf-field input,.pf-field select{width:100%;padding:11px 13px;border:1px solid #d9dee7;border-radius:9px;font-size:14px;font-family:inherit;box-sizing:border-box;background:#fff;transition:border-color .15s,box-shadow .15s}
.pf-field input:focus,.pf-field select:focus{border-color:var(--primary);outline:none;box-shadow:0 0 0 3px rgba(23,59,103,.1)}
.pf-invalid{border-color:#d92d20!important;box-shadow:0 0 0 3px rgba(217,45,32,.08)!important}
.pf-error{color:#b42318;font-size:12px;margin-top:5px}
.pf-hint{color:var(--muted);font-size:12px;margin-top:5px}
.pf-card-foot{display:flex;justify-content:flex-end;padding:14px 22px;background:#fafbfc;border-top:1px solid var(--border)}
.pf-pass{position:relative}
.pf-pass input{padding-right:40px}
.pf-pass button{position:absolute;right:8px;top:50%;transform:translateY(-50%);border:0;background:none;color:var(--muted);cursor:pointer;padding:6px}
.pf-strength{display:flex;gap:4px;margin-top:8px}
.pf-strength span{flex:1;height:4px;border-radius:99px;background:#eef1f6}
.pf-meta{display:grid;grid-template-columns:repeat(2,1fr);gap:0}
.pf-meta div{display:flex;justify-content:space-between;gap:10px;padding:12px 0;border-bottom:1px dashed var(--border);font-size:14px}
.pf-meta div:nth-child(odd){padding-right:18px}
.pf-meta div:nth-child(even){padding-left:18px}
.pf-meta span{color:var(--muted)}
.pf-meta strong{color:var(--dark)}
.pf-note{display:flex;gap:10px;background:#eef3f8;border-radius:10px;padding:12px 14px;font-size:13px;color:var(--primary);margin-top:18px}
@media (max-width:640px){.pf-fields{grid-template-columns:1fr}.pf-meta{grid-template-columns:1fr}.pf-meta div:nth-child(n){padding-left:0;padding-right:0}.pf-complete{margin-left:0;width:100%}}
</style>

<div class="page"><div class="container"><div class="pf-wrap">

<div class="pf-head">
<h1><?=e($tabs[$tab][0])?></h1>
<p class="muted" style="margin:0"><?=e($tabs[$tab][1])?></p>
</div>

<div class="panel" style="margin-bottom:20px">
<div class="pf-user">
<form method="post" enctype="multipart/form-data" id="avatarForm" style="margin:0">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="section" value="avatar">
<input type="hidden" name="tab" value="<?=e($tab)?>">
<input type="hidden" name="MAX_FILE_SIZE" value="2097152">
<label class="pf-avatar pf-avatar-upload" title="Change profile photo">
<?php if (!empty($me['avatar'])): ?><img src="<?=e($me['avatar'])?>" alt="Profile photo"><?php else: ?><?=e($initials ?: '?')?><?php endif; ?>
<span class="pf-cam"><i class="fas fa-camera"></i></span>
<input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp" hidden>
</label>
</form>
<div>
<h2><?=e($me['name'])?></h2>
<div class="muted"><?=e($me['email'])?> &middot; Member since <?=date('F Y', strtotime($me['created_at']))?></div>
<div class="pf-photo-actions">
<button type="button" class="pf-link" onclick="document.getElementById('avatarInput').click()"><i class="fas fa-upload"></i> <?=!empty($me['avatar']) ? 'Change photo' : 'Upload photo'?></button>
<?php if (!empty($me['avatar'])): ?>
<form method="post" style="display:inline;margin:0" onsubmit="return confirm('Remove your profile photo?')">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="section" value="avatar_remove">
<input type="hidden" name="tab" value="<?=e($tab)?>">
<button type="submit" class="pf-link pf-link-danger"><i class="fas fa-trash"></i> Remove</button>
</form>
<?php endif; ?>
<span class="pf-photo-hint">JPG, PNG or WEBP, up to 2 MB</span>
</div>
</div>
<div class="pf-complete">
<div class="row"><span>Profile completion</span><strong><?=$completion?>%</strong></div>
<div class="pf-bar"><span style="width:<?=$completion?>%"></span></div>
</div>
</div>
</div>

<?php if ($tab === 'personal'): ?>
<form method="post" class="panel pf-card" id="personal" novalidate>
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="section" value="personal">
<div class="pf-card-body">
<div class="pf-fields">
<div class="pf-field full">
<label for="name">Full name</label>
<input id="name" name="name" value="<?=e($section === 'personal' ? $v('name') : $me['name'])?>" maxlength="120" required class="<?=$cls('name')?>">
<?=$err('name')?>
</div>
<div class="pf-field">
<label for="dob">Date of birth <small>(optional)</small></label>
<input id="dob" type="date" data-no-default name="date_of_birth" value="<?=e($section === 'personal' ? $v('date_of_birth') : ($me['date_of_birth'] ?? ''))?>" max="<?=date('Y-m-d', strtotime('-16 years'))?>" class="<?=$cls('date_of_birth')?>">
<?=$err('date_of_birth')?>
</div>
<div class="pf-field">
<label for="gender">Gender <small>(optional)</small></label>
<?php $g = $section === 'personal' ? $v('gender') : ($me['gender'] ?? ''); ?>
<select id="gender" name="gender" class="<?=$cls('gender')?>">
<option value="">Prefer not to say</option>
<option value="male" <?=$g === 'male' ? 'selected' : ''?>>Male</option>
<option value="female" <?=$g === 'female' ? 'selected' : ''?>>Female</option>
<option value="other" <?=$g === 'other' ? 'selected' : ''?>>Other</option>
</select>
<?=$err('gender')?>
</div>
<div class="pf-field full">
<label for="address">Address <small>(optional)</small></label>
<input id="address" name="address" value="<?=e($section === 'personal' ? $v('address') : ($me['address'] ?? ''))?>" maxlength="255" placeholder="Street, ward, area" class="<?=$cls('address')?>">
<?=$err('address')?>
</div>
<div class="pf-field">
<label for="city">City <small>(optional)</small></label>
<input id="city" name="city" value="<?=e($section === 'personal' ? $v('city') : ($me['city'] ?? ''))?>" maxlength="100" placeholder="Kathmandu" class="<?=$cls('city')?>">
<?=$err('city')?>
</div>
<div class="pf-field">
<label for="country">Country <small>(optional)</small></label>
<input id="country" name="country" value="<?=e($section === 'personal' ? $v('country') : ($me['country'] ?? ''))?>" maxlength="100" placeholder="Nepal" class="<?=$cls('country')?>">
<?=$err('country')?>
</div>
</div>
</div>
<div class="pf-card-foot"><button class="btn orange" style="gap:8px"><i class="fas fa-save"></i> Save changes</button></div>
</form>
<?php endif; ?>

<?php if ($tab === 'contact'): ?>
<form method="post" class="panel pf-card" id="contact" novalidate>
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="section" value="contact">
<div class="pf-card-body">
<div class="pf-fields">
<div class="pf-field">
<label for="email">Email address</label>
<input id="email" type="email" name="email" value="<?=e($section === 'contact' ? $v('email') : $me['email'])?>" maxlength="190" required data-original="<?=e($me['email'])?>" class="<?=$cls('email')?>">
<?=$err('email')?>
</div>
<div class="pf-field">
<label for="phone">Phone number</label>
<input id="phone" type="tel" name="phone" value="<?=e($section === 'contact' ? $v('phone') : ($me['phone'] ?? ''))?>" maxlength="10" inputmode="numeric" placeholder="98XXXXXXXX" class="<?=$cls('phone')?>">
<?=$err('phone') ?: '<div class="pf-hint">10 digits</div>'?>
</div>
<div class="pf-field full" id="emailPassWrap" style="<?=isset($errors['current_password_contact']) || ($section === 'contact' && strtolower($v('email')) !== strtolower($me['email'])) ? '' : 'display:none'?>">
<label for="cpc">Current password <small>(required to change email)</small></label>
<div class="pf-pass"><input id="cpc" type="password" name="current_password_contact" autocomplete="current-password" class="<?=$cls('current_password_contact')?>"><button type="button" class="pf-eye" aria-label="Show password"><i class="fas fa-eye"></i></button></div>
<?=$err('current_password_contact')?>
</div>
</div>
</div>
<div class="pf-card-foot"><button class="btn orange" style="gap:8px"><i class="fas fa-save"></i> Save changes</button></div>
</form>
<?php endif; ?>

<?php if ($tab === 'password'): ?>
<form method="post" class="panel pf-card" id="password" novalidate>
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input type="hidden" name="section" value="password">
<div class="pf-card-body">
<div class="pf-fields">
<div class="pf-field full">
<label for="cp">Current password</label>
<div class="pf-pass"><input id="cp" type="password" name="current_password" autocomplete="current-password" required class="<?=$cls('current_password')?>"><button type="button" class="pf-eye" aria-label="Show password"><i class="fas fa-eye"></i></button></div>
<?=$err('current_password')?>
</div>
<div class="pf-field">
<label for="np">New password</label>
<div class="pf-pass"><input id="np" type="password" name="new_password" autocomplete="new-password" required class="<?=$cls('new_password')?>"><button type="button" class="pf-eye" aria-label="Show password"><i class="fas fa-eye"></i></button></div>
<div class="pf-strength" id="strength"><span></span><span></span><span></span><span></span></div>
<?=$err('new_password')?>
</div>
<div class="pf-field">
<label for="cfp">Confirm new password</label>
<div class="pf-pass"><input id="cfp" type="password" name="confirm_password" autocomplete="new-password" required class="<?=$cls('confirm_password')?>"><button type="button" class="pf-eye" aria-label="Show password"><i class="fas fa-eye"></i></button></div>
<?=$err('confirm_password')?>
</div>
</div>
</div>
<div class="pf-card-foot"><button class="btn orange" style="gap:8px"><i class="fas fa-key"></i> Update password</button></div>
</form>
<?php endif; ?>

<?php if ($tab === 'account'): ?>
<section class="panel pf-card" id="account">
<div class="pf-card-body">
<div class="pf-meta">
<div><span>Account type</span><strong><?=ucfirst($me['role'])?></strong></div>
<div><span>Account status</span><strong style="color:#1e7b3c"><?=ucfirst($me['status'] ?? 'active')?></strong></div>
<div><span>Member since</span><strong><?=date('M j, Y', strtotime($me['created_at']))?></strong></div>
<div><span>Last updated</span><strong><?=$me['updated_at'] ? date('M j, Y g:i A', strtotime($me['updated_at'])) : 'Never'?></strong></div>
</div>
<div class="pf-note"><i class="fas fa-info-circle" style="margin-top:2px"></i><span>To close your account, please contact the hotel at info@stayease.test.</span></div>
</div>
</section>
<?php endif; ?>

</div></div></div>

<script>
(function () {
    // Upload the profile photo as soon as one is chosen
    var ai = document.getElementById('avatarInput');
    if (ai) ai.addEventListener('change', function () {
        var f = ai.files[0]; if (!f) return;
        if (f.size > 2 * 1024 * 1024) { alert('Photo must be 2 MB or smaller.'); ai.value = ''; return; }
        if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) === -1) { alert('Use a JPG, PNG or WEBP image.'); ai.value = ''; return; }
        ai.closest('label').classList.add('uploading');
        document.getElementById('avatarForm').submit();
    });
    document.querySelectorAll('.pf-eye').forEach(function (b) {
        b.addEventListener('click', function () {
            var i = b.parentElement.querySelector('input'); var show = i.type === 'password';
            i.type = show ? 'text' : 'password';
            b.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
    });
    var email = document.getElementById('email'), wrap = document.getElementById('emailPassWrap');
    if (email) email.addEventListener('input', function () {
        wrap.style.display = email.value.trim().toLowerCase() !== email.dataset.original.toLowerCase() ? '' : 'none';
    });
    var phone = document.getElementById('phone');
    if (phone) phone.addEventListener('input', function () { this.value = this.value.replace(/\D/g, '').slice(0, 10); });
    var np = document.getElementById('np'), bars = document.querySelectorAll('#strength span');
    if (np) np.addEventListener('input', function () {
        var v = np.value, score = 0;
        if (v.length >= 6) score++; if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++; if (/\d/.test(v)) score++; if (v.length >= 10 && /[^A-Za-z0-9]/.test(v)) score++;
        var colors = ['#d92d20', '#f79009', '#12b76a', '#039855'];
        bars.forEach(function (s, i) { s.style.background = i < score ? colors[score - 1] : '#eef1f6'; });
    });
})();
</script>
<?php require 'partials_user_footer.php'; ?>
