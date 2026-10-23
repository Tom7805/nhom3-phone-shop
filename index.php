<?php
require __DIR__ . '/inc/db.php';
$title = 'Tổng quan';

$stats = [
    'Hãng'       => q('SELECT COUNT(*) FROM brands')->fetchColumn(),
    'Sản phẩm'   => q('SELECT COUNT(*) FROM products')->fetchColumn(),
    'Thành viên' => q('SELECT COUNT(*) FROM members')->fetchColumn(),
    'Đơn hàng'   => q('SELECT COUNT(*) FROM orders')->fetchColumn(),
];
$revenue = q("SELECT COALESCE(SUM(total),0) FROM orders WHERE status = 'done'")->fetchColumn();
$server  = q('SELECT @@hostname AS host, VERSION() AS ver')->fetch();

$latest = q(
    'SELECT o.id, o.quantity, o.total, o.status, o.created_at, m.full_name, p.name AS product
       FROM orders o
       JOIN members  m ON m.id = o.member_id
       JOIN products p ON p.id = o.product_id
      ORDER BY o.id DESC LIMIT 5'
)->fetchAll();

require __DIR__ . '/inc/header.php';
?>
<h1>Tổng quan hệ thống</h1>

<div class="cards">
  <?php foreach ($stats as $label => $num): ?>
    <div class="card stat"><span><?= e($label) ?></span><b><?= e($num) ?></b></div>
  <?php endforeach; ?>
  <div class="card stat"><span>Doanh thu (đơn hoàn thành)</span><b><?= money($revenue) ?></b></div>
</div>

<div class="card">
  <h2>Kết nối 3 lớp</h2>
  <table class="kv">
    <tr><th>Web server (IIS + PHP)</th><td><?= e(gethostname()) ?></td></tr>
    <tr><th>Database server (MySQL)</th><td><?= e($server['host']) ?> — MySQL <?= e($server['ver']) ?></td></tr>
    <tr><th>Tên DNS dùng để kết nối</th><td><?= e(DB_HOST) ?></td></tr>
    <tr><th>Cơ sở dữ liệu</th><td><?= e(DB_NAME) ?></td></tr>
  </table>
</div>

<div class="card">
  <div class="card-head"><h2>5 đơn hàng mới nhất</h2><a class="btn" href="orders.php?action=create">+ Tạo đơn</a></div>
  <table>
    <tr><th>#</th><th>Thành viên</th><th>Sản phẩm</th><th>SL</th><th>Tổng tiền</th><th>Trạng thái</th><th>Ngày tạo</th></tr>
    <?php foreach ($latest as $o): ?>
      <tr>
        <td><?= $o['id'] ?></td>
        <td><?= e($o['full_name']) ?></td>
        <td><?= e($o['product']) ?></td>
        <td><?= $o['quantity'] ?></td>
        <td><?= money($o['total']) ?></td>
        <td><span class="badge <?= e($o['status']) ?>"><?= e(ORDER_STATUS[$o['status']]) ?></span></td>
        <td><?= e($o['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$latest): ?><tr><td colspan="7" class="muted">Chưa có đơn hàng.</td></tr><?php endif; ?>
  </table>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
