<?php
$page_title = 'Danh sách sách';
require_once 'includes/header.php';

// ============================================================
// XỬ LÝ THAM SỐ TÌM KIẾM & LỌC
// ============================================================
$search      = trim($_GET['search']     ?? '');
$the_loai_id = (int)($_GET['the_loai']  ?? 0);
$gia_tu      = (int)($_GET['gia_tu']    ?? 0);
$gia_den     = (int)($_GET['gia_den']   ?? 0);
$sap_xep     = $_GET['sap_xep']         ?? 'moi_nhat';
$trang_hien  = max(1, (int)($_GET['trang'] ?? 1));
$moi_trang   = 12;

// ============================================================
// BUILD QUERY ĐỘNG
// ============================================================
$where   = ["s.hien_trang = 1", "s.so_luong > 0"];
$params  = [];

if ($search !== '') {
    $where[]  = "(s.ten LIKE ? OR s.tac_gia LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($the_loai_id > 0) {
    $where[]  = "s.the_loai_id = ?";
    $params[] = $the_loai_id;
}

// Lọc theo khoảng giá (giá bán = gia_nhap * (1 + ty_le_ln/100))
if ($gia_tu > 0) {
    $where[]  = "ROUND(s.gia_nhap * (1 + s.ty_le_ln/100), 0) >= ?";
    $params[] = $gia_tu;
}
if ($gia_den > 0) {
    $where[]  = "ROUND(s.gia_nhap * (1 + s.ty_le_ln/100), 0) <= ?";
    $params[] = $gia_den;
}

$where_sql = "WHERE " . implode(" AND ", $where);

$order_sql = match($sap_xep) {
    'gia_tang'  => "ORDER BY gia_ban ASC",
    'gia_giam'  => "ORDER BY gia_ban DESC",
    'ten_az'    => "ORDER BY s.ten ASC",
    default     => "ORDER BY s.ngay_tao DESC",
};

// Đếm tổng để phân trang
$count_sql = "SELECT COUNT(*) FROM sach s $where_sql";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$tong_sp = $stmt->fetchColumn();
$tong_trang = max(1, ceil($tong_sp / $moi_trang));
$trang_hien = min($trang_hien, $tong_trang);
$offset = ($trang_hien - 1) * $moi_trang;

// Lấy sách trang hiện tại
$sql = "
    SELECT s.*, tl.ten AS ten_the_loai,
           ROUND(s.gia_nhap * (1 + s.ty_le_ln/100), 0) AS gia_ban
    FROM sach s
    JOIN the_loai tl ON s.the_loai_id = tl.id
    $where_sql
    $order_sql
    LIMIT $moi_trang OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sachs = $stmt->fetchAll();

// Lấy tất cả thể loại cho sidebar
$the_loais = $pdo->query("SELECT * FROM the_loai WHERE trang_thai = 1 ORDER BY ten")->fetchAll();

// Helper: build URL giữ nguyên các param khác
function buildUrl(array $override = []): string {
    $params = array_merge($_GET, $override);
    unset($params['trang']); // reset về trang 1 khi đổi filter
    if (isset($override['trang'])) $params['trang'] = $override['trang'];
    return '/nhasach/books.php?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== '0' && $v !== 0));
}
?>

