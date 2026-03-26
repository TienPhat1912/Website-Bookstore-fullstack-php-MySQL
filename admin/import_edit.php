<?php
ob_start();
$page_title = 'Chi tiết phiếu nhập';
require_once 'includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /nhasach/admin/import.php'); exit; }

$phieu = $pdo->prepare("SELECT * FROM phieu_nhap WHERE id = ?");
$phieu->execute([$id]);
$phieu = $phieu->fetch();
if (!$phieu) { header('Location: /nhasach/admin/import.php'); exit; }

$is_draft = $phieu['trang_thai'] === 'draft';
$action   = $_REQUEST['action'] ?? '';

// ---- HOÀN THÀNH ----
if ($action === 'complete' && $is_draft) {
    // Reload phieu để check ngày
    $p_check = $pdo->prepare("SELECT ngay_nhap FROM phieu_nhap WHERE id = ?");
    $p_check->execute([$id]);
    $ngay_check = $p_check->fetchColumn();
    if (empty($ngay_check) || $ngay_check === '0000-00-00') {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Vui lòng nhập ngày nhập hàng trước khi hoàn thành phiếu.'];
        header("Location: /nhasach/admin/import_edit.php?id=$id"); exit;
    }
    $rows = $pdo->prepare("SELECT cn.*, s.so_luong AS ton_kho, s.gia_nhap AS gia_nhap_cu FROM chi_tiet_nhap cn JOIN sach s ON s.id = cn.sach_id WHERE cn.phieu_nhap_id = ?");
    $rows->execute([$id]);
    $rows = $rows->fetchAll();
    if (empty($rows)) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Phiếu chưa có sách nào.'];
    } else {
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                // Lấy lại đúng từ sach để tránh race condition
                $s = $pdo->prepare("SELECT so_luong, gia_nhap FROM sach WHERE id = ?");
                $s->execute([$row['sach_id']]);
                $sach = $s->fetch();
                $ton     = (int)$sach['so_luong'];
                $gia_cu  = (float)$sach['gia_nhap'];
                $sl      = (int)$row['so_luong'];  // số lượng nhập từ chi_tiet_nhap
                $gia_moi = (float)$row['don_gia'];
                $bq = ($ton + $sl > 0) ? ($ton * $gia_cu + $sl * $gia_moi) / ($ton + $sl) : $gia_moi;
                $pdo->prepare("UPDATE sach SET so_luong = so_luong + ?, gia_nhap = ROUND(?,0), da_nhap_hang = 1 WHERE id = ?")
                    ->execute([$sl, $bq, $row['sach_id']]);
            }
            $pdo->prepare("UPDATE phieu_nhap SET trang_thai = 'done' WHERE id = ?")->execute([$id]);
            $pdo->commit();
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Hoàn thành phiếu! Tồn kho & giá bình quân đã cập nhật.'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Lỗi: ' . $e->getMessage()];
        }
    }
    header("Location: /nhasach/admin/import_edit.php?id=$id"); exit;
}

// ---- XOÁ DÒNG ----
if ($action === 'delete_item' && $is_draft && isset($_GET['item_id'])) {
    $pdo->prepare("DELETE FROM chi_tiet_nhap WHERE id = ? AND phieu_nhap_id = ?")
        ->execute([(int)$_GET['item_id'], $id]);
    header("Location: /nhasach/admin/import_edit.php?id=$id"); exit;
}

// ---- XOÁ PHIẾU ----
if ($action === 'delete_phieu' && $is_draft) {
    $pdo->prepare("DELETE FROM chi_tiet_nhap WHERE phieu_nhap_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM phieu_nhap WHERE id = ?")->execute([$id]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã xoá phiếu nhập.'];
    header('Location: /nhasach/admin/import.php'); exit;
}

// ---- AUTO-SAVE THÔNG TIN PHIẾU (AJAX) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_info' && $is_draft) {
    $ngay = trim($_POST['ngay_nhap'] ?? '');
    if (empty($ngay)) {
        echo json_encode(['ok' => false, 'msg' => 'Ngày nhập không được để trống.']); exit;
    }
    $pdo->prepare("UPDATE phieu_nhap SET ngay_nhap = ?, ghi_chu = ? WHERE id = ?")
        ->execute([$ngay, trim($_POST['ghi_chu'] ?? ''), $id]);
    echo json_encode(['ok' => true]); exit;
}

