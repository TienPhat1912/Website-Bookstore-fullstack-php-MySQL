<?php
ob_start();
$page_title = 'Quản lý sách';
require_once 'includes/admin_header.php';
require_once __DIR__ . '/includes/admin_search_helper.php';

$the_loais = $pdo->query("SELECT * FROM the_loai WHERE trang_thai = 1 ORDER BY ten")->fetchAll();

$filter_tl     = (int)($_GET['the_loai'] ?? 0);
$filter_search = trim($_GET['search'] ?? '');
$filter_tt     = $_GET['trang_thai'] ?? 'tat_ca';
$per_page      = 10;
$trang_hien    = max(1, (int)($_GET['trang'] ?? 1));

$where  = ["1=1"];
$params = [];
if ($filter_tl > 0)        { $where[] = "s.the_loai_id = ?"; $params[] = $filter_tl; }
if ($filter_tt === 'hien')  { $where[] = "s.hien_trang = 1"; }
if ($filter_tt === 'an')    { $where[] = "s.hien_trang = 0"; }

$where_sql = implode(' AND ', $where);

$sachs_stmt = $pdo->prepare("
    SELECT s.*, tl.ten AS ten_the_loai,
           ROUND(s.gia_nhap * (1 + s.ty_le_ln/100), 0) AS gia_ban
    FROM sach s JOIN the_loai tl ON tl.id = s.the_loai_id
    WHERE $where_sql
    ORDER BY s.ngay_tao DESC
");
$sachs_stmt->execute($params);
$sachs = $sachs_stmt->fetchAll();

if ($filter_search !== '') {
    $sachs = admin_fuzzy_filter_rows($sachs, $filter_search, static function (array $row): array {
        return [
            ['value' => $row['ten'] ?? '', 'weight' => 1.0],
            ['value' => $row['ma_sach'] ?? '', 'weight' => 1.15],
            ['value' => $row['tac_gia'] ?? '', 'weight' => 0.65],
            ['value' => $row['ten_the_loai'] ?? '', 'weight' => 0.5],
        ];
    });
}

$pagination = admin_paginate_rows($sachs, $trang_hien, $per_page);
$total = $pagination['total'];
$total_page = $pagination['total_page'];
$trang_hien = $pagination['page'];
$sachs = $pagination['items'];

function page_url(int $p): string {
    $q = $_GET;
    $q['trang'] = $p;
    return '/nhasach/admin/products.php?' . http_build_query($q);
}
?>

<div class="page-header">
  <h5><i class="bi bi-book me-2" style="color:#f4a261;"></i>Quản lý sách</h5>
  <a href="/nhasach/admin/product_add.php" class="btn btn-sm"
     style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
    <i class="bi bi-plus-lg me-1"></i>Thêm sách mới
  </a>
</div>

<!-- BO LOC -->
<div class="admin-card mb-3">
  <form method="GET" action="/nhasach/admin/products.php" class="row g-2 align-items-end">
    <div class="col-md-4">
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
        <option value="tat_ca" <?= $filter_tt=='tat_ca'?'selected':'' ?>>Tất cả</option>
        <option value="hien"   <?= $filter_tt=='hien'  ?'selected':'' ?>>Đang hiện</option>
        <option value="an"     <?= $filter_tt=='an'    ?'selected':'' ?>>Đang ẩn</option>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-sm btn-primary" style="border-radius:8px;">
        <i class="bi bi-funnel me-1"></i>Lọc
      </button>
      <a href="/nhasach/admin/products.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
        Xoá lọc
      </a>
    </div>
  </form>
</div>

<!-- BANG SACH -->
<div class="admin-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="card-title mb-0">
      Danh sách sách
      <span class="text-muted" style="font-size:.82rem;font-weight:400;">
        (<?= $total ?> cuốn — trang <?= $trang_hien ?>/<?= $total_page ?>)
      </span>
    </div>
  </div>

  <?php if (empty($sachs)): ?>
    <p class="text-muted text-center py-4">Không có sách nào.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table admin-table table-hover">
      <thead>
        <tr>
          <th style="width:60px;">Ảnh</th>
          <th>Tên sách</th>
          <th>Thể loại</th>
          <th>Tồn kho</th>
          <th>Giá nhập</th>
          <th>Giá bán</th>
          <th>Trạng thái</th>
          <th style="width:50px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sachs as $s): ?>
          <tr style="cursor:pointer;"
              onclick="window.location='/nhasach/admin/product_edit.php?id=<?= $s['id'] ?>'"
              onmouseover="this.style.background='#fff8f3'"
              onmouseout="this.style.background=''">
            <td>
              <?php if (!empty($s['hinh']) && file_exists('../uploads/' . $s['hinh'])): ?>
                <img src="/nhasach/uploads/<?= htmlspecialchars($s['hinh']) ?>"
                     style="width:45px;height:58px;object-fit:cover;border-radius:6px;">
              <?php else: ?>
                <div style="width:45px;height:58px;background:#f0f0f0;border-radius:6px;
                            display:flex;align-items:center;justify-content:center;color:#ccc;">
                  <i class="bi bi-book"></i>
                </div>
              <?php endif; ?>
            </td>
            <td>
              <div class="fw-semibold" style="font-size:.88rem;">
                <?= htmlspecialchars($s['ten']) ?>
              </div>
              <small class="text-muted"><?= htmlspecialchars($s['ma_sach']) ?></small>
              <?php if (!empty($s['tac_gia'])): ?>
                <br><small class="text-muted"><?= htmlspecialchars($s['tac_gia']) ?></small>
              <?php endif; ?>
            </td>
            <td><small><?= htmlspecialchars($s['ten_the_loai']) ?></small></td>
            <td>
              <?php if ($s['so_luong'] == 0): ?>
                <span class="badge bg-danger">Hết hàng</span>
              <?php elseif ($s['so_luong'] <= 5): ?>
                <span class="badge bg-warning text-dark"><?= $s['so_luong'] ?></span>
              <?php else: ?>
                <span class="badge bg-success"><?= $s['so_luong'] ?></span>
              <?php endif; ?>
            </td>
            <td style="font-size:.85rem;">
              <?= $s['gia_nhap'] > 0 ? number_format($s['gia_nhap'], 0, ',', '.').'₫' : '—' ?>
            </td>
            <td style="font-size:.85rem;color:#e63946;font-weight:600;">
              <?= $s['gia_ban'] > 0 ? number_format($s['gia_ban'], 0, ',', '.').'₫' : '—' ?>
              <br><small class="text-muted">LN: <?= $s['ty_le_ln'] ?>%</small>
            </td>
            <td>
              <?php if ($s['hien_trang']): ?>
                <span class="badge bg-success">Đang bán</span>
              <?php else: ?>
                <span class="badge bg-secondary">Đã ẩn</span>
              <?php endif; ?>
            </td>
            <td onclick="event.stopPropagation()">
              <a href="/nhasach/admin/product_edit.php?id=<?= $s['id'] ?>"
                 class="btn btn-sm btn-outline-primary" style="border-radius:6px;" title="Chi tiết">
                <i class="bi bi-pencil"></i>
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
        <a class="page-link" href="<?= page_url($trang_hien - 1) ?>">
          <i class="bi bi-chevron-left"></i>
        </a>
      </li>
      <?php
      $start = max(1, $trang_hien - 2);
      $end   = min($total_page, $trang_hien + 2);
      if ($start > 1): ?>
        <li class="page-item"><a class="page-link" href="<?= page_url(1) ?>">1</a></li>
        <?php if ($start > 2): ?>
          <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; ?>
      <?php endif; ?>
      <?php for ($p = $start; $p <= $end; $p++): ?>
        <li class="page-item <?= $p === $trang_hien ? 'active' : '' ?>">
          <a class="page-link" href="<?= page_url($p) ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>
      <?php if ($end < $total_page): ?>
        <?php if ($end < $total_page - 1): ?>
          <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; ?>
        <li class="page-item"><a class="page-link" href="<?= page_url($total_page) ?>"><?= $total_page ?></a></li>
      <?php endif; ?>
      <li class="page-item <?= $trang_hien >= $total_page ? 'disabled' : '' ?>">
        <a class="page-link" href="<?= page_url($trang_hien + 1) ?>">
          <i class="bi bi-chevron-right"></i>
        </a>
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
