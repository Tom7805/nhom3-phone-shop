<?php
// CRUD bang products (San pham) - co khoa ngoai toi brands
require __DIR__ . '/inc/db.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$errors = [];
$form   = ['brand_id' => '', 'name' => '', 'price' => '', 'stock' => '0', 'description' => ''];
$brands = q('SELECT id, name FROM brands ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($action === 'delete') {                                   // DELETE
        try {
            q('DELETE FROM products WHERE id = ?', [$id]);
            flash('ok', "Đã xoá sản phẩm #$id.");
        } catch (PDOException $ex) {
            if (!is_constraint_error($ex)) throw $ex;
            flash('err', 'Không xoá được: sản phẩm này đã có trong đơn hàng.');
        }
        redirect('products.php');
    }

    $form = [
        'brand_id'    => post('brand_id'),
        'name'        => post('name'),
        'price'       => post('price'),
        'stock'       => post('stock'),
        'description' => post('description'),
    ];
    if ($form['name'] === '')                                   $errors[] = 'Tên sản phẩm không được để trống.';
    if (!ctype_digit($form['brand_id']))                        $errors[] = 'Hãy chọn hãng.';
    if (!ctype_digit($form['price']) || (int)$form['price'] <= 0) $errors[] = 'Giá phải là số nguyên lớn hơn 0.';
    if (!ctype_digit($form['stock']))                           $errors[] = 'Tồn kho phải là số nguyên ≥ 0.';

    if (!$errors) {
        $params = [$form['brand_id'], $form['name'], $form['price'], $form['stock'], $form['description']];
        if ($action === 'edit') {                                 // UPDATE
            q('UPDATE products SET brand_id = ?, name = ?, price = ?, stock = ?, description = ? WHERE id = ?',
              [...$params, $id]);
            flash('ok', "Đã cập nhật sản phẩm #$id.");
        } else {                                                  // CREATE
            q('INSERT INTO products (brand_id, name, price, stock, description) VALUES (?, ?, ?, ?, ?)', $params);
            flash('ok', 'Đã thêm sản phẩm mới, id = ' . db()->lastInsertId() . '.');
        }
        redirect('products.php');
    }
} elseif ($action === 'edit') {
    $form = q('SELECT * FROM products WHERE id = ?', [$id])->fetch() ?: redirect('products.php');
}

// ---------- FORM THEM / SUA ----------
if ($action === 'create' || $action === 'edit') {
    $title = $action === 'edit' ? "Sửa sản phẩm #$id" : 'Thêm sản phẩm';
    require __DIR__ . '/inc/header.php'; ?>
    <h1><?= e($title) ?></h1>
    <?php show_errors($errors); ?>
    <form method="post" class="card form">
      <?= csrf_field() ?>
      <label>Hãng *
        <select name="brand_id" required>
          <option value="">-- Chọn hãng --</option>
          <?php foreach ($brands as $b): ?>
            <option value="<?= $b['id'] ?>" <?= (string)$form['brand_id'] === (string)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Tên sản phẩm *<input name="name" value="<?= e($form['name']) ?>" required maxlength="200"></label>
      <div class="row">
        <label>Giá (₫) *<input name="price" type="number" min="1" step="1" value="<?= e($form['price']) ?>" required></label>
        <label>Tồn kho *<input name="stock" type="number" min="0" step="1" value="<?= e($form['stock']) ?>" required></label>
      </div>
      <label>Mô tả<textarea name="description" rows="3"><?= e($form['description']) ?></textarea></label>
      <div class="actions"><button class="btn">Lưu</button> <a class="btn ghost" href="products.php">Huỷ</a></div>
    </form>
    <?php require __DIR__ . '/inc/footer.php';
    exit;
}

// ---------- DANH SACH (Read) + tim kiem, loc theo hang ----------
$kw    = trim($_GET['q'] ?? '');
$brand = (int)($_GET['brand'] ?? 0);
$sql   = 'SELECT p.*, b.name AS brand_name
            FROM products p JOIN brands b ON b.id = p.brand_id
           WHERE p.name LIKE ?';
$params = ['%' . $kw . '%'];
if ($brand > 0) { $sql .= ' AND p.brand_id = ?'; $params[] = $brand; }
$rows = q($sql . ' ORDER BY p.id', $params)->fetchAll();

$title = 'Sản phẩm';
require __DIR__ . '/inc/header.php'; ?>
<div class="page-head">
  <h1>Sản phẩm</h1>
  <a class="btn" href="products.php?action=create">+ Thêm sản phẩm</a>
</div>
<form class="search">
  <input name="q" value="<?= e($kw) ?>" placeholder="Tìm theo tên sản phẩm...">
  <select name="brand">
    <option value="0">Tất cả hãng</option>
    <?php foreach ($brands as $b): ?>
      <option value="<?= $b['id'] ?>" <?= $brand === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn ghost">Lọc</button>
</form>
<div class="card">
<table>
  <tr><th>ID</th><th>Tên sản phẩm</th><th>Hãng</th><th class="right">Giá</th><th class="right">Tồn kho</th><th>Cập nhật</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= $r['id'] ?></td>
    <td><?= e($r['name']) ?></td>
    <td><?= e($r['brand_name']) ?></td>
    <td class="right"><?= money($r['price']) ?></td>
    <td class="right <?= $r['stock'] == 0 ? 'warn' : '' ?>"><?= $r['stock'] ?></td>
    <td><?= e($r['updated_at']) ?></td>
    <td class="right">
      <a class="btn ghost sm" href="products.php?action=edit&id=<?= $r['id'] ?>">Sửa</a>
      <?= delete_button('products.php?action=delete&id=' . $r['id']) ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted">Không có dữ liệu.</td></tr><?php endif; ?>
</table>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
