# Nhom3 Phone Shop

Website quản lý cửa hàng điện thoại, viết bằng **PHP + MySQL**, chạy trên **IIS (Windows Server)**.
Đây là bài thực hành môn Điện toán đám mây (CCP331) của Nhóm 3, ICTU.

Hệ thống chạy theo **kiến trúc 3 lớp** trên 3 máy chủ. Nhóm triển khai ở hai nơi: trên **VMware** (mô hình tự dựng, on-premise) và trên **AWS** (IaaS).

```
Người dùng ──HTTP:80──► WEB01 (IIS + PHP) ──MySQL:3306──► DB01 (MySQL)
                              │                              │
                              └──────── DNS / AD ────────────┘
                                         DC01 (AD DS + DNS)
```

| Máy | IP | Vai trò |
|---|---|---|
| DC01 | 192.168.52.10 | AD DS, DNS (domain `nhom3.ictu.edu.vn`) |
| DB01 | 192.168.52.20 | MySQL, CSDL `nhom3_shop` (bản ghi DNS `db`) |
| WEB01 | 192.168.52.30 | IIS + PHP 8.3, chạy website (bản ghi DNS `www`) |

---

## 1. Chức năng: 4 module CRUD

| Module | Bảng | Thêm | Xem / Tìm | Sửa | Xoá |
|---|---|---|---|---|---|
| Hãng sản xuất | `brands` | ✔ | ✔ tìm theo tên | ✔ | ✔ (chặn nếu hãng còn sản phẩm) |
| Sản phẩm | `products` → `brands` | ✔ | ✔ tìm tên, lọc theo hãng | ✔ | ✔ (chặn nếu đã có đơn) |
| Thành viên | `members` | ✔ | ✔ tìm tên / email / SĐT | ✔ | ✔ (chặn nếu đã có đơn) |
| Đơn hàng | `orders` → `members`, `products` | ✔ tự trừ tồn kho | ✔ lọc trạng thái | ✔ đổi trạng thái (huỷ thì hoàn kho) | ✔ |

Trang **Tổng quan** hiển thị số liệu của 4 bảng, doanh thu, và **tên máy WEB / máy DB đang phục vụ**. Mục này chứng minh web và CSDL chạy trên hai máy khác nhau.

Các điểm kỹ thuật:
- Dùng PDO prepared statement để chống SQL Injection.
- Dùng `htmlspecialchars` để chống XSS.
- Mọi form đều có CSRF token.
- Tạo đơn hàng chạy trong **transaction**: thêm đơn và trừ kho cùng thành công, hoặc cùng huỷ.
- **Khoá ngoại** ngăn xoá dữ liệu đang được bảng khác dùng.
- Email đặt **UNIQUE** nên không thể trùng.

## 2. Cấu trúc thư mục

```
nhom3-phone-shop/
├── index.php          Tổng quan
├── brands.php         CRUD hãng
├── products.php       CRUD sản phẩm
├── members.php        CRUD thành viên
├── orders.php         CRUD đơn hàng
├── config.php         Thông tin kết nối CSDL (DB_HOST, DB_NAME, DB_USER, DB_PASS)
├── inc/               Kết nối PDO, hàm dùng chung, header/footer (bị ẩn khỏi trình duyệt)
├── assets/style.css
├── database/nhom3_shop.sql   Tạo CSDL + dữ liệu mẫu + tài khoản webuser
├── deploy/php.ini     php.ini dựng sẵn cho PHP 8.3 trên IIS
├── storage/sessions/  Nơi lưu session PHP (IIS cần quyền Modify)
└── web.config         Cấu hình IIS: trang mặc định index.php, ẩn inc/ storage/ database/
```

`config.php` mặc định kết nối tới `db.nhom3.ictu.edu.vn`, CSDL `nhom3_shop`, tài khoản `webuser` / `Web@123`.

---

## 3. Đưa code lên GitHub

**Cách 1: qua giao diện web (dễ nhất)**
1. Vào github.com → **New repository**, đặt tên `nhom3-phone-shop`, chọn **Public** → Create.
2. Bấm **uploading an existing file**, kéo toàn bộ nội dung thư mục dự án vào → **Commit changes**.

**Cách 2: dùng Git**
```bash
cd nhom3-phone-shop
git init
git add .
git commit -m "Nhom3 Phone Shop - phien ban dau"
git branch -M main
git remote add origin https://github.com/<tai-khoan>/nhom3-phone-shop.git
git push -u origin main
```

Repo là **nguồn chuẩn** của code. Mỗi lần deploy lên server đều lấy code từ repo (`git clone` hoặc `git pull`), không chép tay.

---

## 4. Triển khai lên AWS (IaaS: dựng lại đủ 3 máy)

Ánh xạ từ VMware sang AWS:

