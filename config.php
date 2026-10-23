<?php
// ================= CAU HINH KET NOI CSDL =================
// WEB server (IIS + PHP) ket noi toi DB server (MySQL) qua ten DNS noi bo.
// Doi moi truong (VMware / AWS) chi can sua cac gia tri duoi day
// hoac dat bien moi truong DB_HOST, DB_NAME, DB_USER, DB_PASS.

define('DB_HOST', getenv('DB_HOST') ?: 'db.nhom3.ictu.edu.vn');
define('DB_NAME', getenv('DB_NAME') ?: 'nhom3_shop');
define('DB_USER', getenv('DB_USER') ?: 'webuser');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'Web@123');

define('APP_NAME', 'Nhom3 Phone Shop');
