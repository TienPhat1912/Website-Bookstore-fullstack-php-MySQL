<?php
ob_start();
$page_title = 'Lịch sử nhập xuất';
require_once 'includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /nhasach/admin/inventory.php'); exit; }

// ---- Khoảng thời gian lọc ----
$tu_ngay  = trim($_GET['tu_ngay']  ?? '');
$den_ngay = trim($_GET['den_ngay'] ?? '');
$co_loc_ngay = (
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $tu_ngay) &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $den_ngay)
);

$sach = $pdo->prepare("
    SELECT s.*, tl.ten AS ten_the_loai,
           ROUND(s.gia_nhap * (1 + s.ty_le_ln/100), 0) AS gia_ban
    FROM sach s JOIN the_loai tl ON tl.id = s.the_loai_id
    WHERE s.id = ?
");
$sach->execute([$id]);
$sach = $sach->fetch();
if (!$sach) { header('Location: /nhasach/admin/inventory.php'); exit; }

// Lịch sử nhập từng lô, có lọc ngày
$sql_nhap = "
    SELECT cn.id, cn.so_luong, cn.don_gia, pn.id AS phieu_id,
           pn.ma_phieu, pn.ngay_nhap, pn.ngay_tao, pn.ghi_chu,
           cn.so_luong * cn.don_gia AS thanh_tien
    FROM chi_tiet_nhap cn
    JOIN phieu_nhap pn ON pn.id = cn.phieu_nhap_id
    WHERE cn.sach_id = ? AND pn.trang_thai = 'done'
";
$params_nhap = [$id];
if ($co_loc_ngay) {
    $sql_nhap .= " AND pn.ngay_nhap BETWEEN ? AND ?";
    $params_nhap[] = $tu_ngay;
    $params_nhap[] = $den_ngay;
}
$sql_nhap .= " ORDER BY CASE WHEN pn.ngay_nhap = '0000-00-00' OR pn.ngay_nhap IS NULL THEN pn.ngay_tao ELSE pn.ngay_nhap END ASC, pn.ngay_tao ASC";

$lo_nhap_stmt = $pdo->prepare($sql_nhap);
$lo_nhap_stmt->execute($params_nhap);
$lo_nhap = $lo_nhap_stmt->fetchAll();

// Tính giá BQ tích lũy sau từng lô
$ton_luy_ke  = 0;
$gia_bq_luy  = 0;
foreach ($lo_nhap as &$lo) {
    $sl  = (int)$lo['so_luong'];
    $gia = (float)$lo['don_gia'];
    if ($ton_luy_ke + $sl > 0) {
        $gia_bq_sau = ($ton_luy_ke * $gia_bq_luy + $sl * $gia) / ($ton_luy_ke + $sl);
    } else {
        $gia_bq_sau = $gia;
    }
    $lo['gia_bq_truoc'] = $gia_bq_luy;
    $lo['gia_bq_sau']   = round($gia_bq_sau);
    $ton_luy_ke         += $sl;
    $gia_bq_luy          = $gia_bq_sau;
}
unset($lo);

// Lịch sử xuất (đơn hàng), có lọc ngày
$sql_xuat = "
    SELECT ct.so_luong, ct.gia_ban_luc_dat,
           dh.id AS don_id, dh.ma_don, dh.ngay_dat, dh.trang_thai,
           kh.ho_ten AS ten_kh
    FROM chi_tiet_don_hang ct
    JOIN don_hang dh ON dh.id = ct.don_hang_id
    JOIN khach_hang kh ON kh.id = dh.khach_hang_id
    WHERE ct.sach_id = ? AND dh.trang_thai = 'da_giao'
";
$params_xuat = [$id];
if ($co_loc_ngay) {
    $sql_xuat .= " AND DATE(dh.ngay_dat) BETWEEN ? AND ?";
    $params_xuat[] = $tu_ngay;
    $params_xuat[] = $den_ngay;
    $sql_xuat .= " ORDER BY dh.ngay_dat ASC";
} else {
    $sql_xuat .= " ORDER BY dh.ngay_dat DESC LIMIT 20";
}

$lo_xuat_stmt = $pdo->prepare($sql_xuat);
$lo_xuat_stmt->execute($params_xuat);
$lo_xuat = $lo_xuat_stmt->fetchAll();

$tong_nhap = array_sum(array_column($lo_nhap, 'so_luong'));
$tong_xuat = array_sum(array_column($lo_xuat, 'so_luong'));
$tong_tien_nhap = array_sum(array_column($lo_nhap, 'thanh_tien'));
$tong_tien_xuat = array_sum(array_map(fn($x) => $x['so_luong'] * $x['gia_ban_luc_dat'], $lo_xuat));

// Tồn đầu kỳ và tồn cuối kỳ (chỉ tính khi có lọc ngày)
$ton_dau_ky  = 0;
$ton_cuoi_ky = $sach['so_luong'];
if ($co_loc_ngay) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(cn.so_luong), 0)
        FROM chi_tiet_nhap cn
        JOIN phieu_nhap pn ON pn.id = cn.phieu_nhap_id
        WHERE cn.sach_id = ? AND pn.trang_thai = 'done'
          AND pn.ngay_nhap < ?
    ");
    $stmt->execute([$id, $tu_ngay]);
    $nhap_truoc = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ct.so_luong), 0)
        FROM chi_tiet_don_hang ct
        JOIN don_hang dh ON dh.id = ct.don_hang_id
        WHERE ct.sach_id = ? AND dh.trang_thai = 'da_giao'
          AND DATE(dh.ngay_dat) < ?
    ");
    $stmt->execute([$id, $tu_ngay]);
    $xuat_truoc = (int)$stmt->fetchColumn();

    $ton_dau_ky  = $nhap_truoc - $xuat_truoc;
    $ton_cuoi_ky = $ton_dau_ky + $tong_nhap - $tong_xuat;
}

