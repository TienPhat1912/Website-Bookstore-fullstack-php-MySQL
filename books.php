<?php
$page_title = 'Danh sách sách';
require_once 'includes/header.php';

$search      = trim($_GET['search']    ?? '');
$the_loai_id = (int)($_GET['the_loai'] ?? 0);
$gia_tu      = (int)($_GET['gia_tu']   ?? 0);
$gia_den     = (int)($_GET['gia_den']  ?? 0);
$sap_xep     = $_GET['sap_xep']        ?? 'moi_nhat';
$trang_hien  = max(1, (int)($_GET['trang'] ?? 1));
$moi_trang   = 12;

$where  = ["s.hien_trang = 1", "s.so_luong > 0"];
$params = [];

if ($search !== '') {
    $where[]  = "(s.ten LIKE ? OR s.tac_gia LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($the_loai_id > 0) {
    $where[]  = "s.the_loai_id = ?";
    $params[] = $the_loai_id;
}
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
    'gia_tang' => "ORDER BY gia_ban ASC",
    'gia_giam' => "ORDER BY gia_ban DESC",
    'ten_az'   => "ORDER BY s.ten ASC",
    default    => "ORDER BY s.ngay_tao DESC",
};

$stmt = $pdo->prepare("SELECT COUNT(*) FROM sach s $where_sql");
$stmt->execute($params);
$tong_sp    = $stmt->fetchColumn();
$tong_trang = max(1, ceil($tong_sp / $moi_trang));
$trang_hien = min($trang_hien, $tong_trang);
$offset     = ($trang_hien - 1) * $moi_trang;

$sql = "
    SELECT s.*, tl.ten AS ten_the_loai,
           ROUND(s.gia_nhap * (1 + s.ty_le_ln/100), 0) AS gia_ban
    FROM sach s
    JOIN the_loai tl ON s.the_loai_id = tl.id
    $where_sql $order_sql
    LIMIT $moi_trang OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sachs = $stmt->fetchAll();

$the_loais = $pdo->query("SELECT * FROM the_loai WHERE trang_thai = 1")->fetchAll();

$gia_max_db = 5000000;
function buildUrl(array $override = []): string {
    $p = array_merge($_GET, $override);
    unset($p['trang']);
    if (isset($override['trang'])) $p['trang'] = $override['trang'];
    return '/nhasach/books.php?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== '0' && $v !== 0));
}
?>