// ---- THÊM SÁCH ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_item' && $is_draft) {
    // Reload phieu từ DB để lấy ngày mới nhất
    $phieu_check = $pdo->prepare("SELECT ngay_nhap FROM phieu_nhap WHERE id = ?");
    $phieu_check->execute([$id]);
    $ngay_check = $phieu_check->fetchColumn();
    if (empty($ngay_check) || $ngay_check === '0000-00-00') {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Vui lòng nhập ngày nhập hàng trước khi thêm sách.'];
        header("Location: /nhasach/admin/import_edit.php?id=$id"); exit;
    }
    $sach_id  = (int)$_POST['sach_id'];
    $so_luong = (int)$_POST['so_luong'];
    $don_gia  = (float)$_POST['don_gia'];
    $errs = [];
    if ($sach_id <= 0)  $errs[] = 'Chưa chọn sách.';
    if ($so_luong <= 0) $errs[] = 'Số lượng phải > 0.';
    if ($don_gia <= 0)  $errs[] = 'Đơn giá phải > 0.';
    if (empty($errs)) {
        $exist = $pdo->prepare("SELECT id FROM chi_tiet_nhap WHERE phieu_nhap_id = ? AND sach_id = ?");
        $exist->execute([$id, $sach_id]);
        $row = $exist->fetch();
        if ($row) {
            $pdo->prepare("UPDATE chi_tiet_nhap SET so_luong = so_luong + ?, don_gia = ? WHERE id = ?")
                ->execute([$so_luong, $don_gia, $row['id']]);
        } else {
            $pdo->prepare("INSERT INTO chi_tiet_nhap (phieu_nhap_id, sach_id, so_luong, don_gia) VALUES (?,?,?,?)")
                ->execute([$id, $sach_id, $so_luong, $don_gia]);
        }
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã thêm sách vào phiếu.'];
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => implode(' ', $errs)];
    }
    header("Location: /nhasach/admin/import_edit.php?id=$id"); exit;
}

// Load dữ liệu
$phieu = $pdo->prepare("SELECT * FROM phieu_nhap WHERE id = ?");
$phieu->execute([$id]);
$phieu = $phieu->fetch();
$is_draft = $phieu['trang_thai'] === 'draft';

