<?php
ob_start();
$page_title = 'Tồn kho & Báo cáo';
require_once 'includes/admin_header.php';

// ---- THỐNG KÊ TỔNG QUAN ----
$tong_sach        = $pdo->query("SELECT COUNT(*) FROM sach WHERE hien_trang = 1")->fetchColumn();
$tong_ton         = $pdo->query("SELECT COALESCE(SUM(so_luong),0) FROM sach WHERE hien_trang = 1")->fetchColumn();
$gia_tri_kho      = $pdo->query("SELECT COALESCE(SUM(so_luong * gia_nhap),0) FROM sach WHERE hien_trang = 1")->fetchColumn();
$so_het_hang      = $pdo->query("SELECT COUNT(*) FROM sach WHERE so_luong = 0 AND hien_trang = 1")->fetchColumn();
$so_sap_het       = $pdo->query("SELECT COUNT(*) FROM sach WHERE so_luong > 0 AND so_luong <= 5 AND hien_trang = 1")->fetchColumn();

// ---- LỌC ----
$filter_search = trim($_GET['search'] ?? '');
$filter_tl     = (int)($_GET['the_loai'] ?? 0);
$filter_tt     = $_GET['trang_thai'] ?? 'tat_ca';

$where  = ["s.hien_trang = 1"];
$params = [];
if ($filter_search !== '') { $where[] = "(s.ten LIKE ? OR s.ma_sach LIKE ?)"; $params[] = "%$filter_search%"; $params[] = "%$filter_search%"; }
if ($filter_tl > 0)        { $where[] = "s.the_loai_id = ?"; $params[] = $filter_tl; }
if ($filter_tt === 'het')  { $where[] = "s.so_luong = 0"; }
if ($filter_tt === 'sap_het') { $where[] = "s.so_luong > 0 AND s.so_luong <= 5"; }
if ($filter_tt === 'con')  { $where[] = "s.so_luong > 5"; }

