<?php
ob_start();
$page_title = 'Quản lý giá';
require_once 'includes/admin_header.php';
require_once __DIR__ . '/includes/admin_search_helper.php';

// ---- CẬP NHẬT TỶ LỆ LỢI NHUẬN ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_ty_le') {
    $id      = (int)$_POST['id'];
    $ty_le   = (float)$_POST['ty_le_ln'];
    if ($ty_le >= 0) {
        $pdo->prepare("UPDATE sach SET ty_le_ln = ? WHERE id = ?")
            ->execute([$ty_le, $id]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã cập nhật tỷ lệ lợi nhuận.'];
    }
    header('Location: /nhasach/admin/prices.php');
    exit;
}

// ---- CẬP NHẬT HÀNG LOẠT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_update') {
    $ids     = $_POST['ids']     ?? [];
    $ty_les  = $_POST['ty_les']  ?? [];
    $count   = 0;
    foreach ($ids as $i => $id) {
        $id    = (int)$id;
        $ty_le = isset($ty_les[$i]) ? (float)$ty_les[$i] : null;
        if ($id > 0 && $ty_le !== null && $ty_le >= 0) {
            $pdo->prepare("UPDATE sach SET ty_le_ln = ? WHERE id = ?")
                ->execute([$ty_le, $id]);
            $count++;
        }
    }
    $_SESSION['flash'] = ['type' => 'success', 'msg' => "Đã cập nhật $count sản phẩm."];
    header('Location: /nhasach/admin/prices.php');
    exit;
}

// ---- LỌC / TÌM KIẾM ----
$filter_tl     = (int)($_GET['the_loai'] ?? 0);
$filter_search = trim($_GET['search'] ?? '');
$filter_nhap   = $_GET['da_nhap'] ?? 'tat_ca';

$filter_nhap   = $_GET['da_nhap'] ?? 'tat_ca';
$per_page      = 20;
$trang_hien    = max(1, (int)($_GET['trang'] ?? 1));

$where  = ["1=1"];
$params = [];
if ($filter_tl > 0)        { $where[] = "s.the_loai_id = ?"; $params[] = $filter_tl; }
if ($filter_nhap === 'co')  { $where[] = "s.da_nhap_hang = 1"; }
if ($filter_nhap === 'chua'){ $where[] = "s.da_nhap_hang = 0"; }

$where_sql = implode(' AND ', $where);

