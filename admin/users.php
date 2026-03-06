<?php
ob_start();
$page_title = 'Người dùng';
require_once 'includes/admin_header.php';

$errors = [];
$old    = [];

// ---- KHOÁ / MỞ KHOÁ ----
if (isset($_GET['action']) && in_array($_GET['action'], ['lock','unlock']) && isset($_GET['id'])) {
    $trang_thai = $_GET['action'] === 'lock' ? 0 : 1;
    $id = (int)$_GET['id'];
    $pdo->prepare("UPDATE khach_hang SET trang_thai = ? WHERE id = ?")
        ->execute([$trang_thai, $id]);
    $_SESSION['flash'] = [
        'type' => $trang_thai ? 'success' : 'warning',
        'msg'  => $trang_thai ? 'Đã mở khoá tài khoản.' : 'Đã khoá tài khoản.'
    ];
    header('Location: /nhasach/admin/users.php');
    exit;
}

// ---- KHỞI TẠO LẠI MẬT KHẨU ----
if (isset($_GET['action']) && $_GET['action'] === 'reset_pass' && isset($_GET['id'])) {
    $id     = (int)$_GET['id'];
    $newPass = 'Nhasach@123';
    $hash   = password_hash($newPass, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE khach_hang SET mat_khau = ? WHERE id = ?")
        ->execute([$hash, $id]);
    $_SESSION['flash'] = ['type' => 'info', 'msg' => "Đã khởi tạo lại mật khẩu thành: <strong>$newPass</strong>"];
    header('Location: /nhasach/admin/users.php');
    exit;
}

// ---- THÊM TÀI KHOẢN MỚI ----
$show_form = isset($_GET['add']) || !empty($errors);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_user') {
    $old      = $_POST;
    $ho_ten   = trim($_POST['ho_ten']  ?? '');
    $email    = trim($_POST['email']   ?? '');
    $sdt      = trim($_POST['so_dien_thoai']     ?? '');
    $dia_chi  = trim($_POST['dia_chi'] ?? '');
    $pass     = $_POST['mat_khau']     ?? '';

    if (empty($ho_ten)) $errors['ho_ten']   = 'Vui lòng nhập họ tên.';
    if (empty($email))  $errors['email']    = 'Vui lòng nhập email.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email không hợp lệ.';
    if (empty($pass) || strlen($pass) < 6)  $errors['mat_khau'] = 'Mật khẩu tối thiểu 6 ký tự.';

    if (empty($errors['email'])) {
        $ck = $pdo->prepare("SELECT id FROM khach_hang WHERE email = ?");
        $ck->execute([$email]);
        if ($ck->fetch()) $errors['email'] = 'Email đã được sử dụng.';
    }

    if (empty($errors)) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $pdo->prepare("
            INSERT INTO khach_hang (ho_ten, email, so_dien_thoai, dia_chi, mat_khau)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$ho_ten, $email, $sdt, $dia_chi, $hash]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Thêm tài khoản thành công!'];
        header('Location: /nhasach/admin/users.php');
        exit;
    }
    $show_form = true;
}

// ---- TÌM KIẾM ----
$filter_search = trim($_GET['search'] ?? '');
$filter_tt     = $_GET['trang_thai'] ?? 'tat_ca';

$where  = ["1=1"];
$params = [];
if ($filter_search !== '') { $where[] = "(ho_ten LIKE ? OR email LIKE ? OR so_dien_thoai LIKE ?)"; $params[] = "%$filter_search%"; $params[] = "%$filter_search%"; $params[] = "%$filter_search%"; }
if ($filter_tt === 'hoat_dong')  { $where[] = "trang_thai = 1"; }
if ($filter_tt === 'bi_khoa')    { $where[] = "trang_thai = 0"; }

