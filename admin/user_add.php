<?php
ob_start();
$page_title = 'Thêm tài khoản';
require_once 'includes/admin_header.php';

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old      = $_POST;
    $ho_ten   = trim($_POST['ho_ten']  ?? '');
    $email    = trim($_POST['email']   ?? '');
    $sdt      = trim($_POST['so_dien_thoai'] ?? '');
    $dia_chi  = trim($_POST['dia_chi'] ?? '');
    $phuong   = trim($_POST['phuong_xa'] ?? '');
    $tinh     = trim($_POST['tinh_tp']   ?? '');
    $pass     = $_POST['mat_khau']     ?? '';

    if (empty($ho_ten)) $errors['ho_ten']   = 'Vui lòng nhập họ tên.';
    if (empty($email))  $errors['email']    = 'Vui lòng nhập email.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email không hợp lệ.';
    if (empty($pass) || strlen($pass) < 6)  $errors['mat_khau'] = 'Mật khẩu tối thiểu 6 ký tự.';
    if (empty($sdt))    $errors['so_dien_thoai'] = 'Vui lòng nhập số điện thoại.';
    elseif (!preg_match('/^[0-9]{9,11}$/', $sdt)) $errors['so_dien_thoai'] = 'Số điện thoại không hợp lệ.';

    if (empty($errors['email'])) {
        $ck = $pdo->prepare("SELECT id FROM khach_hang WHERE email = ?");
        $ck->execute([$email]);
        if ($ck->fetch()) $errors['email'] = 'Email đã được sử dụng.';
    }

    if (empty($errors)) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $pdo->prepare("
            INSERT INTO khach_hang (ho_ten, email, so_dien_thoai, dia_chi, phuong_xa, tinh_tp, mat_khau)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$ho_ten, $email, $sdt, $dia_chi, $phuong, $tinh, $hash]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Thêm tài khoản thành công!'];
        header('Location: /nhasach/admin/users.php');
        exit;
    }
}
?>

<div class="page-header">
  <h5><i class="bi bi-person-plus me-2" style="color:#f4a261;"></i>Thêm tài khoản khách hàng</h5>
  <a href="/nhasach/admin/users.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
    <i class="bi bi-arrow-left me-1"></i>Quay lại
  </a>
</div>

<div class="admin-card">
  <form method="POST" novalidate>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Họ tên *</label>
        <input type="text" name="ho_ten"
               class="form-control <?= isset($errors['ho_ten']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($old['ho_ten'] ?? '') ?>"
               placeholder="Nguyễn Văn A">
        <?php if (isset($errors['ho_ten'])): ?><div class="invalid-feedback"><?= $errors['ho_ten'] ?></div><?php endif; ?>
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Email *</label>
        <input type="email" name="email"
               class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($old['email'] ?? '') ?>"
               placeholder="example@gmail.com">
        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= $errors['email'] ?></div><?php endif; ?>
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Mật khẩu *</label>
        <input type="text" name="mat_khau"
               class="form-control <?= isset($errors['mat_khau']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($old['mat_khau'] ?? '') ?>"
               placeholder="Tối thiểu 6 ký tự">
        <?php if (isset($errors['mat_khau'])): ?><div class="invalid-feedback"><?= $errors['mat_khau'] ?></div><?php endif; ?>
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Số điện thoại *</label>
        <input type="text" name="so_dien_thoai"
               class="form-control <?= isset($errors['so_dien_thoai']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($old['so_dien_thoai'] ?? '') ?>"
               placeholder="0901234567">
        <?php if (isset($errors['so_dien_thoai'])): ?><div class="invalid-feedback"><?= $errors['so_dien_thoai'] ?></div><?php endif; ?>
      </div>
      <div class="col-md-12">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Địa chỉ</label>
        <input type="text" name="dia_chi" class="form-control"
               value="<?= htmlspecialchars($old['dia_chi'] ?? '') ?>"
               placeholder="Số nhà, đường...">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Phường / Xã</label>
        <input type="text" name="phuong_xa" class="form-control"
               value="<?= htmlspecialchars($old['phuong_xa'] ?? '') ?>"
               placeholder="Phường/Xã">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Tỉnh / Thành phố</label>
        <input type="text" name="tinh_tp" class="form-control"
               value="<?= htmlspecialchars($old['tinh_tp'] ?? '') ?>"
               placeholder="Tỉnh/TP">
      </div>
      <div class="col-12 d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-sm px-4"
                style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
          <i class="bi bi-plus-lg me-1"></i>Thêm tài khoản
        </button>
        <a href="/nhasach/admin/users.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">Huỷ</a>
      </div>
    </div>
  </form>
</div>

<?php
require_once 'includes/admin_footer.php';
ob_end_flush();
?>
