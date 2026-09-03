<?php
require_once 'config/config.php';
if(current_user()) redirect('index.php');
$title='Register | '.APP_NAME;
if($_SERVER['REQUEST_METHOD']==='POST'){ verify_csrf(); $name=trim($_POST['name']);$email=trim($_POST['email']);$phone=trim($_POST['phone']);$pass=$_POST['password'];
if(!$name||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<6){flash('error','Enter valid details. Password must be at least 6 characters.');}
else {try{$s=db()->prepare("INSERT INTO users(name,email,phone,password) VALUES(?,?,?,?)");$s->execute([$name,$email,$phone,password_hash($pass,PASSWORD_DEFAULT)]);flash('success','Account created. Please log in.');redirect('login.php');}catch(PDOException $e){flash('error','Email already exists.');}}
}
require 'partials_header.php'; ?>
<div class="page"><div class="panel auth"><h2>Create account</h2><form method="post"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><p><label>Full name</label><input name="name" required></p><p><label>Email</label><input type="email" name="email" required></p><p><label>Phone</label><input name="phone"></p><p><label>Password</label><input type="password" name="password" minlength="6" required></p><button class="btn orange">Register</button></form></div></div><?php require 'partials_footer.php'; ?>
