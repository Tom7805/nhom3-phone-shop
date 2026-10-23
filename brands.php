<?php
// CRUD bang brands (Hang san xuat)
require __DIR__ . '/inc/db.php';

$action = $_GET['action'] ?? 'list';   // list | create | edit | delete
$id     = (int)($_GET['id'] ?? 0);
$errors = [];
$form   = ['name' => '', 'country' => ''];

// ---------- XU LY POST (Create / Update / Delete) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($action === 'delete') {                                   // DELETE
        try {
            q('DELETE FROM brands WHERE id = ?', [$id]);
            flash('ok', "Đã xoá hãng #$id.");
        } catch (PDOException $ex) {
            if (!is_constraint_error($ex)) throw $ex;
            flash('err', 'Không xoá được: hãng này vẫn còn sản phẩm (ràng buộc khoá ngoại).');
        }
        redirect('brands.php');
    }

    $form = ['name' => post('name'), 'country' => post('country')];
    if ($form['name'] === '') $errors[] = 'Tên hãng không được để trống.';

    if (!$errors) {
        try {
            if ($action === 'edit') {                             // UPDATE
                q('UPDATE brands SET name = ?, country = ? WHERE id = ?', [$form['name'], $form['country'], $id]);
                flash('ok', "Đã cập nhật hãng #$id.");
            } else {                                              // CREATE
                q('INSERT INTO brands (name, country) VALUES (?, ?)', [$form['name'], $form['country']]);
                flash('ok', 'Đã thêm hãng mới, id = ' . db()->lastInsertId() . '.');
            }
            redirect('brands.php');
        } catch (PDOException $ex) {
            if (!is_constraint_error($ex)) throw $ex;
            $errors[] = 'Tên hãng đã tồn tại.';
        }
    }
} elseif ($action === 'edit') {
    $form = q('SELECT * FROM brands WHERE id = ?', [$id])->fetch() ?: redirect('brands.php');
}

// ---------- FORM THEM / SUA ----------
if ($action === 'create' || $action === 'edit') {
    $title = $action === 'edit' ? "Sửa hãng #$id" : 'Thêm hãng';
    require __DIR__ . '/inc/header.php'; ?>
    <h1><?= e($title) ?></h1>
    <?php show_errors($errors); ?>
    <form method="post" class="card form">
      <?= csrf_field() ?>
      <label>Tên hãng *<input name="name" value="<?= e($form['name']) ?>" required maxlength="100"></label>
      <label>Quốc gia<input name="country" value="<?= e($form['country']) ?>" maxlength="100"></label>
      <div class="actions"><button class="btn">Lưu</button> <a class="btn ghost" href="brands.php">Huỷ</a></div>
    </form>
    <?php require __DIR__ . '/inc/footer.php';
    exit;
}

// ---------- DANH SACH (Read) ----------
$kw   = trim($_GET['q'] ?? '');
$rows = q(
    'SELECT b.*, COUNT(p.id) AS product_count
       FROM brands b LEFT JOIN products p ON p.brand_id = b.id
      WHERE b.name LIKE ?
      GROUP BY b.id ORDER BY b.id',
    ['%' . $kw . '%']
)->fetchAll();

$title = 'Hãng sản xuất';
require __DIR__ . '/inc/header.php'; ?>
<div class="page-head">
  <h1>Hãng sản xuất</h1>
  <a class="btn" href="brands.php?action=create">+ Thêm hãng</a>
</div>
<form class="search"><input name="q" value="<?= e($kw) ?>" placeholder="Tìm theo tên hãng..."><button class="btn ghost">Tìm</button></form>
<div class="card">
<table>
  <tr><th>ID</th><th>Tên hãng</th><th>Quốc gia</th><th>Số sản phẩm</th><th>Ngày tạo</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= $r['id'] ?></td>
    <td><?= e($r['name']) ?></td>
    <td><?= e($r['country']) ?></td>
    <td><?= $r['product_count'] ?></td>
    <td><?= e($r['created_at']) ?></td>
    <td class="right">
      <a class="btn ghost sm" href="brands.php?action=edit&id=<?= $r['id'] ?>">Sửa</a>
      <?= delete_button('brands.php?action=delete&id=' . $r['id']) ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="muted">Không có dữ liệu.</td></tr><?php endif; ?>
</table>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
