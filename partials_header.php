<?php require_once __DIR__ . '/config/config.php'; $user = current_user(); $flash = get_flash(); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title ?? APP_NAME) ?></title><link rel="stylesheet" href="assets/style.css"></head><body>
<nav class="nav"><div class="container nav-inner"><a class="brand" href="index.php">Stay<span>Ease</span></a>
<div class="nav-links"><a href="index.php">Home</a><a href="rooms.php">Rooms</a><?php if($user): ?><a href="my_bookings.php">My Bookings</a><?php if($user['role']==='admin'): ?><a href="admin/index.php">Admin</a><?php endif; ?><a href="logout.php" class="btn light">Logout</a><?php else: ?><a href="login.php">Login</a><a href="register.php" class="btn orange">Register</a><?php endif; ?></div></div></nav>
<?php if($flash): ?><div class="container" style="padding-top:18px"><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div></div><?php endif; ?>
