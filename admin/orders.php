<?php
// ---- AJAX: CẬP NHẬT TRẠNG THÁI (phải đứng trước ob_start và header) ----
if (isset($_GET['action']) && isset($_GET['id']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['admin_id'])) { echo json_encode(['ok'=>false,'msg'=>'Chưa đăng nhập.','trang_thai'=>'']); exit; }
    require_once dirname(__DIR__) . '/config/database.php';

    $id     = (int)$_GET['id'];
    $act    = $_GET['action'];
    $new_tt = null;

    if ($act === 'xac_nhan') $new_tt = 'da_xac_nhan';
    if ($act === 'da_giao')  $new_tt = 'da_giao';
    if ($act === 'huy')      $new_tt = 'da_huy';

    $result = ['ok' => false, 'msg' => 'Hành động không hợp lệ.', 'trang_thai' => ''];

    if ($new_tt) {
        $don = $pdo->prepare("SELECT trang_thai FROM don_hang WHERE id = ?");
        $don->execute([$id]);
        $don = $don->fetch();

        if ($don) {
            $tt_cu = $don['trang_thai'];
            if ($new_tt === 'da_huy') {
                if (in_array($tt_cu, ['cho_xu_ly', 'da_xac_nhan'])) {
                    $ct = $pdo->prepare("SELECT sach_id, so_luong FROM chi_tiet_don_hang WHERE don_hang_id = ?");
                    $ct->execute([$id]);
                    foreach ($ct->fetchAll() as $row) {
                        $pdo->prepare("UPDATE sach SET so_luong = so_luong + ? WHERE id = ?")
                            ->execute([$row['so_luong'], $row['sach_id']]);
                    }
                    $pdo->prepare("UPDATE don_hang SET trang_thai = ? WHERE id = ?")->execute([$new_tt, $id]);
                    $result = ['ok' => true, 'msg' => 'Đơn đã huỷ. Tồn kho đã hoàn lại.', 'trang_thai' => $new_tt];
                } else {
                    $result = ['ok' => false, 'msg' => 'Không thể huỷ đơn đã giao.', 'trang_thai' => $tt_cu];
                }
            } else {
                $pdo->prepare("UPDATE don_hang SET trang_thai = ? WHERE id = ?")->execute([$new_tt, $id]);
                $result = ['ok' => true, 'msg' => 'Đã cập nhật trạng thái.', 'trang_thai' => $new_tt];
            }
        } else {
            $result['msg'] = 'Không tìm thấy đơn hàng.';
        }
    }

    if (ob_get_level() > 0) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

ob_start();
$page_title = 'Đơn hàng';
require_once 'includes/admin_header.php';

$trang_thai_info = [
    'cho_xu_ly'   => ['label' => 'Chờ xử lý',   'class' => 'bg-warning text-dark', 'icon' => 'bi-hourglass-split'],
    'da_xac_nhan' => ['label' => 'Đã xác nhận',  'class' => 'bg-info text-white',   'icon' => 'bi-check-circle'],
    'da_giao'     => ['label' => 'Đã giao',       'class' => 'bg-success text-white','icon' => 'bi-truck'],
    'da_huy'      => ['label' => 'Đã huỷ',       'class' => 'bg-danger text-white', 'icon' => 'bi-x-circle'],
];

// ---- CẬP NHẬT TRẠNG THÁI (non-AJAX fallback) ----
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id     = (int)$_GET['id'];
    $act    = $_GET['action'];
    $new_tt = null;

    if ($act === 'xac_nhan') $new_tt = 'da_xac_nhan';
    if ($act === 'da_giao')  $new_tt = 'da_giao';
    if ($act === 'huy')      $new_tt = 'da_huy';

    if ($new_tt) {
        $don = $pdo->prepare("SELECT trang_thai FROM don_hang WHERE id = ?");
        $don->execute([$id]);
        $don = $don->fetch();
        if ($don) {
            $tt_cu = $don['trang_thai'];
            if ($new_tt === 'da_huy') {
                if (in_array($tt_cu, ['cho_xu_ly', 'da_xac_nhan'])) {
                    $ct = $pdo->prepare("SELECT sach_id, so_luong FROM chi_tiet_don_hang WHERE don_hang_id = ?");
                    $ct->execute([$id]);
                    foreach ($ct->fetchAll() as $row) {
                        $pdo->prepare("UPDATE sach SET so_luong = so_luong + ? WHERE id = ?")
                            ->execute([$row['so_luong'], $row['sach_id']]);
                    }
                    $pdo->prepare("UPDATE don_hang SET trang_thai = ? WHERE id = ?")->execute([$new_tt, $id]);
                    $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Đơn đã huỷ. Tồn kho đã hoàn lại.'];
                } else {
                    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Không thể huỷ đơn đã giao.'];
                }
            } else {
                $pdo->prepare("UPDATE don_hang SET trang_thai = ? WHERE id = ?")->execute([$new_tt, $id]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã cập nhật trạng thái.'];
            }
        }
    }
    $redirect = isset($_GET['from_detail']) ? "/nhasach/admin/orders.php?id=$id" : "/nhasach/admin/orders.php";
    header("Location: $redirect");
    exit;
}

