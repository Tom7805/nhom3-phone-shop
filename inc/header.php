<?php
$current = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$menu = [
    'index.php'    => 'Tổng quan',
    'brands.php'   => 'Hãng',
    'products.php' => 'Sản phẩm',
    'members.php'  => 'Thành viên',
    'orders.php'   => 'Đơn hàng',
];
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?= e(($title ?? '') . ' · ' . APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <div class="wrap">
    <a class="brand" href="index.php"><span class="logo">N3</span> <?= e(APP_NAME) ?></a>
    <nav>
      <?php foreach ($menu as $file => $label): ?>
        <a href="<?= $file ?>" class="<?= $current === $file ? 'active' : '' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>
<main class="wrap">
<?php show_flash(); ?>
