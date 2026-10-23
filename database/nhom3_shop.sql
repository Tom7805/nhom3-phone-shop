-- =====================================================================
--  NHOM3 PHONE SHOP - CSDL nhom3_shop (MySQL 8.0)
--  Chay tren DB server:
--    mysql -u root -p --default-character-set=utf8mb4 < nhom3_shop.sql
-- =====================================================================

DROP DATABASE IF EXISTS nhom3_shop;
CREATE DATABASE nhom3_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nhom3_shop;

-- 1. Hang san xuat
CREATE TABLE brands (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL UNIQUE,
  country     VARCHAR(100) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. San pham (moi san pham thuoc 1 hang)
CREATE TABLE products (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  brand_id     INT UNSIGNED NOT NULL,
  name         VARCHAR(200) NOT NULL,
  price        DECIMAL(12,0) NOT NULL,
  stock        INT NOT NULL DEFAULT 0,
  description  TEXT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_brand FOREIGN KEY (brand_id) REFERENCES brands(id),
  CONSTRAINT chk_products_price CHECK (price > 0),
  CONSTRAINT chk_products_stock CHECK (stock >= 0)
) ENGINE=InnoDB;

-- 3. Thanh vien (khach hang)
CREATE TABLE members (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name   VARCHAR(100) NOT NULL,
  email       VARCHAR(150) NOT NULL UNIQUE,
  phone       VARCHAR(10)  NOT NULL,
  address     VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. Don hang (1 thanh vien mua 1 san pham voi so luong)
CREATE TABLE orders (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id   INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  quantity    INT NOT NULL,
  unit_price  DECIMAL(12,0) NOT NULL,
  total       DECIMAL(14,0) NOT NULL,
  status      ENUM('pending','shipping','done','cancelled') NOT NULL DEFAULT 'pending',
  note        VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_member  FOREIGN KEY (member_id)  REFERENCES members(id),
  CONSTRAINT fk_orders_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT chk_orders_qty CHECK (quantity > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- DU LIEU MAU
-- ---------------------------------------------------------------------
INSERT INTO brands (name, country) VALUES
 ('Apple',   'Mỹ'),
 ('Samsung', 'Hàn Quốc'),
 ('Xiaomi',  'Trung Quốc'),
 ('OPPO',    'Trung Quốc'),
 ('Sony',    'Nhật Bản');

INSERT INTO products (brand_id, name, price, stock, description) VALUES
 (1, 'iPhone 16 Pro Max 256GB', 30990000, 12, 'Chip A18 Pro, màn hình 6.9 inch'),
 (1, 'iPhone 16 128GB',          21990000, 20, 'Chip A18, camera 48MP'),
 (1, 'AirPods Pro 2',             5990000, 30, 'Tai nghe chống ồn chủ động'),
 (2, 'Galaxy S25 Ultra 256GB',   28990000, 10, 'Bút S Pen, camera 200MP'),
 (2, 'Galaxy A56 5G',             9490000, 25, 'Pin 5000mAh, màn hình 120Hz'),
 (2, 'Galaxy Watch 7',            6490000, 15, 'Đồng hồ thông minh theo dõi sức khoẻ'),
 (3, 'Xiaomi 15 12GB/256GB',     19990000,  8, 'Camera Leica'),
 (3, 'Redmi Note 14 Pro',         7290000, 35, 'Sạc nhanh 45W'),
 (4, 'OPPO Reno 13 5G',          12990000, 14, 'Chống nước IP69'),
 (5, 'Sony WH-1000XM5',           7990000,  0, 'Tai nghe chụp tai chống ồn');

INSERT INTO members (full_name, email, phone, address) VALUES
 ('Nguyễn Văn An',  'an.nguyen@gmail.com',  '0912345678', '12 Lương Ngọc Quyến, Thái Nguyên'),
 ('Trần Thị Bình',  'binh.tran@gmail.com',  '0987654321', '45 Hoàng Văn Thụ, Thái Nguyên'),
 ('Lê Minh Châu',   'chau.le@gmail.com',    '0901122334', '8 Cầu Giấy, Hà Nội'),
 ('Phạm Quốc Dũng', 'dung.pham@gmail.com',  '0934567890', '20 Quang Trung, Thái Nguyên'),
 ('Võ Thu Hà',      'ha.vo@gmail.com',      '0978123456', '5 Lê Lợi, Bắc Ninh');

-- Don hang mau (ton kho o tren da tru san so luong cua cac don chua huy)
INSERT INTO orders (member_id, product_id, quantity, unit_price, total, status, note, created_at) VALUES
 (1, 1, 1, 30990000, 30990000, 'done',      'Giao giờ hành chính',  '2026-10-10 09:15:00'),
 (2, 5, 2,  9490000, 18980000, 'done',      NULL,                   '2026-10-12 14:20:00'),
 (3, 3, 1,  5990000,  5990000, 'shipping',  'Gọi trước khi giao',   '2026-10-18 10:05:00'),
 (4, 8, 1,  7290000,  7290000, 'pending',   NULL,                   '2026-10-20 16:40:00'),
 (5, 4, 1, 28990000, 28990000, 'cancelled', 'Khách đổi ý',          '2026-10-21 08:30:00'),
 (1, 6, 1,  6490000,  6490000, 'pending',   NULL,                   '2026-10-22 19:10:00');

-- ---------------------------------------------------------------------
-- TAI KHOAN CHO WEB SERVER (quyen toi thieu: chi thao tac du lieu nhom3_shop)
-- '%' = cho phep ket noi tu may khac (WEB server); co the doi thanh IP cua WEB.
-- ---------------------------------------------------------------------
CREATE USER IF NOT EXISTS 'webuser'@'%' IDENTIFIED BY 'Web@123';
GRANT SELECT, INSERT, UPDATE, DELETE ON nhom3_shop.* TO 'webuser'@'%';
FLUSH PRIVILEGES;