<!-- BAR THỂ LOẠI -->
<div style="background:#fff; border-bottom:1px solid #e9ecef; padding:12px 0;">
  <div class="container">
    <div class="d-flex gap-2 flex-wrap align-items-center">
      <a href="/nhasach/books.php" class="btn btn-sm px-3"
         style="border-radius:20px; font-size:.84rem;
                <?= $the_loai_id == 0 ? 'background:#1a1a2e;color:#fff;border:none;' : 'background:transparent;color:#555;border:1px solid #dee2e6;' ?>">
        Tất cả
      </a>
      <?php foreach ($the_loais as $tl): ?>
        <a href="<?= buildUrl(['the_loai' => $tl['id']]) ?>" class="btn btn-sm px-3"
           style="border-radius:20px; font-size:.84rem;
                  <?= $the_loai_id == $tl['id']
                      ? 'background:#f4a261;color:#fff;border:none;'
                      : 'background:transparent;color:#555;border:1px solid #dee2e6;' ?>">
          <?= htmlspecialchars($tl['ten']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="container py-4">
  <div class="row g-4">

    <!-- SIDEBAR TRÁI -->
    <div class="col-lg-3">
      <div class="filter-sidebar">

        <!-- Tìm kiếm nâng cao -->
        <div class="filter-title">
          <i class="bi bi-search me-2" style="color:#f4a261;"></i>Tìm kiếm nâng cao
        </div>

        <form action="/nhasach/books.php" method="GET" id="form-search">

          <!-- Tên sách -->
          <label class="form-label mb-1" style="font-size:.82rem; color:#666;">Tên sách / Tác giả</label>
          <input type="text" class="form-control form-control-sm mb-3" name="search"
                 placeholder="Nhập tên sách..."
                 value="<?= htmlspecialchars($search) ?>"
                 style="border-radius:8px;">

          <!-- Thể loại -->
          <label class="form-label mb-1" style="font-size:.82rem; color:#666;">Thể loại</label>
          <select class="form-select form-select-sm mb-3" name="the_loai" style="border-radius:8px;">
            <option value="">Tất cả</option>
            <?php foreach ($the_loais as $tl): ?>
              <option value="<?= $tl['id'] ?>" <?= $the_loai_id == $tl['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($tl['ten']) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <!-- Khoảng giá -->
          <label class="form-label mb-1" style="font-size:.82rem; color:#666;">Khoảng giá (₫)</label>
          <div class="d-flex align-items-center gap-1 mb-2">
            <input type="text" id="inp-gia-tu" name="gia_tu"
                   class="form-control form-control-sm text-center"
                   value="<?= $gia_tu ?: 0 ?>"
                   style="border-radius:8px;">
            <span class="text-muted px-1">—</span>
            <input type="text" id="inp-gia-den" name="gia_den"
                   class="form-control form-control-sm text-center"
                   value="<?= $gia_den ?: $gia_max_db ?>"
                   style="border-radius:8px;">
          </div>

          <!-- Thanh kéo đôi -->
          <div id="price-slider" style="position:relative; height:24px; margin:4px 6px 16px;">
            <div style="position:absolute; top:10px; left:0; right:0; height:4px;
                        background:#dee2e6; border-radius:4px;"></div>
            <div id="slider-track" style="position:absolute; top:10px; height:4px;
                                          background:#f4a261; border-radius:4px;"></div>
            <input type="range" id="range-min" min="0" max="<?= $gia_max_db ?>"
                   value="<?= $gia_tu ?: 0 ?>" step="10000"
                   style="position:absolute; width:100%; top:2px; appearance:none;
                          -webkit-appearance:none; background:transparent; pointer-events:none;">
            <input type="range" id="range-max" min="0" max="<?= $gia_max_db ?>"
                   value="<?= $gia_den ?: $gia_max_db ?>" step="10000"
                   style="position:absolute; width:100%; top:2px; appearance:none;
                          -webkit-appearance:none; background:transparent; pointer-events:none;">
          </div>

          <button type="submit" class="btn btn-sm w-100 fw-semibold"
                  style="background:#f4a261; color:#fff; border:none; border-radius:8px; padding:8px;">
            <i class="bi bi-search me-1"></i>Tìm kiếm
          </button>

        </form>
      </div>
    </div>

    <!-- NỘI DUNG PHẢI -->
    <div class="col-lg-9">

      <!-- Tiêu đề & sắp xếp -->
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h5 class="fw-bold mb-0" style="color:#1a1a2e;">
            <?php if ($search !== ''): ?>
              Kết quả: "<span style="color:#f4a261;"><?= htmlspecialchars($search) ?></span>"
            <?php elseif ($the_loai_id > 0): ?>
              <?php foreach ($the_loais as $tl) { if ($tl['id'] == $the_loai_id) echo htmlspecialchars($tl['ten']); } ?>
            <?php else: ?>
              Tất cả sách
            <?php endif; ?>
          </h5>
          <small class="text-muted">Tìm thấy <strong><?= $tong_sp ?></strong> cuốn</small>
        </div>
        <div class="d-flex align-items-center gap-2">
          <label class="text-muted" style="font-size:.84rem;">Sắp xếp:</label>
          <select class="form-select form-select-sm" style="width:160px; border-radius:8px;"
                  onchange="window.location='<?= buildUrl() ?>&sap_xep='+this.value">
            <option value="moi_nhat" <?= $sap_xep=='moi_nhat'?'selected':'' ?>>Mới nhất</option>
            <option value="gia_tang" <?= $sap_xep=='gia_tang'?'selected':'' ?>>Giá tăng dần</option>
            <option value="gia_giam" <?= $sap_xep=='gia_giam'?'selected':'' ?>>Giá giảm dần</option>
            <option value="ten_az"   <?= $sap_xep=='ten_az'  ?'selected':'' ?>>Tên A → Z</option>
          </select>
        </div>
      </div>

      <!-- Danh sách sách -->
      <?php if (count($sachs) > 0): ?>
        <div class="row g-3">
          <?php foreach ($sachs as $sach): ?>
            <?php include 'includes/card_sach.php'; ?>
          <?php endforeach; ?>
        </div>

        <?php if ($tong_trang > 1): ?>
          <nav class="mt-5 d-flex justify-content-center">
            <ul class="pagination">
              <li class="page-item <?= $trang_hien <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildUrl(['trang' => $trang_hien - 1]) ?>">
                  <i class="bi bi-chevron-left"></i>
                </a>
              </li>
              <?php
              $start = max(1, $trang_hien - 2);
              $end   = min($tong_trang, $trang_hien + 2);
              if ($start > 1): ?>
                <li class="page-item"><a class="page-link" href="<?= buildUrl(['trang'=>1]) ?>">1</a></li>
                <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
              <?php endif; ?>
              <?php for ($i = $start; $i <= $end; $i++): ?>
                <li class="page-item <?= $i==$trang_hien?'active':'' ?>">
                  <a class="page-link" href="<?= buildUrl(['trang'=>$i]) ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
              <?php if ($end < $tong_trang): ?>
                <?php if ($end < $tong_trang-1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <li class="page-item"><a class="page-link" href="<?= buildUrl(['trang'=>$tong_trang]) ?>"><?= $tong_trang ?></a></li>
              <?php endif; ?>
              <li class="page-item <?= $trang_hien >= $tong_trang ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildUrl(['trang' => $trang_hien + 1]) ?>">
                  <i class="bi bi-chevron-right"></i>
                </a>
              </li>
            </ul>
          </nav>
          <p class="text-center text-muted" style="font-size:.83rem;">
            Trang <?= $trang_hien ?> / <?= $tong_trang ?> &nbsp;·&nbsp; <?= $tong_sp ?> cuốn sách
          </p>
        <?php endif; ?>

      <?php else: ?>
        <div class="empty-state">
          <i class="bi bi-search"></i>
          <h5>Không tìm thấy sách nào</h5>
          <p class="text-muted">Thử thay đổi từ khoá hoặc bộ lọc khác nhé.</p>
          <a href="/nhasach/books.php" class="btn btn-accent mt-2 px-4">Xem tất cả sách</a>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<style>
#range-min, #range-max { height: 24px; }
#range-min::-webkit-slider-thumb,
#range-max::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 16px; height: 16px;
  border-radius: 50%;
  background: #f4a261;
  border: 2px solid #fff;
  box-shadow: 0 1px 4px rgba(0,0,0,.25);
  cursor: pointer;
  pointer-events: all;
}
#range-min::-moz-range-thumb,
#range-max::-moz-range-thumb {
  width: 16px; height: 16px;
  border-radius: 50%;
  background: #f4a261;
  border: 2px solid #fff;
  cursor: pointer;
  pointer-events: all;
}
</style>

<script>
const rangeMin  = document.getElementById('range-min');
const rangeMax  = document.getElementById('range-max');
const inpGiaTu  = document.getElementById('inp-gia-tu');
const inpGiaDen = document.getElementById('inp-gia-den');
const track     = document.getElementById('slider-track');
const maxVal    = <?= $gia_max_db ?>;

function fmt(n) { return parseInt(n).toLocaleString('vi-VN'); }
function parseFmt(s) { return parseInt(String(s).replace(/\./g,'').replace(/,/g,'')) || 0; }

function updateTrack() {
  const minPct = (rangeMin.value / maxVal) * 100;
  const maxPct = (rangeMax.value / maxVal) * 100;
  track.style.left  = minPct + '%';
  track.style.width = (maxPct - minPct) + '%';
}

rangeMin.addEventListener('input', function() {
  if (parseInt(this.value) >= parseInt(rangeMax.value) - 10000)
    this.value = parseInt(rangeMax.value) - 10000;
  inpGiaTu.value = fmt(this.value);
  updateTrack();
});
rangeMax.addEventListener('input', function() {
  if (parseInt(this.value) <= parseInt(rangeMin.value) + 10000)
    this.value = parseInt(rangeMin.value) + 10000;
  inpGiaDen.value = fmt(this.value);
  updateTrack();
});
inpGiaTu.addEventListener('blur', function() {
  let v = Math.max(0, Math.min(parseFmt(this.value), maxVal - 10000));
  rangeMin.value = v;
  this.value = fmt(v);
  updateTrack();
});
inpGiaDen.addEventListener('blur', function() {
  let v = Math.max(10000, Math.min(parseFmt(this.value), maxVal));
  rangeMax.value = v;
  this.value = fmt(v);
  updateTrack();
});
document.getElementById('form-search').addEventListener('submit', function() {
  inpGiaTu.value  = parseFmt(inpGiaTu.value) || 0;
  inpGiaDen.value = parseFmt(inpGiaDen.value) || 0;
});

inpGiaTu.value  = fmt(rangeMin.value);
inpGiaDen.value = fmt(rangeMax.value);
updateTrack();
</script>

<?php require_once 'includes/footer.php'; ?>