| VMware | AWS |
|---|---|
| Mạng VMnet 192.168.52.0/24 | **VPC** `192.168.52.0/24` + 1 subnet `192.168.52.0/24` |
| Gateway 192.168.52.1 / .2 | VPC router `192.168.52.1`, DNS của AWS ở `192.168.52.2` |
| 3 máy ảo Windows Server | 3 **EC2** Windows Server 2022, đặt IP riêng cố định .10 / .20 / .30 |
| DHCP trên DC01 | **Không dùng.** VPC không hỗ trợ broadcast nên DHCP tự dựng không hoạt động. AWS tự cấp IP. |
| Windows Firewall / NAT | **Security Group** (tường lửa ở mức hạ tầng) + Internet Gateway |
| Truy cập từ ngoài | **Elastic IP** (IP công khai cố định) gắn vào WEB01 |
| Snapshot VMware | **AMI** (Create image) |

> **Chi phí:** dùng region **Asia Pacific (Singapore)**. Máy Windows có tính phí bản quyền, và t3.micro (1 GB RAM) quá yếu cho AD. Nên dùng **t3.small** cho cả 3 máy. Dùng xong nhớ **Stop** máy, chấm xong thì **Terminate** (mục 4.9). Kiểm tra credit tại *Billing and Cost Management*.

### 4.1. Tạo VPC
VPC console → **Create VPC** → chọn **VPC and more**:
- Name: `nhom3`, IPv4 CIDR: `192.168.52.0/24`
- Number of AZs: **1**. Public subnets: **1**. Private subnets: **0**
- Bấm **Customize subnets CIDR blocks**, đặt public subnet = `192.168.52.0/24`
- NAT gateways: **None**. VPC endpoints: **None**
- Create VPC. AWS tự tạo Internet Gateway và bảng định tuyến.

Vào subnet vừa tạo → **Actions → Edit subnet settings** → bật **Auto-assign public IPv4 address** để có IP công khai khi RDP.

### 4.2. Tạo 3 Security Group
EC2 → Security Groups → Create, chọn VPC `nhom3`:

| Tên | Inbound rule | Ý nghĩa |
|---|---|---|
| `nhom3-dc` | All traffic – Source `192.168.52.0/24`; RDP 3389 – My IP | DC chỉ phục vụ trong VPC |
| `nhom3-web` | HTTP 80 – `0.0.0.0/0`; All ICMP-IPv4 – `192.168.52.0/24`; RDP 3389 – My IP | Chỉ WEB được mở ra Internet |
| `nhom3-db` | MySQL/Aurora 3306 – Source **`nhom3-web`**; All ICMP-IPv4 – `192.168.52.0/24`; RDP 3389 – My IP | CSDL **chỉ nhận kết nối từ WEB** |

Security Group là **stateful**: DB và WEB tự gọi ra DC để join domain hay hỏi DNS mà không cần mở thêm chiều vào.

### 4.3. Tạo 3 máy EC2
EC2 → **Launch instance**, tạo lần lượt 3 máy:
- Name: `DC01` / `DB01` / `WEB01`
- AMI: **Microsoft Windows Server 2022 Base**
- Instance type: **t3.small**
- Key pair: tạo một lần `nhom3-key` (.pem), **giữ file này cẩn thận**, dùng chung cho cả 3 máy
- Network settings → Edit: VPC `nhom3`, subnet public, Auto-assign public IP **Enable**, chọn đúng Security Group ở 4.2
- **Advanced network configuration → Primary IP**: `192.168.52.10` / `.20` / `.30`
- Storage: 30 GiB gp3

**Đăng nhập:** chọn máy → **Connect → RDP client** → **Get password** → tải file `nhom3-key.pem` lên để giải mã mật khẩu Administrator → **Download remote desktop file** rồi mở.

**Elastic IP:** EC2 → Elastic IPs → **Allocate** → **Associate** với WEB01. Đây là địa chỉ public của website.

### 4.4. DC01: AD DS + DNS
1. Đổi tên máy thành `DC01` → restart.
2. Đặt IP tĩnh trùng với IP AWS đã cấp: IP `192.168.52.10`, mask `255.255.255.0`, gateway `192.168.52.1`, DNS `127.0.0.1`. **Gõ đúng IP, gõ sai sẽ mất kết nối RDP.**
3. Server Manager → Add Roles → **Active Directory Domain Services** (và DNS) → Promote: *Add a new forest* `nhom3.ictu.edu.vn` → restart.
4. DNS Manager:
   - Forwarders = `192.168.52.2` (DNS của AWS, giúp các máy ra được Internet).
   - Trong zone `nhom3.ictu.edu.vn`, tạo bản ghi A: `db` → `192.168.52.20`, `www` → `192.168.52.30`.
5. **Không cài DHCP.** Trong VPC, AWS cấp IP qua DHCP của chính nó.

### 4.5. DB01: MySQL
1. Đổi tên máy thành `DB01`. Card mạng giữ IP tự động (AWS luôn cấp .20), chỉ sửa **DNS = 192.168.52.10** → join domain `nhom3.ictu.edu.vn` → restart.
2. Cài **Visual C++ Redistributable x64**, rồi cài **MySQL Server** (bản MSI 8.4 LTS, hoặc bộ cài 8.0 nhóm đã dùng): Server only, port 3306, tick *Open Windows Firewall port*, mật khẩu root `Root@123`.
3. Chép `database/nhom3_shop.sql` sang máy, bằng cách copy/paste qua RDP hoặc tải từ GitHub, rồi chạy:
   ```
   "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" -u root -p --default-character-set=utf8mb4 < C:\nhom3_shop.sql
   ```
   Nếu dùng 8.0 thì đổi `8.4` thành `8.0` trong đường dẫn. File SQL tạo luôn tài khoản `webuser`, chỉ có quyền SELECT / INSERT / UPDATE / DELETE trên `nhom3_shop`.