// ---- XEM CHI TIẾT ĐƠN ----
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $don = $pdo->prepare("
        SELECT dh.*, kh.ho_ten, kh.email, kh.so_dien_thoai
        FROM don_hang dh
        JOIN khach_hang kh ON kh.id = dh.khach_hang_id
        WHERE dh.id = ?
    ");
    $don->execute([$id]);
    $don = $don->fetch();

    if (!$don) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Không tìm thấy đơn hàng.'];
        header('Location: /nhasach/admin/orders.php');
        exit;
    }

    $items = $pdo->prepare("
        SELECT ct.*, s.ten AS ten_sach, s.ma_sach, s.hinh
        FROM chi_tiet_don_hang ct
        JOIN sach s ON s.id = ct.sach_id
        WHERE ct.don_hang_id = ?
    ");
    $items->execute([$id]);
    $items = $items->fetchAll();

    // Trang chi tiết
    ?>
    <div class="page-header">
      <h5>
        <i class="bi bi-bag-check me-2" style="color:#f4a261;"></i>
        Chi tiết đơn: <strong><?= htmlspecialchars($don['ma_don']) ?></strong>
      </h5>
      <a href="/nhasach/admin/orders.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
      </a>
    </div>

    <div class="row g-4">
      <div class="col-lg-4">

        <!-- Thông tin khách hàng -->
        <div class="admin-card mb-4">
          <div class="card-title">Khách hàng</div>
          <p class="mb-1 fw-semibold"><?= htmlspecialchars($don['ho_ten']) ?></p>
          <p class="mb-1" style="font-size:.85rem;">📧 <?= htmlspecialchars($don['email']) ?></p>
          <p class="mb-0" style="font-size:.85rem;">📞 <?= htmlspecialchars($don['so_dien_thoai'] ?? '—') ?></p>
        </div>

        <!-- Thông tin giao hàng -->
        <div class="admin-card mb-4">
          <div class="card-title">Địa chỉ giao hàng</div>
          <p class="mb-0" style="font-size:.88rem; line-height:1.7;">
            <?= htmlspecialchars($don['dia_chi']) ?>
            <?php if(!empty($don['phuong_xa'])): ?>, <?= htmlspecialchars($don['phuong_xa']) ?><?php endif; ?>
            <?php if(!empty($don['tinh_tp'])): ?>, <?= htmlspecialchars($don['tinh_tp']) ?><?php endif; ?>
          </p>
        </div>

        <!-- Thanh toán & trạng thái -->
        <div class="admin-card mb-4">
          <div class="card-title">Thanh toán & Trạng thái</div>
          <div class="mb-2">
            <span class="text-muted" style="font-size:.82rem;">Thanh toán:</span><br>
            <strong><?= htmlspecialchars($don['phuong_thuc_tt'] ?? '—') ?></strong>
          </div>
          <div class="mb-3">
            <span class="text-muted" style="font-size:.82rem;">Trạng thái:</span><br>
            <span class="badge <?= $trang_thai_info[$don['trang_thai']]['class'] ?> mt-1">
              <i class="bi <?= $trang_thai_info[$don['trang_thai']]['icon'] ?> me-1"></i>
              <?= $trang_thai_info[$don['trang_thai']]['label'] ?>
            </span>
          </div>

          <!-- Nút hành động -->
          <div class="d-flex flex-column gap-2" id="don-actions-<?= $don['id'] ?>">
            <?php if ($don['trang_thai'] === 'cho_xu_ly'): ?>
              <button type="button" class="btn btn-sm btn-info text-white" style="border-radius:8px;"
                      onclick="doAction(<?= $don['id'] ?>, 'xac_nhan', this)">
                <i class="bi bi-check-circle me-1"></i>Xác nhận đơn
              </button>
              <button type="button" class="btn btn-sm btn-outline-danger" style="border-radius:8px;"
                      onclick="doAction(<?= $don['id'] ?>, 'huy', this, true)">
                <i class="bi bi-x-circle me-1"></i>Huỷ đơn
              </button>
            <?php elseif ($don['trang_thai'] === 'da_xac_nhan'): ?>
              <button type="button" class="btn btn-sm btn-success" style="border-radius:8px;"
                      onclick="doAction(<?= $don['id'] ?>, 'da_giao', this)">
                <i class="bi bi-truck me-1"></i>Đánh dấu đã giao
              </button>
              <button type="button" class="btn btn-sm btn-outline-danger" style="border-radius:8px;"
                      onclick="doAction(<?= $don['id'] ?>, 'huy', this, true)">
                <i class="bi bi-x-circle me-1"></i>Huỷ đơn
              </button>
            <?php endif; ?>
          </div>
        </div>

        <!-- Thời gian -->
        <div class="admin-card">
          <div class="card-title">Thời gian</div>
          <p style="font-size:.84rem; margin-bottom:4px;">
            <span class="text-muted">Đặt hàng:</span><br>
            <?= date('d/m/Y H:i', strtotime($don['ngay_dat'])) ?>
          </p>
          <?php if (!empty($don['ghi_chu'])): ?>
          <p class="mt-2" style="font-size:.84rem;">
            <span class="text-muted">Ghi chú:</span><br>
            <?= htmlspecialchars($don['ghi_chu']) ?>
          </p>
          <?php endif; ?>
        </div>

      </div>

      <div class="col-lg-8">
        <div class="admin-card">
          <div class="card-title">Sản phẩm trong đơn (<?= count($items) ?>)</div>

          <div class="table-responsive">
            <table class="table admin-table">
              <thead>
                <tr>
                  <th style="width:50px;"></th>
                  <th>Sách</th>
                  <th class="text-center">SL</th>
                  <th class="text-end">Đơn giá</th>
                  <th class="text-end">Thành tiền</th>
                </tr>
              </thead>
              <tbody>
                <?php $tong = 0; foreach ($items as $item): $tt = $item['so_luong'] * $item['gia_ban_luc_dat']; $tong += $tt; ?>
                <tr>
                  <td>
                    <?php if (!empty($item['hinh']) && file_exists('../uploads/'.$item['hinh'])): ?>
                      <img src="/nhasach/uploads/<?= htmlspecialchars($item['hinh']) ?>"
                           style="width:38px;height:48px;object-fit:cover;border-radius:4px;">
                    <?php else: ?>
                      <div style="width:38px;height:48px;background:#f0f0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:.7rem;"><i class="bi bi-book"></i></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="fw-semibold" style="font-size:.88rem;"><?= htmlspecialchars($item['ten_sach']) ?></div>
                    <small class="text-muted"><?= htmlspecialchars($item['ma_sach']) ?></small>
                  </td>
                  <td class="text-center"><?= $item['so_luong'] ?></td>
                  <td class="text-end"><?= number_format($item['gia_ban_luc_dat'], 0, ',', '.') ?>₫</td>
                  <td class="text-end" style="font-weight:600;"><?= number_format($tt, 0, ',', '.') ?>₫</td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="4" class="text-end fw-bold">Tổng cộng:</td>
                  <td class="text-end" style="color:#e63946; font-weight:700; font-size:1.05rem;">
                    <?= number_format($don['tong_tien'], 0, ',', '.') ?>₫
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php
    require_once 'includes/admin_footer.php';
    ob_end_flush();
    exit;
}

