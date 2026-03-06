<?php
// Bảo vệ tất cả trang admin
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: /nhasach/admin/login.php');
    exit;
}
require_once __DIR__ . '/../../config/database.php';

$current_page = basename($_SERVER['PHP_SELF']);

$nav_items = [
    ['file' => 'index.php',     'icon' => 'bi-speedometer2',  'label' => 'Dashboard'],
    ['file' => 'products.php',  'icon' => 'bi-book',          'label' => 'Quản lý sách'],
    ['file' => 'categories.php','icon' => 'bi-tag',           'label' => 'Thể loại'],
    ['file' => 'import.php',    'icon' => 'bi-box-arrow-in-down','label' => 'Nhập hàng'],
    ['file' => 'prices.php',    'icon' => 'bi-currency-dollar','label' => 'Quản lý giá'],
    ['file' => 'orders.php',    'icon' => 'bi-bag-check',     'label' => 'Đơn hàng'],
    ['file' => 'inventory.php', 'icon' => 'bi-bar-chart',     'label' => 'Tồn kho & Báo cáo'],
    ['file' => 'users.php',     'icon' => 'bi-people',        'label' => 'Người dùng'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($page_title) ? $page_title . ' — ' : '' ?>Admin Nhà Sách</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="/nhasach/assets/css/style.css" rel="stylesheet">
  <style>
    body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }

    /* Sidebar */
    .admin-sidebar {
      width: 240px; background: #1a1a2e;
      min-height: 100vh; position: fixed;
      left: 0; top: 0; z-index: 100;
      display: flex; flex-direction: column;
    }
    .sidebar-brand {
      padding: 20px; font-size: 1.1rem; font-weight: 800;
      color: #fff; border-bottom: 1px solid rgba(255,255,255,.08);
    }
    .sidebar-brand span { color: #f4a261; }
    .sidebar-admin-info {
      padding: 14px 20px; border-bottom: 1px solid rgba(255,255,255,.08);
    }
    .sidebar-admin-info .name {
      color: #fff; font-size: .88rem; font-weight: 600;
    }
    .sidebar-admin-info .role {
      color: rgba(255,255,255,.4); font-size: .75rem;
    }
    .sidebar-nav { flex: 1; padding: 8px 0; }
    .admin-nav-link {
      display: flex; align-items: center; gap: 10px;
      color: rgba(255,255,255,.65); padding: 10px 20px;
      font-size: .88rem; text-decoration: none;
      transition: all .15s; border-left: 3px solid transparent;
    }
    .admin-nav-link:hover {
      color: #fff; background: rgba(255,255,255,.06);
    }
    .admin-nav-link.active {
      color: #f4a261; background: rgba(244,162,97,.08);
      border-left-color: #f4a261;
    }
    .admin-nav-link i { font-size: 1rem; width: 20px; text-align: center; }
    .sidebar-footer {
      padding: 12px 20px; border-top: 1px solid rgba(255,255,255,.08);
    }
    .sidebar-footer a {
      color: rgba(255,255,255,.4); font-size: .82rem;
      text-decoration: none; display: flex; align-items: center; gap: 8px;
    }
    .sidebar-footer a:hover { color: #f4a261; }

    /* Content */
    .admin-content {
      margin-left: 240px; padding: 24px;
      min-height: 100vh;
    }
    .page-header {
      background: #fff; border-radius: 12px;
      padding: 18px 24px; margin-bottom: 24px;
      box-shadow: 0 2px 8px rgba(0,0,0,.05);
      display: flex; align-items: center;
      justify-content: space-between; flex-wrap: wrap; gap: 12px;
    }
    .page-header h5 { margin: 0; font-weight: 700; color: #1a1a2e; }

    /* Flash */
    .admin-flash {
      position: fixed; top: 20px; right: 20px;
      z-index: 9999; min-width: 300px;
    }

    /* Cards */
    .admin-card {
      background: #fff; border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,.05);
      padding: 20px 24px; margin-bottom: 20px;
    }
    .admin-card .card-title {
      font-size: .8rem; text-transform: uppercase;
      letter-spacing: .1em; color: #888;
      font-weight: 600; margin-bottom: 16px;
    }

    /* Table */
    .admin-table { font-size: .88rem; }
    .admin-table th {
      font-size: .75rem; text-transform: uppercase;
      letter-spacing: .08em; color: #888; font-weight: 600;
      border-top: none; background: #f8f9fa;
    }
    .admin-table td { vertical-align: middle; }
    .admin-table tr:hover td { background: #fafafa; }

    @media (max-width: 768px) {
      .admin-sidebar { display: none; }
      .admin-content { margin-left: 0; }
    }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="admin-sidebar">
  <div class="sidebar-brand">
    <i class="bi bi-book-half me-2"></i>Nhà<span>Sách</span>
  </div>
  <div class="sidebar-admin-info">
    <div class="name"><i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($_SESSION['admin_ten']) ?></div>
    <div class="role">Quản trị viên</div>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($nav_items as $item): ?>
      <a href="/nhasach/admin/<?= $item['file'] ?>"
         class="admin-nav-link <?= $current_page === $item['file'] ? 'active' : '' ?>">
        <i class="bi <?= $item['icon'] ?>"></i>
        <?= $item['label'] ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <a href="/nhasach/index.php" target="_blank">
      <i class="bi bi-box-arrow-up-right"></i> Xem trang web
    </a>
    <a href="/nhasach/admin/logout.php" class="mt-2">
      <i class="bi bi-box-arrow-left"></i> Đăng xuất
    </a>
  </div>
</div>

<!-- CONTENT -->
<div class="admin-content">

<!-- Flash message -->
<?php if (isset($_SESSION['flash'])): ?>
  <div class="admin-flash">
    <div class="alert alert-<?= $_SESSION['flash']['type'] ?> alert-dismissible fade show shadow">
      <?= htmlspecialchars($_SESSION['flash']['msg']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  </div>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
