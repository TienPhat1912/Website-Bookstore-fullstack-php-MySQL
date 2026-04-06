<?php
ob_start();
$page_title = 'Tồn kho';
require_once 'includes/admin_header.php';

// ---- MỨC CẢNH BÁO HẾT HÀNG ----
if (isset($_POST['doi_muc_canh_bao'])) {
    $muc_moi = max(1, (int)$_POST['muc_canh_bao']);
    $_SESSION['muc_canh_bao'] = $muc_moi;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}
$muc_canh_bao = (int)($_SESSION['muc_canh_bao'] ?? 5);

// ---- THỐNG KÊ TỔNG QUAN (luôn theo thực tế hiện tại) ----
$tong_sach   = $pdo->query("SELECT COUNT(*) FROM sach WHERE hien_trang = 1")->fetchColumn();
$tong_ton    = $pdo->query("SELECT COALESCE(SUM(so_luong),0) FROM sach WHERE hien_trang = 1")->fetchColumn();
$gia_tri_kho = $pdo->query("SELECT COALESCE(SUM(so_luong * gia_nhap),0) FROM sach WHERE hien_trang = 1")->fetchColumn();
$so_het_hang = $pdo->query("SELECT COUNT(*) FROM sach WHERE so_luong = 0 AND hien_trang = 1")->fetchColumn();
$stmt_sap = $pdo->prepare("SELECT COUNT(*) FROM sach WHERE so_luong > 0 AND so_luong <= ? AND hien_trang = 1");
$stmt_sap->execute([$muc_canh_bao]);
$so_sap_het = $stmt_sap->fetchColumn();

