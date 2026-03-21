<?php
ob_start();
$page_title = 'Báo cáo';
require_once 'includes/admin_header.php';

$filter_tu  = trim($_GET['tu']  ?? date('Y-m-01'));
$filter_den = trim($_GET['den'] ?? date('Y-m-d'));

// ---- NHẬP HÀNG THEO THÁNG ----
$nhap_thang = $pdo->query("
    SELECT DATE_FORMAT(pn.ngay_nhap, '%Y-%m') AS thang,
           COUNT(DISTINCT pn.id) AS so_phieu,
           SUM(cn.so_luong) AS tong_sl,
           SUM(cn.so_luong * cn.don_gia) AS tong_tien
    FROM phieu_nhap pn
    JOIN chi_tiet_nhap cn ON cn.phieu_nhap_id = pn.id
    WHERE pn.trang_thai = 'done'
      AND pn.ngay_nhap >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
      AND pn.ngay_nhap != '0000-00-00'
    GROUP BY DATE_FORMAT(pn.ngay_nhap, '%Y-%m')
    ORDER BY thang DESC
")->fetchAll();

// ---- DOANH THU THEO THÁNG ----
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

// ---- TOP 10 SÁCH BÁN CHẠY ----
$top_ban = $pdo->query("
    SELECT s.ten, s.ma_sach, s.don_vi_tinh,
           SUM(ct.so_luong) AS da_ban,
           SUM(ct.so_luong * ct.gia_ban_luc_dat) AS doanh_thu
    FROM chi_tiet_don_hang ct
    JOIN sach s ON s.id = ct.sach_id
    JOIN don_hang dh ON dh.id = ct.don_hang_id
    WHERE dh.trang_thai != 'da_huy'
    GROUP BY ct.sach_id
    ORDER BY da_ban DESC
    LIMIT 10
")->fetchAll();

// ---- TOP 5 SÁCH TỒN NHIỀU NHẤT ----
$top_ton = $pdo->query("
    SELECT s.ten, s.ma_sach, s.so_luong, s.gia_nhap, s.don_vi_tinh,
           ROUND(s.so_luong * s.gia_nhap, 0) AS gia_tri
    FROM sach s
    WHERE s.hien_trang = 1 AND s.so_luong > 0
    ORDER BY gia_tri DESC
    LIMIT 5
")->fetchAll();

// ---- THỐNG KÊ TỔNG ----
$doanh_thu_thang = $pdo->prepare("
    SELECT COALESCE(SUM(tong_tien),0) FROM don_hang
    WHERE trang_thai != 'da_huy'
      AND DATE_FORMAT(ngay_dat,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')
");
$doanh_thu_thang->execute();
$doanh_thu_thang = $doanh_thu_thang->fetchColumn();

$chi_nhap_thang = $pdo->query("
    SELECT COALESCE(SUM(cn.so_luong * cn.don_gia),0)
    FROM chi_tiet_nhap cn JOIN phieu_nhap pn ON pn.id = cn.phieu_nhap_id
    WHERE pn.trang_thai = 'done'
      AND DATE_FORMAT(pn.ngay_nhap,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')
")->fetchColumn();
?>

<div class="page-header">
  <h5><i class="bi bi-bar-chart me-2" style="color:#f4a261;"></i>Báo cáo</h5>
  <a href="/nhasach/admin/inventory.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
    <i class="bi bi-boxes me-1"></i>Về tồn kho
  </a>
</div>

<!-- TỔNG QUAN THÁNG NÀY -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="admin-card text-center py-3">
      <div style="font-size:1.3rem;font-weight:700;color:#3fe0a0;"><?= number_format($doanh_thu_thang/1000000, 1) ?>tr₫</div>
      <div style="font-size:.8rem;color:#888;">Doanh thu tháng này</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card text-center py-3">
      <div style="font-size:1.3rem;font-weight:700;color:#e63946;"><?= number_format($chi_nhap_thang/1000000, 1) ?>tr₫</div>
      <div style="font-size:.8rem;color:#888;">Chi nhập hàng tháng này</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card text-center py-3">
      <?php $loi_nhuan = $doanh_thu_thang - $chi_nhap_thang; ?>
      <div style="font-size:1.3rem;font-weight:700;color:<?= $loi_nhuan >= 0 ? '#3fe0a0' : '#e63946' ?>;">
        <?= ($loi_nhuan >= 0 ? '+' : '') . number_format($loi_nhuan/1000000, 1) ?>tr₫
      </div>
      <div style="font-size:.8rem;color:#888;">Lợi nhuận gộp tháng này</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card text-center py-3">
      <div style="font-size:1.3rem;font-weight:700;color:#5b9fff;">
        <?= $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai != 'da_huy' AND DATE_FORMAT(ngay_dat,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')")->fetchColumn() ?>
      </div>
      <div style="font-size:.8rem;color:#888;">Đơn hàng tháng này</div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">

  <!-- NHẬP HÀNG -->
  <div class="col-lg-6">
    <div class="admin-card h-100">
      <div class="card-title">Nhập hàng 6 tháng gần nhất</div>
      <?php if (empty($nhap_thang)): ?>
        <p class="text-muted text-center py-3">Chưa có dữ liệu.</p>
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
              <td class="text-end fw-semibold"><?= number_format($nt['tong_tien']/1000000, 2) ?>tr₫</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- DOANH THU -->
  <div class="col-lg-6">
    <div class="admin-card h-100">
      <div class="card-title">Doanh thu 6 tháng gần nhất</div>
      <?php if (empty($ban_thang)): ?>
        <p class="text-muted text-center py-3">Chưa có dữ liệu.</p>
      <?php else:
        $max_dt = max(array_column($ban_thang, 'doanh_thu') ?: [1]);
      ?>
        <?php foreach ($ban_thang as $bt):
          $pct = $max_dt > 0 ? round($bt['doanh_thu'] / $max_dt * 100) : 0;
        ?>
        <div class="d-flex align-items-center gap-2 mb-2">
          <span style="width:52px;font-size:.78rem;color:#888;flex-shrink:0;">
            <?= date('m/Y', strtotime($bt['thang'].'-01')) ?>
          </span>
          <div style="flex:1;background:#f0f0f0;border-radius:4px;height:22px;overflow:hidden;position:relative;">
            <div style="width:<?= $pct ?>%;background:#f4a261;height:100%;border-radius:4px;transition:width .4s;"></div>
            <span style="position:absolute;left:8px;top:3px;font-size:.72rem;color:#555;"><?= $bt['so_don'] ?> đơn</span>
          </div>
          <span style="width:80px;font-size:.78rem;color:#444;text-align:right;flex-shrink:0;">
            <?= number_format($bt['doanh_thu']/1000000, 2) ?>tr₫
          </span>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<div class="row g-4">

  <!-- TOP BÁN CHẠY -->
  <div class="col-lg-7">
    <div class="admin-card">
      <div class="card-title">Top 10 sách bán chạy</div>
      <?php if (empty($top_ban)): ?>
        <p class="text-muted text-center py-3">Chưa có dữ liệu.</p>
      <?php else: ?>
        <table class="table admin-table">
          <thead>
            <tr>
              <th>#</th><th>Sách</th>
              <th class="text-center">Đã bán</th>
              <th class="text-end">Doanh thu</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($top_ban as $i => $t): ?>
            <tr>
              <td>
                <?php if ($i===0) echo '🥇';
                elseif ($i===1) echo '🥈';
                elseif ($i===2) echo '🥉';
                else echo "<span style='color:#888;'>".($i+1)."</span>"; ?>
              </td>
              <td>
                <div class="fw-semibold" style="font-size:.88rem;"><?= htmlspecialchars($t['ten']) ?></div>
                <small class="text-muted"><?= htmlspecialchars($t['ma_sach']) ?></small>
              </td>
              <td class="text-center">
                <span class="badge bg-success"><?= number_format($t['da_ban']) ?> <?= htmlspecialchars($t['don_vi_tinh']) ?></span>
              </td>
              <td class="text-end" style="color:#e63946;font-weight:600;">
                <?= number_format($t['doanh_thu']/1000000, 2) ?>tr₫
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- TOP TỒN NHIỀU NHẤT -->
  <div class="col-lg-5">
    <div class="admin-card">
      <div class="card-title">Top 5 tồn kho giá trị cao nhất</div>
      <?php if (empty($top_ton)): ?>
        <p class="text-muted text-center py-3">Chưa có dữ liệu.</p>
      <?php else: ?>
        <table class="table admin-table">
          <thead>
            <tr>
              <th>Sách</th>
              <th class="text-end">Tồn</th>
              <th class="text-end">Giá trị</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($top_ton as $t): ?>
            <tr>
              <td>
                <div class="fw-semibold" style="font-size:.85rem;"><?= htmlspecialchars($t['ten']) ?></div>
                <small class="text-muted"><?= htmlspecialchars($t['ma_sach']) ?></small>
              </td>
              <td class="text-end fw-semibold"><?= number_format($t['so_luong']) ?></td>
              <td class="text-end" style="font-size:.85rem;color:#f4a261;font-weight:600;">
                <?= number_format($t['gia_tri']/1000000, 2) ?>tr₫
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php
require_once 'includes/admin_footer.php';
ob_end_flush();
?>
