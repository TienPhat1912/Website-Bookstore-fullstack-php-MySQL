<?php
$page_title = 'Tài khoản của tôi';
require_once 'includes/header.php';

// Bảo vệ: phải đăng nhập
if (!isset($_SESSION['khach_hang_id'])) {
    $_SESSION['redirect_after_login'] = '/nhasach/profile.php';
    header('Location: /nhasach/login.php');
    exit;
}

// Lấy thông tin khách hàng
$stmt = $pdo->prepare("SELECT * FROM khach_hang WHERE id = ?");
$stmt->execute([$_SESSION['khach_hang_id']]);
$kh = $stmt->fetch();

$errors  = [];
$tab     = 'info';

// ============================================================
// XỬ LÝ CẬP NHẬT THÔNG TIN
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_info') {
    $ho_ten  = trim($_POST['ho_ten']        ?? '');
    $sdt     = trim($_POST['so_dien_thoai'] ?? '');
    $dia_chi = trim($_POST['dia_chi']       ?? '');
    $phuong  = trim($_POST['phuong_xa']     ?? '');
    $tinh    = trim($_POST['tinh_tp']       ?? '');

    if (empty($ho_ten))   $errors['ho_ten']  = 'Vui lòng nhập họ tên.';
    if (empty($sdt))      $errors['sdt']     = 'Vui lòng nhập số điện thoại.';
    elseif (!preg_match('/^[0-9]{9,11}$/', $sdt))
                          $errors['sdt']     = 'Số điện thoại không hợp lệ.';
    if (empty($dia_chi))  $errors['dia_chi'] = 'Vui lòng nhập địa chỉ.';
    if (empty($phuong))   $errors['phuong']  = 'Vui lòng nhập phường/xã.';
    if (empty($tinh))     $errors['tinh']    = 'Vui lòng nhập tỉnh/thành phố.';

    if (empty($errors)) {
        $pdo->prepare("
            UPDATE khach_hang
            SET ho_ten = ?, so_dien_thoai = ?, dia_chi = ?, phuong_xa = ?, tinh_tp = ?
            WHERE id = ?
        ")->execute([$ho_ten, $sdt, $dia_chi, $phuong, $tinh, $kh['id']]);

        // Cập nhật lại session tên
        $_SESSION['khach_hang_ten'] = $ho_ten;

        // Reload lại thông tin mới
        $stmt = $pdo->prepare("SELECT * FROM khach_hang WHERE id = ?");
        $stmt->execute([$kh['id']]);
        $kh = $stmt->fetch();

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Cập nhật thông tin thành công!'];
        header('Location: /nhasach/profile.php?tab=info');
        exit;
    }
    $tab = 'info';
}
// Thống kê đơn hàng
$so_don   = $pdo->prepare("SELECT COUNT(*) FROM don_hang WHERE khach_hang_id = ?");
$so_don->execute([$kh['id']]);
$so_don   = $so_don->fetchColumn();

$tong_chi = $pdo->prepare("SELECT COALESCE(SUM(tong_tien),0) FROM don_hang WHERE khach_hang_id = ? AND trang_thai != 'da_huy'");
$tong_chi->execute([$kh['id']]);
$tong_chi = $tong_chi->fetchColumn();
?>