// ---- BỘ LỌC ----
$filter_search  = trim($_GET['search']    ?? '');
$filter_tl      = (int)($_GET['the_loai'] ?? 0);
$filter_tt      = $_GET['trang_thai']     ?? 'tat_ca';
$filter_ngay    = trim($_GET['ngay']      ?? ''); // thời điểm tra cứu
$filter_sort    = $_GET['sort'] ?? 'ton_asc';
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
$gia_nhap_subquery = "
        COALESCE((
            SELECT ROUND(SUM(cn2.so_luong * cn2.don_gia) / NULLIF(SUM(cn2.so_luong), 0), 0)
            FROM chi_tiet_nhap cn2
            JOIN phieu_nhap pn2 ON pn2.id = cn2.phieu_nhap_id
            WHERE cn2.sach_id = s.id AND pn2.trang_thai = 'done'
              AND pn2.ngay_nhap <= $ngay_safe
        ), 0)
    ";
} else {
    $ton_subquery = "s.so_luong";
    $gia_nhap_subquery = "s.gia_nhap";
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
if ($filter_tt === 'sap_het') $having = "HAVING ton_kho > 0 AND ton_kho <= $muc_canh_bao";
if ($filter_tt === 'con')     $having = "HAVING ton_kho > $muc_canh_bao";

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

switch($filter_sort) {
    case 'ton_desc':      $order = 'ton_kho DESC'; break;
    case 'gia_nhap_asc':  $order = 's.gia_nhap ASC'; break;
    case 'gia_nhap_desc': $order = 's.gia_nhap DESC'; break;
    case 'gia_ban_asc':   $order = 'gia_ban ASC'; break;
    case 'gia_ban_desc':  $order = 'gia_ban DESC'; break;
    case 'tt_chua':       $order = 's.da_nhap_hang ASC, ton_kho ASC'; break;
    case 'tt_het':        $order = 'ton_kho ASC, s.ten ASC'; break;
    default:              $order = 'ton_kho ASC'; break;
}

$sql = "
    SELECT s.id, s.ma_sach, s.ten, $gia_nhap_subquery AS gia_nhap, s.ty_le_ln, s.da_nhap_hang, s.don_vi_tinh,
           tl.ten AS ten_the_loai,
           ROUND($gia_nhap_subquery * (1 + s.ty_le_ln / 100), 0) AS gia_ban,
           $ton_subquery AS ton_kho
    FROM sach s JOIN the_loai tl ON tl.id = s.the_loai_id
    WHERE $where_sql
    $having
    ORDER BY $order, s.ten ASC
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
  <div class="d-flex gap-2 flex-wrap">
    <button type="button" class="btn btn-sm btn-outline-primary" style="border-radius:8px;"
            onclick="togglePanel('panelTonKho')">
      <i class="bi bi-search me-1"></i>Tra cứu tồn kho
    </button>
    <button type="button" class="btn btn-sm btn-outline-info" style="border-radius:8px;"
            onclick="togglePanel('panelNhapXuat')">
      <i class="bi bi-arrow-left-right me-1"></i>Tra cứu nhập xuất
    </button>
    <button type="button" class="btn btn-sm btn-outline-warning" style="border-radius:8px;"
            onclick="togglePanel('panelCanhBao')">
      <i class="bi bi-bell me-1"></i>Cảnh báo: ≤<?= $muc_canh_bao ?>
    </button>
    <a href="/nhasach/admin/inventory_report.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
      <i class="bi bi-bar-chart me-1"></i>Báo cáo
    </a>
    <a href="/nhasach/admin/import.php" class="btn btn-sm"
       style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
      <i class="bi bi-box-arrow-in-down me-1"></i>Nhập hàng
    </a>
  </div>
</div>

<script>
function togglePanel(id) {
  document.querySelectorAll('.inv-panel').forEach(p => {
    if (p.id !== id) { p.style.display = 'none'; }
  });
  const el = document.getElementById(id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>

<!-- PANEL: Tra cứu tồn kho -->
<div id="panelTonKho" class="inv-panel admin-card mb-3" style="display:none;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="fw-semibold" style="color:#5b9fff;"><i class="bi bi-search me-2"></i>Tra cứu tồn kho tại thời điểm</div>
    <button type="button" class="btn-close" onclick="togglePanel('panelTonKho')"></button>
  </div>
  <form method="GET" action="/nhasach/admin/inventory.php">
    <div class="row g-2 align-items-end">
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
          <option value="tat_ca">Tất cả</option>
          <option value="het">Hết hàng</option>
          <option value="sap_het">Sắp hết (≤<?= $muc_canh_bao ?>)</option>
          <option value="con">Còn hàng</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label fw-semibold" style="font-size:.8rem;">
          Tại thời điểm
          <small class="text-muted fw-normal">(để trống = hiện tại)</small>
        </label>
        <input type="date" name="ngay" class="form-control form-control-sm"
               value="<?= htmlspecialchars($filter_ngay) ?>">
      </div>
      <div class="col-md-auto">
        <button type="submit" class="btn btn-sm btn-primary" style="border-radius:8px;">
          <i class="bi bi-search me-1"></i>Tra cứu
        </button>
      </div>
    </div>
  </form>
</div>

<!-- PANEL: Tra cứu nhập xuất -->
<div id="panelNhapXuat" class="inv-panel admin-card mb-3" style="display:none;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="fw-semibold" style="color:#3fe0a0;"><i class="bi bi-arrow-left-right me-2"></i>Tra cứu nhập xuất theo khoảng thời gian</div>
    <button type="button" class="btn-close" onclick="togglePanel('panelNhapXuat')"></button>
  </div>
  <form method="GET" action="/nhasach/admin/inventory_detail.php">
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label fw-semibold" style="font-size:.8rem;">Chọn sản phẩm <span class="text-danger">*</span></label>
        <?php
        $ds_sach = $pdo->query("SELECT id, ma_sach, ten FROM sach WHERE hien_trang = 1 ORDER BY ten")->fetchAll();
        ?>
        <div style="position:relative;">
          <input type="text" id="sachSearch" class="form-control form-control-sm"
                 placeholder="Nhập tên hoặc mã sách..."
                 autocomplete="off">
          <input type="hidden" name="id" id="sachId">
          <div id="sachDropdown" style="
            display:none; position:absolute; z-index:999; background:#fff;
            border:1px solid #dee2e6; border-radius:8px; width:100%;
            max-height:220px; overflow-y:auto; box-shadow:0 4px 12px rgba(0,0,0,.1);
            top:calc(100% + 4px); left:0;
          "></div>
        </div>
        <script>
        var _sachData = <?= json_encode(array_map(fn($s) => ['id' => $s['id'], 'ten' => $s['ten'], 'ma' => $s['ma_sach']], $ds_sach)) ?>;

        function initSachSearch() {
          var input    = document.getElementById('sachSearch');
          var hidden   = document.getElementById('sachId');
          var dropdown = document.getElementById('sachDropdown');
          if (!input || input._init) return;
          input._init = true;

          function renderList(items) {
            dropdown.innerHTML = '';
            if (!items.length) {
              dropdown.innerHTML = '<div style="padding:10px 14px;color:#999;font-size:.83rem;">Không tìm thấy</div>';
            } else {
              items.slice(0, 30).forEach(function(s) {
                var div = document.createElement('div');
                div.style.cssText = 'padding:8px 14px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #f3f3f3;';
                div.innerHTML = '<span style="font-weight:600;">' + s.ten + '</span> <small style="color:#aaa;">' + s.ma + '</small>';
                div.addEventListener('mousedown', function() {
                  input.value  = s.ten + ' (' + s.ma + ')';
                  hidden.value = s.id;
                  input.style.borderColor = '';
                  dropdown.style.display = 'none';
                });
                div.addEventListener('mouseover', function() { div.style.background = '#fff8f3'; });
                div.addEventListener('mouseout',  function() { div.style.background = ''; });
                dropdown.appendChild(div);
              });
            }
            dropdown.style.display = 'block';
          }

          input.addEventListener('input', function() {
            hidden.value = '';
            var q = this.value.trim().toLowerCase();
            if (!q) { dropdown.style.display = 'none'; return; }
            renderList(_sachData.filter(function(s) {
              return s.ten.toLowerCase().indexOf(q) >= 0 || s.ma.toLowerCase().indexOf(q) >= 0;
            }));
          });

          input.addEventListener('focus', function() {
            if (this.value.trim()) this.dispatchEvent(new Event('input'));
          });

          document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !dropdown.contains(e.target))
              dropdown.style.display = 'none';
          });

          input.closest('form').addEventListener('submit', function(e) {
            if (!hidden.value) {
              e.preventDefault();
              input.style.borderColor = '#e63946';
              input.focus();
            } else {
              input.style.borderColor = '';
            }
          });
        }

        // Chạy ngay khi DOM xong, và cả khi panel được mở
        document.addEventListener('DOMContentLoaded', initSachSearch);
        document.addEventListener('click', function(e) {
          if (e.target.closest('[onclick*="panelNhapXuat"]')) {
            setTimeout(initSachSearch, 50);
          }
        });
        </script>
      </div>
      <div class="col-md-2">
        <label class="form-label fw-semibold" style="font-size:.8rem;">Từ ngày <span class="text-danger">*</span></label>
        <input type="date" name="tu_ngay" class="form-control form-control-sm" required>
      </div>
      <div class="col-md-2">
        <label class="form-label fw-semibold" style="font-size:.8rem;">Đến ngày <span class="text-danger">*</span></label>
        <input type="date" name="den_ngay" class="form-control form-control-sm" required>
      </div>
      <div class="col-md-auto">
        <button type="submit" class="btn btn-sm" style="background:#3fe0a0;color:#fff;border-radius:8px;">
          <i class="bi bi-search me-1"></i>Tra cứu
        </button>
      </div>
    </div>
  </form>