$items = $pdo->prepare("
    SELECT cn.*, s.ten AS ten_sach, s.ma_sach, s.gia_nhap AS gia_hien_tai,
           s.so_luong AS ton_hien_tai, s.don_vi_tinh
    FROM chi_tiet_nhap cn JOIN sach s ON s.id = cn.sach_id
    WHERE cn.phieu_nhap_id = ? ORDER BY cn.id
");
$items->execute([$id]);
$items = $items->fetchAll();
$tong  = array_sum(array_map(fn($i) => $i['so_luong'] * $i['don_gia'], $items));

$sach_list = $pdo->query("
    SELECT id, ma_sach, ten, gia_nhap, so_luong, don_vi_tinh,
           CASE WHEN gia_nhap > 0
               THEN CONCAT('Giá nhập BQ lần trước: ', FORMAT(gia_nhap,0), '₫')
               ELSE 'Chưa có giá nhập — nhập giá mới'
           END AS gia_ghi_chu
    FROM sach ORDER BY ten
")->fetchAll();
?>

<div class="page-header">
  <h5>
    <i class="bi bi-box-arrow-in-down me-2" style="color:#f4a261;"></i>
    Phiếu: <strong><?= htmlspecialchars($phieu['ma_phieu']) ?></strong>
    <span class="badge <?= $is_draft ? 'bg-warning text-dark' : 'bg-success' ?> ms-2">
      <?= $is_draft ? 'Đang soạn' : 'Đã hoàn thành' ?>
    </span>
  </h5>
  <div class="d-flex gap-2">
    <?php if ($is_draft): ?>
      <?php if (!empty($items)): ?>
        <a href="?id=<?= $id ?>&action=complete" class="btn btn-sm btn-success" style="border-radius:8px;"
           onclick="return confirm('Hoàn thành phiếu? Tồn kho sẽ được cập nhật và không thể sửa sau đó.')">
          <i class="bi bi-check2-circle me-1"></i>Hoàn thành & Cập nhật kho
        </a>
      <?php endif; ?>
      <a href="?id=<?= $id ?>&action=delete_phieu" class="btn btn-sm btn-outline-danger" style="border-radius:8px;"
         onclick="return confirm('Xoá hẳn phiếu này?')">
        <i class="bi bi-trash3 me-1"></i>Xoá phiếu
      </a>
    <?php endif; ?>
    <a href="/nhasach/admin/import.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
      <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
  </div>
</div>

<!-- HÀNG TRÊN: thông tin phiếu | thêm sách -->
<div class="row g-4 mb-4">

  <!-- THÔNG TIN PHIẾU -->
  <div class="col-lg-4">
    <div class="admin-card h-100">
      <div class="card-title d-flex align-items-center gap-2">
        Thông tin phiếu
        <span id="save-status" style="font-size:.75rem; color:#28a745; display:none;">
          <i class="bi bi-check-circle me-1"></i>Đã lưu
        </span>
      </div>

      <?php if ($is_draft): ?>
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.85rem;">Ngày nhập hàng <span class="text-danger">*</span></label>
          <input type="date" id="inp-ngay" class="form-control"
                 value="<?= htmlspecialchars($phieu['ngay_nhap'] && $phieu['ngay_nhap'] !== '0000-00-00' ? $phieu['ngay_nhap'] : date('Y-m-d')) ?>"
                 max="<?= date('Y-m-d') ?>">
          <small class="text-muted">Cho phép chọn ngày trong quá khứ.</small>
        </div>
        <div>
          <label class="form-label fw-semibold" style="font-size:.85rem;">Ghi chú</label>
          <textarea id="inp-ghichu" class="form-control" rows="4"
                    placeholder="VD: Nhập hàng tháng 3, từ NXB Kim Đồng..."><?= htmlspecialchars($phieu['ghi_chu'] ?? '') ?></textarea>
        </div>
      <?php else: ?>
        <div style="font-size:.88rem;" class="row g-2">
          <div class="col-5 text-muted">Mã phiếu</div>
          <div class="col-7 fw-semibold"><?= htmlspecialchars($phieu['ma_phieu']) ?></div>
          <div class="col-5 text-muted">Ngày nhập</div>
          <div class="col-7 fw-semibold">
            <?= ($phieu['ngay_nhap'] && $phieu['ngay_nhap'] !== '0000-00-00') ? date('d/m/Y', strtotime($phieu['ngay_nhap'])) : '<span class="text-danger">—</span>' ?>
          </div>
          <div class="col-5 text-muted">Ngày tạo</div>
          <div class="col-7"><?= date('d/m/Y H:i', strtotime($phieu['ngay_tao'])) ?></div>
          <?php if (!empty($phieu['ghi_chu'])): ?>
            <div class="col-5 text-muted">Ghi chú</div>
            <div class="col-7"><?= htmlspecialchars($phieu['ghi_chu']) ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- THÊM SÁCH -->
  <?php if ($is_draft): ?>
  <div class="col-lg-8">
    <div class="admin-card h-100">
      <div class="card-title">Thêm sách vào phiếu</div>
      <form method="POST" action="?id=<?= $id ?>">
        <input type="hidden" name="action" value="add_item">
        <div class="row g-3">
          <div class="col-md-5">
            <input type="text" id="sach-search" class="form-control form-control-sm mb-1"
                   placeholder="Tìm tên hoặc mã sách...">
            <select name="sach_id" id="sach-select" class="form-select form-select-sm" size="6" style="height:auto;">
              <?php foreach ($sach_list as $s): ?>
                <option value="<?= $s['id'] ?>"
                        data-gia="<?= $s['gia_nhap'] ?>"
                        data-ghichu="<?= htmlspecialchars($s['gia_ghi_chu']) ?>"
                        data-dvt="<?= htmlspecialchars($s['don_vi_tinh']) ?>">
                  [<?= htmlspecialchars($s['ma_sach']) ?>] <?= htmlspecialchars($s['ten']) ?> (tồn: <?= $s['so_luong'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Nhấp để chọn sách</small>
          </div>
          <div class="col-md-7">
            <div class="row g-2 mb-2">
              <div class="col-6">
                <label class="form-label fw-semibold" style="font-size:.82rem;">Số lượng *</label>
                <input type="number" name="so_luong" id="inp-soluong" class="form-control form-control-sm" min="1" value="1">
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold" style="font-size:.82rem;">Đơn giá nhập (₫) *</label>
                <input type="text" id="inp-dongia-display" class="form-control form-control-sm" placeholder="0" inputmode="numeric" autocomplete="off">
                <input type="hidden" name="don_gia" id="inp-dongia">
              </div>
            </div>
            <div id="gia-source-note" style="display:none; font-size:.78rem; color:#666; padding:6px 10px;
                 background:#f8f9fa; border-radius:6px; border-left:3px solid #f4a261; margin-bottom:8px;"></div>
            <div id="gia-preview" style="display:none; font-size:.82rem; padding:8px 10px;
                 background:#fff8e8; border:1px solid #ffd970; border-radius:6px; margin-bottom:8px;"></div>
            <button type="submit" class="btn btn-sm w-100"
                    style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
              <i class="bi bi-plus-lg me-1"></i>Thêm vào phiếu
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- HÀNG DƯỚI: danh sách hàng nhập -->
<div class="admin-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="card-title mb-0">Danh sách hàng nhập (<?= count($items) ?> sách)</div>
    <div style="font-size:.85rem; color:#888;">
      Tổng: <strong style="color:#e63946;"><?= number_format($tong, 0, ',', '.') ?>₫</strong>
    </div>
  </div>

  <?php if (empty($items)): ?>
    <p class="text-muted text-center py-4">Chưa có sách nào.<?= $is_draft ? ' Thêm sách từ form bên trên.' : '' ?></p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table admin-table table-hover">
        <thead>
          <tr>
            <th>#</th><th>Sách</th>
            <th class="text-end">Tồn hiện tại</th>
            <th class="text-end">SL nhập</th>
            <th class="text-end">Đơn giá nhập</th>
            <th class="text-end">Thành tiền</th>
            <?php if ($is_draft): ?><th style="width:40px;"></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $i => $item):
            $tt     = $item['so_luong'] * $item['don_gia'];
            $ton    = (int)$item['ton_hien_tai'];
            $sl     = (int)$item['so_luong'];
            $gia_cu = (float)$item['gia_hien_tai'];
            $gia_moi= (float)$item['don_gia'];
            $bq     = ($ton + $sl > 0) ? ($ton * $gia_cu + $sl * $gia_moi) / ($ton + $sl) : $gia_moi;
          ?>
          <tr>
            <td style="color:#888;"><?= $i+1 ?></td>
            <td>
              <div class="fw-semibold" style="font-size:.88rem;"><?= htmlspecialchars($item['ten_sach']) ?></div>
              <small class="text-muted"><?= htmlspecialchars($item['ma_sach']) ?></small>
            </td>
            <td class="text-end">
              <span class="badge <?= $ton == 0 ? 'bg-danger' : 'bg-secondary' ?>">
                <?= $ton ?> <?= htmlspecialchars($item['don_vi_tinh']) ?>
              </span>
            </td>
            <td class="text-end fw-semibold"><?= number_format($sl) ?></td>
            <td class="text-end"><?= number_format($gia_moi, 0, ',', '.') ?>₫</td>
            <td class="text-end" style="color:#e63946;font-weight:600;">
              <?= number_format($tt, 0, ',', '.') ?>₫
              <?php if ($is_draft): ?>
                <br><small class="text-muted" style="font-size:.72rem;">Giá BQ sau: <?= number_format(round($bq), 0, ',', '.') ?>₫</small>
              <?php endif; ?>
            </td>
            <?php if ($is_draft): ?>
            <td>
              <a href="?id=<?= $id ?>&action=delete_item&item_id=<?= $item['id'] ?>"
                 class="btn btn-sm btn-outline-danger" style="border-radius:6px;"
                 onclick="return confirm('Xoá dòng này?')"><i class="bi bi-trash3"></i></a>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="5" class="text-end fw-bold">Tổng cộng:</td>
            <td class="text-end" style="color:#e63946;font-weight:700;font-size:1rem;">
              <?= number_format($tong, 0, ',', '.') ?>₫
            </td>
            <?php if ($is_draft): ?><td></td><?php endif; ?>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
// Auto-save thông tin phiếu khi blur
<?php if ($is_draft): ?>
let saveTimer;
function autoSave() {
  clearTimeout(saveTimer);
  saveTimer = setTimeout(() => {
    const data = new FormData();
    data.append('action', 'save_info');
    data.append('ngay_nhap', document.getElementById('inp-ngay').value);
    data.append('ghi_chu', document.getElementById('inp-ghichu').value);
    fetch('/nhasach/admin/import_edit.php?id=<?= $id ?>', { method: 'POST', body: data })
      .then(r => r.json()).then(res => {
        const s = document.getElementById('save-status');
        if (res.ok) {
          s.style.display = 'inline';
          s.innerHTML = '<i class="bi bi-check-circle me-1"></i>Đã lưu';
          s.style.color = '#28a745';
          setTimeout(() => s.style.display = 'none', 2000);
        } else {
          s.style.display = 'inline';
          s.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>' + (res.msg || 'Lỗi lưu');
          s.style.color = '#e63946';
        }
      });
  }, 600);
}
document.getElementById('inp-ngay').addEventListener('change', autoSave);

document.querySelector('form[action*="add_item"], form input[name="action"][value="add_item"]')
  ?.closest('form')
  ?.addEventListener('submit', function(e) {
    if (!document.getElementById('inp-ngay').value) {
      e.preventDefault();
      alert('Vui lòng nhập ngày nhập hàng trước khi thêm sách.');
      document.getElementById('inp-ngay').focus();
    }
  });
document.getElementById('inp-ghichu').addEventListener('input', autoSave);

// Tìm kiếm sách
document.getElementById('sach-search').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#sach-select option').forEach(o => {
    o.style.display = o.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

// === Format giá tiền ===
function formatMoney(n) {
  return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
function unformatMoney(s) {
  return parseInt(s.replace(/\./g, ''), 10) || 0;
}

// Ô hiển thị → ô hidden
const dspEl = document.getElementById('inp-dongia-display');
const hidEl = document.getElementById('inp-dongia');

dspEl.addEventListener('input', function() {
  let raw = this.value.replace(/[^\d]/g, '');       // chỉ giữ số
  if (raw === '') { this.value = ''; hidEl.value = ''; }
  else {
    let num = parseInt(raw, 10);
    this.value = formatMoney(num);
    hidEl.value = num;
  }
  updatePreview();
});

// Khi chọn sách → điền giá đã format
document.getElementById('sach-select').addEventListener('change', function() {
  const opt    = this.options[this.selectedIndex];
  const gia    = parseFloat(opt.dataset.gia) || 0;
  const ghichu = opt.dataset.ghichu || '';
  if (gia > 0) {
    dspEl.value = formatMoney(gia);
    hidEl.value = gia;
  } else {
    dspEl.value = '';
    hidEl.value = '';
  }
  const note = document.getElementById('gia-source-note');
  note.style.display = 'block';
  note.innerHTML = gia > 0
    ? `<i class="bi bi-info-circle me-1"></i><strong>Giá đề xuất:</strong> ${gia.toLocaleString('vi-VN')}₫ — <em>${ghichu}</em>`
    : `<i class="bi bi-exclamation-circle me-1" style="color:#e63946;"></i>Chưa có giá nhập trước — vui lòng nhập giá mới.`;
  updatePreview();
});

function updatePreview() {
  const sl  = parseInt(document.getElementById('inp-soluong').value) || 0;
  const dg  = unformatMoney(dspEl.value);
  const box = document.getElementById('gia-preview');
  if (sl > 0 && dg > 0) {
    box.style.display = 'block';
    box.innerHTML = `${sl} × ${formatMoney(dg)}₫ = <b style="color:#e63946;">${formatMoney(sl*dg)}₫</b>`;
  } else { box.style.display = 'none'; }
}
document.getElementById('inp-soluong').addEventListener('input', updatePreview);
<?php endif; ?>
</script>

<?php
require_once 'includes/admin_footer.php';
ob_end_flush();
?>