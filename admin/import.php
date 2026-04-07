<?php
ob_start();
$page_title = 'Nhập hàng';
require_once 'includes/admin_header.php';
require_once __DIR__ . '/includes/admin_search_helper.php';


// Tạo phiếu draft mới rồi redirect thẳng sang edit
if (($_GET['action'] ?? '') === 'create') {
    $ma_phieu = 'PN' . date('ymdHis');
    $pdo->prepare("INSERT INTO phieu_nhap (ma_phieu, ngay_nhap, ghi_chu, trang_thai, nguoi_tao) VALUES (?,?,?,'draft',?)")->execute([$ma_phieu, date('Y-m-d'), '', $_SESSION['admin_id']]);
    header('Location: /nhasach/admin/import_edit.php?id=' . $pdo->lastInsertId());
    exit;
}
$filter_tt     = $_GET['trang_thai'] ?? 'tat_ca';
$filter_search = trim($_GET['search'] ?? '');
$per_page      = 15;
$trang_hien    = max(1, (int)($_GET['trang'] ?? 1));

$where  = ["1=1"];
$params = [];
if ($filter_tt === 'draft') { $where[] = "trang_thai = 'draft'"; }
if ($filter_tt === 'done')  { $where[] = "trang_thai = 'done'"; }

$where_sql = implode(' AND ', $where);

$phieus = $pdo->prepare("
    SELECT pn.*,
           (SELECT COUNT(*) FROM chi_tiet_nhap WHERE phieu_nhap_id = pn.id) AS so_dong,
           (SELECT COALESCE(SUM(so_luong * don_gia),0) FROM chi_tiet_nhap WHERE phieu_nhap_id = pn.id) AS tong_tien
    FROM phieu_nhap pn
    WHERE $where_sql
    ORDER BY pn.ngay_tao DESC
");
$phieus->execute($params);
$phieus = $phieus->fetchAll();

if ($filter_search !== '') {
    $phieus = admin_fuzzy_filter_rows($phieus, $filter_search, static function (array $row): array {
        return [
            ['value' => $row['ma_phieu'] ?? '', 'weight' => 1.15],
            ['value' => $row['ghi_chu'] ?? '', 'weight' => 0.55],
        ];
    });
}

$pagination = admin_paginate_rows($phieus, $trang_hien, $per_page);
$total = $pagination['total'];
$total_page = $pagination['total_page'];
$trang_hien = $pagination['page'];
$phieus = $pagination['items'];
?>

<div class="page-header">
  <h5><i class="bi bi-box-arrow-in-down me-2" style="color:#f4a261;"></i>Nhập hàng</h5>
  <a href="/nhasach/admin/import.php?action=create" class="btn btn-sm"
     style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
    <i class="bi bi-plus-lg me-1"></i>Tạo phiếu nhập mới
  </a>
</div>

<!-- BỘ LỌC -->
<div class="admin-card mb-3">
  <form method="GET" action="/nhasach/admin/import.php" class="row g-2 align-items-end">
    <div class="col-md-4">
      <input type="text" name="search" class="form-control form-control-sm"
             placeholder="Tìm theo mã phiếu..."
             value="<?= htmlspecialchars($filter_search) ?>">
    </div>
    <div class="col-md-3">
      <select name="trang_thai" class="form-select form-select-sm">
        <option value="tat_ca" <?= $filter_tt=='tat_ca'?'selected':'' ?>>Tất cả trạng thái</option>
        <option value="draft"  <?= $filter_tt=='draft'  ?'selected':'' ?>>Đang soạn</option>
        <option value="done"   <?= $filter_tt=='done'   ?'selected':'' ?>>Đã hoàn thành</option>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-sm btn-primary" style="border-radius:8px;">
        <i class="bi bi-funnel me-1"></i>Lọc
      </button>
      <a href="/nhasach/admin/import.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
        Xoá lọc
      </a>
    </div>
  </form>
</div>

<!-- BẢNG PHIẾU -->
<div class="admin-card">
  <div class="card-title">Danh sách phiếu nhập (<?= $total ?> — trang <?= $trang_hien ?>/<?= $total_page ?>)</div>

  <?php if (empty($phieus)): ?>
    <p class="text-muted text-center py-4">Chưa có phiếu nhập nào.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table admin-table table-hover">
        <thead>
          <tr>
            <th>Mã phiếu</th>
            <th>Ngày nhập</th>
            <th>Ngày tạo</th>
            <th>Số loại sách</th>
            <th class="text-end">Tổng tiền</th>
            <th>Trạng thái</th>
            <th>Ghi chú</th>
            <th style="width:80px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($phieus as $p): ?>
          <tr style="cursor:pointer;"
              onclick="window.location='/nhasach/admin/import_edit.php?id=<?= $p['id'] ?>'"
              onmouseover="this.style.background='#fff8f3'"
              onmouseout="this.style.background=''">
            <td><strong><?= htmlspecialchars($p['ma_phieu']) ?></strong></td>
            <td style="font-size:.85rem;">
              <?= ($p['ngay_nhap'] && $p['ngay_nhap'] !== '0000-00-00')
                  ? date('d/m/Y', strtotime($p['ngay_nhap']))
                  : '<span class="text-danger">—</span>' ?>
            </td>
            <td style="font-size:.85rem;"><?= date('d/m/Y H:i', strtotime($p['ngay_tao'])) ?></td>
            <td><span class="badge bg-light text-dark border"><?= $p['so_dong'] ?> loại</span></td>
            <td class="text-end" style="color:#e63946; font-weight:600;">
              <?= $p['tong_tien'] > 0 ? number_format($p['tong_tien'], 0, ',', '.') . '₫' : '—' ?>
            </td>
            <td>
              <?php if ($p['trang_thai'] === 'done'): ?>
                <span class="badge bg-success">Đã hoàn thành</span>
              <?php else: ?>
                <span class="badge bg-warning text-dark">Đang soạn</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.82rem; color:#666; max-width:160px;" class="text-truncate">
              <?= htmlspecialchars($p['ghi_chu'] ?? '') ?>
            </td>
            <td onclick="event.stopPropagation()">
              <a href="/nhasach/admin/import_edit.php?id=<?= $p['id'] ?>"
                 class="btn btn-sm btn-outline-primary" style="border-radius:6px;"
                 title="<?= $p['trang_thai'] === 'draft' ? 'Sửa phiếu' : 'Xem phiếu' ?>">
                <i class="bi <?= $p['trang_thai'] === 'draft' ? 'bi-pencil' : 'bi-eye' ?>"></i>
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
</div>

<?php
require_once 'includes/admin_footer.php';
ob_end_flush();
?>