$tt_labels = [
    'cho_xu_ly'   => ['Chờ xử lý',    'bg-warning text-dark'],
    'da_xac_nhan' => ['Đã xác nhận',   'bg-info text-white'],
    'da_giao'     => ['Đã giao',        'bg-success text-white'],
    'da_huy'      => ['Đã huỷ',         'bg-danger text-white'],
];
?>

<div class="page-header">
  <h5>
    <i class="bi bi-journal-text me-2" style="color:#f4a261;"></i>
    <?= $co_loc_ngay ? 'Nhập xuất' : 'Lịch sử nhập' ?> —
    <span style="color:#f4a261;"><?= htmlspecialchars($sach['ma_sach']) ?></span>
  </h5>
  <div class="d-flex gap-2">
    <a href="/nhasach/admin/product_edit.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
      <i class="bi bi-pencil me-1"></i>Sửa sách
    </a>
    <a href="/nhasach/admin/inventory.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
      <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
  </div>
</div>

<!-- BỘ LỌC KHOẢNG NGÀY -->
<div class="admin-card mb-4">
  <form method="GET" action="/nhasach/admin/inventory_detail.php" class="row g-2 align-items-end">
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="col-12 col-md-auto">
      <span class="fw-semibold" style="font-size:.85rem;color:#555;">
        <i class="bi bi-calendar-range me-1" style="color:#f4a261;"></i>Tra cứu theo khoảng thời gian
      </span>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label fw-semibold" style="font-size:.8rem;">Từ ngày</label>
      <input type="date" name="tu_ngay" class="form-control form-control-sm"
             value="<?= htmlspecialchars($tu_ngay) ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label fw-semibold" style="font-size:.8rem;">Đến ngày</label>
      <input type="date" name="den_ngay" class="form-control form-control-sm"
             value="<?= htmlspecialchars($den_ngay) ?>">
    </div>
    <div class="col-auto d-flex gap-2">
      <button type="submit" class="btn btn-sm btn-primary" style="border-radius:8px;">
        <i class="bi bi-search me-1"></i>Lọc
      </button>
      <a href="/nhasach/admin/inventory_detail.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
        Xoá lọc
      </a>
    </div>
    <?php if ($co_loc_ngay): ?>
    <div class="col-12 mt-1">
      <small class="text-warning">
        <i class="bi bi-clock-history me-1"></i>
        Đang xem nhập/xuất từ <strong><?= date('d/m/Y', strtotime($tu_ngay)) ?></strong>
        đến <strong><?= date('d/m/Y', strtotime($den_ngay)) ?></strong>
      </small>
    </div>
    <?php endif; ?>
  </form>
