<?php
$page_title = 'Đăng ký tài khoản';
require_once 'includes/header.php';

// Nếu đã đăng nhập rồi thì về trang chủ
if (isset($_SESSION['khach_hang_id'])) {
    header('Location: /nhasach/index.php');
    exit;
}

$errors = [];
$old    = []; // giữ lại giá trị form khi có lỗi

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    // Lấy & làm sạch dữ liệu
    $ho_ten    = trim($_POST['ho_ten']    ?? '');
    $email     = trim($_POST['email']     ?? '');
    $mat_khau  = trim($_POST['mat_khau']  ?? '');
    $xac_nhan  = trim($_POST['xac_nhan']  ?? '');
    $sdt       = trim($_POST['so_dien_thoai'] ?? '');
    $dia_chi   = trim($_POST['dia_chi']   ?? '');
    $phuong    = trim($_POST['phuong_xa'] ?? '');
    $tinh      = trim($_POST['tinh_tp']   ?? '');

    // Validate
    if (empty($ho_ten))   $errors['ho_ten']   = 'Vui lòng nhập họ tên.';
    if (empty($email))    $errors['email']     = 'Vui lòng nhập email.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
                          $errors['email']     = 'Email không hợp lệ.';
    if (empty($mat_khau)) $errors['mat_khau']  = 'Vui lòng nhập mật khẩu.';
    elseif (strlen($mat_khau) < 6)
                          $errors['mat_khau']  = 'Mật khẩu tối thiểu 6 ký tự.';
    if ($xac_nhan !== $mat_khau)
                          $errors['xac_nhan']  = 'Mật khẩu xác nhận không khớp.';
    if (empty($sdt))      $errors['sdt']       = 'Vui lòng nhập số điện thoại.';
    elseif (!preg_match('/^[0-9]{9,11}$/', $sdt))
                          $errors['sdt']       = 'Số điện thoại không hợp lệ.';
    if (empty($dia_chi))  $errors['dia_chi']   = 'Vui lòng nhập địa chỉ.';
    if (empty($phuong))   $errors['phuong']    = 'Vui lòng nhập phường/xã.';
    if (empty($tinh))     $errors['tinh']      = 'Vui lòng nhập tỉnh/thành phố.';

    // Kiểm tra email đã tồn tại
    if (empty($errors['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM khach_hang WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors['email'] = 'Email này đã được đăng ký.';
    }

    // Nếu không có lỗi → lưu DB
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO khach_hang
                (ho_ten, email, mat_khau, so_dien_thoai, dia_chi, phuong_xa, tinh_tp)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $ho_ten, $email,
            password_hash($mat_khau, PASSWORD_BCRYPT),
            $sdt, $dia_chi, $phuong, $tinh
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đăng ký thành công! Mời bạn đăng nhập.'];
        header('Location: /nhasach/login.php');
        exit;
    }
}
?>

