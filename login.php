<?php
require_once 'config/config.php';
if(current_user()) redirect('index.php');
$title='Login | '.APP_NAME;

if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $email = trim($_POST['email']);
    $pass = $_POST['password'];
    
    // Rate limiting: 5 attempts per 15 minutes per IP + email combination
    $rate_limit_id = $_SERVER['REMOTE_ADDR'] . ':' . strtolower($email);
    
    if (!check_rate_limit('login', $rate_limit_id, 5, 900)) {
        $wait_time = get_rate_limit_wait_time('login', $rate_limit_id);
        $minutes = ceil($wait_time / 60);
        flash('error', "Too many login attempts. Please try again in $minutes minute(s).");
        redirect('login.php');
        exit;
    }
    
    // Validation
    $errors = [];
    
    if(empty($email)) {
        $errors[] = 'Email is required';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if(empty($pass)) {
        $errors[] = 'Password is required';
    }
    
    if(count($errors) > 0) {
        flash('error', implode("\n", $errors));
    } else {
        $s = db()->prepare("SELECT * FROM users WHERE email=?");
        $s->execute([$email]);
        $u = $s->fetch();
        
        if($u && password_verify($pass, $u['password'])) {
            // Successful login - reset rate limit
            reset_rate_limit('login', $rate_limit_id);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $u['id'];
            redirect($u['role'] === 'admin' ? 'admin/index.php' : 'index.php');
        }
        
        // Failed login - increment rate limit counter
        increment_rate_limit('login', $rate_limit_id);
        
        // Generic error message to prevent user enumeration
        flash('error', 'Invalid email or password. Please try again.');
    }
}

require 'partials_header.php'; ?>
<div class="auth-page">
<div class="auth-container">
<div class="auth-left">
<div class="auth-brand">
<div class="brand" style="font-size:32px;margin-bottom:16px">Stay<span>Ease</span></div>
<p style="color:#667085;font-size:18px;line-height:1.6">Welcome back! Sign in to manage your bookings and discover great stays.</p>
</div>
<div class="auth-features">
<div class="feature-item">
<div class="feature-icon"><i class="fas fa-hotel"></i></div>
<div>
<h4>Browse Hotels</h4>
<p>Find the perfect room for your stay</p>
</div>
</div>
<div class="feature-item">
<div class="feature-icon"><i class="fas fa-calendar-check"></i></div>
<div>
<h4>Easy Booking</h4>
<p>Reserve rooms in just a few clicks</p>
</div>
</div>
<div class="feature-item">
<div class="feature-icon"><i class="fas fa-list-check"></i></div>
<div>
<h4>Manage Bookings</h4>
<p>Track and manage your reservations</p>
</div>
</div>
</div>
</div>
<div class="auth-right">
<div class="auth-form-container">
<h2 style="margin:0 0 8px;font-size:28px;color:var(--dark)">Welcome back</h2>
<p style="color:var(--muted);margin-bottom:32px">Sign in to your account to continue</p>
<form method="post" novalidate>
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<div class="form-group">
<label>Email address</label>
<input type="email" name="email" required maxlength="190" value="<?=isset($_POST['email'])?e($_POST['email']):''?>" autofocus placeholder="you@example.com">
</div>
<div class="form-group">
<label>Password</label>
<input type="password" name="password" required placeholder="Enter your password">
</div>
<button class="btn orange" style="width:100%;padding:14px;font-size:16px;margin-top:8px">Sign In</button>
</form>
<div class="auth-divider">
<span>Demo Credentials</span>
</div>
<div class="demo-credentials">
<p><strong>Admin:</strong> admin@stayease.test / password</p>
</div>
<p class="auth-footer-text">Don't have an account? <a href="register.php">Create one</a></p>
</div>
</div>
</div>
</div>
<?php require 'partials_footer.php'; ?>
