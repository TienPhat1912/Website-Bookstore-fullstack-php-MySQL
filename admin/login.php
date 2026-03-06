<?php
ob_start();
session_start();

// Đã đăng nhập admin rồi thì vào dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: /nhasach/admin/index.php');
    exit;
}

require_once '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $mat_khau = trim($_POST['mat_khau'] ?? '');

    if (empty($email) || empty($mat_khau)) {
        $error = 'Vui lòng nhập đầy đủ email và mật khẩu.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if (!$admin) {
            $error = 'Email không tồn tại.';
        } elseif ($admin['bi_khoa']) {
            $error = 'Tài khoản đã bị khoá.';
        } elseif (!password_verify($mat_khau, $admin['mat_khau'])) {
            $error = 'Mật khẩu không đúng.';
        } else {
            $_SESSION['admin_id']  = $admin['id'];
            $_SESSION['admin_ten'] = $admin['ho_ten'];
            header('Location: /nhasach/admin/index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đăng nhập Admin — Nhà Sách</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      font-family: 'Segoe UI', sans-serif;
    }
    .login-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0,0,0,.3);
      padding: 44px 40px;
      width: 100%; max-width: 400px;
    }
    .login-card .logo {
      font-size: 1.4rem; font-weight: 800;
      color: #1a1a2e; text-align: center; margin-bottom: 6px;
    }
    .login-card .logo span { color: #f4a261; }
    .login-card .subtitle {
      text-align: center; font-size: .82rem;
      color: #888; margin-bottom: 28px;
      text-transform: uppercase; letter-spacing: .1em;
    }
    .form-control {
      border-radius: 10px; padding: 10px 14px;
      border: 1px solid #dee2e6; font-size: .92rem;
    }
    .form-control:focus {
      border-color: #f4a261;
      box-shadow: 0 0 0 3px rgba(244,162,97,.15);
    }
    .btn-login {
      background: #f4a261; color: #fff; border: none;
      border-radius: 10px; padding: 11px;
      font-weight: 600; font-size: .95rem; width: 100%;
    }
    .btn-login:hover { background: #e08c4a; color: #fff; }
    .back-link {
      display: block; text-align: center; margin-top: 16px;
      font-size: .83rem; color: #aaa; text-decoration: none;
    }
    .back-link:hover { color: #666; }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="logo"><i class="bi bi-book-half me-2"></i>Nhà<span>Sách</span></div>
    <div class="subtitle">Trang quản trị</div>

    <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 mb-3"
           style="border-radius:10px; font-size:.88rem;">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="/nhasach/admin/login.php">
      <div class="mb-3">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Email</label>
        <div class="input-group">
          <span class="input-group-text" style="background:#f8f9fa; border-right:none; border-radius:10px 0 0 10px;">
            <i class="bi bi-envelope text-muted"></i>
          </span>
          <input type="email" name="email" class="form-control"
                 placeholder="admin@nhasach.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 style="border-left:none; border-radius:0 10px 10px 0;"
                 autofocus>
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Mật khẩu</label>
        <div class="input-group">
          <span class="input-group-text" style="background:#f8f9fa; border-right:none; border-radius:10px 0 0 10px;">
            <i class="bi bi-lock text-muted"></i>
          </span>
          <input type="password" name="mat_khau" id="mat_khau" class="form-control"
                 placeholder="Nhập mật khẩu"
                 style="border-left:none; border-right:none; border-radius:0;">
          <button class="btn btn-outline-secondary" type="button"
                  onclick="togglePass()"
                  style="border-radius:0 10px 10px 0;">
            <i class="bi bi-eye" id="eye-icon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login">
        <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
      </button>
    </form>

    <a href="/nhasach/index.php" class="back-link">
      <i class="bi bi-arrow-left me-1"></i>Về trang chủ
    </a>
  </div>

  <script>
  function togglePass() {
    const input = document.getElementById('mat_khau');
    const icon  = document.getElementById('eye-icon');
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
  }
  </script>
</body>
</html>
<?php ob_end_flush(); ?>