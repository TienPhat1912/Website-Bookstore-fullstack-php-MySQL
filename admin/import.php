<?php
ob_start();
$page_title = 'Nhập hàng';
require_once 'includes/admin_header.php';

// ============================================================
// XỬ LÝ HÀNH ĐỘNG
// ============================================================
$action = $_REQUEST['action'] ?? '';

// ---- HOÀN THÀNH PHIẾU ----
if ($action === 'complete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $phieu = $pdo->prepare("SELECT * FROM phieu_nhap WHERE id = ? AND trang_thai = 'nhap'");
    $phieu->execute([$id]);
    $phieu = $phieu->fetch();

    if ($phieu) {
        $chi_tiet = $pdo->prepare("SELECT * FROM chi_tiet_nhap WHERE phieu_nhap_id = ?");
        $chi_tiet->execute([$id]);
        $items = $chi_tiet->fetchAll();

        $pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                // Lấy tồn kho & giá nhập hiện tại
                $s = $pdo->prepare("SELECT so_luong, gia_nhap FROM sach WHERE id = ?");
                $s->execute([$item['sach_id']]);
                $sach = $s->fetch();

                $ton_cu  = (int)$sach['so_luong'];
                $gia_cu  = (float)$sach['gia_nhap'];
                $so_nhap = (int)$item['so_luong'];
                $gia_nhap_moi = (float)$item['don_gia'];

                // Tính giá bình quân
                if ($ton_cu + $so_nhap > 0) {
                    $gia_bq = ($ton_cu * $gia_cu + $so_nhap * $gia_nhap_moi) / ($ton_cu + $so_nhap);
                } else {
                    $gia_bq = $gia_nhap_moi;
                }

                // Cập nhật sách
                $pdo->prepare("
                    UPDATE sach
                    SET so_luong = so_luong + ?,
                        gia_nhap = ROUND(?, 0),
                        da_nhap_hang = 1
                    WHERE id = ?
                ")->execute([$so_nhap, $gia_bq, $item['sach_id']]);
            }

            // Đánh dấu phiếu đã hoàn thành
            $pdo->prepare("
                UPDATE phieu_nhap SET trang_thai = 'hoan_thanh', ngay_hoan_thanh = NOW() WHERE id = ?
            ")->execute([$id]);

            $pdo->commit();
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Hoàn thành phiếu nhập! Tồn kho & giá bình quân đã được cập nhật.'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Lỗi: ' . $e->getMessage()];
        }
    }
    header('Location: /nhasach/admin/import.php');
    exit;
}

// ---- XOÁ PHIẾU (chỉ khi còn nháp) ----
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $check = $pdo->prepare("SELECT id FROM phieu_nhap WHERE id = ? AND trang_thai = 'nhap'");
    $check->execute([$id]);
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM chi_tiet_nhap WHERE phieu_nhap_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM phieu_nhap WHERE id = ?")->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã xoá phiếu nhập.'];
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Không thể xoá phiếu đã hoàn thành.'];
    }
    header('Location: /nhasach/admin/import.php');
    exit;
}