<div class="container py-5">
  <div style="max-width:560px; margin:0 auto;">

    <div style="background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.08); padding:36px 40px;">

      <!-- Tiêu đề -->
      <div class="text-center mb-4">
        <i class="bi bi-person-plus" style="font-size:2.5rem; color:#f4a261;"></i>
        <h4 class="fw-bold mt-2 mb-0" style="color:#1a1a2e;">Tạo tài khoản</h4>
        <p class="text-muted" style="font-size:.88rem;">Điền đầy đủ thông tin để đặt hàng</p>
      </div>

      <form method="POST" action="/nhasach/register.php" novalidate>

        <!-- Thông tin cá nhân -->
        <div class="section-label mb-3">
          <span style="font-size:.75rem; text-transform:uppercase; letter-spacing:.1em;
                       color:#f4a261; font-weight:600;">
            Thông tin cá nhân
          </span>
        </div>

        <!-- Họ tên -->
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.88rem;">
            Họ và tên <span class="text-danger">*</span>
          </label>
          <input type="text" name="ho_ten"
                 class="form-control <?= isset($errors['ho_ten']) ? 'is-invalid' : '' ?>"
                 placeholder="Nguyễn Văn A"
                 value="<?= htmlspecialchars($old['ho_ten'] ?? '') ?>">
          <?php if (isset($errors['ho_ten'])): ?>
            <div class="invalid-feedback"><?= $errors['ho_ten'] ?></div>
          <?php endif; ?>
        </div>

        <!-- Email -->
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.88rem;">
            Email <span class="text-danger">*</span>
          </label>
          <input type="email" name="email"
                 class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                 placeholder="example@email.com"
                 value="<?= htmlspecialchars($old['email'] ?? '') ?>">
          <?php if (isset($errors['email'])): ?>
            <div class="invalid-feedback"><?= $errors['email'] ?></div>
          <?php endif; ?>
        </div>

        <!-- Mật khẩu -->
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.88rem;">
            Mật khẩu <span class="text-danger">*</span>
          </label>
          <div class="input-group">
            <input type="password" name="mat_khau" id="mat_khau"
                   class="form-control <?= isset($errors['mat_khau']) ? 'is-invalid' : '' ?>"
                   placeholder="Tối thiểu 6 ký tự">
            <button class="btn btn-outline-secondary" type="button"
                    onclick="togglePass('mat_khau', this)">
              <i class="bi bi-eye"></i>
            </button>
            <?php if (isset($errors['mat_khau'])): ?>
              <div class="invalid-feedback"><?= $errors['mat_khau'] ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Xác nhận mật khẩu -->
        <div class="mb-4">
          <label class="form-label fw-semibold" style="font-size:.88rem;">
            Xác nhận mật khẩu <span class="text-danger">*</span>
          </label>
          <div class="input-group">
            <input type="password" name="xac_nhan" id="xac_nhan"
                   class="form-control <?= isset($errors['xac_nhan']) ? 'is-invalid' : '' ?>"
                   placeholder="Nhập lại mật khẩu">
            <button class="btn btn-outline-secondary" type="button"
                    onclick="togglePass('xac_nhan', this)">
              <i class="bi bi-eye"></i>
            </button>
            <?php if (isset($errors['xac_nhan'])): ?>
              <div class="invalid-feedback"><?= $errors['xac_nhan'] ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Thông tin giao hàng -->
        <div class="section-label mb-3">
          <span style="font-size:.75rem; text-transform:uppercase; letter-spacing:.1em;
                       color:#f4a261; font-weight:600;">
            Thông tin giao hàng
          </span>
          <hr class="mt-1">
        </div>

        <!-- Số điện thoại -->
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.88rem;">
            Số điện thoại <span class="text-danger">*</span>
          </label>
          <input type="tel" name="so_dien_thoai"
                 class="form-control <?= isset($errors['sdt']) ? 'is-invalid' : '' ?>"
                 placeholder="0909 123 456"
                 value="<?= htmlspecialchars($old['so_dien_thoai'] ?? '') ?>">
          <?php if (isset($errors['sdt'])): ?>
            <div class="invalid-feedback"><?= $errors['sdt'] ?></div>
          <?php endif; ?>
        </div>

        <?php
$dc_prefix = 'reg';
$dc_tinh   = $old['tinh_tp']   ?? '';
$dc_phuong = $old['phuong_xa'] ?? '';
$dc_diachi = $old['dia_chi']   ?? '';
include 'includes/diachi_dropdown.php';
?>

        <button type="submit" class="btn w-100 py-2 fw-semibold"
                style="background:#f4a261; color:#fff; border:none; border-radius:10px; font-size:.95rem;">
          <i class="bi bi-person-check me-2"></i>Tạo tài khoản
        </button>

        <p class="text-center mt-3 mb-0" style="font-size:.88rem; color:#888;">
          Đã có tài khoản?
          <a href="/nhasach/login.php" style="color:#f4a261; font-weight:600;">Đăng nhập</a>
        </p>

      </form>
    </div>
  </div>
</div>

<script>
function togglePass(fieldId, btn) {
  const input = document.getElementById(fieldId);
  const icon  = btn.querySelector('i');
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
