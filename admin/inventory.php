<?php
ob_start();
$page_title = 'Tồn kho';
require_once 'includes/admin_header.php';

// ---- THỐNG KÊ TỔNG QUAN (luôn theo thực tế hiện tại) ----
$tong_sach   = $pdo->query("SELECT COUNT(*) FROM sach WHERE hien_trang = 1")->fetchColumn();
$tong_ton    = $pdo->query("SELECT COALESCE(SUM(so_luong),0) FROM sach WHERE hien_trang = 1")->fetchColumn();
$gia_tri_kho = $pdo->query("SELECT COALESCE(SUM(so_luong * gia_nhap),0) FROM sach WHERE hien_trang = 1")->fetchColumn();
$so_het_hang = $pdo->query("SELECT COUNT(*) FROM sach WHERE so_luong = 0 AND hien_trang = 1")->fetchColumn();
$so_sap_het  = $pdo->query("SELECT COUNT(*) FROM sach WHERE so_luong > 0 AND so_luong <= 5 AND hien_trang = 1")->fetchColumn();

// ---- BỘ LỌC ----
$filter_search  = trim($_GET['search']    ?? '');
$filter_tl      = (int)($_GET['the_loai'] ?? 0);
$filter_tt      = $_GET['trang_thai']     ?? 'tat_ca';
$filter_ngay    = trim($_GET['ngay']      ?? ''); // thời điểm tra cứu
$per_page       = 20;
$trang_hien     = max(1, (int)($_GET['trang'] ?? 1));

$the_loais = $pdo->query("SELECT * FROM the_loai WHERE trang_thai = 1 ORDER BY ten")->fetchAll();

// ---- TÍNH TỒN KHO THEO THỜI ĐIỂM ----
// Nếu có ngày tra cứu: tồn = tổng nhập đến ngày đó - tổng xuất đến ngày đó
// Nếu không: lấy so_luong thực tế
$use_date = ($filter_ngay !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_ngay));

if ($use_date) {
    // Dùng pdo->quote() thay vì named param vì subquery xuất hiện 2 lần trong SQL
    $ngay_safe = $pdo->quote($filter_ngay);
    $ton_subquery = "
        (
            COALESCE((
                SELECT SUM(cn.so_luong)
                FROM chi_tiet_nhap cn
                JOIN phieu_nhap pn ON pn.id = cn.phieu_nhap_id
                WHERE cn.sach_id = s.id AND pn.trang_thai = 'done'
                  AND pn.ngay_nhap <= $ngay_safe
            ), 0)
            -
            COALESCE((
                SELECT SUM(ct.so_luong)
                FROM chi_tiet_don_hang ct
                JOIN don_hang dh ON dh.id = ct.don_hang_id
                WHERE ct.sach_id = s.id AND dh.trang_thai != 'da_huy'
                  AND DATE(dh.ngay_dat) <= $ngay_safe
            ), 0)
        )
    ";
} else {
    $ton_subquery = "s.so_luong";
}

$where  = ["s.hien_trang = 1"];
$params = [];
if ($filter_search !== '') {
    $where[]  = "(s.ten LIKE :search1 OR s.ma_sach LIKE :search2)";
    $params[':search1'] = "%$filter_search%";
    $params[':search2'] = "%$filter_search%";
}
if ($filter_tl > 0) {
    $where[]           = "s.the_loai_id = :the_loai";
    $params[':the_loai'] = $filter_tl;
}
// ngay_nhap/ngay_xuat được nhúng trực tiếp vào $ton_subquery qua pdo->quote(), không cần bind

// Lọc trạng thái tồn kho theo thời điểm cần HAVING
$having = "";
if ($filter_tt === 'het')     $having = "HAVING ton_kho = 0";
if ($filter_tt === 'sap_het') $having = "HAVING ton_kho > 0 AND ton_kho <= 5";
if ($filter_tt === 'con')     $having = "HAVING ton_kho > 5";

$where_sql = implode(' AND ', $where);

// Đếm tổng (dùng subquery)
$count_sql = "
    SELECT COUNT(*) FROM (
        SELECT s.id, $ton_subquery AS ton_kho
        FROM sach s JOIN the_loai tl ON tl.id = s.the_loai_id
        WHERE $where_sql $having
    ) t
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total      = (int)$count_stmt->fetchColumn();
$total_page = max(1, ceil($total / $per_page));
$trang_hien = min($trang_hien, $total_page);
$offset     = ($trang_hien - 1) * $per_page;

$sql = "
    SELECT s.id, s.ma_sach, s.ten, s.gia_nhap, s.ty_le_ln, s.da_nhap_hang, s.don_vi_tinh,
           tl.ten AS ten_the_loai,
           ROUND(s.gia_nhap * (1 + s.ty_le_ln / 100), 0) AS gia_ban,
           $ton_subquery AS ton_kho
    FROM sach s JOIN the_loai tl ON tl.id = s.the_loai_id
    WHERE $where_sql
    $having
    ORDER BY ton_kho ASC, s.ten ASC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