$where_sql = implode(' AND ', $where);
$sachs = $pdo->prepare("
    SELECT s.id, s.ma_sach, s.ten, s.so_luong, s.gia_nhap, s.ty_le_ln, s.da_nhap_hang,
           tl.ten AS ten_the_loai,
           ROUND(s.gia_nhap * (1 + s.ty_le_ln / 100), 0) AS gia_ban,
           ROUND(s.so_luong * s.gia_nhap, 0) AS gia_tri_ton
    FROM sach s
    JOIN the_loai tl ON tl.id = s.the_loai_id
    WHERE $where_sql
    ORDER BY s.so_luong ASC, s.ten ASC
");
$sachs->execute($params);
$sachs = $sachs->fetchAll();

// ---- BÁO CÁO NHẬP HÀNG THEO THÁNG (6 tháng gần nhất) ----
$nhap_thang = $pdo->query("
    SELECT DATE_FORMAT(pn.ngay_tao, '%Y-%m') AS thang,
           COUNT(DISTINCT pn.id) AS so_phieu,
           SUM(cn.so_luong) AS tong_sl,
           SUM(cn.so_luong * cn.don_gia) AS tong_tien
    FROM phieu_nhap pn
    JOIN chi_tiet_nhap cn ON cn.phieu_nhap_id = pn.id
    WHERE pn.trang_thai = 'done'
      AND pn.ngay_tao >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(pn.ngay_tao, '%Y-%m')
    ORDER BY thang DESC
")->fetchAll();

// ---- BÁO CÁO ĐƠN HÀNG THEO THÁNG (6 tháng) ----
$ban_thang = $pdo->query("
    SELECT DATE_FORMAT(ngay_dat, '%Y-%m') AS thang,
           COUNT(*) AS so_don,
           SUM(tong_tien) AS doanh_thu
    FROM don_hang
    WHERE trang_thai != 'da_huy'
      AND ngay_dat >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(ngay_dat, '%Y-%m')
    ORDER BY thang DESC
")->fetchAll();

// ---- TOP 5 SÁCH BÁN CHẠY ----
$top_ban = $pdo->query("
    SELECT s.ten, s.ma_sach, SUM(ct.so_luong) AS da_ban,
           SUM(ct.so_luong * ct.gia_ban_luc_dat) AS doanh_thu
    FROM chi_tiet_don_hang ct
    JOIN sach s ON s.id = ct.sach_id
    JOIN don_hang dh ON dh.id = ct.don_hang_id
    WHERE dh.trang_thai != 'da_huy'
    GROUP BY ct.sach_id
    ORDER BY da_ban DESC
    LIMIT 5
")->fetchAll();

$the_loais = $pdo->query("SELECT * FROM the_loai WHERE trang_thai = 1 ORDER BY ten")->fetchAll();
?>

<div class="page-header">
  <h5><i class="bi bi-bar-chart me-2" style="color:#f4a261;"></i>Tồn kho & Báo cáo</h5>
</div>

<!-- TỔNG QUAN TỒN KHO -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="admin-card text-center py-3">
      <div style="font-size:1.6rem; font-weight:700; color:#5b9fff;"><?= number_format($tong_sach) ?></div>
      <div style="font-size:.8rem; color:#888;">Đầu sách đang bán</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card text-center py-3">
      <div style="font-size:1.6rem; font-weight:700; color:#3fe0a0;"><?= number_format($tong_ton) ?></div>
      <div style="font-size:.8rem; color:#888;">Tổng tồn kho (quyển)</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card text-center py-3">
      <div style="font-size:1.3rem; font-weight:700; color:#f4a261;"><?= number_format($gia_tri_kho/1000, 0) ?>k₫</div>
      <div style="font-size:.8rem; color:#888;">Giá trị tồn kho (theo giá vốn)</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card text-center py-3">
      <div style="font-size:1.6rem; font-weight:700; color:#e63946;"><?= $so_het_hang ?></div>
      <div style="font-size:.8rem; color:#888;">
        Hết hàng &nbsp;|&nbsp;
        <span style="color:#ff9f1c;"><?= $so_sap_het ?></span> sắp hết
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">

  <!-- BÁO CÁO NHẬP HÀNG -->
  <div class="col-lg-6">
    <div class="admin-card h-100">
      <div class="card-title">Nhập hàng 6 tháng gần nhất</div>
      <?php if (empty($nhap_thang)): ?>
        <p class="text-muted text-center py-3">Chưa có dữ liệu nhập hàng.</p>
      <?php else: ?>
        <table class="table admin-table">
          <thead>
            <tr>
              <th>Tháng</th>
              <th class="text-center">Số phiếu</th>
              <th class="text-end">SL nhập</th>
              <th class="text-end">Tổng chi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($nhap_thang as $nt): ?>
            <tr>
              <td><?= date('m/Y', strtotime($nt['thang'].'-01')) ?></td>
              <td class="text-center"><?= $nt['so_phieu'] ?></td>
              <td class="text-end"><?= number_format($nt['tong_sl']) ?></td>
              <td class="text-end" style="font-weight:600;"><?= number_format($nt['tong_tien']/1000, 0) ?>k₫</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- BÁO CÁO BÁN HÀNG -->
  <div class="col-lg-6">
    <div class="admin-card h-100">
      <div class="card-title">Doanh thu 6 tháng gần nhất</div>
      <?php if (empty($ban_thang)): ?>
        <p class="text-muted text-center py-3">Chưa có dữ liệu đơn hàng.</p>
      <?php else:
        $max_dt = max(array_column($ban_thang, 'doanh_thu') ?: [1]);
      ?>
        <?php foreach ($ban_thang as $bt):
          $pct = $max_dt > 0 ? round($bt['doanh_thu'] / $max_dt * 100) : 0;
        ?>
        <div class="d-flex align-items-center gap-2 mb-2">
          <span style="width:48px; font-size:.78rem; color:#888; flex-shrink:0;">
            <?= date('m/Y', strtotime($bt['thang'].'-01')) ?>
          </span>
          <div style="flex:1; background:#f0f0f0; border-radius:4px; height:22px; overflow:hidden; position:relative;">
            <div style="width:<?= $pct ?>%; background:#f4a261; height:100%; border-radius:4px; transition:width .4s;"></div>
            <span style="position:absolute; left:8px; top:3px; font-size:.72rem; color:#555;">
              <?= $bt['so_don'] ?> đơn
            </span>
          </div>
          <span style="width:80px; font-size:.78rem; color:#444; text-align:right; flex-shrink:0;">
            <?= number_format($bt['doanh_thu']/1000, 0) ?>k₫
          </span>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- TOP SÁCH BÁN CHẠY -->
<?php if (!empty($top_ban)): ?>
<div class="admin-card mb-4">
  <div class="card-title">Top 5 sách bán chạy</div>
  <div class="table-responsive">
    <table class="table admin-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Sách</th>
          <th class="text-center">Đã bán</th>
          <th class="text-end">Doanh thu</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($top_ban as $i => $t): ?>
        <tr>
          <td>
            <?php if ($i === 0): ?>
              <span style="font-size:1.1rem;">🥇</span>
            <?php elseif ($i === 1): ?>
              <span style="font-size:1.1rem;">🥈</span>
            <?php elseif ($i === 2): ?>
              <span style="font-size:1.1rem;">🥉</span>
            <?php else: ?>
              <span style="color:#888;"><?= $i+1 ?></span>
            <?php endif; ?>
          </td>
          <td>
            <div class="fw-semibold" style="font-size:.88rem;"><?= htmlspecialchars($t['ten']) ?></div>
            <small class="text-muted"><?= htmlspecialchars($t['ma_sach']) ?></small>
          </td>
          <td class="text-center"><span class="badge bg-success"><?= number_format($t['da_ban']) ?> quyển</span></td>
          <td class="text-end" style="color:#e63946; font-weight:600;"><?= number_format($t['doanh_thu']/1000, 0) ?>k₫</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- BỘ LỌC TỒN KHO -->
<div class="admin-card mb-3">
  <form method="GET" action="/nhasach/admin/inventory.php" class="row g-2 align-items-end">
    <div class="col-md-3">
      <input type="text" name="search" class="form-control form-control-sm"
             placeholder="Tìm theo tên, mã sách..."
             value="<?= htmlspecialchars($filter_search) ?>">
    </div>
    <div class="col-md-3">
      <select name="the_loai" class="form-select form-select-sm">
        <option value="">-- Tất cả thể loại --</option>
        <?php foreach ($the_loais as $tl): ?>
          <option value="<?= $tl['id'] ?>" <?= $filter_tl == $tl['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($tl['ten']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="trang_thai" class="form-select form-select-sm">
        <option value="tat_ca"  <?= $filter_tt=='tat_ca' ?'selected':'' ?>>Tất cả</option>
        <option value="het"     <?= $filter_tt=='het'    ?'selected':'' ?>>Hết hàng</option>
        <option value="sap_het" <?= $filter_tt=='sap_het'?'selected':'' ?>>Sắp hết (≤5)</option>
        <option value="con"     <?= $filter_tt=='con'    ?'selected':'' ?>>Còn hàng (>5)</option>
      </select>
    </div>
    <div class="col-md-4 d-flex gap-2">
      <button type="submit" class="btn btn-sm btn-primary" style="border-radius:8px;">
        <i class="bi bi-funnel me-1"></i>Lọc
      </button>
      <a href="/nhasach/admin/inventory.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
        Xoá lọc
      </a>
      <a href="/nhasach/admin/import.php" class="btn btn-sm"
         style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
        <i class="bi bi-box-arrow-in-down me-1"></i>Nhập hàng
      </a>
    </div>
  </form>
</div>

<!-- BẢNG TỒN KHO -->
<div class="admin-card">
  <div class="card-title">Bảng tồn kho (<?= count($sachs) ?> sách)</div>

  <?php if (empty($sachs)): ?>
    <p class="text-muted text-center py-4">Không có sách nào.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table admin-table table-hover">
        <thead>
          <tr>
            <th>Sách</th>
            <th>Thể loại</th>
            <th class="text-center">Tồn kho</th>
            <th class="text-end">Giá vốn BQ</th>
            <th class="text-end">Giá bán</th>
            <th class="text-end">Giá trị tồn</th>
            <th>Trạng thái</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sachs as $s): ?>
          <tr>
            <td>
              <div class="fw-semibold" style="font-size:.88rem;"><?= htmlspecialchars($s['ten']) ?></div>
              <small class="text-muted"><?= htmlspecialchars($s['ma_sach']) ?></small>
            </td>
            <td><small><?= htmlspecialchars($s['ten_the_loai']) ?></small></td>
            <td class="text-center">
              <?php if ($s['so_luong'] == 0): ?>
                <span class="badge bg-danger">Hết hàng</span>
              <?php elseif ($s['so_luong'] <= 5): ?>
                <span class="badge bg-warning text-dark"><?= $s['so_luong'] ?></span>
              <?php else: ?>
                <span class="badge bg-success"><?= number_format($s['so_luong']) ?></span>
              <?php endif; ?>
            </td>
            <td class="text-end" style="font-size:.85rem;">
              <?= $s['gia_nhap'] > 0 ? number_format($s['gia_nhap'], 0, ',', '.') . '₫' : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="text-end" style="font-size:.85rem; color:#e63946;">
              <?= $s['gia_ban'] > 0 ? number_format($s['gia_ban'], 0, ',', '.') . '₫' : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="text-end" style="font-size:.85rem; font-weight:600;">
              <?= $s['gia_tri_ton'] > 0 ? number_format($s['gia_tri_ton'], 0, ',', '.') . '₫' : '—' ?>
            </td>
            <td>
              <?php if (!$s['da_nhap_hang']): ?>
                <span class="badge bg-light text-secondary border">Chưa nhập hàng</span>
              <?php elseif ($s['so_luong'] == 0): ?>
                <span class="badge bg-danger">Hết kho</span>
              <?php elseif ($s['so_luong'] <= 5): ?>
                <span class="badge bg-warning text-dark">Sắp hết</span>
              <?php else: ?>
                <span class="badge bg-success">Bình thường</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <?php
          $total_ton   = array_sum(array_column($sachs, 'so_luong'));
          $total_giaTri = array_sum(array_column($sachs, 'gia_tri_ton'));
          ?>
          <tr style="background:#f8f9fa; font-weight:700;">
            <td colspan="2">Tổng cộng (<?= count($sachs) ?> loại)</td>
            <td class="text-center"><?= number_format($total_ton) ?></td>
            <td colspan="2"></td>
            <td class="text-end" style="color:#e63946;"><?= number_format($total_giaTri, 0, ',', '.') ?>₫</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php
require_once 'includes/admin_footer.php';
ob_end_flush();
?>