</div>

<!-- THÔNG TIN SÁCH -->
<div class="admin-card mb-4">
  <div class="row g-3 align-items-center">
    <div class="col-md-5">
      <div class="fw-bold" style="font-size:1.05rem;color:#1a1a2e;"><?= htmlspecialchars($sach['ten']) ?></div>
      <div style="font-size:.85rem;color:#888;"><?= htmlspecialchars($sach['ma_sach']) ?> — <?= htmlspecialchars($sach['ten_the_loai']) ?></div>
      <?php if ($sach['tac_gia']): ?>
        <div style="font-size:.85rem;color:#888;"><i class="bi bi-person me-1"></i><?= htmlspecialchars($sach['tac_gia']) ?></div>
      <?php endif; ?>
    </div>
    <div class="col-md-7">
      <div class="row g-2 text-center">
        <?php if ($co_loc_ngay): ?>
        <div class="col-3">
          <div style="font-size:1.2rem;font-weight:700;color:#5b9fff;"><?= number_format($tong_nhap) ?></div>
          <div style="font-size:.72rem;color:#888;">Nhập trong kỳ</div>
        </div>
        <div class="col-3">
          <div style="font-size:1.2rem;font-weight:700;color:#f4a261;"><?= number_format($tong_xuat) ?></div>
          <div style="font-size:.72rem;color:#888;">Xuất trong kỳ</div>
        </div>
        <div class="col-3">
          <div style="font-size:1.2rem;font-weight:700;color:#888;"><?= number_format($ton_dau_ky) ?></div>
          <div style="font-size:.72rem;color:#888;">Tồn đầu kỳ</div>
        </div>
        <div class="col-3">
          <div style="font-size:1.1rem;font-weight:700;color:#3fe0a0;"><?= number_format($ton_cuoi_ky) ?></div>
          <div style="font-size:.72rem;color:#888;">Tồn cuối kỳ</div>
        </div>
        <?php else: ?>
        <div class="col-4">
          <div style="font-size:1.3rem;font-weight:700;color:#3fe0a0;"><?= number_format($sach['so_luong']) ?></div>
          <div style="font-size:.75rem;color:#888;">Tồn kho hiện tại</div>
        </div>
        <div class="col-4">
          <div style="font-size:1.3rem;font-weight:700;color:#5b9fff;"><?= number_format($tong_nhap) ?></div>
          <div style="font-size:.75rem;color:#888;">Tổng đã nhập</div>
        </div>
        <div class="col-4">
          <div style="font-size:1.3rem;font-weight:700;color:#f4a261;"><?= $sach['gia_nhap'] > 0 ? number_format($sach['gia_nhap'],0,',','.').'₫' : '—' ?></div>
          <div style="font-size:.75rem;color:#888;">Giá vốn BQ hiện tại</div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">

  <!-- LỊCH SỬ NHẬP LÔ -->
  <div class="col-lg-7">
    <div class="admin-card">
      <div class="card-title">
        <?= $co_loc_ngay ? 'Nhập trong kỳ' : 'Lịch sử nhập theo lô' ?>
        (<?= count($lo_nhap) ?> lô)
        <?php if ($co_loc_ngay): ?>
          <small class="text-muted fw-normal"> — <?= date('d/m/Y', strtotime($tu_ngay)) ?> → <?= date('d/m/Y', strtotime($den_ngay)) ?></small>
        <?php endif; ?>
      </div>
      <?php if (empty($lo_nhap)): ?>
        <p class="text-muted text-center py-3">Chưa có lô nhập nào<?= $co_loc_ngay ? ' trong khoảng thời gian này' : '' ?>.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Phiếu nhập</th>
                <th>Ngày nhập</th>
                <th class="text-end">SL nhập</th>
                <th class="text-end">Đơn giá lô</th>
                <th class="text-end">Giá BQ sau lô</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lo_nhap as $i => $lo): ?>
              <tr>
                <td style="color:#888;"><?= $i+1 ?></td>
                <td>
                  <a href="/nhasach/admin/import_edit.php?id=<?= $lo['phieu_id'] ?>"
                     style="font-size:.85rem;color:#f4a261;">
                    <?= htmlspecialchars($lo['ma_phieu']) ?>
                  </a>
                  <?php if (!empty($lo['ghi_chu'])): ?>
                    <br><small class="text-muted"><?= htmlspecialchars($lo['ghi_chu']) ?></small>
                  <?php endif; ?>
                </td>
                <td style="font-size:.85rem;">
                  <?= $lo['ngay_nhap'] && $lo['ngay_nhap'] !== '0000-00-00'
                      ? date('d/m/Y', strtotime($lo['ngay_nhap']))
                      : '<span class="text-muted">—</span>' ?>
                </td>
                <td class="text-end fw-semibold"><?= number_format($lo['so_luong']) ?></td>
                <td class="text-end" style="font-size:.85rem;"><?= number_format($lo['don_gia'],0,',','.')?>₫</td>
                <td class="text-end">
                  <span class="fw-semibold" style="color:#f4a261;"><?= number_format($lo['gia_bq_sau'],0,',','.')?>₫</span>
                  <?php if ($lo['gia_bq_truoc'] > 0 && $lo['gia_bq_truoc'] != $lo['gia_bq_sau']): ?>
                    <br><small class="text-muted" style="font-size:.72rem;">
                      trước: <?= number_format($lo['gia_bq_truoc'],0,',','.')?>₫
                    </small>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr style="background:#f8f9fa;font-weight:700;">
                <td colspan="3">Tổng nhập<?= $co_loc_ngay ? ' trong kỳ' : '' ?></td>
                <td class="text-end"><?= number_format($tong_nhap) ?></td>
                <td class="text-end" style="font-size:.85rem;color:#5b9fff;">
                  <?= number_format($tong_tien_nhap, 0, ',', '.') ?>₫
                </td>
                <td class="text-end" style="font-size:.85rem;">
                  <?php if (!$co_loc_ngay): ?>
                    Giá vốn BQ: <span style="color:#f4a261;"><?= $sach['gia_nhap'] > 0 ? number_format($sach['gia_nhap'],0,',','.').'₫' : '—' ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- LỊCH SỬ XUẤT (ĐƠN HÀNG) -->
  <div class="col-lg-5">
    <div class="admin-card">
      <div class="card-title">
        <?= $co_loc_ngay ? 'Xuất trong kỳ' : '20 đơn xuất gần nhất' ?>
        <?php if ($co_loc_ngay): ?>
          <small class="text-muted fw-normal"> (<?= count($lo_xuat) ?> đơn)</small>
        <?php endif; ?>
      </div>
      <?php if (empty($lo_xuat)): ?>
        <p class="text-muted text-center py-3">Chưa có đơn hàng nào<?= $co_loc_ngay ? ' trong khoảng thời gian này' : '' ?>.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table admin-table">
            <thead>
              <tr>
                <th>Đơn hàng</th>
                <th class="text-end">SL</th>
                <th class="text-end">Giá bán</th>
                <th>Trạng thái</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lo_xuat as $x): ?>
              <tr>
                <td>
                  <a href="/nhasach/admin/orders.php?id=<?= $x['don_id'] ?>"
                     style="font-size:.82rem;color:#f4a261;"><?= htmlspecialchars($x['ma_don']) ?></a>
                  <br><small class="text-muted"><?= date('d/m/Y', strtotime($x['ngay_dat'])) ?> — <?= htmlspecialchars($x['ten_kh']) ?></small>
                </td>
                <td class="text-end fw-semibold"><?= $x['so_luong'] ?></td>
                <td class="text-end" style="font-size:.82rem;"><?= number_format($x['gia_ban_luc_dat'],0,',','.')?>₫</td>
                <td>
                  <?php $tt = $tt_labels[$x['trang_thai']] ?? ['—','bg-secondary']; ?>
                  <span class="badge <?= $tt[1] ?>" style="font-size:.72rem;"><?= $tt[0] ?></span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr style="background:#f8f9fa;font-weight:700;">
                <td>Tổng xuất</td>
                <td class="text-end"><?= number_format($tong_xuat) ?></td>
                <td class="text-end" style="font-size:.82rem;color:#e63946;">
                  <?= number_format($tong_tien_xuat, 0, ',', '.') ?>₫
                </td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php
require_once 'includes/admin_footer.php';
ob_end_flush();
?>