</div>

<!-- PANEL: Mức cảnh báo -->
<div id="panelCanhBao" class="inv-panel admin-card mb-3" style="display:none;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="fw-semibold" style="color:#ff9f1c;"><i class="bi bi-bell me-2"></i>Thay đổi mức cảnh báo hết hàng</div>
    <button type="button" class="btn-close" onclick="togglePanel('panelCanhBao')"></button>
  </div>
  <form method="POST" action="/nhasach/admin/inventory.php?<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') ?>">
    <input type="hidden" name="doi_muc_canh_bao" value="1">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label fw-semibold" style="font-size:.8rem;">
          Ngưỡng cảnh báo
          <small class="text-muted fw-normal">(tồn ≤ ngưỡng → Sắp hết)</small>
        </label>
        <input type="number" name="muc_canh_bao" class="form-control form-control-sm"
               min="1" max="9999" value="<?= $muc_canh_bao ?>" required>
      </div>
      <div class="col-md-auto">
        <button type="submit" class="btn btn-sm btn-warning" style="border-radius:8px;">
          <i class="bi bi-check2 me-1"></i>Lưu
        </button>
      </div>
    </div>
  </form>
</div>

<!-- TỔNG QUAN -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="admin-card admin-stat-card text-center py-3">
      <div style="font-size:1.6rem;font-weight:700;color:#5b9fff;"><?= number_format($tong_sach) ?></div>
      <div style="font-size:.8rem;color:#888;">Đầu sách đang bán</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card admin-stat-card text-center py-3">
      <div style="font-size:1.6rem;font-weight:700;color:#3fe0a0;"><?= number_format($tong_ton) ?></div>
      <div style="font-size:.8rem;color:#888;">Tổng tồn kho hiện tại</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card admin-stat-card text-center py-3">
      <div style="font-size:1.3rem;font-weight:700;color:#f4a261;"><?= number_format($gia_tri_kho/1000000, 1) ?>tr₫</div>
      <div style="font-size:.8rem;color:#888;">Giá trị tồn kho (giá vốn)</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="admin-card admin-stat-card text-center py-3">
      <div style="font-size:1.6rem;font-weight:700;color:#e63946;"><?= $so_het_hang ?></div>
      <div style="font-size:.8rem;color:#888;">
        Hết hàng &nbsp;|&nbsp; <span style="color:#ff9f1c;"><?= $so_sap_het ?></span> sắp hết
      </div>
    </div>
  </div>
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
    <form method="GET" action="/nhasach/admin/inventory.php" class="d-flex align-items-center gap-2">
      <?php foreach ($_GET as $k => $v): if ($k === 'sort') continue; ?>
        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
      <?php endforeach; ?>
      <select name="sort" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
        <option value="ton_asc"       <?= $filter_sort=='ton_asc'       ?'selected':'' ?>>Tồn kho tăng</option>
        <option value="ton_desc"      <?= $filter_sort=='ton_desc'       ?'selected':'' ?>>Tồn kho giảm</option>
        <option value="gia_nhap_asc"  <?= $filter_sort=='gia_nhap_asc'  ?'selected':'' ?>>Giá vốn tăng</option>
        <option value="gia_nhap_desc" <?= $filter_sort=='gia_nhap_desc' ?'selected':'' ?>>Giá vốn giảm</option>
        <option value="gia_ban_asc"   <?= $filter_sort=='gia_ban_asc'   ?'selected':'' ?>>Giá bán tăng</option>
        <option value="gia_ban_desc"  <?= $filter_sort=='gia_ban_desc'  ?'selected':'' ?>>Giá bán giảm</option>
        <option value="tt_chua"       <?= $filter_sort=='tt_chua'       ?'selected':'' ?>>Chưa nhập hàng</option>
        <option value="tt_het"        <?= $filter_sort=='tt_het'        ?'selected':'' ?>>Hết hàng trước</option>
      </select>
    </form>
  </div>

  <?php if ($use_date): ?>
    <div class="mb-3 px-3 py-2 rounded"
         style="background:#eef7ff; border:1px solid #b9ddff; color:#245c8a; font-size:.85rem;">
      <i class="bi bi-calendar-event me-2"></i>
      Đang tra cứu tồn kho tại ngày <strong><?= date('d/m/Y', strtotime($filter_ngay)) ?></strong>.
    </div>
  <?php endif; ?>

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
              <?php elseif ($ton <= $muc_canh_bao): ?>
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
              <?php elseif ($ton <= $muc_canh_bao): ?>
                <span class="badge bg-warning text-dark">Sắp hết (≤<?= $muc_canh_bao ?>)</span>
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
