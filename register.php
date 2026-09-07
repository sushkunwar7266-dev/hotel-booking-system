<?php
require_once 'config/config.php';
if(current_user()) redirect('index.php');
$title='Register | '.APP_NAME;

if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $pass = $_POST['password'];
    
    // Validation
    $errors = [];
    
    // Name validation
    if(empty($name)) {
        $errors[] = 'Name is required';
    } elseif(strlen($name) < 3) {
        $errors[] = 'Name must be at least 3 characters';
    } elseif(strlen($name) > 120) {
        $errors[] = 'Name must not exceed 120 characters';
    }
    
    // Email validation
    if(empty($email)) {
        $errors[] = 'Email is required';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    } elseif(strlen($email) > 190) {
        $errors[] = 'Email must not exceed 190 characters';
    }
    
    // Phone validation
    if(!empty($phone)) {
        if(!preg_match('/^[0-9]{10}$/', $phone)) {
            $errors[] = 'Phone number must be exactly 10 digits';
        }
    }
    
    // Password validation
    if(empty($pass)) {
        $errors[] = 'Password is required';
    } elseif(strlen($pass) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    } elseif(strlen($pass) > 255) {
        $errors[] = 'Password is too long';
    } elseif(!preg_match('/[A-Z]/', $pass)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    } elseif(!preg_match('/[a-z]/', $pass)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    } elseif(!preg_match('/[0-9]/', $pass)) {
        $errors[] = 'Password must contain at least one number';
    }
    
    if(count($errors) > 0) {
        flash('error', implode('<br>', $errors));
    } else {
        try {
            $s = db()->prepare("INSERT INTO users(name,email,phone,password) VALUES(?,?,?,?)");
            $s->execute([$name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT)]);
            flash('success', 'Account created successfully. Please log in.');
            redirect('login.php');
        } catch(PDOException $e) {
            if(strpos($e->getMessage(), 'Duplicate entry') !== false) {
                flash('error', 'Email already exists. Please use a different email or login.');
            } else {
                flash('error', 'Registration failed. Please try again.');
            }
        }
    }
}

require 'partials_header.php'; ?>
<div class="page"><div class="panel auth"><h2>Create account</h2>
<form method="post" novalidate>
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<p><label>Full name</label><input name="name" required maxlength="120" value="<?=isset($_POST['name'])?e($_POST['name']):''?>"><div class="form-hint">Minimum 3 characters</div></p>
<p><label>Email</label><input type="email" name="email" required maxlength="190" value="<?=isset($_POST['email'])?e($_POST['email']):''?>"><div class="form-hint">We'll never share your email</div></p>
<p><label>Phone</label><input name="phone" pattern="[0-9]{10}" maxlength="10" placeholder="10 digits" value="<?=isset($_POST['phone'])?e($_POST['phone']):''?>"><div class="form-hint">Optional: 10 digit number</div></p>
<p><label>Password</label><input type="password" name="password" minlength="6" required maxlength="255"><div class="form-hint">Must contain uppercase, lowercase, and number</div></p>
<button class="btn orange">Register</button>
</form>
<p class="muted" style="margin-top:20px">Already have an account? <a href="login.php" style="color:var(--accent);font-weight:700">Login</a></p>
</div></div><?php require 'partials_footer.php'; ?>
