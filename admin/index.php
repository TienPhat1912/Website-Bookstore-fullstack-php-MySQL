<?php
ob_start();
$page_title = 'Dashboard';
require_once 'includes/admin_header.php';

// Thống kê tổng quan
$tong_sach     = $pdo->query("SELECT COUNT(*) FROM sach")->fetchColumn();
$tong_don      = $pdo->query("SELECT COUNT(*) FROM don_hang")->fetchColumn();
$don_cho       = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai = 'cho_xu_ly'")->fetchColumn();
$doanh_thu     = $pdo->query("SELECT COALESCE(SUM(tong_tien),0) FROM don_hang WHERE trang_thai != 'da_huy'")->fetchColumn();
$tong_kh       = $pdo->query("SELECT COUNT(*) FROM khach_hang")->fetchColumn();
$het_hang      = $pdo->query("SELECT COUNT(*) FROM sach WHERE so_luong = 0 AND hien_trang = 1")->fetchColumn();
$sap_het       = $pdo->query("SELECT COUNT(*) FROM sach WHERE so_luong > 0 AND so_luong <= 5 AND hien_trang = 1")->fetchColumn();

// 5 đơn hàng mới nhất
$don_moi = $pdo->query("
    SELECT dh.*, kh.ho_ten AS ten_kh
    FROM don_hang dh
    JOIN khach_hang kh ON kh.id = dh.khach_hang_id
    ORDER BY dh.ngay_dat DESC LIMIT 5
")->fetchAll();

// Doanh thu 7 ngày gần nhất
$doanh_thu_7ngay = $pdo->query("
    SELECT DATE(ngay_dat) AS ngay, SUM(tong_tien) AS tong
    FROM don_hang
    WHERE trang_thai != 'da_huy'
      AND ngay_dat >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(ngay_dat)
    ORDER BY ngay ASC
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Sách sắp hết hàng
$sach_sap_het = $pdo->query("
    SELECT id, ten, so_luong FROM sach
    WHERE so_luong <= 5 AND so_luong > 0 AND hien_trang = 1
    ORDER BY so_luong ASC LIMIT 5
")->fetchAll();

$trang_thai_info = [
    'cho_xu_ly'   => ['label' => 'Chờ xử lý',    'class' => 'bg-warning text-dark'],
    'da_xac_nhan' => ['label' => 'Đã xác nhận',   'class' => 'bg-info text-white'],
    'da_giao'     => ['label' => 'Đã giao',        'class' => 'bg-success text-white'],
    'da_huy'      => ['label' => 'Đã huỷ',        'class' => 'bg-danger text-white'],
];
?>

<div class="page-header">
  <h5><i class="bi bi-speedometer2 me-2" style="color:#f4a261;"></i>Dashboard</h5>
  <small class="text-muted"><?= date('d/m/Y H:i') ?></small>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card" style="border-left-color:#5b9fff;">
      <div class="stat-label">Tổng sách</div>
      <div class="stat-number" style="color:#5b9fff;"><?= number_format($tong_sach) ?></div>
      <small class="text-muted"><a href="/nhasach/admin/products.php" style="color:#5b9fff;">Quản lý →</a></small>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card" style="border-left-color:#f4a261;">
      <div class="stat-label">Tổng đơn hàng</div>
      <div class="stat-number" style="color:#f4a261;"><?= number_format($tong_don) ?></div>
      <?php if ($don_cho > 0): ?>
        <small style="color:#e63946;font-weight:600;">⚠ <?= $don_cho ?> đơn chờ xử lý</small>
      <?php else: ?>
        <small class="text-muted">Không có đơn chờ</small>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card" style="border-left-color:#3fe0a0;">
      <div class="stat-label">Doanh thu</div>
      <div class="stat-number" style="color:#3fe0a0; font-size:1.4rem;">
        <?= number_format($doanh_thu, 0, ',', '.') ?>₫
      </div>
      <small class="text-muted">Không tính đơn huỷ</small>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card" style="border-left-color:#a66eff;">
      <div class="stat-label">Khách hàng</div>
      <div class="stat-number" style="color:#a66eff;"><?= number_format($tong_kh) ?></div>
      <small class="text-muted"><a href="/nhasach/admin/users.php" style="color:#a66eff;">Quản lý →</a></small>
    </div>
  </div>
</div>

<!-- CẢNH BÁO TỒN KHO -->
<?php if ($het_hang > 0 || $sap_het > 0): ?>
<div class="alert d-flex align-items-center gap-3 mb-4"
     style="background:#fff8e8; border:1px solid #ffd970; border-radius:12px;">
  <i class="bi bi-exclamation-triangle-fill fs-4" style="color:#f4a261;"></i>
  <div>
    <?php if ($het_hang > 0): ?>
      <strong><?= $het_hang ?> sách đã hết hàng</strong> —
    <?php endif; ?>
    <?php if ($sap_het > 0): ?>
      <strong><?= $sap_het ?> sách sắp hết hàng</strong>
    <?php endif; ?>
    &nbsp;<a href="/nhasach/admin/inventory.php" style="color:#f4a261;">Xem tồn kho →</a>
  </div>
</div>
<?php endif; ?>

<div class="row g-4">

  <!-- ĐƠN HÀNG MỚI NHẤT -->
  <div class="col-lg-7">
    <div class="admin-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="card-title mb-0">Đơn hàng mới nhất</div>
        <a href="/nhasach/admin/orders.php" style="font-size:.82rem; color:#f4a261;">Xem tất cả →</a>
      </div>
      <?php if (empty($don_moi)): ?>
        <p class="text-muted text-center py-3">Chưa có đơn hàng nào.</p>
      <?php else: ?>
        <table class="table admin-table table-hover mb-0">
          <thead>
            <tr>
              <th>Mã đơn</th>
              <th>Khách hàng</th>
              <th>Tổng tiền</th>
              <th>Trạng thái</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($don_moi as $don): ?>
              <tr>
                <td><strong><?= htmlspecialchars($don['ma_don']) ?></strong><br>
                  <small class="text-muted"><?= date('d/m H:i', strtotime($don['ngay_dat'])) ?></small>
                </td>
                <td><?= htmlspecialchars($don['ten_kh']) ?></td>
                <td style="color:#e63946; font-weight:600;">
                  <?= number_format($don['tong_tien'], 0, ',', '.') ?>₫
                </td>
                <td>
                  <span class="badge <?= $trang_thai_info[$don['trang_thai']]['class'] ?>">
                    <?= $trang_thai_info[$don['trang_thai']]['label'] ?>
                  </span>
                </td>
                <td>
                  <a href="/nhasach/admin/orders.php?id=<?= $don['id'] ?>"
                     class="btn btn-sm btn-outline-secondary" style="border-radius:20px; font-size:.75rem;">
                    Xem
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- SÁCH SẮP HẾT + DOANH THU 7 NGÀY -->
  <div class="col-lg-5">

    <!-- Doanh thu 7 ngày -->
    <div class="admin-card mb-4">
      <div class="card-title">Doanh thu 7 ngày gần nhất</div>
      <?php
      $max_dt = max(array_values($doanh_thu_7ngay) ?: [1]);
      for ($i = 6; $i >= 0; $i--):
        $ngay = date('Y-m-d', strtotime("-$i days"));
        $nhan = date('d/m', strtotime("-$i days"));
        $dt   = $doanh_thu_7ngay[$ngay] ?? 0;
        $pct  = $max_dt > 0 ? round($dt / $max_dt * 100) : 0;
      ?>
        <div class="d-flex align-items-center gap-2 mb-2">
          <span style="width:36px; font-size:.75rem; color:#888; flex-shrink:0;"><?= $nhan ?></span>
          <div style="flex:1; background:#f0f0f0; border-radius:4px; height:18px; overflow:hidden;">
            <div style="width:<?= $pct ?>%; background:#f4a261; height:100%; border-radius:4px; transition:width .3s;"></div>
          </div>
          <span style="width:90px; font-size:.75rem; color:#444; text-align:right; flex-shrink:0;">
            <?= $dt > 0 ? number_format($dt/1000, 0).'k₫' : '—' ?>
          </span>
        </div>
      <?php endfor; ?>
    </div>

    <!-- Sách sắp hết -->
    <?php if (!empty($sach_sap_het)): ?>
    <div class="admin-card">
      <div class="card-title">⚠ Sách sắp hết hàng</div>
      <?php foreach ($sach_sap_het as $s): ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <a href="/nhasach/admin/products.php?edit=<?= $s['id'] ?>"
             class="text-truncate" style="color:#1a1a2e; font-size:.85rem; max-width:160px;">
            <?= htmlspecialchars($s['ten']) ?>
          </a>
          <span class="badge <?= $s['so_luong'] == 0 ? 'bg-danger' : 'bg-warning text-dark' ?>">
            Còn <?= $s['so_luong'] ?>
          </span>
        </div>
      <?php endforeach; ?>
      <a href="/nhasach/admin/import.php" class="btn btn-sm w-100 mt-2"
         style="background:#f4a261; color:#fff; border-radius:8px; font-size:.82rem; border:none;">
        <i class="bi bi-box-arrow-in-down me-1"></i>Tạo phiếu nhập hàng
      </a>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php
require_once 'includes/admin_footer.php';
ob_end_flush();
?>