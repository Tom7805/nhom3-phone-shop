<?php
// CRUD bang orders (Don hang) - lien ket members + products, tu dong tru / hoan ton kho
require __DIR__ . '/inc/db.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$errors = [];
$form   = ['member_id' => '', 'product_id' => '', 'quantity' => '1', 'status' => 'pending', 'note' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pdo = db();

    try {
        if ($action === 'delete') {                               // DELETE
            $pdo->beginTransaction();
            $o = q('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$id])->fetch();
            if ($o) {
                if ($o['status'] !== 'cancelled') {               // don chua huy -> tra hang ve kho
                    q('UPDATE products SET stock = stock + ? WHERE id = ?', [$o['quantity'], $o['product_id']]);
                }
                q('DELETE FROM orders WHERE id = ?', [$id]);
            }
            $pdo->commit();
            flash('ok', "Đã xoá đơn hàng #$id.");
            redirect('orders.php');
        }

        if ($action === 'edit') {                                 // UPDATE (trang thai + ghi chu)
            $form['status'] = post('status');
            $form['note']   = post('note');
            if (!isset(ORDER_STATUS[$form['status']])) $errors[] = 'Trạng thái không hợp lệ.';

            if (!$errors) {
                $pdo->beginTransaction();
                $o = q('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$id])->fetch() ?: redirect('orders.php');
                $p = q('SELECT stock FROM products WHERE id = ? FOR UPDATE', [$o['product_id']])->fetch();

                if ($o['status'] !== 'cancelled' && $form['status'] === 'cancelled') {
                    q('UPDATE products SET stock = stock + ? WHERE id = ?', [$o['quantity'], $o['product_id']]);
                } elseif ($o['status'] === 'cancelled' && $form['status'] !== 'cancelled') {
                    if ($p['stock'] < $o['quantity']) {
                        $errors[] = 'Không đủ tồn kho để khôi phục đơn (còn ' . $p['stock'] . ').';
                    } else {
                        q('UPDATE products SET stock = stock - ? WHERE id = ?', [$o['quantity'], $o['product_id']]);
                    }
                }
                if ($errors) {
                    $pdo->rollBack();
                } else {
                    q('UPDATE orders SET status = ?, note = ? WHERE id = ?', [$form['status'], $form['note'], $id]);
                    $pdo->commit();
                    flash('ok', "Đã cập nhật đơn hàng #$id.");
                    redirect('orders.php');
                }
            }
        } else {                                                  // CREATE
            $form = array_merge($form, [
                'member_id'  => post('member_id'),
                'product_id' => post('product_id'),
                'quantity'   => post('quantity'),
                'note'       => post('note'),
            ]);
            if (!ctype_digit($form['member_id']))  $errors[] = 'Hãy chọn thành viên.';
            if (!ctype_digit($form['product_id'])) $errors[] = 'Hãy chọn sản phẩm.';
            if (!ctype_digit($form['quantity']) || (int)$form['quantity'] < 1) $errors[] = 'Số lượng phải ≥ 1.';

            if (!$errors) {
                $pdo->beginTransaction();                         // giao dich: tao don + tru kho cung thanh cong hoac cung huy
                $p = q('SELECT name, price, stock FROM products WHERE id = ? FOR UPDATE', [$form['product_id']])->fetch();
                $qty = (int)$form['quantity'];
                if (!$p) {
                    $errors[] = 'Sản phẩm không tồn tại.';
                } elseif ($p['stock'] < $qty) {
                    $errors[] = 'Không đủ hàng: "' . $p['name'] . '" chỉ còn ' . $p['stock'] . ' chiếc.';
                }
                if ($errors) {
                    $pdo->rollBack();
                } else {
                    $total = $p['price'] * $qty;                  // tong tien tinh o server, khong tin du lieu tu form
                    q('INSERT INTO orders (member_id, product_id, quantity, unit_price, total, note) VALUES (?, ?, ?, ?, ?, ?)',
                      [$form['member_id'], $form['product_id'], $qty, $p['price'], $total, $form['note']]);
                    $newId = $pdo->lastInsertId();
                    q('UPDATE products SET stock = stock - ? WHERE id = ?', [$qty, $form['product_id']]);
                    $pdo->commit();
                    flash('ok', "Đã tạo đơn hàng #$newId, tổng " . money($total) . '.');
                    redirect('orders.php');
                }
            }
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
}

// ---------- FORM TAO DON ----------
if ($action === 'create') {
    $members  = q('SELECT id, full_name, phone FROM members ORDER BY full_name')->fetchAll();
    $products = q('SELECT id, name, price, stock FROM products ORDER BY name')->fetchAll();
    $title = 'Tạo đơn hàng';
    require __DIR__ . '/inc/header.php'; ?>
    <h1>Tạo đơn hàng</h1>
    <?php show_errors($errors); ?>
    <form method="post" class="card form">
      <?= csrf_field() ?>
      <label>Thành viên *
        <select name="member_id" required>
          <option value="">-- Chọn thành viên --</option>
          <?php foreach ($members as $m): ?>
            <option value="<?= $m['id'] ?>" <?= (string)$form['member_id'] === (string)$m['id'] ? 'selected' : '' ?>><?= e($m['full_name'] . ' – ' . $m['phone']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Sản phẩm *
        <select name="product_id" required>
          <option value="">-- Chọn sản phẩm --</option>
          <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>" <?= (string)$form['product_id'] === (string)$p['id'] ? 'selected' : '' ?> <?= $p['stock'] == 0 ? 'disabled' : '' ?>>
              <?= e($p['name'] . ' – ' . money($p['price']) . ' (còn ' . $p['stock'] . ')') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Số lượng *<input name="quantity" type="number" min="1" step="1" value="<?= e($form['quantity']) ?>" required></label>
      <label>Ghi chú<input name="note" value="<?= e($form['note']) ?>" maxlength="255"></label>
      <div class="actions"><button class="btn">Tạo đơn</button> <a class="btn ghost" href="orders.php">Huỷ</a></div>
    </form>
    <?php require __DIR__ . '/inc/footer.php';
    exit;
}

// ---------- FORM SUA DON (doi trang thai) ----------
if ($action === 'edit') {
    $o = q('SELECT o.*, m.full_name, p.name AS product
              FROM orders o JOIN members m ON m.id = o.member_id JOIN products p ON p.id = o.product_id
             WHERE o.id = ?', [$id])->fetch() ?: redirect('orders.php');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $form['status'] = $o['status'];
        $form['note']   = $o['note'];
    }
    $title = "Sửa đơn hàng #$id";
    require __DIR__ . '/inc/header.php'; ?>
    <h1><?= e($title) ?></h1>
    <?php show_errors($errors); ?>
    <form method="post" class="card form">
      <?= csrf_field() ?>
      <table class="kv">
        <tr><th>Thành viên</th><td><?= e($o['full_name']) ?></td></tr>
        <tr><th>Sản phẩm</th><td><?= e($o['product']) ?> × <?= $o['quantity'] ?></td></tr>
        <tr><th>Tổng tiền</th><td><?= money($o['total']) ?></td></tr>
      </table>
      <label>Trạng thái
        <select name="status">
          <?php foreach (ORDER_STATUS as $k => $v): ?>
            <option value="<?= $k ?>" <?= $form['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Ghi chú<input name="note" value="<?= e($form['note']) ?>" maxlength="255"></label>
      <div class="actions"><button class="btn">Lưu</button> <a class="btn ghost" href="orders.php">Huỷ</a></div>
    </form>
    <?php require __DIR__ . '/inc/footer.php';
    exit;
}

// ---------- DANH SACH (Read) + loc theo trang thai ----------
$status = $_GET['status'] ?? '';
$sql = 'SELECT o.*, m.full_name, p.name AS product
          FROM orders o
          JOIN members  m ON m.id = o.member_id
          JOIN products p ON p.id = o.product_id';
$params = [];
if (isset(ORDER_STATUS[$status])) { $sql .= ' WHERE o.status = ?'; $params[] = $status; }
$rows = q($sql . ' ORDER BY o.id DESC', $params)->fetchAll();

$title = 'Đơn hàng';
require __DIR__ . '/inc/header.php'; ?>
<div class="page-head">
  <h1>Đơn hàng</h1>
  <a class="btn" href="orders.php?action=create">+ Tạo đơn</a>
</div>
<form class="search">
  <select name="status">
    <option value="">Tất cả trạng thái</option>
    <?php foreach (ORDER_STATUS as $k => $v): ?>
      <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($v) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn ghost">Lọc</button>
</form>
<div class="card">
<table>
  <tr><th>ID</th><th>Thành viên</th><th>Sản phẩm</th><th class="right">SL</th><th class="right">Đơn giá</th><th class="right">Tổng tiền</th><th>Trạng thái</th><th>Ghi chú</th><th>Ngày tạo</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= $r['id'] ?></td>
    <td><?= e($r['full_name']) ?></td>
    <td><?= e($r['product']) ?></td>
    <td class="right"><?= $r['quantity'] ?></td>
    <td class="right"><?= money($r['unit_price']) ?></td>
    <td class="right"><?= money($r['total']) ?></td>
    <td><span class="badge <?= e($r['status']) ?>"><?= e(ORDER_STATUS[$r['status']]) ?></span></td>
    <td><?= e($r['note']) ?></td>
    <td><?= e($r['created_at']) ?></td>
    <td class="right">
      <a class="btn ghost sm" href="orders.php?action=edit&id=<?= $r['id'] ?>">Sửa</a>
      <?= delete_button('orders.php?action=delete&id=' . $r['id']) ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="10" class="muted">Không có dữ liệu.</td></tr><?php endif; ?>
</table>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
