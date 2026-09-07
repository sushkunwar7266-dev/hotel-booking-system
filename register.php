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
        flash('error', implode("\n", $errors));
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
<div class="auth-page">
<div class="auth-container">
<div class="auth-left">
<div class="auth-brand">
<div class="brand" style="font-size:32px;margin-bottom:16px">Stay<span>Ease</span></div>
<p style="color:#667085;font-size:18px;line-height:1.6">Join us today and start enjoying seamless hotel bookings and exceptional service.</p>
</div>
<div class="auth-features">
<div class="feature-item">
<div class="feature-icon"><i class="fas fa-bolt"></i></div>
<div>
<h4>Quick Registration</h4>
<p>Create your account in seconds</p>
</div>
</div>
<div class="feature-item">
<div class="feature-icon"><i class="fas fa-lock"></i></div>
<div>
<h4>Secure & Private</h4>
<p>Your data is safe with us</p>
</div>
</div>
<div class="feature-item">
<div class="feature-icon"><i class="fas fa-user-circle"></i></div>
<div>
<h4>Personalized Experience</h4>
<p>Track bookings and preferences</p>
</div>
</div>
</div>
</div>
<div class="auth-right">
<div class="auth-form-container">
<h2 style="margin:0 0 8px;font-size:28px;color:var(--dark)">Create account</h2>
<p style="color:var(--muted);margin-bottom:32px">Sign up to start booking your perfect stay</p>
<form method="post" novalidate>
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<div class="form-group">
<label>Full name</label>
<input name="name" required maxlength="120" value="<?=isset($_POST['name'])?e($_POST['name']):''?>" placeholder="John Doe">
<div class="form-hint">Minimum 3 characters</div>
</div>
<div class="form-group">
<label>Email address</label>
<input type="email" name="email" required maxlength="190" value="<?=isset($_POST['email'])?e($_POST['email']):''?>" placeholder="you@example.com">
<div class="form-hint">We'll never share your email</div>
</div>
<div class="form-group">
<label>Phone number <span style="color:var(--muted);font-weight:400">(Optional)</span></label>
<input name="phone" pattern="[0-9]{10}" maxlength="10" placeholder="9800000000" value="<?=isset($_POST['phone'])?e($_POST['phone']):''?>">
<div class="form-hint">10 digit number</div>
</div>
<div class="form-group">
<label>Password</label>
<input type="password" name="password" minlength="6" required maxlength="255" placeholder="Create a strong password">
<div class="form-hint">Must contain uppercase, lowercase, and number</div>
</div>
<button class="btn orange" style="width:100%;padding:14px;font-size:16px;margin-top:8px">Create Account</button>
</form>
<p class="auth-footer-text">Already have an account? <a href="login.php">Sign in</a></p>
</div>
</div>
</div>
</div>
<?php require 'partials_footer.php'; ?>