$sachs = $pdo->prepare("
    SELECT s.id, s.ma_sach, s.ten, s.gia_nhap, s.ty_le_ln, s.so_luong, s.da_nhap_hang,
           tl.ten AS ten_the_loai,
           ROUND(s.gia_nhap * (1 + s.ty_le_ln / 100), 0) AS gia_ban,
           ROUND(s.gia_nhap * s.ty_le_ln / 100, 0) AS tien_ln
    FROM sach s
    JOIN the_loai tl ON tl.id = s.the_loai_id
    WHERE $where_sql
    ORDER BY s.ten ASC
");
$sachs->execute($params);
$sachs = $sachs->fetchAll();

if ($filter_search !== '') {
    $sachs = admin_fuzzy_filter_rows($sachs, $filter_search, static function (array $row): array {
        return [
            ['value' => $row['ten'] ?? '', 'weight' => 1.0],
            ['value' => $row['ma_sach'] ?? '', 'weight' => 1.15],
            ['value' => $row['ten_the_loai'] ?? '', 'weight' => 0.45],
        ];
    });
}

$pagination = admin_paginate_rows($sachs, $trang_hien, $per_page);
$total = $pagination['total'];
$total_page = $pagination['total_page'];
$trang_hien = $pagination['page'];
$sachs = $pagination['items'];

// Lịch sử nhập hàng theo sách (tra cứu)
$lich_su_sach_id = (int)($_GET['lich_su'] ?? 0);
$lich_su = [];
if ($lich_su_sach_id > 0) {
    $lich_su = $pdo->prepare("
        SELECT cn.so_luong, cn.don_gia, pn.ma_phieu, pn.ngay_tao AS ngay_hoan_thanh,
               cn.so_luong * cn.don_gia AS thanh_tien
        FROM chi_tiet_nhap cn
        JOIN phieu_nhap pn ON pn.id = cn.phieu_nhap_id
        WHERE cn.sach_id = ? AND pn.trang_thai = 'done'
        ORDER BY pn.ngay_tao DESC
    ");
    $lich_su->execute([$lich_su_sach_id]);
    $lich_su = $lich_su->fetchAll();

    $sach_ls = $pdo->prepare("SELECT ten, gia_nhap, ty_le_ln FROM sach WHERE id = ?");
    $sach_ls->execute([$lich_su_sach_id]);
    $sach_ls = $sach_ls->fetch();
}

$the_loais = $pdo->query("SELECT * FROM the_loai WHERE trang_thai = 1 ORDER BY ten")->fetchAll();
?>

<div class="page-header">
  <h5><i class="bi bi-currency-dollar me-2" style="color:#f4a261;"></i>Quản lý giá bán</h5>
</div>

<!-- LịCH SỬ GIÁ VỐN (nếu đang xem) -->
<?php if ($lich_su_sach_id && $sach_ls): ?>
<div class="admin-card mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="card-title mb-0">
      Lịch sử nhập: <strong><?= htmlspecialchars($sach_ls['ten']) ?></strong>
    </div>
    <a href="/nhasach/admin/prices.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
      <i class="bi bi-x-lg me-1"></i>Đóng
    </a>
  </div>

  <?php if (empty($lich_su)): ?>
    <p class="text-muted">Sách này chưa có phiếu nhập nào được hoàn thành.</p>
  <?php else: ?>
    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <div class="p-3 rounded" style="background:#f8f9fa; border:1px solid #eee; text-align:center;">
          <div style="font-size:.75rem; color:#888; text-transform:uppercase; letter-spacing:.08em;">Giá vốn BQ hiện tại</div>
          <div style="font-size:1.3rem; font-weight:700; color:#1a1a2e;">
            <?= number_format($sach_ls['gia_nhap'], 0, ',', '.') ?>₫
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="p-3 rounded" style="background:#f8f9fa; border:1px solid #eee; text-align:center;">
          <div style="font-size:.75rem; color:#888; text-transform:uppercase; letter-spacing:.08em;">Tỷ lệ lợi nhuận</div>
          <div style="font-size:1.3rem; font-weight:700; color:#f4a261;"><?= $sach_ls['ty_le_ln'] ?>%</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="p-3 rounded" style="background:#f8f9fa; border:1px solid #eee; text-align:center;">
          <div style="font-size:.75rem; color:#888; text-transform:uppercase; letter-spacing:.08em;">Giá bán hiện tại</div>
          <div style="font-size:1.3rem; font-weight:700; color:#e63946;">
            <?= number_format(round($sach_ls['gia_nhap'] * (1 + $sach_ls['ty_le_ln'] / 100)), 0, ',', '.') ?>₫
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="p-3 rounded" style="background:#f8f9fa; border:1px solid #eee; text-align:center;">
          <div style="font-size:.75rem; color:#888; text-transform:uppercase; letter-spacing:.08em;">Số lần nhập</div>
          <div style="font-size:1.3rem; font-weight:700; color:#3fe0a0;"><?= count($lich_su) ?></div>
        </div>
      </div>
    </div>

    <table class="table admin-table table-hover">
      <thead>
        <tr>
          <th>Phiếu nhập</th>
          <th>Ngày hoàn thành</th>
          <th class="text-end">SL nhập</th>
          <th class="text-end">Đơn giá lô</th>
          <th class="text-end">Thành tiền lô</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lich_su as $ls): ?>
        <tr>
          <td><strong><?= htmlspecialchars($ls['ma_phieu']) ?></strong></td>
          <td style="font-size:.85rem;"><?= $ls['ngay_hoan_thanh'] ? date('d/m/Y H:i', strtotime($ls['ngay_hoan_thanh'])) : '—' ?></td>
          <td class="text-end"><?= number_format($ls['so_luong']) ?></td>
          <td class="text-end"><?= number_format($ls['don_gia'], 0, ',', '.') ?>₫</td>
          <td class="text-end" style="font-weight:600;"><?= number_format($ls['thanh_tien'], 0, ',', '.') ?>₫</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- BỘ LỌC -->
<div class="admin-card mb-3">
  <form method="GET" action="/nhasach/admin/prices.php" class="row g-2 align-items-end">
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
      <select name="da_nhap" class="form-select form-select-sm">
        <option value="tat_ca" <?= $filter_nhap=='tat_ca'?'selected':'' ?>>Tất cả</option>
        <option value="co"     <?= $filter_nhap=='co'    ?'selected':'' ?>>Đã nhập hàng</option>
        <option value="chua"   <?= $filter_nhap=='chua'  ?'selected':'' ?>>Chưa nhập hàng</option>
      </select>
    </div>
    <div class="col-md-4 d-flex gap-2">
      <button type="submit" class="btn btn-sm btn-primary" style="border-radius:8px;">
        <i class="bi bi-funnel me-1"></i>Lọc
      </button>
      <a href="/nhasach/admin/prices.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
        Xoá lọc
      </a>
    </div>
  </form>
</div>

<!-- BẢNG GIÁ -->
<div class="admin-card">
  <form method="POST" action="/nhasach/admin/prices.php">
    <input type="hidden" name="action" value="bulk_update">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="card-title mb-0">Bảng giá sản phẩm (<?= $total ?> — trang <?= $trang_hien ?>/<?= $total_page ?>)</div>
      <button type="submit" class="btn btn-sm"
              style="background:#f4a261;color:#fff;border:none;border-radius:8px;"
              onclick="return confirm('Lưu tất cả thay đổi?')">
        <i class="bi bi-save me-1"></i>Lưu tất cả
      </button>
    </div>

    <?php if (empty($sachs)): ?>
      <p class="text-muted text-center py-4">Không có sách nào.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table admin-table table-hover">
          <thead>
            <tr>
              <th>Sách</th>
              <th>Thể loại</th>
              <th class="text-end">Giá vốn BQ</th>
              <th style="width:140px;" class="text-center">Tỷ lệ LN (%)</th>
              <th class="text-end">Giá bán</th>
              <th class="text-end">Lợi nhuận/sp</th>
              <th style="width:80px;"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sachs as $s): ?>
            <tr>
              <input type="hidden" name="ids[]" value="<?= $s['id'] ?>">
              <td>
                <div class="fw-semibold" style="font-size:.88rem;"><?= htmlspecialchars($s['ten']) ?></div>
                <small class="text-muted"><?= htmlspecialchars($s['ma_sach']) ?></small>
              </td>
              <td><small><?= htmlspecialchars($s['ten_the_loai']) ?></small></td>
              <td class="text-end">
                <?php if ($s['gia_nhap'] > 0): ?>
                  <span style="font-weight:600;"><?= number_format($s['gia_nhap'], 0, ',', '.') ?>₫</span>
                <?php else: ?>
                  <span class="text-muted">Chưa nhập</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <input type="number" name="ty_les[]"
                       class="form-control form-control-sm text-center ty-le-input"
                       value="<?= $s['ty_le_ln'] ?>"
                       min="0" max="1000" step="0.1"
                       style="width:100px; display:inline-block;"
                       data-gia="<?= $s['gia_nhap'] ?>">
              </td>
              <td class="text-end gia-ban-cell" style="color:#e63946; font-weight:600;">
                <?= $s['gia_nhap'] > 0 ? number_format($s['gia_ban'], 0, ',', '.') . '₫' : '—' ?>
              </td>
              <td class="text-end tien-ln-cell" style="font-size:.82rem; color:#3a86ff;">
                <?= $s['gia_nhap'] > 0 ? '+' . number_format($s['tien_ln'], 0, ',', '.') . '₫' : '—' ?>
              </td>
              <td>
                <a href="/nhasach/admin/prices.php?lich_su=<?= $s['id'] ?><?= $filter_search ? '&search='.urlencode($filter_search) : '' ?>"
                   class="btn btn-sm btn-outline-secondary" style="border-radius:6px;"
                   title="Xem lịch sử nhập hàng">
                  <i class="bi bi-clock-history"></i>
                </a>
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
  </form>
</div>

<script>
// Live preview giá bán khi thay đổi tỷ lệ
document.querySelectorAll('.ty-le-input').forEach(inp => {
  inp.addEventListener('input', function() {
    const gia  = parseFloat(this.dataset.gia) || 0;
    const tl   = parseFloat(this.value) || 0;
    const row  = this.closest('tr');
    const ban  = row.querySelector('.gia-ban-cell');
    const ln   = row.querySelector('.tien-ln-cell');
    if (gia > 0) {
      const gb = Math.round(gia * (1 + tl / 100));
      const tl2 = gb - gia;
      ban.textContent = gb.toLocaleString('vi') + '₫';
      ln.textContent  = '+' + tl2.toLocaleString('vi') + '₫';
    }
  });
});
</script>

<?php
require_once 'includes/admin_footer.php';
ob_end_flush();
?>
