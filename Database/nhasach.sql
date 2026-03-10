-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th3 10, 2026 lúc 02:05 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `nhasach`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `ho_ten` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mat_khau` varchar(255) NOT NULL,
  `bi_khoa` tinyint(1) NOT NULL DEFAULT 0,
  `ngay_tao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `admins`
--

INSERT INTO `admins` (`id`, `ho_ten`, `email`, `mat_khau`, `bi_khoa`, `ngay_tao`) VALUES
(1, 'Quản Trị Viên', 'admin@nhasach.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, '2026-03-06 14:31:06');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chi_tiet_don_hang`
--

CREATE TABLE `chi_tiet_don_hang` (
  `id` int(11) NOT NULL,
  `don_hang_id` int(11) NOT NULL,
  `sach_id` int(11) NOT NULL,
  `so_luong` int(11) NOT NULL,
  `gia_ban_luc_dat` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `chi_tiet_don_hang`
--

INSERT INTO `chi_tiet_don_hang` (`id`, `don_hang_id`, `sach_id`, `so_luong`, `gia_ban_luc_dat`) VALUES
(1, 1, 6, 2, 100500.00),
(2, 2, 1, 1, 87100.00),
(3, 3, 4, 1, 50700.00),
(4, 4, 1, 1, 87100.00),
(5, 5, 4, 1, 50700.00),
(6, 5, 5, 1, 90000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chi_tiet_nhap`
--

CREATE TABLE `chi_tiet_nhap` (
  `id` int(11) NOT NULL,
  `phieu_nhap_id` int(11) NOT NULL,
  `sach_id` int(11) NOT NULL,
  `so_luong` int(11) NOT NULL DEFAULT 0,
  `don_gia` decimal(15,0) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `chi_tiet_nhap`
--

INSERT INTO `chi_tiet_nhap` (`id`, `phieu_nhap_id`, `sach_id`, `so_luong`, `don_gia`) VALUES
(1, 1, 6, 100, 75000),
(2, 1, 7, 100, 36000),
(3, 1, 1, 100, 67000),
(4, 2, 8, 100, 29000),
(5, 2, 3, 100, 69000),
(6, 2, 4, 100, 39000),
(7, 2, 5, 100, 72000);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `don_hang`
--

CREATE TABLE `don_hang` (
  `id` int(11) NOT NULL,
  `ma_don` varchar(20) NOT NULL,
  `khach_hang_id` int(11) NOT NULL,
  `ngay_dat` datetime DEFAULT current_timestamp(),
  `ten_nguoi_nhan` varchar(100) NOT NULL,
  `so_dien_thoai` varchar(15) NOT NULL,
  `dia_chi` varchar(255) NOT NULL,
  `phuong_xa` varchar(100) DEFAULT NULL,
  `tinh_tp` varchar(100) DEFAULT NULL,
  `phuong_thuc_tt` enum('tien_mat','chuyen_khoan','truc_tuyen') NOT NULL DEFAULT 'tien_mat',
  `tong_tien` decimal(15,2) NOT NULL,
  `trang_thai` enum('cho_xu_ly','da_xac_nhan','da_giao','da_huy') NOT NULL DEFAULT 'cho_xu_ly',
  `ghi_chu` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `don_hang`
--

INSERT INTO `don_hang` (`id`, `ma_don`, `khach_hang_id`, `ngay_dat`, `ten_nguoi_nhan`, `so_dien_thoai`, `dia_chi`, `phuong_xa`, `tinh_tp`, `phuong_thuc_tt`, `tong_tien`, `trang_thai`, `ghi_chu`) VALUES
(1, 'DH2026030708212157', 1, '2026-03-07 14:21:21', 'Trần Tiến Phát', '0392986719', '126B Mai Chí Thọ', 'Phường Bình Trưng Tây', 'Thành phố Hồ Chí Minh', 'chuyen_khoan', 201000.00, 'da_giao', NULL),
(2, 'DH2026030709565565', 1, '2026-03-07 15:56:55', 'Trần Tiến Phát', '0392986719', '126B Mai Chí Thọ', 'Phường Bình Trưng Tây', 'Thành phố Hồ Chí Minh', 'tien_mat', 87100.00, 'cho_xu_ly', NULL),
(3, 'DH2026030914350912', 1, '2026-03-09 20:35:09', 'Trần Tiến Phát', '0392986719', '126B Mai Chí Thọ', 'Phường Bình Trưng Tây', 'Thành phố Hồ Chí Minh', 'tien_mat', 50700.00, 'cho_xu_ly', NULL),
(4, 'DH2026030914483428', 1, '2026-03-09 20:48:34', 'Trần Tiến Phát', '0392986719', '126B Mai Chí Thọ', 'Phường Bình Trưng Tây', 'Thành phố Hồ Chí Minh', 'tien_mat', 87100.00, 'cho_xu_ly', NULL),
(5, 'DH2026030915331746', 1, '2026-03-09 21:33:17', 'Trần Tiến Phát', '0392986719', '126B Mai Chí Thọ', 'Phường Bình Trưng Tây', 'Thành phố Hồ Chí Minh', 'tien_mat', 140700.00, 'cho_xu_ly', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `gio_hang`
--

CREATE TABLE `gio_hang` (
  `id` int(11) NOT NULL,
  `khach_hang_id` int(11) NOT NULL,
  `sach_id` int(11) NOT NULL,
  `so_luong` int(11) NOT NULL DEFAULT 1,
  `ngay_them` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `gio_hang`
--

INSERT INTO `gio_hang` (`id`, `khach_hang_id`, `sach_id`, `so_luong`, `ngay_them`) VALUES
(84, 1, 4, 1, '2026-03-10 13:15:08'),
(85, 1, 5, 1, '2026-03-10 13:15:08'),
(86, 1, 6, 1, '2026-03-10 13:15:08'),
(87, 1, 7, 1, '2026-03-10 13:15:08'),
(88, 1, 8, 1, '2026-03-10 13:15:08');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khach_hang`
--

CREATE TABLE `khach_hang` (
  `id` int(11) NOT NULL,
  `ho_ten` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mat_khau` varchar(255) NOT NULL,
  `so_dien_thoai` varchar(15) DEFAULT NULL,
  `dia_chi` varchar(255) DEFAULT NULL,
  `phuong_xa` varchar(100) DEFAULT NULL,
  `tinh_tp` varchar(100) DEFAULT NULL,
  `bi_khoa` tinyint(1) NOT NULL DEFAULT 0,
  `trang_thai` tinyint(1) NOT NULL DEFAULT 1,
  `ngay_tao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `khach_hang`
--

INSERT INTO `khach_hang` (`id`, `ho_ten`, `email`, `mat_khau`, `so_dien_thoai`, `dia_chi`, `phuong_xa`, `tinh_tp`, `bi_khoa`, `trang_thai`, `ngay_tao`) VALUES
(1, 'Trần Tiến Phát', 'tienphatt1912@gmail.com', '$2y$10$YDppB2pIzJgKHbGa2HOSWuGqtzrQmygyMkI.I7jIbwZKpc9TTUfTS', '0392986719', '126B Mai Chí Thọ', 'Phường Bình Trưng Tây', 'Thành phố Hồ Chí Minh', 0, 1, '2026-03-06 19:27:22'),
(2, 'Cao Thái Phương Thanh', 'example@gmail.com', '$2y$10$GD975fsJBOS9AM9qFgXzHuYHDuTj4Xsigzrh9tICjxb8YJ.CxtI4O', '123456789', '273, An Dương Vương', 'Phường 3', 'TP. Hồ Chí Minh', 0, 1, '2026-03-06 19:27:22'),
(3, 'Lê Nguyễn Anh Bảo', 'lebao09@gmail.com', '$2y$10$tbLNAj4U2pBKfMOzCy9dCeruNzHvYvzdKB1BVM2Y3N4N5Yuw3bC42', '0909090099', '1, Võ Văn Ngân', 'Phường Linh Trung', 'Thành phố Hồ Chí Minh', 0, 1, '2026-03-06 19:27:22');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `phieu_nhap`
--

CREATE TABLE `phieu_nhap` (
  `id` int(11) NOT NULL,
  `ma_phieu` varchar(20) NOT NULL,
  `ngay_nhap` date NOT NULL,
  `ghi_chu` text DEFAULT NULL,
  `trang_thai` enum('draft','done') NOT NULL DEFAULT 'draft',
  `nguoi_tao` int(11) DEFAULT NULL,
  `ngay_tao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `phieu_nhap`
--

INSERT INTO `phieu_nhap` (`id`, `ma_phieu`, `ngay_nhap`, `ghi_chu`, `trang_thai`, `nguoi_tao`, `ngay_tao`) VALUES
(1, 'PN260307081255', '0000-00-00', '', 'done', NULL, '2026-03-07 14:12:55'),
(2, 'PN260307100153', '0000-00-00', '', 'done', NULL, '2026-03-07 16:01:53');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sach`
--

CREATE TABLE `sach` (
  `id` int(11) NOT NULL,
  `ma_sach` varchar(20) NOT NULL,
  `ten` varchar(255) NOT NULL,
  `tac_gia` varchar(150) DEFAULT NULL,
  `the_loai_id` int(11) NOT NULL,
  `nha_xb` varchar(150) DEFAULT NULL,
  `mo_ta` text DEFAULT NULL,
  `don_vi_tinh` varchar(30) DEFAULT 'cuốn',
  `hinh` varchar(255) DEFAULT NULL,
  `so_luong` int(11) NOT NULL DEFAULT 0,
  `gia_nhap` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ty_le_ln` decimal(5,2) NOT NULL DEFAULT 0.00,
  `hien_trang` tinyint(1) NOT NULL DEFAULT 1,
  `da_nhap_hang` tinyint(1) NOT NULL DEFAULT 0,
  `ngay_tao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `sach`
--

INSERT INTO `sach` (`id`, `ma_sach`, `ten`, `tac_gia`, `the_loai_id`, `nha_xb`, `mo_ta`, `don_vi_tinh`, `hinh`, `so_luong`, `gia_nhap`, `ty_le_ln`, `hien_trang`, `da_nhap_hang`, `ngay_tao`) VALUES
(1, 'S001', 'Đắc Nhân Tâm', 'Dale Carnegie', 4, 'NXB Tổng hợp TP.HCM', 'Nghệ thuật giao tiếp và thuyết phục', 'cuốn', 'sach_1772867749_220.jpg', 98, 67000.00, 30.00, 1, 1, '2026-03-06 14:31:07'),
(3, 'S003', 'Sapiens: Lược Sử Loài Người', 'Yuval Noah Harari', 7, 'NXB Tri Thức', 'Lịch sử tiến hoá của loài người', 'cuốn', 'sach_1772894477_392.jpg', 100, 69000.00, 28.00, 1, 1, '2026-03-06 14:31:07'),
(4, 'S004', 'Tư Duy Nhanh Và Chậm', 'Daniel Kahneman', 8, 'NXB Thế Giới', 'Khám phá hai hệ thống tư duy của não bộ', 'cuốn', 'sach_1772894501_775.jpg', 98, 39000.00, 30.00, 1, 1, '2026-03-06 14:31:07'),
(5, 'S005', 'Tuổi Trẻ Đáng Giá Bao Nhiêu', 'Rosie Nguyễn', 4, 'NXB Hội Nhà Văn', 'Sách truyền cảm hứng cho giới trẻ Việt Nam', 'cuốn', 'sach_1772894510_567.jpg', 99, 72000.00, 25.00, 1, 1, '2026-03-06 14:31:07'),
(6, 'S006', 'Clean Code', 'Robert C. Martin', 5, 'NXB Lao Động', 'Hướng dẫn viết code sạch chuyên nghiệp', 'cuốn', 'sach_1772867804_490.jpg', 98, 75000.00, 34.00, 1, 1, '2026-03-06 14:31:07'),
(7, 'S007', 'Doraemon - Tập 1', 'Fujiko F. Fujio', 6, 'NXB Kim Đồng', 'Truyện tranh thiếu nhi nổi tiếng Nhật Bản', 'cuốn', 'sach_1772868008_431.jpg', 100, 36000.00, 20.00, 1, 1, '2026-03-06 14:31:07'),
(8, 'S008', 'Khởi Nghiệp Tinh Gọn', 'Eric Ries', 3, 'NXB Lao Động', 'Phương pháp Lean Startup cho doanh nghiệp', 'cuốn', 'sach_1772894520_818.jpg', 100, 29000.00, 30.00, 1, 1, '2026-03-06 14:31:07');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `the_loai`
--

CREATE TABLE `the_loai` (
  `id` int(11) NOT NULL,
  `ten` varchar(100) NOT NULL,
  `mo_ta` text DEFAULT NULL,
  `trang_thai` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `the_loai`
--

INSERT INTO `the_loai` (`id`, `ten`, `mo_ta`, `trang_thai`) VALUES
(1, 'Văn học trong nước', 'Tiểu thuyết, truyện ngắn, thơ của tác giả Việt Nam', 1),
(2, 'Văn học nước ngoài', 'Sách dịch từ tác phẩm nổi tiếng thế giới', 1),
(3, 'Kinh tế - Kinh doanh', 'Sách về tài chính, khởi nghiệp, quản trị', 1),
(4, 'Kỹ năng sống', 'Phát triển bản thân, tư duy, giao tiếp', 1),
(5, 'Khoa học - Công nghệ', 'Lập trình, AI, vật lý, toán học', 1),
(6, 'Sách thiếu nhi', 'Truyện tranh, sách giáo dục cho trẻ em', 1),
(7, 'Lịch sử - Địa lý', 'Sách về lịch sử Việt Nam và thế giới', 1),
(8, 'Tâm lý học', 'Sách về tâm lý học hành vi và ứng dụng', 1);

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_canh_bao_ton_kho`
-- (See below for the actual view)
--
CREATE TABLE `v_canh_bao_ton_kho` (
`id` int(11)
,`ma_sach` varchar(20)
,`ten` varchar(255)
,`so_luong` int(11)
);

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_gia_ban`
-- (See below for the actual view)
--
CREATE TABLE `v_gia_ban` (
`id` int(11)
,`ma_sach` varchar(20)
,`ten` varchar(255)
,`tac_gia` varchar(150)
,`the_loai` varchar(100)
,`so_luong` int(11)
,`gia_nhap` decimal(15,2)
,`ty_le_ln` decimal(5,2)
,`gia_ban` decimal(18,0)
,`hien_trang` tinyint(1)
,`da_nhap_hang` tinyint(1)
);

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_thong_ke_nhap_xuat`
-- (See below for the actual view)
--
CREATE TABLE `v_thong_ke_nhap_xuat` (
`sach_id` int(11)
,`ma_sach` varchar(20)
,`ten` varchar(255)
,`tong_nhap` decimal(32,0)
,`tong_xuat` decimal(32,0)
,`ton_kho_thuc_te` int(11)
);

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_canh_bao_ton_kho`
--
DROP TABLE IF EXISTS `v_canh_bao_ton_kho`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_canh_bao_ton_kho`  AS SELECT `sach`.`id` AS `id`, `sach`.`ma_sach` AS `ma_sach`, `sach`.`ten` AS `ten`, `sach`.`so_luong` AS `so_luong` FROM `sach` WHERE `sach`.`so_luong` <= 5 AND `sach`.`hien_trang` = 1 ;

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_gia_ban`
--
DROP TABLE IF EXISTS `v_gia_ban`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gia_ban`  AS SELECT `s`.`id` AS `id`, `s`.`ma_sach` AS `ma_sach`, `s`.`ten` AS `ten`, `s`.`tac_gia` AS `tac_gia`, `tl`.`ten` AS `the_loai`, `s`.`so_luong` AS `so_luong`, `s`.`gia_nhap` AS `gia_nhap`, `s`.`ty_le_ln` AS `ty_le_ln`, round(`s`.`gia_nhap` * (1 + `s`.`ty_le_ln` / 100),0) AS `gia_ban`, `s`.`hien_trang` AS `hien_trang`, `s`.`da_nhap_hang` AS `da_nhap_hang` FROM (`sach` `s` join `the_loai` `tl` on(`s`.`the_loai_id` = `tl`.`id`)) ;

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_thong_ke_nhap_xuat`
--
DROP TABLE IF EXISTS `v_thong_ke_nhap_xuat`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_thong_ke_nhap_xuat`  AS SELECT `s`.`id` AS `sach_id`, `s`.`ma_sach` AS `ma_sach`, `s`.`ten` AS `ten`, coalesce((select sum(`cn`.`so_luong`) from (`chi_tiet_nhap` `cn` join `phieu_nhap` `pn` on(`pn`.`id` = `cn`.`phieu_nhap_id`)) where `cn`.`sach_id` = `s`.`id` and `pn`.`trang_thai` = 'done'),0) AS `tong_nhap`, coalesce((select sum(`ct`.`so_luong`) from (`chi_tiet_don_hang` `ct` join `don_hang` `dh` on(`dh`.`id` = `ct`.`don_hang_id`)) where `ct`.`sach_id` = `s`.`id` and `dh`.`trang_thai` <> 'da_huy'),0) AS `tong_xuat`, `s`.`so_luong` AS `ton_kho_thuc_te` FROM `sach` AS `s` ;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Chỉ mục cho bảng `chi_tiet_don_hang`
--
ALTER TABLE `chi_tiet_don_hang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `don_hang_id` (`don_hang_id`),
  ADD KEY `sach_id` (`sach_id`);

--
-- Chỉ mục cho bảng `chi_tiet_nhap`
--
ALTER TABLE `chi_tiet_nhap`
  ADD PRIMARY KEY (`id`),
  ADD KEY `phieu_nhap_id` (`phieu_nhap_id`),
  ADD KEY `sach_id` (`sach_id`);

--
-- Chỉ mục cho bảng `don_hang`
--
ALTER TABLE `don_hang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ma_don` (`ma_don`),
  ADD KEY `idx_don_hang_kh` (`khach_hang_id`),
  ADD KEY `idx_don_hang_trang_thai` (`trang_thai`),
  ADD KEY `idx_don_hang_ngay` (`ngay_dat`),
  ADD KEY `idx_don_hang_phuong` (`phuong_xa`);

--
-- Chỉ mục cho bảng `gio_hang`
--
ALTER TABLE `gio_hang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_kh_sach` (`khach_hang_id`,`sach_id`),
  ADD KEY `sach_id` (`sach_id`);

--
-- Chỉ mục cho bảng `khach_hang`
--
ALTER TABLE `khach_hang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Chỉ mục cho bảng `phieu_nhap`
--
ALTER TABLE `phieu_nhap`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ma_phieu` (`ma_phieu`),
  ADD KEY `nguoi_tao` (`nguoi_tao`);

--
-- Chỉ mục cho bảng `sach`
--
ALTER TABLE `sach`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ma_sach` (`ma_sach`),
  ADD KEY `idx_sach_ten` (`ten`),
  ADD KEY `idx_sach_the_loai` (`the_loai_id`),
  ADD KEY `idx_sach_hien_trang` (`hien_trang`);
ALTER TABLE `sach` ADD FULLTEXT KEY `idx_sach_ft` (`ten`,`tac_gia`,`mo_ta`);

--
-- Chỉ mục cho bảng `the_loai`
--
ALTER TABLE `the_loai`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `chi_tiet_don_hang`
--
ALTER TABLE `chi_tiet_don_hang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `chi_tiet_nhap`
--
ALTER TABLE `chi_tiet_nhap`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `don_hang`
--
ALTER TABLE `don_hang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `gio_hang`
--
ALTER TABLE `gio_hang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT cho bảng `khach_hang`
--
ALTER TABLE `khach_hang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `phieu_nhap`
--
ALTER TABLE `phieu_nhap`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `sach`
--
ALTER TABLE `sach`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT cho bảng `the_loai`
--
ALTER TABLE `the_loai`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `chi_tiet_don_hang`
--
ALTER TABLE `chi_tiet_don_hang`
  ADD CONSTRAINT `chi_tiet_don_hang_ibfk_1` FOREIGN KEY (`don_hang_id`) REFERENCES `don_hang` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chi_tiet_don_hang_ibfk_2` FOREIGN KEY (`sach_id`) REFERENCES `sach` (`id`);

--
-- Các ràng buộc cho bảng `chi_tiet_nhap`
--
ALTER TABLE `chi_tiet_nhap`
  ADD CONSTRAINT `chi_tiet_nhap_ibfk_1` FOREIGN KEY (`phieu_nhap_id`) REFERENCES `phieu_nhap` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chi_tiet_nhap_ibfk_2` FOREIGN KEY (`sach_id`) REFERENCES `sach` (`id`);

--
-- Các ràng buộc cho bảng `don_hang`
--
ALTER TABLE `don_hang`
  ADD CONSTRAINT `don_hang_ibfk_1` FOREIGN KEY (`khach_hang_id`) REFERENCES `khach_hang` (`id`);

--
-- Các ràng buộc cho bảng `gio_hang`
--
ALTER TABLE `gio_hang`
  ADD CONSTRAINT `gio_hang_ibfk_1` FOREIGN KEY (`khach_hang_id`) REFERENCES `khach_hang` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gio_hang_ibfk_2` FOREIGN KEY (`sach_id`) REFERENCES `sach` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `phieu_nhap`
--
ALTER TABLE `phieu_nhap`
  ADD CONSTRAINT `phieu_nhap_ibfk_1` FOREIGN KEY (`nguoi_tao`) REFERENCES `admins` (`id`);

--
-- Các ràng buộc cho bảng `sach`
--
ALTER TABLE `sach`
  ADD CONSTRAINT `sach_ibfk_1` FOREIGN KEY (`the_loai_id`) REFERENCES `the_loai` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