<div class="container py-4">

  <!-- TIÊU ĐỀ & KẾT QUẢ -->
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h4 class="section-title mb-1">
        <?php if ($search !== ''): ?>
          Kết quả tìm kiếm: "<span style="color:#f4a261;"><?= htmlspecialchars($search) ?></span>"
        <?php elseif ($the_loai_id > 0): ?>
          <?php
            $tl_name = '';
            foreach ($the_loais as $tl) { if ($tl['id'] == $the_loai_id) $tl_name = $tl['ten']; }
          ?>
          Thể loại: <?= htmlspecialchars($tl_name) ?>
        <?php else: ?>
          Tất cả sách
        <?php endif; ?>
      </h4>
      <small class="text-muted">Tìm thấy <strong><?= $tong_sp ?></strong> cuốn sách</small>
    </div>

    <!-- Sắp xếp -->
    <div class="d-flex align-items-center gap-2">
      <label class="text-muted" style="font-size:.85rem; white-space:nowrap;">Sắp xếp:</label>
      <select class="form-select form-select-sm" style="width:160px; border-radius:8px;"
              onchange="window.location='<?= buildUrl() ?>&sap_xep='+this.value">
        <option value="moi_nhat"  <?= $sap_xep=='moi_nhat' ?'selected':'' ?>>Mới nhất</option>
        <option value="gia_tang"  <?= $sap_xep=='gia_tang'  ?'selected':'' ?>>Giá tăng dần</option>
        <option value="gia_giam"  <?= $sap_xep=='gia_giam'  ?'selected':'' ?>>Giá giảm dần</option>
        <option value="ten_az"    <?= $sap_xep=='ten_az'    ?'selected':'' ?>>Tên A → Z</option>
      </select>
    </div>
  </div>

  <div class="row g-4">

    <!-- SIDEBAR LỌC -->
    <div class="col-lg-3">
      <div class="filter-sidebar">

        <!-- Tìm kiếm cơ bản -->
        <div class="filter-title">Tìm kiếm</div>
        <form action="/nhasach/books.php" method="GET" class="mb-4">
          <div class="input-group input-group-sm">
            <input type="text" class="form-control" name="search"
                   placeholder="Tên sách, tác giả..."
                   value="<?= htmlspecialchars($search) ?>"
                   style="border-radius:8px 0 0 8px;">
            <button class="btn" type="submit"
                    style="background:#f4a261;color:#fff;border-radius:0 8px 8px 0;">
              <i class="bi bi-search"></i>
            </button>
          </div>
        </form>

        <!-- Thể loại -->
        <div class="filter-title">Thể loại</div>
        <div class="list-group list-group-flush mb-4">
          <a href="<?= buildUrl(['the_loai' => '']) ?>"
             class="list-group-item <?= $the_loai_id == 0 ? 'active' : '' ?>">
            <i class="bi bi-grid me-2"></i>Tất cả
          </a>
          <?php foreach ($the_loais as $tl): ?>
            <a href="<?= buildUrl(['the_loai' => $tl['id']]) ?>"
               class="list-group-item <?= $the_loai_id == $tl['id'] ? 'active' : '' ?>">
              <i class="bi bi-chevron-right me-1" style="font-size:.75rem;"></i>
              <?= htmlspecialchars($tl['ten']) ?>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- Tìm kiếm nâng cao -->
        <div class="filter-title">Tìm kiếm nâng cao</div>
        <form action="/nhasach/books.php" method="GET">
          <!-- Giữ lại search nếu có -->
          <?php if ($search): ?>
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
          <?php endif; ?>

          <label class="form-label mb-1" style="font-size:.82rem; color:#666;">Thể loại</label>
          <select class="form-select form-select-sm mb-2" name="the_loai" style="border-radius:8px;">
            <option value="">-- Tất cả --</option>
            <?php foreach ($the_loais as $tl): ?>
              <option value="<?= $tl['id'] ?>"
                      <?= $the_loai_id == $tl['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($tl['ten']) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <label class="form-label mb-1" style="font-size:.82rem; color:#666;">Giá từ (₫)</label>
          <input type="number" class="form-control form-control-sm mb-2" name="gia_tu"
                 placeholder="VD: 50000" value="<?= $gia_tu ?: '' ?>"
                 min="0" style="border-radius:8px;">

          <label class="form-label mb-1" style="font-size:.82rem; color:#666;">Giá đến (₫)</label>
          <input type="number" class="form-control form-control-sm mb-3" name="gia_den"
                 placeholder="VD: 200000" value="<?= $gia_den ?: '' ?>"
                 min="0" style="border-radius:8px;">

          <button type="submit" class="btn btn-sm w-100"
                  style="background:#1a1a2e; color:#fff; border-radius:8px;">
            <i class="bi bi-funnel me-1"></i> Lọc kết quả
          </button>
        </form>

        <!-- Nút xoá bộ lọc -->
        <?php if ($search || $the_loai_id || $gia_tu || $gia_den): ?>
          <a href="/nhasach/books.php" class="btn btn-sm btn-outline-secondary w-100 mt-2"
             style="border-radius:8px;">
            <i class="bi bi-x-circle me-1"></i> Xoá bộ lọc
          </a>
        <?php endif; ?>

      </div>
    </div>

    <!-- DANH SÁCH SÁCH -->
    <div class="col-lg-9">

      <?php if (count($sachs) > 0): ?>
        <div class="row g-3">
          <?php foreach ($sachs as $sach): ?>
            <?php include 'includes/card_sach.php'; ?>
          <?php endforeach; ?>
        </div>

        <!-- PHÂN TRANG -->
        <?php if ($tong_trang > 1): ?>
          <nav class="mt-5 d-flex justify-content-center">
            <ul class="pagination">

              <!-- Trang trước -->
              <li class="page-item <?= $trang_hien <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildUrl(['trang' => $trang_hien - 1]) ?>">
                  <i class="bi bi-chevron-left"></i>
                </a>
              </li>

              <?php
              // Hiển thị tối đa 5 số trang xung quanh trang hiện tại
              $start = max(1, $trang_hien - 2);
              $end   = min($tong_trang, $trang_hien + 2);
              if ($start > 1): ?>
                <li class="page-item">
                  <a class="page-link" href="<?= buildUrl(['trang' => 1]) ?>">1</a>
                </li>
                <?php if ($start > 2): ?>
                  <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
              <?php endif; ?>

              <?php for ($i = $start; $i <= $end; $i++): ?>
                <li class="page-item <?= $i == $trang_hien ? 'active' : '' ?>">
                  <a class="page-link" href="<?= buildUrl(['trang' => $i]) ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>

              <?php if ($end < $tong_trang): ?>
                <?php if ($end < $tong_trang - 1): ?>
                  <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
                <li class="page-item">
                  <a class="page-link" href="<?= buildUrl(['trang' => $tong_trang]) ?>"><?= $tong_trang ?></a>
                </li>
              <?php endif; ?>

              <!-- Trang sau -->
              <li class="page-item <?= $trang_hien >= $tong_trang ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildUrl(['trang' => $trang_hien + 1]) ?>">
                  <i class="bi bi-chevron-right"></i>
                </a>
              </li>

            </ul>
          </nav>
          <p class="text-center text-muted" style="font-size:.83rem;">
            Trang <?= $trang_hien ?> / <?= $tong_trang ?>
            &nbsp;·&nbsp; <?= $tong_sp ?> cuốn sách
          </p>
        <?php endif; ?>

      <?php else: ?>
        <!-- Không có kết quả -->
        <div class="empty-state">
          <i class="bi bi-search"></i>
          <h5>Không tìm thấy sách nào</h5>
          <p class="text-muted">Thử thay đổi từ khoá hoặc bộ lọc khác nhé.</p>
          <a href="/nhasach/books.php" class="btn btn-accent mt-2 px-4">
            Xem tất cả sách
          </a>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>