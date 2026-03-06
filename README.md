# 📚 Nhà Sách Online — Website Bán Hàng PHP/MySQL

> Đồ án cuối kỳ môn **Lập trình Web và Ứng dụng Nâng cao**  
> Website bán sách trực tuyến xây dựng bằng PHP thuần + MySQL + Bootstrap 5

---

## 🖼️ Giới thiệu dự án

**Nhà Sách Online** là một website thương mại điện tử bán sách, cho phép khách hàng duyệt, tìm kiếm và đặt mua sách trực tuyến. Hệ thống bao gồm hai phần:

- **Giao diện khách hàng** — duyệt sách, giỏ hàng, đặt hàng, xem lịch sử
- **Bảng điều khiển Admin** — quản lý toàn bộ hoạt động cửa hàng

**Công nghệ sử dụng:** PHP 8+, MySQL, PDO, Bootstrap 5, Bootstrap Icons, JavaScript (vanilla)

---

## ✨ Chức năng chính

### 👤 Phía khách hàng

| Chức năng         | Mô tả                                                                                             |
| ----------------- | ------------------------------------------------------------------------------------------------- |
| 🏠 Trang chủ      | Hiển thị sách nổi bật, sách mới nhất theo thể loại                                                |
| 📖 Danh sách sách | Lọc theo thể loại, tìm kiếm cơ bản & nâng cao (tên + thể loại + khoảng giá), phân trang           |
| 🔍 Chi tiết sách  | Thông tin đầy đủ: tác giả, NXB, mô tả, giá bán                                                    |
| 🛒 Giỏ hàng       | Thêm/bớt/xoá sản phẩm, cập nhật số lượng theo thời gian thực                                      |
| 💳 Đặt hàng       | Chọn địa chỉ từ tài khoản hoặc nhập mới, 3 phương thức thanh toán (COD, chuyển khoản, trực tuyến) |
| ✅ Xác nhận đơn   | Trang tóm tắt đơn hàng sau khi đặt thành công                                                     |
| 📋 Lịch sử đơn    | Xem lại toàn bộ đơn hàng đã đặt, chi tiết từng đơn                                                |
| 🔐 Tài khoản      | Đăng ký, đăng nhập, đăng xuất                                                                     |

> **Lưu ý giá:** Giá bán = Giá nhập × (100% + Tỷ lệ lợi nhuận). Giá nhập được tính theo phương pháp **bình quân gia quyền** khi nhập hàng.

---

### 🔧 Phía Admin (`/admin`)

| Module               | Chức năng                                                                                           |
| -------------------- | --------------------------------------------------------------------------------------------------- |
| 📊 Dashboard         | Thống kê tổng quan: doanh thu, đơn hàng, tồn kho, cảnh báo hết hàng                                 |
| 📚 Quản lý sách      | Thêm/sửa/xoá (hoặc ẩn nếu đã từng nhập hàng), upload ảnh bìa                                        |
| 🏷️ Thể loại          | Thêm/sửa/xoá/ẩn thể loại; không thể xoá nếu còn sách thuộc thể loại đó                              |
| 📦 Nhập hàng         | Tạo phiếu nhập, thêm nhiều sách/phiếu, hoàn thành phiếu → tự động cập nhật tồn kho và giá bình quân |
| 💰 Quản lý giá       | Chỉnh tỷ lệ lợi nhuận từng sách, xem lịch sử nhập theo lô, preview giá bán tức thì                  |
| 🛍️ Đơn hàng          | Xem danh sách, lọc theo trạng thái/thời gian/phường, cập nhật trạng thái (xác nhận → đã giao → huỷ) |
| 📈 Tồn kho & Báo cáo | Bảng tồn kho, báo cáo nhập/bán theo tháng, top sách bán chạy                                        |
| 👥 Người dùng        | Thêm tài khoản, khoá/mở khoá, khởi tạo lại mật khẩu                                                 |

---

## 🚀 Hướng dẫn cài đặt với XAMPP

### Yêu cầu