<div class="container py-5">
  <div class="row g-4">

    <!-- SIDEBAR -->
    <div class="col-lg-3">

      <!-- Avatar + tên -->
      <div style="background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06);
                  padding:24px; text-align:center; margin-bottom:16px;">
        <div style="width:72px; height:72px; border-radius:50%; background:#f4a261;
                    display:flex; align-items:center; justify-content:center;
                    margin:0 auto 12px; font-size:2rem; color:#fff; font-weight:700;">
          <?= mb_substr($kh['ho_ten'], 0, 1, 'UTF-8') ?>
        </div>
        <div class="fw-bold" style="color:#1a1a2e;"><?= htmlspecialchars($kh['ho_ten']) ?></div>
        <div style="font-size:.82rem; color:#888;"><?= htmlspecialchars($kh['email']) ?></div>
        <div class="mt-2">
          <span class="badge bg-success">Đang hoạt động</span>
        </div>
      </div>

      <!-- Thống kê nhanh -->
      <div style="background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06);
                  padding:16px; margin-bottom:16px;">
        <div class="d-flex justify-content-between align-items-center py-2"
             style="border-bottom:1px solid #f0f0f0;">
          <span style="font-size:.85rem; color:#888;"><i class="bi bi-bag-check me-2"></i>Tổng đơn hàng</span>
          <span class="fw-bold" style="color:#f4a261;"><?= $so_don ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center pt-2">
          <span style="font-size:.85rem; color:#888;"><i class="bi bi-cash-coin me-2"></i>Tổng chi tiêu</span>
          <span class="fw-bold" style="color:#e63946; font-size:.88rem;">
            <?= $tong_chi > 0 ? number_format($tong_chi, 0, ',', '.') . '₫' : '—' ?>
          </span>
        </div>
      </div>

      <!-- Menu điều hướng -->
      <div style="background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); overflow:hidden;">
        <a href="/nhasach/profile.php?tab=info"
           class="d-flex align-items-center px-4 py-3 text-decoration-none"
           style="color:<?= $tab==='info' ? '#f4a261' : '#555' ?>;
                  background:<?= $tab==='info' ? '#fff8f3' : '#fff' ?>;
                  border-left:3px solid <?= $tab==='info' ? '#f4a261' : 'transparent' ?>;
                  font-size:.9rem;">
          <i class="bi bi-person me-3"></i>Thông tin cá nhân
        </a>
        <a href="/nhasach/orders.php"
           class="d-flex align-items-center px-4 py-3 text-decoration-none"
           style="color:#555; font-size:.9rem; border-left:3px solid transparent;">
          <i class="bi bi-bag-check me-3"></i>Lịch sử đơn hàng
        </a>
        <a href="/nhasach/logout.php"
           class="d-flex align-items-center px-4 py-3 text-decoration-none"
           style="color:#e63946; font-size:.9rem; border-left:3px solid transparent;
                  border-top:1px solid #f0f0f0;">
          <i class="bi bi-box-arrow-right me-3"></i>Đăng xuất
        </a>
      </div>

    </div>

    <!-- NỘI DUNG CHÍNH -->
    <div class="col-lg-9">

      <?php if ($tab === 'info'): ?>
      <!-- ========== TAB THÔNG TIN CÁ NHÂN ========== -->
      <div style="background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); padding:32px;">

        <h5 class="fw-bold mb-4" style="color:#1a1a2e;">
          <i class="bi bi-person-circle me-2" style="color:#f4a261;"></i>Thông tin cá nhân
        </h5>

        <!-- Xem thông tin -->
        <div class="row g-3 mb-4 p-3"
             style="background:#f8f9fa; border-radius:10px; font-size:.9rem;">
          <div class="col-md-6">
            <div class="text-muted mb-1" style="font-size:.78rem; text-transform:uppercase; letter-spacing:.05em;">Họ và tên</div>
            <div class="fw-semibold"><?= htmlspecialchars($kh['ho_ten']) ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted mb-1" style="font-size:.78rem; text-transform:uppercase; letter-spacing:.05em;">Email</div>
            <div class="fw-semibold"><?= htmlspecialchars($kh['email']) ?>
              <span class="badge bg-secondary ms-1" style="font-size:.7rem;">Không thể đổi</span>
            </div>
          </div>
          <div class="col-md-6">
            <div class="text-muted mb-1" style="font-size:.78rem; text-transform:uppercase; letter-spacing:.05em;">Số điện thoại</div>
            <div class="fw-semibold"><?= htmlspecialchars($kh['so_dien_thoai'] ?? '—') ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted mb-1" style="font-size:.78rem; text-transform:uppercase; letter-spacing:.05em;">Ngày đăng ký</div>
            <div class="fw-semibold"><?= date('d/m/Y', strtotime($kh['ngay_tao'])) ?></div>
          </div>
          <div class="col-12">
            <div class="text-muted mb-1" style="font-size:.78rem; text-transform:uppercase; letter-spacing:.05em;">Địa chỉ</div>
            <div class="fw-semibold">
              <?php
                $dia_chi_parts = array_filter([
                    $kh['dia_chi'] ?? '',
                    $kh['phuong_xa'] ?? '',
                    $kh['tinh_tp'] ?? ''
                ]);
                echo $dia_chi_parts ? htmlspecialchars(implode(', ', $dia_chi_parts)) : '—';
              ?>
            </div>
          </div>
        </div>

        <hr>

        <!-- Form chỉnh sửa -->
        <h6 class="fw-bold mb-3" style="color:#1a1a2e;">
          <i class="bi bi-pencil-square me-2" style="color:#f4a261;"></i>Chỉnh sửa thông tin
        </h6>

        <form method="POST" action="/nhasach/profile.php?tab=info" novalidate>
          <input type="hidden" name="action" value="update_info">

          <div class="row g-3">
            <!-- Họ tên -->
            <div class="col-md-6">
              <label class="form-label fw-semibold" style="font-size:.88rem;">
                Họ và tên <span class="text-danger">*</span>
              </label>
              <input type="text" name="ho_ten"
                     class="form-control <?= isset($errors['ho_ten']) ? 'is-invalid' : '' ?>"
                     value="<?= htmlspecialchars($_POST['ho_ten'] ?? $kh['ho_ten']) ?>"
                     placeholder="Nguyễn Văn A">
              <?php if (isset($errors['ho_ten'])): ?>
                <div class="invalid-feedback"><?= $errors['ho_ten'] ?></div>
              <?php endif; ?>
            </div>

            <!-- Email (readonly) -->
            <div class="col-md-6">
              <label class="form-label fw-semibold" style="font-size:.88rem;">Email</label>
              <input type="email" class="form-control"
                     value="<?= htmlspecialchars($kh['email']) ?>"
                     disabled style="background:#f8f9fa; color:#888;">
            </div>

            <!-- SĐT -->
            <div class="col-md-6">
              <label class="form-label fw-semibold" style="font-size:.88rem;">
                Số điện thoại <span class="text-danger">*</span>
              </label>
              <input type="tel" name="so_dien_thoai"
                     class="form-control <?= isset($errors['sdt']) ? 'is-invalid' : '' ?>"
                     value="<?= htmlspecialchars($_POST['so_dien_thoai'] ?? $kh['so_dien_thoai'] ?? '') ?>"
                     placeholder="0909 123 456">
              <?php if (isset($errors['sdt'])): ?>
                <div class="invalid-feedback"><?= $errors['sdt'] ?></div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Địa chỉ -->
          <div class="mt-3">
            <?php
              $dc_prefix = 'profile';
              $dc_tinh   = $_POST['tinh_tp']   ?? $kh['tinh_tp']   ?? '';
              $dc_phuong = $_POST['phuong_xa'] ?? $kh['phuong_xa'] ?? '';
              $dc_diachi = $_POST['dia_chi']   ?? $kh['dia_chi']   ?? '';
              include 'includes/diachi_dropdown.php';
            ?>
          </div>

          <div class="mt-4">
            <button type="submit" class="btn px-4 py-2 fw-semibold"
                    style="background:#f4a261; color:#fff; border:none; border-radius:10px;">
              <i class="bi bi-check-lg me-2"></i>Lưu thay đổi
            </button>
          </div>
        </form>
      </div>  
      <?php endif; ?>    
    </div>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
