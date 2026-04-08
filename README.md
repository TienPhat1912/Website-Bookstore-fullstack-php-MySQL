# 📚 Nhà Sách Online — Website Bán Hàng PHP/MySQL

> Đồ án cuối kỳ môn **Lập trình Web và Ứng dụng Nâng cao**  
> Website bán sách trực tuyến xây dựng bằng PHP thuần + MySQL + Bootstrap 5

---

## 🖼️ Giới thiệu dự án

**Nhà Sách Online** là một website thương mại điện tử bán sách, cho phép khách hàng duyệt, tìm kiếm và đặt mua sách trực tuyến. Hệ thống gồm hai phần:

- **Giao diện khách hàng** — duyệt sách, giỏ hàng, đặt hàng, xem lịch sử
- **Bảng điều khiển Admin** — quản lý toàn bộ hoạt động cửa hàng

**Công nghệ sử dụng:** PHP 8+, MySQL / MariaDB, PDO, Bootstrap 5, Bootstrap Icons, JavaScript (vanilla)

---

## ✨ Chức năng chính

### 👤 Phía khách hàng

| Chức năng         | Mô tả                                                                                                                                 |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| 🏠 Trang chủ      | Hiển thị sách nổi bật, sách mới nhất theo thể loại                                                                                    |
| 📖 Danh sách sách | Lọc theo thể loại, tìm kiếm cơ bản & nâng cao (tên + thể loại + khoảng giá), phân trang                                               |
| 🔍 Chi tiết sách  | Thông tin đầy đủ: tác giả, NXB, mô tả, giá bán                                                                                        |
| 🛒 Giỏ hàng       | Thêm/bớt/xoá sản phẩm, cập nhật số lượng                                                                                              |
| 💳 Đặt hàng       | Chọn địa chỉ từ tài khoản hoặc nhập địa chỉ giao hàng mới (tỉnh/quận/phường), 3 phương thức thanh toán: COD, chuyển khoản, trực tuyến |
| ✅ Xác nhận đơn   | Trang tóm tắt đơn hàng sau khi đặt thành công                                                                                         |
| 📋 Lịch sử đơn    | Xem lại toàn bộ đơn hàng đã đặt, chi tiết từng đơn                                                                                    |
| 🔐 Tài khoản      | Đăng ký, đăng nhập, đăng xuất                                                                                                         |

> **Cách tính giá:** Giá bán = Giá nhập × (100% + Tỷ lệ lợi nhuận). Giá nhập được tính theo phương pháp **bình quân gia quyền** mỗi khi nhập hàng.

---

### 🔧 Phía Admin (`/admin`)

| Module               | Chức năng                                                                                                         |
| -------------------- | ----------------------------------------------------------------------------------------------------------------- |
| 📊 Dashboard         | Thống kê tổng quan: doanh thu 7 ngày, đơn hàng mới, cảnh báo tồn kho                                              |
| 📚 Quản lý sách      | Thêm/sửa/xoá (hoặc ẩn nếu đã nhập hàng), upload ảnh bìa                                                           |
| 🏷️ Thể loại          | Thêm/sửa/xoá/ẩn; không thể xoá thể loại đang có sách                                                              |
| 📦 Nhập hàng         | Tạo phiếu nhập, thêm nhiều sách/phiếu, hoàn thành phiếu → tự động cập nhật tồn kho & giá bình quân                |
| 💰 Quản lý giá       | Chỉnh tỷ lệ lợi nhuận từng sách, xem lịch sử nhập theo lô, preview giá bán tức thì                                |
| 🛍️ Đơn hàng          | Danh sách đơn, lọc theo trạng thái/thời gian/phường, cập nhật trạng thái (xác nhận → đã giao → huỷ), xem chi tiết |
| 📈 Tồn kho & Báo cáo | Bảng tồn kho, báo cáo nhập/bán theo tháng, top sách bán chạy                                                      |
| 👥 Người dùng        | Thêm tài khoản, khoá/mở khoá, khởi tạo lại mật khẩu                                                               |

---

## 🚀 Hướng dẫn cài đặt với XAMPP

### Yêu cầu

