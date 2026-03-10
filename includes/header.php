<?php
// Bắt đầu session nếu chưa có
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// Đếm số sản phẩm trong giỏ hàng
$so_san_pham_gio = 0;
if (isset($_SESSION['khach_hang_id'])) {
    $stmt_gio = $pdo->prepare("SELECT COALESCE(SUM(so_luong),0) FROM gio_hang WHERE khach_hang_id=?");
    $stmt_gio->execute([$_SESSION['khach_hang_id']]);
    $so_san_pham_gio = (int)$stmt_gio->fetchColumn();
} elseif (isset($_SESSION['gio_hang'])) {
    foreach ($_SESSION['gio_hang'] as $sl) {
        $so_san_pham_gio += $sl;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' — ' : '' ?>Nhà Sách Online</title>

  <!-- Bootstrap 5 CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <link href="/nhasach/assets/css/style.css?v=2" rel="stylesheet">
  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; }

    /* Navbar */
    .navbar { background: #1a1a2e !important; box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
    .navbar-brand { font-size: 1.4rem; font-weight: 700; color: #fff !important; letter-spacing: 0.5px; }
    .navbar-brand span { color: #f4a261; }
    .nav-link { color: rgba(255,255,255,0.85) !important; font-size: 0.92rem; transition: color .2s; }
    .nav-link:hover, .nav-link.active { color: #f4a261 !important; }

    /* Search bar */
    .search-form .form-control {
      border-radius: 20px 0 0 20px;
      border: none; font-size: 0.9rem;
      background: rgba(255,255,255,0.1);
      color: #fff;
    }
    .search-form .form-control::placeholder { color: rgba(255,255,255,0.5); }
    .search-form .form-control:focus {
      background: rgba(255,255,255,0.15);
      color: #fff; box-shadow: none; border: none;
    }
    .search-form .btn-search {
      border-radius: 0 20px 20px 0;
      background: #f4a261; border: none; color: #fff;
      padding: 6px 16px;
    }
    .search-form .btn-search:hover { background: #e08c4a; }

    /* Giỏ hàng badge */
    .cart-icon { position: relative; color: rgba(255,255,255,0.85) !important; font-size: 1.3rem; }
    .cart-icon:hover { color: #f4a261 !important; }
    .cart-badge {
      position: absolute; top: -6px; right: -8px;
      background: #e63946; color: #fff;
      font-size: 0.65rem; font-weight: 700;
      padding: 2px 5px; border-radius: 99px;
      min-width: 18px; text-align: center;
    }

    /* Dropdown tài khoản */
    .dropdown-menu { border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.12); border: none; }
    .dropdown-item:hover { background: #f0f4ff; color: #1a1a2e; }
    .user-avatar {
      width: 30px; height: 30px; border-radius: 50%;
      background: #f4a261; color: #fff;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 0.8rem; font-weight: 700; margin-right: 6px;
    }

    /* Breadcrumb */
    .breadcrumb-wrap {
      background: #fff; border-bottom: 1px solid #e9ecef;
      padding: 8px 0; font-size: 0.85rem;
    }
    .breadcrumb { margin: 0; }
    .breadcrumb-item a { color: #f4a261; text-decoration: none; }
    .breadcrumb-item.active { color: #6c757d; }

    /* Flash message */
    .flash-message { position: fixed; top: 70px; right: 20px; z-index: 9999; min-width: 300px; }
    .suggest-item {
  display:flex; align-items:center; gap:10px;
  padding:10px 14px; cursor:pointer; border-bottom:1px solid #f5f5f5;
  text-decoration:none; color:#1a1a2e;
}
.suggest-item:hover { background:#fff8f3; }
.suggest-item img { width:36px; height:48px; object-fit:contain; border-radius:4px; }
.suggest-item-no-img { width:36px; height:48px; background:#f0f0f0; border-radius:4px;
  display:flex; align-items:center; justify-content:center; color:#aaa; font-size:.9rem; }
.suggest-title { font-size:.88rem; font-weight:600; }
.suggest-author { font-size:.78rem; color:#888; }
.suggest-price { font-size:.82rem; color:#f4a261; font-weight:600; margin-left:auto; white-space:nowrap; }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">

    <!-- Logo -->
    <a class="navbar-brand" href="/nhasach/index.php">
      <i class="bi bi-book-half me-2"></i>Nhà<span>Sách</span>
    </a>

    <!-- Toggle mobile -->
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <i class="bi bi-list text-white fs-4"></i>
    </button>

    <div class="collapse navbar-collapse" id="navMenu">

      <!-- Thanh tìm kiếm -->
      <form class="search-form d-flex mx-auto my-2 my-lg-0" style="width:380px; position:relative;"
      action="/nhasach/books.php" method="GET">
  <input class="form-control" type="search" name="search" id="search-input"
         placeholder="Tìm sách, tác giả..."
         value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
         autocomplete="off">
  <button class="btn btn-search" type="submit">
    <i class="bi bi-search"></i>
  </button>
  <!-- Dropdown gợi ý -->
  <div id="search-suggest" style="
    display:none; position:absolute; top:100%; left:0; right:0; z-index:9999;
    background:#fff; border-radius:0 0 12px 12px;
    box-shadow:0 8px 24px rgba(0,0,0,.15); overflow:hidden;"></div>
</form>

      <!-- Menu chính -->
      <ul class="navbar-nav me-3">
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>"
             href="/nhasach/index.php">Trang chủ</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'books.php' ? 'active' : '' ?>"
             href="/nhasach/books.php">Sách</a>
        </li>
      </ul>

      <!-- Giỏ hàng -->
      <a href="/nhasach/cart.php" class="cart-icon text-decoration-none me-3">
        <i class="bi bi-cart3"></i>
        <?php if ($so_san_pham_gio > 0): ?>
          <span class="cart-badge"><?= $so_san_pham_gio ?></span>
        <?php endif; ?>
      </a>

      <!-- Tài khoản -->
      <?php if (isset($_SESSION['khach_hang_id'])): ?>
        <div class="dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center" href="#"
             data-bs-toggle="dropdown">
            <span class="user-avatar">
              <?= mb_substr($_SESSION['khach_hang_ten'], 0, 1, 'UTF-8') ?>
            </span>
            <span class="text-white" style="font-size:.9rem;">
              <?= htmlspecialchars($_SESSION['khach_hang_ten']) ?>
            </span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li>
              <a class="dropdown-item" href="/nhasach/profile.php">
                <i class="bi bi-person me-2 text-muted"></i>Tài khoản của tôi
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="/nhasach/orders.php">
                <i class="bi bi-bag-check me-2 text-muted"></i>Lịch sử đơn hàng
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item text-danger" href="/nhasach/logout.php">
                <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
              </a>
            </li>
          </ul>
        </div>
      <?php else: ?>
        <div class="d-flex gap-2">
          <a href="/nhasach/login.php" class="btn btn-outline-light btn-sm px-3" style="border-radius:20px;">
            Đăng nhập
          </a>
          <a href="/nhasach/register.php" class="btn btn-sm px-3"
             style="background:#f4a261;color:#fff;border-radius:20px;border:none;">
            Đăng ký
          </a>
        </div>
      <?php endif; ?>

    </div>
  </div>
</nav>

<!-- FLASH MESSAGE (thông báo tạm) -->
<?php if (isset($_SESSION['flash'])): ?>
  <div class="flash-message">
    <div class="alert alert-<?= $_SESSION['flash']['type'] ?> alert-dismissible fade show shadow" role="alert">
      <?= htmlspecialchars($_SESSION['flash']['msg']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  </div>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>