// ============================================================
// DANH SÁCH ĐƠN HÀNG
// ============================================================
$filter_tt    = $_GET['trang_thai'] ?? 'tat_ca';
$filter_tu    = $_GET['tu_ngay']   ?? '';
$filter_den   = $_GET['den_ngay']  ?? '';
$filter_phuong= $_GET['phuong']    ?? '';
$filter_search= trim($_GET['search'] ?? '');
$per_page     = 20;
$trang_hien   = max(1, (int)($_GET['trang'] ?? 1));

$where  = ["1=1"];
$params = [];
if ($filter_tt !== 'tat_ca')    { $where[] = "dh.trang_thai = ?"; $params[] = $filter_tt; }
if ($filter_tu !== '')           { $where[] = "DATE(dh.ngay_dat) >= ?"; $params[] = $filter_tu; }
if ($filter_den !== '')          { $where[] = "DATE(dh.ngay_dat) <= ?"; $params[] = $filter_den; }
if ($filter_phuong !== '')       { $where[] = "dh.phuong_xa LIKE ?"; $params[] = "%$filter_phuong%"; }
if ($filter_search !== '')       { $where[] = "(dh.ma_don LIKE ? OR kh.ho_ten LIKE ?)"; $params[] = "%$filter_search%"; $params[] = "%$filter_search%"; }