- [XAMPP](https://www.apachefriends.org/) phiên bản 8.x trở lên (có Apache + MySQL)
- Trình duyệt Chrome / Firefox

---

### Bước 1 — Sao chép dự án vào htdocs

Clone repo hoặc giải nén vào thư mục `htdocs` của XAMPP:

```
C:\xampp\htdocs\nhasach\
```

Cấu trúc sau khi đặt đúng chỗ:

```
htdocs/
└── nhasach/
    ├── index.php
    ├── admin/
    ├── config/
    ├── Database/
    │   └── nhasach.sql   ← file database
    └── ...
```

---

### Bước 2 — Khởi động XAMPP

1. Mở **XAMPP Control Panel**
2. Nhấn **Start** cho **Apache** và **MySQL**
3. Đảm bảo cả hai hiện trạng thái **Running** (màu xanh)

---

### Bước 3 — Tạo database và import dữ liệu

1. Mở trình duyệt, vào [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Nhấn **New** ở cột trái → đặt tên `nhasach` → chọn `utf8mb4_unicode_ci` → **Create**
3. Chọn database `nhasach` vừa tạo → vào tab **Import**
4. Nhấn **Choose File** → chọn file `Database/nhasach.sql` trong thư mục dự án → **Import**

---

### Bước 4 — Kiểm tra cấu hình kết nối

Mở `config/database.php`, đảm bảo thông tin khớp với XAMPP của bạn:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'nhasach');
define('DB_USER', 'root');
define('DB_PASS', '');   // XAMPP mặc định không có mật khẩu
```

---

### Bước 5 — Truy cập website

| Trang         | URL                            |
| ------------- | ------------------------------ |
| 🏠 Khách hàng | http://localhost/nhasach       |
| 🔧 Admin      | http://localhost/nhasach/admin |

---

## 🔑 Tài khoản mặc định

### Admin

|          |                     |
| -------- | ------------------- |
| Email    | `admin@nhasach.com` |
| Mật khẩu | `password`          |

### Khách hàng demo

|          |                     |
| -------- | ------------------- |
| Email    | `example@gmail.com` |
| Mật khẩu | `123456`            |

---

---

### Bước 6 — (Tuỳ chọn) Reset dữ liệu & Seed sách mẫu

Nếu muốn xoá toàn bộ dữ liệu cũ và tạo lại sách mẫu có ảnh bìa từ đầu:

#### 6.1 — Reset database

Mở [phpMyAdmin](http://localhost/phpmyadmin), chọn database `nhasach`, vào tab **SQL** và chạy:

```sql
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE chi_tiet_don_hang;
TRUNCATE TABLE don_hang;
TRUNCATE TABLE chi_tiet_nhap;
TRUNCATE TABLE phieu_nhap;
TRUNCATE TABLE gio_hang;
TRUNCATE TABLE sach;
SET FOREIGN_KEY_CHECKS = 1;
ALTER TABLE sach AUTO_INCREMENT = 1;
```

> ⚠️ Thao tác này sẽ xoá **toàn bộ sách, phiếu nhập, đơn hàng và giỏ hàng**. Dữ liệu thể loại, tài khoản admin và khách hàng vẫn giữ nguyên.

#### 6.2 — Chạy Seed

Truy cập trình duyệt:

```
http://localhost/nhasach/load.php
```

Script sẽ tự động tạo sách mẫu cho từng thể loại kèm ảnh bìa tải từ internet. Quá trình mất khoảng **2–5 phút** tuỳ tốc độ mạng. Trang sẽ hiển thị tiến trình từng sách được thêm.

#### 6.3 — Nhập hàng ban đầu

Sau khi seed xong, sách mới có **tồn kho = 0** và chưa hiển thị trên trang bán. Cần vào Admin tạo phiếu nhập:

1. Truy cập [http://localhost/nhasach/admin](http://localhost/nhasach/admin) → đăng nhập
2. Vào **Nhập hàng** → **Tạo phiếu nhập mới**
3. Nhập ngày, thêm sách với số lượng và đơn giá → **Hoàn thành phiếu**
4. Tồn kho và giá bán sẽ tự động cập nhật

> 💡 **Mẹo:** Có thể tạo 1 phiếu nhập chung cho tất cả sách với số lượng 100 để test nhanh.

#### 6.4 — Dọn dẹp

**Xoá file `load.php` sau khi seed xong** để tránh bị chạy lại vô tình:

```bash
# Windows
del C:\xampp\htdocs\nhasach\load.php

# macOS / Linux
rm /path/to/htdocs/nhasach/load.php
```

## 📁 Cấu trúc thư mục

```text
nhasach/
|-- admin/
|   |-- includes/
|   |   |-- admin_footer.php        # Footer admin
|   |   |-- admin_header.php        # Header/sidebar + auth admin
|   |   `-- admin_search_helper.php # Helper tìm kiếm/phân trang cho admin
|   |-- categories.php              # Quản lý thể loại
|   |-- import.php                  # Danh sách phiếu nhập
|   |-- import_edit.php             # Sửa/hoàn thành phiếu nhập
|   |-- index.php                   # Dashboard admin
|   |-- inventory.php               # Tồn kho tổng hợp
|   |-- inventory_detail.php        # Chi tiết nhập/xuất
|   |-- inventory_report.php        # Báo cáo tồn kho
|   |-- login.php                   # Đăng nhập admin
|   |-- logout.php                  # Đăng xuất admin
|   |-- orders.php                  # Quản lý đơn hàng
|   |-- prices.php                  # Quản lý giá bán
|   |-- product_add.php             # Thêm sách
|   |-- product_edit.php            # Sửa/xóa/ẩn sách
|   |-- products.php                # Danh sách sách
|   |-- user_add.php                # Thêm tài khoản khách hàng
|   `-- users.php                   # Quản lý người dùng
|-- assets/
|   |-- css/
|   |   `-- style.css               # CSS toàn site
|   |-- img/
|   |   `-- .keep                   # Giữ thư mục ảnh trong git
|   `-- js/
|       |-- admin-search.js         # Script tìm kiếm admin
|       `-- diachi.json             # Dữ liệu địa chỉ
|-- config/
|   `-- database.php                # Cấu hình kết nối CSDL
|-- Database/
|   `-- nhasach.sql                 # File SQL để import
|-- includes/
|   |-- card_sach.php               # Component card sách
|   |-- diachi_dropdown.php         # Khối nhập địa chỉ dùng lại
|   |-- footer.php                  # Footer chung
|   |-- header.php                  # Header chung
|   `-- search_helper.php           # Helper tìm kiếm phía khách
|-- uploads/                        # Ảnh bìa sách được upload khi chạy
|-- book.php                        # Chi tiết sách
|-- books.php                       # Danh sách sách + lọc/tìm kiếm
|-- cart.php                        # Giỏ hàng
|-- cart_ajax.php                   # Xử lý giỏ hàng bằng AJAX
|-- checkout.php                    # Đặt hàng
|-- index.php                       # Trang chủ
|-- load.php                        # Seed/nạp dữ liệu sách mẫu
|-- login.php                       # Đăng nhập khách hàng
|-- logout.php                      # Đăng xuất khách hàng
|-- order_success.php               # Thông báo đặt hàng thành công
|-- orders.php                      # Lịch sử đơn hàng
|-- profile.php                     # Hồ sơ khách hàng
|-- README.md
|-- register.php                    # Đăng ký tài khoản
|-- search_ajax.php                 # Tìm kiếm gợi ý bằng AJAX
```

---

## ⚠️ Lưu ý

- Thư mục `uploads/` cần có quyền **ghi** để upload ảnh bìa. Trên Windows/XAMPP thường không cần cấu hình thêm.
- Nếu gặp lỗi **"Lỗi kết nối"**, kiểm tra MySQL đã Start trong XAMPP và thông tin trong `config/database.php`.
- Sách mới thêm sẽ **ẩn** và **tồn kho = 0** cho đến khi tạo phiếu nhập hàng trong Admin → Nhập hàng.
- Tính năng **Thanh toán trực tuyến** hiển thị giao diện nhưng chưa xử lý thực tế (theo yêu cầu đề bài).

---

_Đồ án môn Lập trình Web và Ứng dụng Nâng cao — Khoa CNTT, Trường Đại học Sài Gòn_