### 4.6. WEB01: IIS + PHP + deploy code
1. Đổi tên máy thành `WEB01`, DNS = `192.168.52.10`, join domain → restart.
2. Add Roles → **Web Server (IIS)**, tick thêm **CGI** (Application Development).
3. Cài **VC++ Redistributable x64**. Giải nén **PHP 8.3 NTS x64** vào `C:\PHP`. Chép file `deploy/php.ini` có sẵn trong repo vào `C:\PHP\php.ini` (làm sau bước 5 khi đã clone code). File này đã bật sẵn các dòng:
   - `extension_dir = "C:\PHP\ext"`
   - `extension=pdo_mysql`, `extension=mbstring`, `extension=openssl`
   - `cgi.fix_pathinfo=1`, `fastcgi.impersonate=1`
   - `date.timezone = Asia/Ho_Chi_Minh`
4. IIS Manager → server → **Handler Mappings → Add Module Mapping**: `*.php`, `FastCgiModule`, `C:\PHP\php-cgi.exe`, tên `PHP_via_FastCGI`.
5. **Deploy code từ GitHub:** cài *Git for Windows*, rồi chạy PowerShell:
   ```powershell
   git clone https://github.com/<tai-khoan>/nhom3-phone-shop.git C:\inetpub\nhom3-phone-shop
   icacls C:\PHP /grant "IIS_IUSRS:(OI)(CI)RX" "IUSR:(OI)(CI)RX" /T /Q
   icacls C:\inetpub\nhom3-phone-shop /grant "IIS_IUSRS:(OI)(CI)RX" "IUSR:(OI)(CI)RX" /T /Q
   icacls C:\inetpub\nhom3-phone-shop\storage\sessions /grant "IIS_IUSRS:(OI)(CI)M" "IUSR:(OI)(CI)M" /T /Q
   ```
6. IIS Manager → **Default Web Site → Basic Settings** → Physical path = `C:\inetpub\nhom3-phone-shop` → chạy `iisreset`.
7. Kiểm tra:
   - `ping db.nhom3.ictu.edu.vn` phải ra 192.168.52.20.
   - Mở `http://localhost` thấy trang Tổng quan.

**Cập nhật code về sau:** sửa code → push lên GitHub → trên WEB01 chạy `cd C:\inetpub\nhom3-phone-shop` rồi `git pull`.

### 4.7. Truy cập từ Internet
- Trên laptop bất kỳ mở `http://<Elastic-IP>`.
- Muốn gõ tên miền khi demo: tên `www.nhom3.ictu.edu.vn` chỉ phân giải được bên trong VPC. Trên laptop demo, sửa file `C:\Windows\System32\drivers\etc\hosts` (mở Notepad bằng quyền Admin), thêm dòng:
  ```
  <Elastic-IP>   www.nhom3.ictu.edu.vn
  ```
  Khi triển khai thật thì mua tên miền và trỏ bản ghi A (hoặc dùng Route 53) về Elastic IP.

### 4.8. Sao lưu
EC2 → chọn máy → **Actions → Image and templates → Create image** (AMI). Cách này tương đương snapshot "official" trên VMware.

### 4.9. Dọn dẹp để không mất tiền
Chấm xong thì làm theo thứ tự:
1. **Terminate** 3 instance.
2. **Release** Elastic IP.
3. Xoá AMI (Deregister) và các snapshot đi kèm.
4. Xoá VPC `nhom3`.

---

## 5. Kịch bản demo nhanh (lệnh kiểm tra trên DB01)

Mở MySQL:
```
"C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" -u root -p nhom3_shop
```

| Thao tác trên web | Lệnh kiểm tra trên DB01 |
|---|---|
| Thêm thành viên | `SELECT * FROM members ORDER BY id DESC LIMIT 1;` |
| Thêm / sửa sản phẩm | `SELECT id, name, price, stock, updated_at FROM products ORDER BY updated_at DESC LIMIT 3;` |
| Tạo đơn hàng | `SELECT * FROM orders ORDER BY id DESC LIMIT 1;` và `SELECT name, stock FROM products WHERE id = <id SP>;` (tồn kho đã giảm) |
| Huỷ đơn | `SELECT id, status FROM orders WHERE id = <id>;` (tồn kho tăng lại) |
| Xoá hãng còn sản phẩm | Web báo lỗi ràng buộc khoá ngoại, `SELECT * FROM brands;` vẫn còn hãng đó |
| Thêm dữ liệu trực tiếp trong DB | `INSERT INTO brands(name, country) VALUES ('Nokia','Phần Lan');` → tải lại trang Hãng trên web là thấy |
