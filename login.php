<?php
$page_title = 'Đăng nhập';
require_once 'includes/header.php';

// Nếu đã đăng nhập rồi thì về trang chủ
if (isset($_SESSION['khach_hang_id'])) {
    header('Location: /nhasach/index.php');
    exit;
}

$error = '';
$old_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $mat_khau = trim($_POST['mat_khau'] ?? '');
    $old_email = $email;

    if (empty($email) || empty($mat_khau)) {
        $error = 'Vui lòng nhập đầy đủ email và mật khẩu.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM khach_hang WHERE email = ?");
        $stmt->execute([$email]);
        $kh = $stmt->fetch();

        if (!$kh) {
            $error = 'Email không tồn tại trong hệ thống.';
        } elseif ($kh['bi_khoa']) {
            $error = 'Tài khoản của bạn đã bị khoá. Vui lòng liên hệ quản trị viên.';
        } elseif (!password_verify($mat_khau, $kh['mat_khau'])) {
            $error = 'Mật khẩu không đúng.';
        } else {
            // Đăng nhập thành công
            $_SESSION['khach_hang_id']  = $kh['id'];
            $_SESSION['khach_hang_ten'] = $kh['ho_ten'];

            $_SESSION['flash'] = [
                'type' => 'success',
                'msg'  => 'Chào mừng bạn quay lại, ' . $kh['ho_ten'] . '!'
            ];

            // Nếu có trang redirect trước đó thì quay về đó
            $redirect = $_SESSION['redirect_after_login'] ?? '/nhasach/index.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        }
    }
}
?>

<div class="container py-5">
  <div style="max-width:420px; margin:0 auto;">

    <div style="background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.08); padding:40px;">

      <!-- Tiêu đề -->
      <div class="text-center mb-4">
        <i class="bi bi-box-arrow-in-right" style="font-size:2.5rem; color:#f4a261;"></i>
        <h4 class="fw-bold mt-2 mb-0" style="color:#1a1a2e;">Đăng nhập</h4>
        <p class="text-muted" style="font-size:.88rem;">Chào mừng bạn trở lại!</p>
      </div>

      <!-- Thông báo lỗi -->
      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-3"
             style="border-radius:10px; font-size:.9rem;">
          <i class="bi bi-exclamation-circle-fill"></i>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="/nhasach/login.php" novalidate>

        <!-- Email -->
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.88rem;">Email</label>
          <div class="input-group">
            <span class="input-group-text" style="background:#f8f9fa; border-right:none;">
              <i class="bi bi-envelope text-muted"></i>
            </span>
            <input type="email" name="email"
                   class="form-control <?= $error ? 'is-invalid' : '' ?>"
                   placeholder="example@email.com"
                   value="<?= htmlspecialchars($old_email) ?>"
                   style="border-left:none;"
                   autofocus>
          </div>
        </div>

        <!-- Mật khẩu -->
        <div class="mb-4">
          <label class="form-label fw-semibold" style="font-size:.88rem;">Mật khẩu</label>
          <div class="input-group">
            <span class="input-group-text" style="background:#f8f9fa; border-right:none;">
              <i class="bi bi-lock text-muted"></i>
            </span>
            <input type="password" name="mat_khau" id="mat_khau"
                   class="form-control <?= $error ? 'is-invalid' : '' ?>"
                   placeholder="Nhập mật khẩu"
                   style="border-left:none; border-right:none;">
            <button class="btn btn-outline-secondary" type="button"
                    onclick="togglePass()"
                    style="border-left:none;">
              <i class="bi bi-eye" id="eye-icon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn w-100 py-2 fw-semibold mb-3"
                style="background:#f4a261; color:#fff; border:none; border-radius:10px; font-size:.95rem;">
          <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
        </button>

        <div class="text-center" style="font-size:.88rem; color:#888;">
          Chưa có tài khoản?
          <a href="/nhasach/register.php" style="color:#f4a261; font-weight:600;">Đăng ký ngay</a>
        </div>

      </form>
    </div>

    <!-- Gợi ý tài khoản demo -->
    <div class="mt-3 p-3 text-center"
         style="background:rgba(244,162,97,.08); border-radius:10px;
                border:1px dashed #f4a261; font-size:.82rem; color:#888;">
      <i class="bi bi-info-circle me-1" style="color:#f4a261;"></i>
      Để test: đăng ký tài khoản mới hoặc nhờ admin tạo cho bạn.
    </div>

  </div>
</div>

<script>
function togglePass() {
  const input = document.getElementById('mat_khau');
  const icon  = document.getElementById('eye-icon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}
</script>

<?php require_once 'includes/footer.php'; ?>