// ---- XOÁ DÒNG CHI TIẾT ----
if ($action === 'delete_item' && isset($_GET['item_id'])) {
    $item_id  = (int)$_GET['item_id'];
    $phieu_id = (int)($_GET['phieu_id'] ?? 0);
    // Chỉ xoá nếu phiếu còn nháp
    $check = $pdo->prepare("
        SELECT pn.id FROM chi_tiet_nhap cn
        JOIN phieu_nhap pn ON pn.id = cn.phieu_nhap_id
        WHERE cn.id = ? AND pn.trang_thai = 'nhap'
    ");
    $check->execute([$item_id]);
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM chi_tiet_nhap WHERE id = ?")->execute([$item_id]);
        // Cập nhật tổng tiền phiếu
        $pdo->prepare("
            UPDATE phieu_nhap pn
            SET tong_tien = (SELECT COALESCE(SUM(so_luong * don_gia),0) FROM chi_tiet_nhap WHERE phieu_nhap_id = pn.id)
            WHERE id = ?
        ")->execute([$phieu_id]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã xoá dòng khỏi phiếu.'];
    }
    header("Location: /nhasach/admin/import.php?edit=$phieu_id");
    exit;
}

// ---- TẠO PHIẾU MỚI ----
if ($action === 'create_phieu' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ghi_chu  = trim($_POST['ghi_chu'] ?? '');
    $ma_phieu = 'PN' . date('ymdHis');
    $pdo->prepare("INSERT INTO phieu_nhap (ma_phieu, ghi_chu, trang_thai) VALUES (?, ?, 'nhap')")
        ->execute([$ma_phieu, $ghi_chu]);
    $new_id = $pdo->lastInsertId();
    $_SESSION['flash'] = ['type' => 'success', 'msg' => "Tạo phiếu $ma_phieu thành công. Bây giờ thêm sách vào phiếu."];
    header("Location: /nhasach/admin/import.php?edit=$new_id");
    exit;
}

// ---- THÊM/SỬA DÒNG CHI TIẾT ----
if ($action === 'add_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $phieu_id = (int)$_POST['phieu_id'];
    $sach_id  = (int)$_POST['sach_id'];
    $so_luong = (int)$_POST['so_luong'];
    $don_gia  = (float)$_POST['don_gia'];
    $errors_item = [];

    if ($sach_id <= 0)  $errors_item[] = 'Chưa chọn sách.';
    if ($so_luong <= 0) $errors_item[] = 'Số lượng phải > 0.';
    if ($don_gia <= 0)  $errors_item[] = 'Đơn giá phải > 0.';

    // Kiểm tra phiếu còn nháp
    $phieu_check = $pdo->prepare("SELECT id FROM phieu_nhap WHERE id = ? AND trang_thai = 'nhap'");
    $phieu_check->execute([$phieu_id]);
    if (!$phieu_check->fetch()) $errors_item[] = 'Phiếu không hợp lệ hoặc đã hoàn thành.';

    if (empty($errors_item)) {
        // Kiểm tra sách đã có trong phiếu chưa → nếu rồi thì cộng thêm
        $exist = $pdo->prepare("SELECT id, so_luong FROM chi_tiet_nhap WHERE phieu_nhap_id = ? AND sach_id = ?");
        $exist->execute([$phieu_id, $sach_id]);
        $row = $exist->fetch();
        if ($row) {
            $pdo->prepare("UPDATE chi_tiet_nhap SET so_luong = so_luong + ?, don_gia = ? WHERE id = ?")
                ->execute([$so_luong, $don_gia, $row['id']]);
        } else {
            $pdo->prepare("INSERT INTO chi_tiet_nhap (phieu_nhap_id, sach_id, so_luong, don_gia) VALUES (?,?,?,?)")
                ->execute([$phieu_id, $sach_id, $so_luong, $don_gia]);
        }
        // Cập nhật tổng tiền phiếu
        $pdo->prepare("
            UPDATE phieu_nhap SET tong_tien = (
                SELECT COALESCE(SUM(so_luong * don_gia),0) FROM chi_tiet_nhap WHERE phieu_nhap_id = ?
            ) WHERE id = ?
        ")->execute([$phieu_id, $phieu_id]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã thêm sách vào phiếu.'];
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => implode(' ', $errors_item)];
    }
    header("Location: /nhasach/admin/import.php?edit=$phieu_id");
    exit;
}

// ============================================================
// TRANG SỬA PHIẾU (edit mode)
// ============================================================
$edit_phieu = null;
$edit_items = [];
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM phieu_nhap WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_phieu = $stmt->fetch();

    if ($edit_phieu) {
        $stmt = $pdo->prepare("
            SELECT cn.*, s.ten AS ten_sach, s.ma_sach, s.gia_nhap AS gia_hien_tai, s.so_luong AS ton_hien_tai
            FROM chi_tiet_nhap cn
            JOIN sach s ON s.id = cn.sach_id
            WHERE cn.phieu_nhap_id = ?
            ORDER BY cn.id
        ");
        $stmt->execute([$edit_phieu['id']]);
        $edit_items = $stmt->fetchAll();
    }
}

// ============================================================
// DANH SÁCH PHIẾU
// ============================================================
$filter_tt     = $_GET['trang_thai'] ?? 'tat_ca';
$filter_search = trim($_GET['search'] ?? '');

$where  = ["1=1"];
$params = [];
if ($filter_tt === 'nhap')        { $where[] = "trang_thai = 'nhap'"; }
if ($filter_tt === 'hoan_thanh')  { $where[] = "trang_thai = 'hoan_thanh'"; }
if ($filter_search !== '')        { $where[] = "ma_phieu LIKE ?"; $params[] = "%$filter_search%"; }

$where_sql = implode(' AND ', $where);
$phieus = $pdo->prepare("
    SELECT pn.*,
           (SELECT COUNT(*) FROM chi_tiet_nhap WHERE phieu_nhap_id = pn.id) AS so_dong
    FROM phieu_nhap pn
    WHERE $where_sql
    ORDER BY pn.ngay_tao DESC
");
$phieus->execute($params);
$phieus = $phieus->fetchAll();

// Lấy danh sách sách để chọn trong form
$sach_list = $pdo->query("
    SELECT id, ma_sach, ten, gia_nhap, so_luong FROM sach
    WHERE hien_trang = 1 OR hien_trang = 0
    ORDER BY ten
")->fetchAll();
?>

<?php if ($edit_phieu): ?>
<!-- ============================================================
     TRANG SỬA / XEM PHIẾU
     ============================================================ -->
<div class="page-header">
  <h5>
    <i class="bi bi-box-arrow-in-down me-2" style="color:#f4a261;"></i>
    Phiếu nhập: <strong><?= htmlspecialchars($edit_phieu['ma_phieu']) ?></strong>
    <?php if ($edit_phieu['trang_thai'] === 'hoan_thanh'): ?>
      <span class="badge bg-success ms-2">Đã hoàn thành</span>
    <?php else: ?>
      <span class="badge bg-warning text-dark ms-2">Đang soạn</span>
    <?php endif; ?>
  </h5>
  <a href="/nhasach/admin/import.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
    <i class="bi bi-arrow-left me-1"></i>Quay lại
  </a>
</div>

<div class="row g-4">

  <!-- THÊM SÁCH VÀO PHIẾU -->
  <?php if ($edit_phieu['trang_thai'] === 'nhap'): ?>
  <div class="col-lg-4">
    <div class="admin-card">
      <div class="card-title">Thêm sách vào phiếu</div>

      <form method="POST" action="/nhasach/admin/import.php">
        <input type="hidden" name="action" value="add_item">
        <input type="hidden" name="phieu_id" value="<?= $edit_phieu['id'] ?>">

        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.85rem;">Tìm sách</label>
          <input type="text" id="sach-search" class="form-control form-control-sm"
                 placeholder="Gõ tên hoặc mã sách...">
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.85rem;">Sách đã chọn</label>
          <select name="sach_id" id="sach-select" class="form-select form-select-sm" size="6"
                  style="height:auto;">
            <?php foreach ($sach_list as $s): ?>
              <option value="<?= $s['id'] ?>"
                      data-ten="<?= htmlspecialchars($s['ten']) ?>"
                      data-ma="<?= htmlspecialchars($s['ma_sach']) ?>"
                      data-gia="<?= $s['gia_nhap'] ?>">
                [<?= htmlspecialchars($s['ma_sach']) ?>] <?= htmlspecialchars($s['ten']) ?>
                (tồn: <?= $s['so_luong'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">Nhấp để chọn sách</small>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label fw-semibold" style="font-size:.85rem;">Số lượng *</label>
            <input type="number" name="so_luong" id="inp-soluong"
                   class="form-control form-control-sm" min="1" value="1">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold" style="font-size:.85rem;">Đơn giá nhập (₫) *</label>
            <input type="number" name="don_gia" id="inp-dongia"
                   class="form-control form-control-sm" min="0" step="100" placeholder="0">
          </div>
        </div>

        <div class="mb-3 p-2 rounded" id="gia-bq-preview"
             style="background:#fff8e8; border:1px solid #ffd970; display:none; font-size:.82rem;">
        </div>

        <button type="submit" class="btn btn-sm w-100"
                style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
          <i class="bi bi-plus-lg me-1"></i>Thêm vào phiếu
        </button>
      </form>
    </div>

    <!-- Nút hoàn thành phiếu -->
    <?php if (count($edit_items) > 0): ?>
    <div class="admin-card">
      <div class="card-title">Hoàn thành phiếu</div>
      <p style="font-size:.84rem; color:#555;">
        Sau khi hoàn thành, tồn kho và giá nhập bình quân sẽ được cập nhật.
        <strong>Không thể sửa sau khi hoàn thành.</strong>
      </p>
      <a href="/nhasach/admin/import.php?action=complete&id=<?= $edit_phieu['id'] ?>"
         class="btn btn-success btn-sm w-100"
         style="border-radius:8px;"
         onclick="return confirm('Hoàn thành phiếu nhập này? Thao tác không thể hoàn tác.')">
        <i class="bi bi-check2-circle me-1"></i>Hoàn thành & Cập nhật kho
      </a>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- DANH SÁCH DÒNG PHIẾU -->
  <div class="col-lg-<?= $edit_phieu['trang_thai'] === 'nhap' ? '8' : '12' ?>">
    <div class="admin-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="card-title mb-0">
          Chi tiết phiếu (<?= count($edit_items) ?> sách)
        </div>
        <div style="font-size:.85rem; color:#555;">
          Ngày tạo: <?= date('d/m/Y H:i', strtotime($edit_phieu['ngay_tao'])) ?>
          <?php if ($edit_phieu['ngay_hoan_thanh']): ?>
            &nbsp;|&nbsp; Hoàn thành: <?= date('d/m/Y H:i', strtotime($edit_phieu['ngay_hoan_thanh'])) ?>
          <?php endif; ?>
        </div>
      </div>

      <?php if (empty($edit_items)): ?>
        <p class="text-muted text-center py-4">Chưa có sách nào trong phiếu. Thêm sách từ form bên trái.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table admin-table table-hover">
            <thead>
              <tr>
                <th>#</th>
                <th>Sách</th>
                <th class="text-end">Tồn hiện tại</th>
                <th class="text-end">Số lượng nhập</th>
                <th class="text-end">Đơn giá nhập</th>
                <th class="text-end">Thành tiền</th>
                <?php if ($edit_phieu['trang_thai'] === 'nhap'): ?>
                  <th style="width:50px;"></th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php
              $tong = 0;
              foreach ($edit_items as $i => $item):
                $tt = $item['so_luong'] * $item['don_gia'];
                $tong += $tt;

                // Tính giá bình quân sẽ là
                $ton_cu = $item['ton_hien_tai'];
                $gia_cu = $item['gia_hien_tai'];
                if ($ton_cu + $item['so_luong'] > 0) {
                    $bq = ($ton_cu * $gia_cu + $item['so_luong'] * $item['don_gia']) / ($ton_cu + $item['so_luong']);
                } else {
                    $bq = $item['don_gia'];
                }
              ?>
              <tr>
                <td style="color:#888;"><?= $i+1 ?></td>
                <td>
                  <div class="fw-semibold" style="font-size:.88rem;"><?= htmlspecialchars($item['ten_sach']) ?></div>
                  <small class="text-muted"><?= htmlspecialchars($item['ma_sach']) ?></small>
                </td>
                <td class="text-end">
                  <span class="badge <?= $item['ton_hien_tai'] == 0 ? 'bg-danger' : 'bg-secondary' ?>">
                    <?= $item['ton_hien_tai'] ?>
                  </span>
                </td>
                <td class="text-end fw-semibold"><?= number_format($item['so_luong']) ?></td>
                <td class="text-end"><?= number_format($item['don_gia'], 0, ',', '.') ?>₫</td>
                <td class="text-end" style="color:#e63946; font-weight:600;">
                  <?= number_format($tt, 0, ',', '.') ?>₫
                  <?php if ($edit_phieu['trang_thai'] === 'nhap'): ?>
                    <br><small class="text-muted" style="font-size:.72rem;">
                      Giá BQ sau: <?= number_format(round($bq), 0, ',', '.') ?>₫
                    </small>
                  <?php endif; ?>
                </td>
                <?php if ($edit_phieu['trang_thai'] === 'nhap'): ?>
                <td>
                  <a href="/nhasach/admin/import.php?action=delete_item&item_id=<?= $item['id'] ?>&phieu_id=<?= $edit_phieu['id'] ?>"
                     class="btn btn-sm btn-outline-danger" style="border-radius:6px;"
                     onclick="return confirm('Xoá dòng này?')">
                    <i class="bi bi-trash3"></i>
                  </a>
                </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="<?= $edit_phieu['trang_thai'] === 'nhap' ? '5' : '5' ?>"
                    class="text-end fw-bold">Tổng cộng:</td>
                <td class="text-end" style="color:#e63946; font-weight:700; font-size:1rem;">
                  <?= number_format($tong, 0, ',', '.') ?>₫
                </td>
                <?php if ($edit_phieu['trang_thai'] === 'nhap'): ?><td></td><?php endif; ?>
              </tr>
            </tfoot>
          </table>
        </div>

        <?php if (!empty($edit_phieu['ghi_chu'])): ?>
          <p style="font-size:.84rem; color:#666; margin-top:8px;">
            <strong>Ghi chú:</strong> <?= htmlspecialchars($edit_phieu['ghi_chu']) ?>
          </p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
// Lọc sách trong listbox
document.getElementById('sach-search')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  const opts = document.querySelectorAll('#sach-select option');
  opts.forEach(o => {
    const text = o.textContent.toLowerCase();
    o.style.display = text.includes(q) ? '' : 'none';
  });
});

// Khi chọn sách → điền giá nhập hiện tại
document.getElementById('sach-select')?.addEventListener('change', function() {
  const opt = this.options[this.selectedIndex];
  const gia = parseFloat(opt.dataset.gia) || 0;
  document.getElementById('inp-dongia').value = gia || '';
  updatePreview();
});

function updatePreview() {
  const sel    = document.getElementById('sach-select');
  const sl     = parseInt(document.getElementById('inp-soluong').value) || 0;
  const dg     = parseFloat(document.getElementById('inp-dongia').value) || 0;
  const box    = document.getElementById('gia-bq-preview');
  if (!sel || !sel.value || sl <= 0 || dg <= 0) { box.style.display='none'; return; }

  const opt    = sel.options[sel.selectedIndex];
  const tenSach = opt.dataset.ten;
  const thanh  = sl * dg;
  box.style.display = 'block';
  box.innerHTML = `<strong>${tenSach}</strong><br>
    Số lượng: <b>${sl}</b> × Đơn giá: <b>${dg.toLocaleString('vi')}₫</b><br>
    Thành tiền: <b style="color:#e63946;">${thanh.toLocaleString('vi')}₫</b>`;
}

document.getElementById('inp-soluong')?.addEventListener('input', updatePreview);
document.getElementById('inp-dongia')?.addEventListener('input', updatePreview);
</script>

<?php else: ?>
<!-- ============================================================
     TRANG DANH SÁCH PHIẾU
     ============================================================ -->
<div class="page-header">
  <h5><i class="bi bi-box-arrow-in-down me-2" style="color:#f4a261;"></i>Nhập hàng</h5>
  <button class="btn btn-sm" onclick="toggleForm()"
          style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
    <i class="bi bi-plus-lg me-1"></i>Tạo phiếu nhập mới
  </button>
</div>

<!-- FORM TẠO PHIẾU -->
<div class="admin-card mb-4" id="form-wrap" style="display:none;">
  <div class="card-title">Tạo phiếu nhập hàng mới</div>
  <form method="POST" action="/nhasach/admin/import.php">
    <input type="hidden" name="action" value="create_phieu">
    <div class="row g-3 align-items-end">
      <div class="col-md-8">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Ghi chú (tuỳ chọn)</label>
        <input type="text" name="ghi_chu" class="form-control"
               placeholder="VD: Nhập hàng tháng 6, từ NXB Kim Đồng...">
      </div>
      <div class="col-md-4 d-flex gap-2">
        <button type="submit" class="btn btn-sm px-4"
                style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
          <i class="bi bi-plus-lg me-1"></i>Tạo phiếu
        </button>
        <button type="button" onclick="toggleForm()"
                class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">Huỷ</button>
      </div>
    </div>
  </form>
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
        <option value="nhap"         <?= $filter_tt=='nhap'        ?'selected':'' ?>>Đang soạn</option>
        <option value="hoan_thanh"   <?= $filter_tt=='hoan_thanh'  ?'selected':'' ?>>Đã hoàn thành</option>
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
  <div class="card-title">Danh sách phiếu nhập (<?= count($phieus) ?>)</div>

  <?php if (empty($phieus)): ?>
    <p class="text-muted text-center py-4">Chưa có phiếu nhập nào.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table admin-table table-hover">
        <thead>
          <tr>
            <th>Mã phiếu</th>
            <th>Ngày tạo</th>
            <th>Số loại sách</th>
            <th class="text-end">Tổng tiền</th>
            <th>Trạng thái</th>
            <th>Ghi chú</th>
            <th style="width:110px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($phieus as $p): ?>
          <tr>
            <td><strong><?= htmlspecialchars($p['ma_phieu']) ?></strong></td>
            <td style="font-size:.85rem;"><?= date('d/m/Y H:i', strtotime($p['ngay_tao'])) ?></td>
            <td><span class="badge bg-light text-dark border"><?= $p['so_dong'] ?> loại</span></td>
            <td class="text-end" style="color:#e63946; font-weight:600;">
              <?= $p['tong_tien'] > 0 ? number_format($p['tong_tien'], 0, ',', '.') . '₫' : '—' ?>
            </td>
            <td>
              <?php if ($p['trang_thai'] === 'hoan_thanh'): ?>
                <span class="badge bg-success">Đã hoàn thành</span>
                <?php if ($p['ngay_hoan_thanh']): ?>
                  <br><small class="text-muted"><?= date('d/m/Y', strtotime($p['ngay_hoan_thanh'])) ?></small>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge bg-warning text-dark">Đang soạn</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.82rem; color:#666; max-width:180px;" class="text-truncate">
              <?= htmlspecialchars($p['ghi_chu'] ?? '') ?>
            </td>
            <td>
              <div class="d-flex gap-1">
                <a href="/nhasach/admin/import.php?edit=<?= $p['id'] ?>"
                   class="btn btn-sm btn-outline-primary" style="border-radius:6px;"
                   title="<?= $p['trang_thai'] === 'nhap' ? 'Sửa phiếu' : 'Xem phiếu' ?>">
                  <i class="bi <?= $p['trang_thai'] === 'nhap' ? 'bi-pencil' : 'bi-eye' ?>"></i>
                </a>
                <?php if ($p['trang_thai'] === 'nhap'): ?>
                  <a href="/nhasach/admin/import.php?action=complete&id=<?= $p['id'] ?>"
                     class="btn btn-sm btn-outline-success" style="border-radius:6px;"
                     title="Hoàn thành phiếu"
                     onclick="return confirm('Hoàn thành phiếu này? Tồn kho sẽ được cập nhật.')">
                    <i class="bi bi-check2-circle"></i>
                  </a>
                  <a href="/nhasach/admin/import.php?action=delete&id=<?= $p['id'] ?>"
                     class="btn btn-sm btn-outline-danger" style="border-radius:6px;"
                     title="Xoá phiếu"
                     onclick="return confirm('Xoá phiếu này?')">
                    <i class="bi bi-trash3"></i>
                  </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
function toggleForm() {
  const w = document.getElementById('form-wrap');
  w.style.display = w.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php endif; ?>

<?php
require_once 'includes/admin_footer.php';
ob_end_flush();
?>
