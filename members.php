<?php
// CRUD bang members (Thanh vien / khach hang)
require __DIR__ . '/inc/db.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$errors = [];
$form   = ['full_name' => '', 'email' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($action === 'delete') {                                   // DELETE
        try {
            q('DELETE FROM members WHERE id = ?', [$id]);
            flash('ok', "Đã xoá thành viên #$id.");
        } catch (PDOException $ex) {
            if (!is_constraint_error($ex)) throw $ex;
            flash('err', 'Không xoá được: thành viên này đã có đơn hàng.');
        }
        redirect('members.php');
    }

    $form = [
        'full_name' => post('full_name'),
        'email'     => strtolower(post('email')),
        'phone'     => post('phone'),
        'address'   => post('address'),
    ];
    if ($form['full_name'] === '')                          $errors[] = 'Họ tên không được để trống.';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
    if (!preg_match('/^0\d{9}$/', $form['phone']))          $errors[] = 'Số điện thoại phải gồm 10 số, bắt đầu bằng 0.';

    if (!$errors) {
        try {
            $params = [$form['full_name'], $form['email'], $form['phone'], $form['address']];
            if ($action === 'edit') {                             // UPDATE
                q('UPDATE members SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?', [...$params, $id]);
                flash('ok', "Đã cập nhật thành viên #$id.");
            } else {                                              // CREATE
                q('INSERT INTO members (full_name, email, phone, address) VALUES (?, ?, ?, ?)', $params);
                flash('ok', 'Đã thêm thành viên mới, id = ' . db()->lastInsertId() . '.');
            }
            redirect('members.php');
        } catch (PDOException $ex) {
            if (!is_constraint_error($ex)) throw $ex;
            $errors[] = 'Email này đã được đăng ký.';
        }
    }
} elseif ($action === 'edit') {
    $form = q('SELECT * FROM members WHERE id = ?', [$id])->fetch() ?: redirect('members.php');
}

// ---------- FORM THEM / SUA ----------
if ($action === 'create' || $action === 'edit') {
    $title = $action === 'edit' ? "Sửa thành viên #$id" : 'Thêm thành viên';
    require __DIR__ . '/inc/header.php'; ?>
    <h1><?= e($title) ?></h1>
    <?php show_errors($errors); ?>
    <form method="post" class="card form">
      <?= csrf_field() ?>
      <label>Họ tên *<input name="full_name" value="<?= e($form['full_name']) ?>" required maxlength="100"></label>
      <div class="row">
        <label>Email *<input name="email" type="email" value="<?= e($form['email']) ?>" required maxlength="150"></label>
        <label>Số điện thoại *<input name="phone" value="<?= e($form['phone']) ?>" required maxlength="10"></label>
      </div>
      <label>Địa chỉ<input name="address" value="<?= e($form['address']) ?>" maxlength="255"></label>
      <div class="actions"><button class="btn">Lưu</button> <a class="btn ghost" href="members.php">Huỷ</a></div>
    </form>
    <?php require __DIR__ . '/inc/footer.php';
    exit;
}

// ---------- DANH SACH (Read) + tim kiem ----------
$kw   = trim($_GET['q'] ?? '');
$like = '%' . $kw . '%';
$rows = q(
    'SELECT m.*, COUNT(o.id) AS order_count
       FROM members m LEFT JOIN orders o ON o.member_id = m.id
      WHERE m.full_name LIKE ? OR m.email LIKE ? OR m.phone LIKE ?
      GROUP BY m.id ORDER BY m.id',
    [$like, $like, $like]
)->fetchAll();

$title = 'Thành viên';
require __DIR__ . '/inc/header.php'; ?>
<div class="page-head">
  <h1>Thành viên</h1>
  <a class="btn" href="members.php?action=create">+ Thêm thành viên</a>
</div>
<form class="search"><input name="q" value="<?= e($kw) ?>" placeholder="Tìm theo tên, email, số điện thoại..."><button class="btn ghost">Tìm</button></form>
<div class="card">
<table>
  <tr><th>ID</th><th>Họ tên</th><th>Email</th><th>Điện thoại</th><th>Địa chỉ</th><th>Số đơn</th><th>Ngày đăng ký</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= $r['id'] ?></td>
    <td><?= e($r['full_name']) ?></td>
    <td><?= e($r['email']) ?></td>
    <td><?= e($r['phone']) ?></td>
    <td><?= e($r['address']) ?></td>
    <td><?= $r['order_count'] ?></td>
    <td><?= e($r['created_at']) ?></td>
    <td class="right">
      <a class="btn ghost sm" href="members.php?action=edit&id=<?= $r['id'] ?>">Sửa</a>
      <?= delete_button('members.php?action=delete&id=' . $r['id']) ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="muted">Không có dữ liệu.</td></tr><?php endif; ?>
</table>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