$sort = in_array($_GET['sort'] ?? '', ['phuong']) ? 'dh.phuong_xa ASC' : 'dh.ngay_dat DESC';

$where_sql = implode(' AND ', $where);

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM don_hang dh JOIN khach_hang kh ON kh.id = dh.khach_hang_id WHERE $where_sql");
$count_stmt->execute($params);
$total      = (int)$count_stmt->fetchColumn();
$total_page = max(1, ceil($total / $per_page));
$trang_hien = min($trang_hien, $total_page);
$offset     = ($trang_hien - 1) * $per_page;

$don_hangs = $pdo->prepare("
    SELECT dh.id, dh.ma_don, dh.ngay_dat, dh.tong_tien, dh.trang_thai,
           CONCAT(dh.dia_chi, ', ', COALESCE(dh.phuong_xa,''), ', ', COALESCE(dh.tinh_tp,'')) AS dia_chi_giao, dh.phuong_thuc_tt,
           kh.ho_ten, kh.email
    FROM don_hang dh
    JOIN khach_hang kh ON kh.id = dh.khach_hang_id
    WHERE $where_sql
    ORDER BY $sort
    LIMIT $per_page OFFSET $offset
");
$don_hangs->execute($params);
$don_hangs = $don_hangs->fetchAll();

// Thống kê nhanh
$stats = $pdo->query("
    SELECT trang_thai, COUNT(*) AS cnt, COALESCE(SUM(tong_tien),0) AS tong
    FROM don_hang GROUP BY trang_thai
")->fetchAll();
$stats_map = [];
foreach ($stats as $st) $stats_map[$st['trang_thai']] = $st;
?>

<div class="page-header">
  <h5><i class="bi bi-bag-check me-2" style="color:#f4a261;"></i>Quản lý đơn hàng</h5>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
  <?php foreach ($trang_thai_info as $key => $info):
    $cnt  = $stats_map[$key]['cnt']  ?? 0;
    $tong = $stats_map[$key]['tong'] ?? 0;
  ?>
  <div class="col-6 col-lg-3">
    <a href="/nhasach/admin/orders.php?trang_thai=<?= $key ?>" class="text-decoration-none">
      <div class="admin-card text-center py-3" style="cursor:pointer; <?= $filter_tt === $key ? 'border:2px solid #f4a261;' : '' ?>">
        <div style="font-size:1.5rem; font-weight:700;"><?= $cnt ?></div>
        <div><span class="badge <?= $info['class'] ?>"><?= $info['label'] ?></span></div>
        <div style="font-size:.78rem; color:#888; margin-top:4px;">
          <?= $tong > 0 ? number_format($tong/1000, 0) . 'k₫' : '' ?>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- BỘ LỌC -->
<div class="admin-card mb-3">
  <form method="GET" action="/nhasach/admin/orders.php" class="row g-2 align-items-end">
    <div class="col-md-3">
      <input type="text" name="search" class="form-control form-control-sm"
             placeholder="Mã đơn, tên khách..."
             value="<?= htmlspecialchars($filter_search) ?>">
    </div>
    <div class="col-md-2">
      <select name="trang_thai" class="form-select form-select-sm">
        <option value="tat_ca" <?= $filter_tt=='tat_ca'?'selected':'' ?>>Tất cả</option>
        <?php foreach ($trang_thai_info as $k => $v): ?>
          <option value="<?= $k ?>" <?= $filter_tt===$k?'selected':'' ?>><?= $v['label'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <input type="date" name="tu_ngay" class="form-control form-control-sm"
             value="<?= htmlspecialchars($filter_tu) ?>" title="Từ ngày">
    </div>
    <div class="col-md-2">
      <input type="date" name="den_ngay" class="form-control form-control-sm"
             value="<?= htmlspecialchars($filter_den) ?>" title="Đến ngày">
    </div>
    <div class="col-md-2">
      <input type="text" name="phuong" class="form-control form-control-sm"
             placeholder="Lọc theo phường..."
             value="<?= htmlspecialchars($filter_phuong) ?>">
    </div>
    <div class="col-md-1 d-flex gap-1">
      <button type="submit" class="btn btn-sm btn-primary" style="border-radius:8px;">
        <i class="bi bi-funnel"></i>
      </button>
      <a href="/nhasach/admin/orders.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
        <i class="bi bi-x"></i>
      </a>
    </div>
  </form>

  <!-- Sắp xếp theo địa chỉ -->
  <?php if ($filter_tt !== 'tat_ca'): ?>
  <div class="mt-2">
    <a href="/nhasach/admin/orders.php?trang_thai=<?= $filter_tt ?>&sort=phuong"
       class="btn btn-sm btn-outline-secondary" style="border-radius:8px; font-size:.8rem;">
      <i class="bi bi-geo-alt me-1"></i>Sắp xếp theo địa chỉ (phường)
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- BẢNG ĐƠN HÀNG -->
<div class="admin-card">
  <div class="card-title">Danh sách đơn hàng (<?= $total ?> — trang <?= $trang_hien ?>/<?= $total_page ?>)</div>

  <?php if (empty($don_hangs)): ?>
    <p class="text-muted text-center py-4">Không có đơn hàng nào.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table admin-table table-hover">
        <thead>
          <tr>
            <th>Mã đơn</th>
            <th>Khách hàng</th>
            <th>Địa chỉ giao</th>
            <th>Thanh toán</th>
            <th class="text-end">Tổng tiền</th>
            <th>Trạng thái</th>
            <th style="width:130px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($don_hangs as $don): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($don['ma_don']) ?></strong><br>
              <small class="text-muted"><?= date('d/m/Y H:i', strtotime($don['ngay_dat'])) ?></small>
            </td>
            <td>
              <div style="font-size:.88rem;"><?= htmlspecialchars($don['ho_ten']) ?></div>
              <small class="text-muted"><?= htmlspecialchars($don['email']) ?></small>
            </td>
            <td style="font-size:.82rem; max-width:180px;" class="text-truncate" title="<?= htmlspecialchars($don['dia_chi_giao']) ?>">
              <?= htmlspecialchars($don['dia_chi_giao']) ?>
            </td>
            <td style="font-size:.82rem;"><?= htmlspecialchars($don['phuong_thuc_tt'] ?? '—') ?></td>
            <td class="text-end" style="color:#e63946; font-weight:600;">
              <?= number_format($don['tong_tien'], 0, ',', '.') ?>₫
            </td>
            <td>
              <span class="badge <?= $trang_thai_info[$don['trang_thai']]['class'] ?>">
                <?= $trang_thai_info[$don['trang_thai']]['label'] ?>
              </span>
            </td>
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <a href="/nhasach/admin/orders.php?id=<?= $don['id'] ?>"
                   class="btn btn-sm btn-outline-primary" style="border-radius:6px;" title="Xem chi tiết">
                  <i class="bi bi-eye"></i>
                </a>
                <?php if ($don['trang_thai'] === 'cho_xu_ly'): ?>
                  <button type="button" class="btn btn-sm btn-outline-info" style="border-radius:6px;" title="Xác nhận"
                          onclick="doAction(<?= $don['id'] ?>, 'xac_nhan', this)">
                    <i class="bi bi-check-circle"></i>
                  </button>
                <?php elseif ($don['trang_thai'] === 'da_xac_nhan'): ?>
                  <button type="button" class="btn btn-sm btn-outline-success" style="border-radius:6px;" title="Đã giao"
                          onclick="doAction(<?= $don['id'] ?>, 'da_giao', this)">
                    <i class="bi bi-truck"></i>
                  </button>
                <?php endif; ?>
                <?php if (in_array($don['trang_thai'], ['cho_xu_ly','da_xac_nhan'])): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger" style="border-radius:6px;" title="Huỷ"
                          onclick="doAction(<?= $don['id'] ?>, 'huy', this, true)">
                    <i class="bi bi-x-circle"></i>
                  </button>
                <?php endif; ?>
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

<!-- Toast thông báo -->
<div id="orderToast" style="
  position:fixed; bottom:24px; right:24px; z-index:9999;
  background:#1a1a2e; color:#fff; padding:12px 20px; border-radius:10px;
  box-shadow:0 4px 16px rgba(0,0,0,.25); font-size:.88rem;
  display:none; align-items:center; gap:10px; min-width:220px;
">
  <i id="orderToastIcon" class="bi"></i>
  <span id="orderToastMsg"></span>
</div>

<script>
var ttInfo = {
  cho_xu_ly:   { label: 'Chờ xử lý',   cls: 'bg-warning text-dark' },
  da_xac_nhan: { label: 'Đã xác nhận',  cls: 'bg-info text-white'   },
  da_giao:     { label: 'Đã giao',       cls: 'bg-success text-white'},
  da_huy:      { label: 'Đã huỷ',       cls: 'bg-danger text-white' }
};

function showToast(msg, ok) {
  var t = document.getElementById('orderToast');
  var icon = document.getElementById('orderToastIcon');
  var text = document.getElementById('orderToastMsg');
  icon.className = 'bi ' + (ok ? 'bi-check-circle-fill text-success' : 'bi-exclamation-circle-fill text-danger');
  text.textContent = msg;
  t.style.display = 'flex';
  clearTimeout(t._timer);
  t._timer = setTimeout(function() { t.style.display = 'none'; }, 3000);
}

function doAction(id, action, btn, confirm_huy) {
  if (confirm_huy && !confirm('Huỷ đơn hàng này?')) return;

  btn.disabled = true;
  var orig = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

  fetch('/nhasach/admin/orders.php?action=' + action + '&id=' + id, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(function(r) {
    return r.text().then(function(text) {
      try {
        return JSON.parse(text);
      } catch(e) {
        console.error('Response không phải JSON:', text);
        throw new Error('invalid json');
      }
    });
  })
  .then(function(data) {
    showToast(data.msg, data.ok);
    if (data.ok) {
      var tt = data.trang_thai;
      var info = ttInfo[tt] || { label: tt, cls: 'bg-secondary' };

      // Cập nhật badge trạng thái trong hàng
      var row = btn.closest('tr');
      if (row) {
        var badge = row.querySelector('.badge');
        if (badge) { badge.className = 'badge ' + info.cls; badge.textContent = info.label; }

        // Ẩn/hiện nút hành động
        var actions = row.querySelector('.d-flex.gap-1');
        if (actions) {
          // Giữ nút xem, xoá nút action cũ
          var btns = actions.querySelectorAll('button');
          btns.forEach(function(b) { b.remove(); });

          if (tt === 'da_xac_nhan') {
            actions.insertAdjacentHTML('beforeend',
              '<button type="button" class="btn btn-sm btn-outline-success" style="border-radius:6px;" title="Đã giao" onclick="doAction(' + id + ',\'da_giao\',this)">' +
              '<i class="bi bi-truck"></i></button>' +
              '<button type="button" class="btn btn-sm btn-outline-danger" style="border-radius:6px;" title="Huỷ" onclick="doAction(' + id + ',\'huy\',this,true)">' +
              '<i class="bi bi-x-circle"></i></button>');
          }
          // da_giao và da_huy: không có nút action
        }
      }

      // Nếu đang ở trang chi tiết, cập nhật khu vực nút
      var actDiv = document.getElementById('don-actions-' + id);
      if (actDiv) {
        actDiv.innerHTML = '';
        if (tt === 'da_xac_nhan') {
          actDiv.innerHTML =
            '<button type="button" class="btn btn-sm btn-success" style="border-radius:8px;" onclick="doAction(' + id + ',\'da_giao\',this)">' +
            '<i class="bi bi-truck me-1"></i>Đánh dấu đã giao</button>' +
            '<button type="button" class="btn btn-sm btn-outline-danger" style="border-radius:8px;" onclick="doAction(' + id + ',\'huy\',this,true)">' +
            '<i class="bi bi-x-circle me-1"></i>Huỷ đơn</button>';
        }
        // Cập nhật badge chi tiết
        var detailBadge = document.querySelector('.badge[class*="bg-"]');
        if (detailBadge) { detailBadge.className = 'badge ' + info.cls + ' mt-1'; detailBadge.textContent = info.label; }
      }
    } else {
      btn.disabled = false;
      btn.innerHTML = orig;
    }
  })
  .catch(function() {
    btn.disabled = false;
    btn.innerHTML = orig;
    showToast('Lỗi kết nối, thử lại.', false);
  });
}
</script>

<?php
require_once 'includes/admin_footer.php';
ob_end_flush();
?>