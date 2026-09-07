<?php
require_once 'config/config.php';
if(current_user()) redirect('index.php');
$title='Login | '.APP_NAME;

if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $email = trim($_POST['email']);
    $pass = $_POST['password'];
    
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
        flash('error', implode('<br>', $errors));
    } else {
        $s = db()->prepare("SELECT * FROM users WHERE email=?");
        $s->execute([$email]);
        $u = $s->fetch();
        
        if($u && password_verify($pass, $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $u['id'];
            redirect($u['role'] === 'admin' ? 'admin/index.php' : 'index.php');
        }
        flash('error', 'Invalid email or password. Please try again.');
    }
}

require 'partials_header.php'; ?>
<div class="page"><div class="panel auth"><h2>Welcome back</h2>
<form method="post" novalidate>
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<p><label>Email</label><input type="email" name="email" required maxlength="190" value="<?=isset($_POST['email'])?e($_POST['email']):''?>" autofocus></p>
<p><label>Password</label><input type="password" name="password" required></p>
<button class="btn orange">Login</button>
</form>
<p class="muted" style="margin-top:20px">Demo admin: <strong>admin@stayease.test</strong> / <strong>password</strong></p>
<p class="muted">Don't have an account? <a href="register.php" style="color:var(--accent);font-weight:700">Register</a></p>
</div></div><?php require 'partials_footer.php'; ?>
