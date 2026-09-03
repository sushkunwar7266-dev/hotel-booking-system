<?php
require_once 'config/config.php';
if(current_user()) redirect('index.php');
$title='Login | '.APP_NAME;
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$email=trim($_POST['email']);$pass=$_POST['password'];$s=db()->prepare("SELECT * FROM users WHERE email=?");$s->execute([$email]);$u=$s->fetch();
if($u && password_verify($pass,$u['password'])){session_regenerate_id(true);$_SESSION['user_id']=$u['id'];redirect($u['role']==='admin'?'admin/index.php':'index.php');}flash('error','Invalid email or password.');}
require 'partials_header.php'; ?>
<div class="page"><div class="panel auth"><h2>Welcome back</h2><form method="post"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><p><label>Email</label><input type="email" name="email" required></p><p><label>Password</label><input type="password" name="password" required></p><button class="btn orange">Login</button></form><p class="muted">Demo admin: admin@stayease.test / password</p></div></div><?php require 'partials_footer.php'; ?>