$stmt->execute();
$sachs = $stmt->fetchAll();

function inv_url(array $override = []): string {
    $q = array_merge($_GET, $override);
    unset($q['trang']);
    if (isset($override['trang'])) $q['trang'] = $override['trang'];
    return '/nhasach/admin/inventory.php?' . http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== '0' && $v !== 0));
}
?>

<div class="page-header">
  <h5><i class="bi bi-boxes me-2" style="color:#f4a261;"></i>Tồn kho</h5>
  <div class="d-flex gap-2">
    <a href="/nhasach/admin/inventory_report.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
      <i class="bi bi-bar-chart me-1"></i>Báo cáo
    </a>
    <a href="/nhasach/admin/import.php" class="btn btn-sm"
       style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
      <i class="bi bi-box-arrow-in-down me-1"></i>Nhập hàng
    </a>
  </div>
</div>

<!-- TỔNG QUAN -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="admin-card text-center py-3">
      <div style="font-size:1.6rem;font-weight:700;color:#5b9fff;"><?= number_format($tong_sach) ?></div>
      <div style="font-size:.8rem;color:#888;">Đầu sách đang bán</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card text-center py-3">
      <div style="font-size:1.6rem;font-weight:700;color:#3fe0a0;"><?= number_format($tong_ton) ?></div>
      <div style="font-size:.8rem;color:#888;">Tổng tồn kho hiện tại</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card text-center py-3">
      <div style="font-size:1.3rem;font-weight:700;color:#f4a261;"><?= number_format($gia_tri_kho/1000000, 1) ?>tr₫</div>
      <div style="font-size:.8rem;color:#888;">Giá trị tồn kho (giá vốn)</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card text-center py-3">
      <div style="font-size:1.6rem;font-weight:700;color:#e63946;"><?= $so_het_hang ?></div>
      <div style="font-size:.8rem;color:#888;">
        Hết hàng &nbsp;|&nbsp; <span style="color:#ff9f1c;"><?= $so_sap_het ?></span> sắp hết
      </div>
    </div>
  </div>
</div>

