<?php 
require_once __DIR__ . '/config/config.php'; 
$user = current_user(); 
$flash = get_flash();
// Get base URL path
$scriptName = $_SERVER['SCRIPT_NAME'];
$baseUrl = str_replace(basename($scriptName), '', $scriptName);
$baseUrl = dirname($baseUrl) === '/' ? '/' : rtrim(dirname($baseUrl), '/') . '/';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title ?? APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/hotel/assets/style.css"></head><body>
<nav class="nav"><div class="container nav-inner"><a class="brand" href="/hotel/index.php">Stay<span>Ease</span></a>
<div class="nav-links"><a href="/hotel/index.php">Home</a><a href="/hotel/rooms.php">Rooms</a><?php if($user): ?><a href="/hotel/my_bookings.php">My Bookings</a><?php if($user['role']==='admin'): ?><a href="/hotel/admin/index.php">Admin</a><?php endif; ?><a href="/hotel/logout.php" class="btn light">Logout</a><?php else: ?><a href="/hotel/login.php">Login</a><a href="/hotel/register.php" class="btn orange">Register</a><?php endif; ?></div></div></nav>
<?php if($flash): ?><div class="container" style="padding-top:18px"><div class="alert <?= e($flash['type']) ?>"><?= nl2br(e($flash['message'])) ?></div></div><?php endif; ?>
