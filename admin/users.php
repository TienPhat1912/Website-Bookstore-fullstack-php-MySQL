<?php
ob_start();
$page_title = 'Người dùng';
require_once 'includes/admin_header.php';

// ---- KHOÁ / MỞ KHOÁ ----
if (isset($_GET['action']) && in_array($_GET['action'], ['lock','unlock']) && isset($_GET['id'])) {
  $bi_khoa = $_GET['action'] === 'lock' ? 1 : 0;
  $id = (int)$_GET['id'];
  $pdo->prepare("UPDATE khach_hang SET bi_khoa = ? WHERE id = ?")
    ->execute([$bi_khoa, $id]);
$_SESSION['flash'] = [
    'type' => $bi_khoa ? 'warning' : 'success',
    'msg'  => $bi_khoa ? 'Đã khoá tài khoản.' : 'Đã mở khoá tài khoản.'
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

// ---- TÌM KIẾM ----
$filter_search = trim($_GET['search'] ?? '');
$filter_tt     = $_GET['trang_thai'] ?? 'tat_ca';
$per_page      = 20;
$trang_hien    = max(1, (int)($_GET['trang'] ?? 1));

$where  = ["1=1"];
$params = [];
if ($filter_search !== '') { $where[] = "(ho_ten LIKE ? OR email LIKE ? OR so_dien_thoai LIKE ?)"; $params[] = "%$filter_search%"; $params[] = "%$filter_search%"; $params[] = "%$filter_search%"; }
if ($filter_tt === 'hoat_dong')  { $where[] = "bi_khoa = 0"; }
if ($filter_tt === 'bi_khoa')    { $where[] = "bi_khoa = 1"; }

$where_sql = implode(' AND ', $where);

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM khach_hang kh WHERE $where_sql");
$count_stmt->execute($params);
$total      = (int)$count_stmt->fetchColumn();
$total_page = max(1, ceil($total / $per_page));
$trang_hien = min($trang_hien, $total_page);
$offset     = ($trang_hien - 1) * $per_page;

$users = $pdo->prepare("
    SELECT kh.*,
           (SELECT COUNT(*) FROM don_hang WHERE khach_hang_id = kh.id) AS so_don,
           (SELECT COALESCE(SUM(tong_tien),0) FROM don_hang WHERE khach_hang_id = kh.id AND trang_thai != 'da_huy') AS tong_chi
    FROM khach_hang kh
    WHERE $where_sql
    ORDER BY kh.ngay_tao DESC
    LIMIT $per_page OFFSET $offset
");
$users->execute($params);
$users = $users->fetchAll();

$tong_kh      = $pdo->query("SELECT COUNT(*) FROM khach_hang")->fetchColumn();
$tong_bi_khoa = $pdo->query("SELECT COUNT(*) FROM khach_hang WHERE bi_khoa = 1")->fetchColumn();
?>

<div class="page-header">
  <h5><i class="bi bi-people me-2" style="color:#f4a261;"></i>Quản lý người dùng</h5>
  <a href="/nhasach/admin/user_add.php"
     class="btn btn-sm" style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
    <i class="bi bi-plus-lg me-1"></i>Thêm tài khoản
  </a>
</div>

<!-- THỐNG KÊ NHANH -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="admin-card admin-stat-card text-center py-3">
      <div style="font-size:1.6rem; font-weight:700; color:#a66eff;"><?= number_format($tong_kh) ?></div>
      <div style="font-size:.8rem; color:#888;">Tổng khách hàng</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card admin-stat-card text-center py-3">
      <div style="font-size:1.6rem; font-weight:700; color:#e63946;"><?= number_format($tong_bi_khoa) ?></div>
      <div style="font-size:.8rem; color:#888;">Tài khoản bị khoá</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card admin-stat-card text-center py-3">
      <div style="font-size:1.6rem; font-weight:700; color:#3fe0a0;"><?= number_format($tong_kh - $tong_bi_khoa) ?></div>
      <div style="font-size:.8rem; color:#888;">Đang hoạt động</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card admin-stat-card text-center py-3">
      <div style="font-size:1.6rem; font-weight:700; color:#f4a261;">
        <?= $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai = 'cho_xu_ly'")->fetchColumn() ?>
      </div>
      <div style="font-size:.8rem; color:#888;">Đơn chờ xử lý</div>
    </div>
  </div>
</div>

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
  <div class="card-title">Danh sách khách hàng (<?= $total ?> — trang <?= $trang_hien ?>/<?= $total_page ?>)</div>

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
              <a href="/nhasach/admin/orders.php?search=<?= urlencode($u['ho_ten']) ?>"
                 class="badge bg-light text-dark border text-decoration-none">
                <?= $u['so_don'] ?> đơn
              </a>
            </td>
            <td class="text-end" style="font-size:.85rem; font-weight:600;">
              <?= $u['tong_chi'] > 0 ? number_format($u['tong_chi'], 0, ',', '.') . '₫' : '—' ?>
            </td>
            <td>
              <?php if (!$u['bi_khoa']): ?>
                <span class="badge bg-success">Hoạt động</span>
              <?php else: ?>
                <span class="badge bg-danger">Bị khoá</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <!-- Khoá / Mở khoá -->
                <?php if (!$u['bi_khoa']): ?>
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

    <?php if ($total_page > 1): ?>
    <nav class="d-flex justify-content-center mt-3">
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $trang_hien <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['trang' => $trang_hien - 1])) ?>"><i class="bi bi-chevron-left"></i></a>
        </li>
        <?php for ($p = max(1, $trang_hien - 2); $p <= min($total_page, $trang_hien + 2); $p++): ?>
          <li class="page-item <?= $p === $trang_hien ? 'active' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['trang' => $p])) ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $trang_hien >= $total_page ? 'disabled' : '' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['trang' => $trang_hien + 1])) ?>"><i class="bi bi-chevron-right"></i></a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>

  <?php endif; ?>
</div>

<?php
require_once 'includes/admin_footer.php';
ob_end_flush();
?>
