<?php
// Ket noi CSDL + cac ham dung chung cho moi trang
require_once __DIR__ . '/../config.php';

// Session luu trong thu muc rieng cua website (IIS can quyen Modify)
$sessDir = __DIR__ . '/../storage/sessions';
if (is_dir($sessDir) && is_writable($sessDir)) {
    session_save_path($sessDir);
}
session_start();

// Mo 1 ket noi PDO toi MySQL (dung lai trong ca request)
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // prepared statement that -> chong SQL Injection
        ]);
    }
    return $pdo;
}

// Chay 1 cau SQL co tham so (?)
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

// In du lieu ra HTML an toan (chong XSS)
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function money($n): string
{
    return number_format((float)$n, 0, ',', '.') . ' ₫';
}

function post(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// Thong bao hien 1 lan sau khi chuyen trang
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function show_flash(): void
{
    foreach ($_SESSION['flash'] ?? [] as [$type, $msg]) {
        echo '<div class="alert ' . e($type) . '">' . e($msg) . '</div>';
    }
    unset($_SESSION['flash']);
}

function show_errors(array $errors): void
{
    foreach ($errors as $msg) {
        echo '<div class="alert err">' . e($msg) . '</div>';
    }
}

// Chong CSRF cho moi form POST
function csrf_field(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return '<input type="hidden" name="_csrf" value="' . e($_SESSION['csrf']) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(400);
        exit('Phiên làm việc hết hạn, vui lòng tải lại trang.');
    }
}

// Nut xoa (gui POST, co hoi xac nhan)
function delete_button(string $url, string $label = 'Xoá'): string
{
    return '<form method="post" action="' . e($url) . '" class="inline" '
         . 'onsubmit="return confirm(\'Chắc chắn xoá?\')">'
         . csrf_field() . '<button class="btn danger sm">' . e($label) . '</button></form>';
}

// Ma SQLSTATE 23000 = vi pham rang buoc (trung UNIQUE, dang duoc khoa ngoai tham chieu...)
function is_constraint_error(PDOException $ex): bool
{
    return $ex->getCode() === '23000';
}

const ORDER_STATUS = [
    'pending'   => 'Chờ xử lý',
    'shipping'  => 'Đang giao',
    'done'      => 'Hoàn thành',
    'cancelled' => 'Đã huỷ',
];