<!-- BỘ LỌC -->
<div class="admin-card mb-3">
  <form method="GET" action="/nhasach/admin/inventory.php" class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label fw-semibold" style="font-size:.8rem;">Tìm sách</label>
      <input type="text" name="search" class="form-control form-control-sm"
             placeholder="Tên hoặc mã sách..."
             value="<?= htmlspecialchars($filter_search) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label fw-semibold" style="font-size:.8rem;">Thể loại</label>
      <select name="the_loai" class="form-select form-select-sm">
        <option value="">Tất cả</option>
        <?php foreach ($the_loais as $tl): ?>
          <option value="<?= $tl['id'] ?>" <?= $filter_tl == $tl['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($tl['ten']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label fw-semibold" style="font-size:.8rem;">Trạng thái</label>
      <select name="trang_thai" class="form-select form-select-sm">
        <option value="tat_ca"  <?= $filter_tt=='tat_ca' ?'selected':'' ?>>Tất cả</option>
        <option value="het"     <?= $filter_tt=='het'    ?'selected':'' ?>>Hết hàng</option>
        <option value="sap_het" <?= $filter_tt=='sap_het'?'selected':'' ?>>Sắp hết (≤5)</option>
        <option value="con"     <?= $filter_tt=='con'    ?'selected':'' ?>>Còn hàng (>5)</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label fw-semibold" style="font-size:.8rem;">
        Tra cứu tại thời điểm
        <i class="bi bi-info-circle text-muted ms-1" title="Tính tồn kho theo lượng nhập - xuất đến ngày này"></i>
      </label>
      <input type="date" name="ngay" class="form-control form-control-sm"
             value="<?= htmlspecialchars($filter_ngay) ?>"
             max="<?= date('Y-m-d') ?>">
    </div>
    <div class="col-md-3 d-flex gap-2 align-items-end">
      <button type="submit" class="btn btn-sm btn-primary" style="border-radius:8px;">
        <i class="bi bi-funnel me-1"></i>Lọc
      </button>
      <a href="/nhasach/admin/inventory.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
        Xoá lọc
      </a>
    </div>
  </form>
  <?php if ($use_date): ?>
    <div class="mt-2 px-1">
      <small class="text-warning">
        <i class="bi bi-clock-history me-1"></i>
        Đang xem tồn kho tại thời điểm <strong><?= date('d/m/Y', strtotime($filter_ngay)) ?></strong>
        — kết quả dựa trên lịch sử nhập/xuất đến ngày đó.
      </small>
    </div>
  <?php endif; ?>
</div>

<!-- BẢNG TỒN KHO -->
<div class="admin-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="card-title mb-0">
      Bảng tồn kho
      <span class="text-muted" style="font-size:.82rem;font-weight:400;">
        (<?= $total ?> sách — trang <?= $trang_hien ?>/<?= $total_page ?>)
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
            <th>Sách</th>
            <th>Thể loại</th>
            <th class="text-center">Tồn kho<?= $use_date ? ' (tại '.date('d/m/Y', strtotime($filter_ngay)).')' : '' ?></th>
            <th class="text-end">Giá vốn BQ</th>
            <th class="text-end">Giá bán</th>
            <th class="text-end">Giá trị tồn</th>
            <th>Trạng thái</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sachs as $s):
            $ton = (int)$s['ton_kho'];
          ?>
          <tr style="cursor:pointer;"
              onclick="window.location='/nhasach/admin/inventory_detail.php?id=<?= $s['id'] ?>'"
              onmouseover="this.style.background='#fff8f3'"
              onmouseout="this.style.background=''">
            <td>
              <div class="fw-semibold" style="font-size:.88rem;"><?= htmlspecialchars($s['ten']) ?></div>
              <small class="text-muted"><?= htmlspecialchars($s['ma_sach']) ?></small>
            </td>
            <td><small><?= htmlspecialchars($s['ten_the_loai']) ?></small></td>
            <td class="text-center">
              <?php if ($ton <= 0): ?>
                <span class="badge bg-danger">Hết hàng</span>
              <?php elseif ($ton <= 5): ?>
                <span class="badge bg-warning text-dark"><?= $ton ?> <?= htmlspecialchars($s['don_vi_tinh']) ?></span>
              <?php else: ?>
                <span class="badge bg-success"><?= number_format($ton) ?> <?= htmlspecialchars($s['don_vi_tinh']) ?></span>
              <?php endif; ?>
            </td>
            <td class="text-end" style="font-size:.85rem;">
              <?= $s['gia_nhap'] > 0 ? number_format($s['gia_nhap'], 0, ',', '.').'₫' : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="text-end" style="font-size:.85rem;color:#e63946;">
              <?= $s['gia_ban'] > 0 ? number_format($s['gia_ban'], 0, ',', '.').'₫' : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="text-end" style="font-size:.85rem;font-weight:600;">
              <?= $ton > 0 && $s['gia_nhap'] > 0 ? number_format($ton * $s['gia_nhap'], 0, ',', '.').'₫' : '—' ?>
            </td>
            <td>
              <?php if (!$s['da_nhap_hang']): ?>
                <span class="badge bg-light text-secondary border">Chưa nhập hàng</span>
              <?php elseif ($ton <= 0): ?>
                <span class="badge bg-danger">Hết kho</span>
              <?php elseif ($ton <= 5): ?>
                <span class="badge bg-warning text-dark">Sắp hết</span>
              <?php else: ?>
                <span class="badge bg-success">Bình thường</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if (!$use_date): ?>
        <tfoot>
          <tr style="background:#f8f9fa;font-weight:700;">
            <td colspan="2">Tổng cộng trang này</td>
            <td class="text-center"><?= number_format(array_sum(array_column($sachs, 'ton_kho'))) ?></td>
            <td colspan="2"></td>
            <td class="text-end" style="color:#e63946;">
              <?= number_format(array_sum(array_map(fn($s) => $s['ton_kho'] * $s['gia_nhap'], $sachs)), 0, ',', '.') ?>₫
            </td>
            <td></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>

    <?php if ($total_page > 1): ?>
    <nav class="d-flex justify-content-center mt-3">
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $trang_hien <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= inv_url(['trang' => $trang_hien - 1]) ?>"><i class="bi bi-chevron-left"></i></a>
        </li>
        <?php
        $start = max(1, $trang_hien - 2);
        $end   = min($total_page, $trang_hien + 2);
        if ($start > 1): ?>
          <li class="page-item"><a class="page-link" href="<?= inv_url(['trang'=>1]) ?>">1</a></li>
          <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
        <?php endif; ?>
        <?php for ($p = $start; $p <= $end; $p++): ?>
          <li class="page-item <?= $p === $trang_hien ? 'active' : '' ?>">
            <a class="page-link" href="<?= inv_url(['trang'=>$p]) ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
        <?php if ($end < $total_page): ?>
          <?php if ($end < $total_page-1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
          <li class="page-item"><a class="page-link" href="<?= inv_url(['trang'=>$total_page]) ?>"><?= $total_page ?></a></li>
        <?php endif; ?>
        <li class="page-item <?= $trang_hien >= $total_page ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= inv_url(['trang' => $trang_hien + 1]) ?>"><i class="bi bi-chevron-right"></i></a>
        </li>
      </ul>
    </nav>
    <p class="text-center text-muted mt-1" style="font-size:.8rem;">Trang <?= $trang_hien ?>/<?= $total_page ?> — <?= $total ?> sách</p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php
require_once 'includes/admin_footer.php';
ob_end_flush();
?>