- [XAMPP](https://www.apachefriends.org/) phiên bản 8.x trở lên (có Apache + MySQL)
- Trình duyệt Chrome/Firefox

---

### Bước 1 — Tải & giải nén dự án

Giải nén file `.zip` vào thư mục `htdocs` của XAMPP:

```
C:\xampp\htdocs\nhasach\
```

Cấu trúc thư mục sau khi giải nén:

```
htdocs/
└── nhasach/
    ├── index.php
    ├── books.php
    ├── admin/
    │   ├── index.php
    │   └── ...
    ├── config/
    │   └── database.php
    ├── uploads/
    └── assets/
```

---

### Bước 2 — Khởi động XAMPP

1. Mở **XAMPP Control Panel**
2. Nhấn **Start** cho **Apache** và **MySQL**
3. Đảm bảo cả hai đều hiện trạng thái **Running** (màu xanh)

---

### Bước 3 — Tạo database

1. Mở trình duyệt, truy cập: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Nhấn **New** ở cột trái → đặt tên database là `nhasach` → chọn `utf8mb4_unicode_ci` → nhấn **Create**
3. Chọn database `nhasach` vừa tạo → vào tab **Import**
4. Nhấn **Choose File** → chọn file `nhasach.sql` trong thư mục dự án → nhấn **Import**

> Nếu chưa có file `nhasach.sql`, hãy chạy thêm file `migration.sql` để tạo các bảng bổ sung.

---

### Bước 4 — Kiểm tra cấu hình database

Mở file `config/database.php` và đảm bảo thông tin kết nối đúng:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'nhasach');
define('DB_USER', 'root');
define('DB_PASS', '');       // XAMPP mặc định không có mật khẩu
```

---

### Bước 5 — Chạy website

Mở trình duyệt và truy cập:

| Trang               | URL                            |
| ------------------- | ------------------------------ |
| 🏠 Trang khách hàng | http://localhost/nhasach       |
| 🔧 Trang Admin      | http://localhost/nhasach/admin |

---

## 🔑 Tài khoản mặc định

### Admin

| Thông tin | Giá trị             |
| --------- | ------------------- |
| Email     | `admin@nhasach.com` |
| Mật khẩu  | `password`          |

### Khách hàng demo

| Thông tin | Giá trị             |
| --------- | ------------------- |
| Email     | `example@gmail.com` |
| Mật khẩu  | `123456`            |

---

## 📁 Cấu trúc thư mục

```
nhasach/
├── config/
│   └── database.php          # Cấu hình kết nối CSDL
├── includes/
│   ├── header.php             # Header chung (navbar, session)
│   ├── footer.php             # Footer chung
│   ├── card_sach.php          # Component card sách
│   └── diachi_dropdown.php    # Dropdown địa chỉ tỉnh/quận/phường
├── admin/
│   ├── includes/
│   │   ├── admin_header.php   # Header admin (sidebar, auth)
│   │   └── admin_footer.php   # Footer admin
│   ├── index.php              # Dashboard
│   ├── products.php           # Quản lý sách
│   ├── categories.php         # Quản lý thể loại
│   ├── import.php             # Nhập hàng
│   ├── prices.php             # Quản lý giá
│   ├── orders.php             # Quản lý đơn hàng
│   ├── inventory.php          # Tồn kho & Báo cáo
│   ├── users.php              # Quản lý người dùng
│   ├── login.php              # Đăng nhập admin
│   └── logout.php
├── assets/
│   ├── css/style.css          # CSS toàn site
│   └── js/diachi.json         # Dữ liệu tỉnh/quận/phường
├── uploads/                   # Ảnh bìa sách (tự động tạo)
├── index.php                  # Trang chủ
├── books.php                  # Danh sách sách
├── book.php                   # Chi tiết sách
├── cart.php                   # Giỏ hàng
├── checkout.php               # Đặt hàng
├── order_success.php          # Xác nhận đơn
├── orders.php                 # Lịch sử đơn hàng
├── register.php               # Đăng ký
├── login.php                  # Đăng nhập
├── logout.php                 # Đăng xuất
└── migration.sql              # SQL bổ sung (chạy nếu thiếu bảng)
```

---

## ⚠️ Lưu ý

- Thư mục `uploads/` cần có quyền **ghi** để upload ảnh bìa sách. Trên Windows/XAMPP thường không cần cấu hình thêm.
- Nếu gặp lỗi **"Lỗi kết nối"**, kiểm tra lại MySQL đã Start trong XAMPP chưa và thông tin trong `config/database.php`.
- Tính năng **Thanh toán trực tuyến** hiển thị giao diện nhưng chưa xử lý thực tế (theo yêu cầu đề bài).

---

_Đồ án môn Lập trình Web và Ứng dụng Nâng cao — Khoa CNTT, Trường Đại học Sài Gòn_