$where_sql = implode(' AND ', $where);
$users = $pdo->prepare("
    SELECT kh.*,
           (SELECT COUNT(*) FROM don_hang WHERE khach_hang_id = kh.id) AS so_don,
           (SELECT COALESCE(SUM(tong_tien),0) FROM don_hang WHERE khach_hang_id = kh.id AND trang_thai != 'da_huy') AS tong_chi
    FROM khach_hang kh
    WHERE $where_sql
    ORDER BY kh.ngay_tao DESC
");
$users->execute($params);
$users = $users->fetchAll();

$tong_kh      = $pdo->query("SELECT COUNT(*) FROM khach_hang")->fetchColumn();
$tong_bi_khoa = $pdo->query("SELECT COUNT(*) FROM khach_hang WHERE trang_thai = 0")->fetchColumn();
?>

<div class="page-header">
  <h5><i class="bi bi-people me-2" style="color:#f4a261;"></i>Quản lý người dùng</h5>
  <a href="/nhasach/admin/users.php?add=1"
     class="btn btn-sm" style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
    <i class="bi bi-plus-lg me-1"></i>Thêm tài khoản
  </a>
</div>

<!-- THỐNG KÊ NHANH -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="admin-card text-center py-3">
      <div style="font-size:1.6rem; font-weight:700; color:#a66eff;"><?= number_format($tong_kh) ?></div>
      <div style="font-size:.8rem; color:#888;">Tổng khách hàng</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card text-center py-3">
      <div style="font-size:1.6rem; font-weight:700; color:#e63946;"><?= number_format($tong_bi_khoa) ?></div>
      <div style="font-size:.8rem; color:#888;">Tài khoản bị khoá</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card text-center py-3">
      <div style="font-size:1.6rem; font-weight:700; color:#3fe0a0;"><?= number_format($tong_kh - $tong_bi_khoa) ?></div>
      <div style="font-size:.8rem; color:#888;">Đang hoạt động</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card text-center py-3">
      <div style="font-size:1.6rem; font-weight:700; color:#f4a261;">
        <?= $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai = 'cho_xu_ly'")->fetchColumn() ?>
      </div>
      <div style="font-size:.8rem; color:#888;">Đơn chờ xử lý</div>
    </div>
  </div>
</div>

<!-- FORM THÊM TÀI KHOẢN -->
<?php if ($show_form): ?>
<div class="admin-card mb-4">
  <div class="card-title">Thêm tài khoản khách hàng</div>
  <form method="POST" action="/nhasach/admin/users.php" novalidate>
    <input type="hidden" name="action" value="create_user">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Họ tên *</label>
        <input type="text" name="ho_ten"
               class="form-control <?= isset($errors['ho_ten']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($old['ho_ten'] ?? '') ?>"
               placeholder="Nguyễn Văn A">
        <?php if (isset($errors['ho_ten'])): ?><div class="invalid-feedback"><?= $errors['ho_ten'] ?></div><?php endif; ?>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Email *</label>
        <input type="email" name="email"
               class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($old['email'] ?? '') ?>"
               placeholder="example@gmail.com">
        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= $errors['email'] ?></div><?php endif; ?>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Mật khẩu *</label>
        <input type="text" name="mat_khau"
               class="form-control <?= isset($errors['mat_khau']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($old['mat_khau'] ?? '') ?>"
               placeholder="Tối thiểu 6 ký tự">
        <?php if (isset($errors['mat_khau'])): ?><div class="invalid-feedback"><?= $errors['mat_khau'] ?></div><?php endif; ?>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Số điện thoại</label>
        <input type="text" name="so_dien_thoai" class="form-control"
               value="<?= htmlspecialchars($old['so_dien_thoai'] ?? '') ?>"
               placeholder="0901234567">
      </div>
      <div class="col-md-8">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Địa chỉ</label>
        <input type="text" name="dia_chi" class="form-control"
               value="<?= htmlspecialchars($old['dia_chi'] ?? '') ?>"
               placeholder="Số nhà, đường, phường, quận, TP...">
      </div>
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-sm px-4"
                style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
          <i class="bi bi-plus-lg me-1"></i>Thêm tài khoản
        </button>
        <a href="/nhasach/admin/users.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">Huỷ</a>
      </div>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- BỘ LỌC -->
<div class="admin-card mb-3">
  <form method="GET" action="/nhasach/admin/users.php" class="row g-2 align-items-end">
    <div class="col-md-5">
      <input type="text" name="search" class="form-control form-control-sm"
             placeholder="Tìm theo tên, email, SĐT..."
             value="<?= htmlspecialchars($filter_search) ?>">
    </div>
    <div class="col-md-3">
      <select name="trang_thai" class="form-select form-select-sm">
        <option value="tat_ca"      <?= $filter_tt=='tat_ca'     ?'selected':'' ?>>Tất cả</option>
        <option value="hoat_dong"   <?= $filter_tt=='hoat_dong'  ?'selected':'' ?>>Đang hoạt động</option>
        <option value="bi_khoa"     <?= $filter_tt=='bi_khoa'    ?'selected':'' ?>>Bị khoá</option>
      </select>
    </div>
    <div class="col-md-4 d-flex gap-2">
      <button type="submit" class="btn btn-sm btn-primary" style="border-radius:8px;">
        <i class="bi bi-funnel me-1"></i>Lọc
      </button>
      <a href="/nhasach/admin/users.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
        Xoá lọc
      </a>
    </div>
  </form>
</div>

<!-- BẢNG NGƯỜI DÙNG -->
<div class="admin-card">
  <div class="card-title">Danh sách khách hàng (<?= count($users) ?>)</div>

  <?php if (empty($users)): ?>
    <p class="text-muted text-center py-4">Không có tài khoản nào.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table admin-table table-hover">
        <thead>
          <tr>
            <th>Họ tên</th>
            <th>Email</th>
            <th>SĐT</th>
            <th>Đăng ký</th>
            <th class="text-center">Đơn hàng</th>
            <th class="text-end">Tổng chi</th>
            <th>Trạng thái</th>
            <th style="width:130px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div class="fw-semibold" style="font-size:.88rem;"><?= htmlspecialchars($u['ho_ten']) ?></div>
            </td>
            <td style="font-size:.85rem;"><?= htmlspecialchars($u['email']) ?></td>
            <td style="font-size:.85rem;"><?= htmlspecialchars($u['so_dien_thoai'] ?? '—') ?></td>
            <td style="font-size:.82rem; color:#888;">
              <?= isset($u['ngay_tao']) ? date('d/m/Y', strtotime($u['ngay_tao'])) : '—' ?>
            </td>
            <td class="text-center">
              <a href="/nhasach/admin/orders.php?search=<?= urlencode($u['email']) ?>"
                 class="badge bg-light text-dark border text-decoration-none">
                <?= $u['so_don'] ?> đơn
              </a>
            </td>
            <td class="text-end" style="font-size:.85rem; font-weight:600;">
              <?= $u['tong_chi'] > 0 ? number_format($u['tong_chi'], 0, ',', '.') . '₫' : '—' ?>
            </td>
            <td>
              <?php if ($u['trang_thai']): ?>
                <span class="badge bg-success">Hoạt động</span>
              <?php else: ?>
                <span class="badge bg-danger">Bị khoá</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <!-- Khoá / Mở khoá -->
                <?php if ($u['trang_thai']): ?>
                  <a href="/nhasach/admin/users.php?action=lock&id=<?= $u['id'] ?>"
                     class="btn btn-sm btn-outline-warning" style="border-radius:6px;" title="Khoá tài khoản"
                     onclick="return confirm('Khoá tài khoản <?= htmlspecialchars($u['ho_ten']) ?>?')">
                    <i class="bi bi-lock"></i>
                  </a>
                <?php else: ?>
                  <a href="/nhasach/admin/users.php?action=unlock&id=<?= $u['id'] ?>"
                     class="btn btn-sm btn-outline-success" style="border-radius:6px;" title="Mở khoá">
                    <i class="bi bi-unlock"></i>
                  </a>
                <?php endif; ?>

                <!-- Reset mật khẩu -->
                <a href="/nhasach/admin/users.php?action=reset_pass&id=<?= $u['id'] ?>"
                   class="btn btn-sm btn-outline-secondary" style="border-radius:6px;" title="Khởi tạo lại mật khẩu"
                   onclick="return confirm('Khởi tạo lại mật khẩu về Nhasach@123?')">
                  <i class="bi bi-key"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php
require_once 'includes/admin_footer.php';
ob_end_flush();
?>
