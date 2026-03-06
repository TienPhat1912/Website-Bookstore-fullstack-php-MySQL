<?php
ob_start();
$page_title = 'Quản lý sách';
require_once 'includes/admin_header.php';

$the_loais = $pdo->query("SELECT * FROM the_loai WHERE trang_thai = 1 ORDER BY ten")->fetchAll();

// ============================================================
// XỬ LÝ HÀNH ĐỘNG
// ============================================================
$action = $_REQUEST['action'] ?? '';

// ---- XOÁ ----
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT da_nhap_hang, hinh FROM sach WHERE id = ?");
    $stmt->execute([$id]);
    $sach = $stmt->fetch();

    if ($sach) {
        if ($sach['da_nhap_hang']) {
            // Đã từng nhập hàng → ẩn (soft delete)
            $pdo->prepare("UPDATE sach SET hien_trang = 0 WHERE id = ?")->execute([$id]);
            $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Sách đã được ẩn khỏi cửa hàng (đã từng nhập hàng).'];
        } else {
            // Chưa nhập hàng → xoá hẳn
            if (!empty($sach['hinh']) && file_exists('../uploads/' . $sach['hinh'])) {
                unlink('../uploads/' . $sach['hinh']);
            }
            $pdo->prepare("DELETE FROM sach WHERE id = ?")->execute([$id]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã xoá sách thành công.'];
        }
    }
    header('Location: /nhasach/admin/products.php');
    exit;
}

// ---- HIỆN LẠI (bỏ ẩn) ----
if ($action === 'show' && isset($_GET['id'])) {
    $pdo->prepare("UPDATE sach SET hien_trang = 1 WHERE id = ?")->execute([(int)$_GET['id']]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã hiện sách trở lại.'];
    header('Location: /nhasach/admin/products.php');
    exit;
}

// ---- THÊM / SỬA ----
$edit_sach = null;
$errors    = [];
$old       = [];

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM sach WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_sach = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['create', 'update'])) {
    $old = $_POST;

    $ma_sach     = trim($_POST['ma_sach']    ?? '');
    $ten         = trim($_POST['ten']        ?? '');
    $tac_gia     = trim($_POST['tac_gia']    ?? '');
    $the_loai_id = (int)($_POST['the_loai_id'] ?? 0);
    $nha_xb      = trim($_POST['nha_xb']     ?? '');
    $mo_ta       = trim($_POST['mo_ta']      ?? '');
    $don_vi      = trim($_POST['don_vi_tinh']?? 'cuốn');
    $ty_le_ln    = (float)($_POST['ty_le_ln'] ?? 0);
    $edit_id     = (int)($_POST['edit_id']   ?? 0);

    // Validate
    if (empty($ma_sach))      $errors['ma_sach']     = 'Vui lòng nhập mã sách.';
    if (empty($ten))          $errors['ten']         = 'Vui lòng nhập tên sách.';
    if ($the_loai_id <= 0)    $errors['the_loai_id'] = 'Vui lòng chọn thể loại.';
    if ($ty_le_ln < 0)        $errors['ty_le_ln']    = 'Tỷ lệ lợi nhuận không hợp lệ.';

    // Kiểm tra mã sách trùng
    if (empty($errors['ma_sach'])) {
        $stmt = $pdo->prepare("SELECT id FROM sach WHERE ma_sach = ? AND id != ?");
        $stmt->execute([$ma_sach, $edit_id]);
        if ($stmt->fetch()) $errors['ma_sach'] = 'Mã sách đã tồn tại.';
    }

    // Upload ảnh
    $hinh = $old['hinh_cu'] ?? '';
    if (!empty($_FILES['hinh']['name'])) {
        $ext      = strtolower(pathinfo($_FILES['hinh']['name'], PATHINFO_EXTENSION));
        $allowed  = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowed)) {
            $errors['hinh'] = 'Chỉ chấp nhận file jpg, jpeg, png, webp.';
        } elseif ($_FILES['hinh']['size'] > 2 * 1024 * 1024) {
            $errors['hinh'] = 'Ảnh tối đa 2MB.';
        } else {
            $ten_file = 'sach_' . time() . '_' . rand(100,999) . '.' . $ext;
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            if (move_uploaded_file($_FILES['hinh']['tmp_name'], $upload_dir . $ten_file)) {
                // Xoá ảnh cũ
                if (!empty($old['hinh_cu']) && file_exists($upload_dir . $old['hinh_cu'])) {
                    unlink($upload_dir . $old['hinh_cu']);
                }
                $hinh = $ten_file;
            }
        }
    }

    if (empty($errors)) {
        if ($action === 'create') {
            $stmt = $pdo->prepare("
                INSERT INTO sach (ma_sach, ten, tac_gia, the_loai_id, nha_xb, mo_ta, don_vi_tinh, hinh, ty_le_ln)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$ma_sach, $ten, $tac_gia, $the_loai_id, $nha_xb, $mo_ta, $don_vi, $hinh, $ty_le_ln]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Thêm sách thành công!'];
        } else {
            $stmt = $pdo->prepare("
                UPDATE sach SET ma_sach=?, ten=?, tac_gia=?, the_loai_id=?, nha_xb=?,
                                mo_ta=?, don_vi_tinh=?, hinh=?, ty_le_ln=?
                WHERE id = ?
            ");
            $stmt->execute([$ma_sach, $ten, $tac_gia, $the_loai_id, $nha_xb, $mo_ta, $don_vi, $hinh, $ty_le_ln, $edit_id]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Cập nhật sách thành công!'];
        }
        header('Location: /nhasach/admin/products.php');
        exit;
    }
    // Có lỗi → giữ lại form
    if ($action === 'update') {
        $stmt = $pdo->prepare("SELECT * FROM sach WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_sach = $stmt->fetch();
    }
}

// ============================================================
// DANH SÁCH SÁCH
// ============================================================
$filter_tl     = (int)($_GET['the_loai'] ?? 0);
$filter_search = trim($_GET['search'] ?? '');
$filter_tt     = $_GET['trang_thai'] ?? 'tat_ca';

$where  = ["1=1"];
$params = [];
if ($filter_tl > 0)          { $where[] = "s.the_loai_id = ?"; $params[] = $filter_tl; }
if ($filter_search !== '')   { $where[] = "(s.ten LIKE ? OR s.ma_sach LIKE ?)"; $params[] = "%$filter_search%"; $params[] = "%$filter_search%"; }
if ($filter_tt === 'hien')   { $where[] = "s.hien_trang = 1"; }
if ($filter_tt === 'an')     { $where[] = "s.hien_trang = 0"; }

$where_sql = implode(' AND ', $where);
$sachs = $pdo->prepare("
    SELECT s.*, tl.ten AS ten_the_loai,
           ROUND(s.gia_nhap * (1 + s.ty_le_ln/100), 0) AS gia_ban
    FROM sach s JOIN the_loai tl ON tl.id = s.the_loai_id
    WHERE $where_sql
    ORDER BY s.ngay_tao DESC
");
$sachs->execute($params);
$sachs = $sachs->fetchAll();
?>

<div class="page-header">
  <h5><i class="bi bi-book me-2" style="color:#f4a261;"></i>Quản lý sách</h5>
  <button class="btn btn-sm" onclick="toggleForm()"
          style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
    <i class="bi bi-plus-lg me-1"></i>Thêm sách mới
  </button>
</div>

<!-- FORM THÊM / SỬA -->
<div class="admin-card mb-4" id="form-wrap"
     style="<?= ($edit_sach || !empty($errors)) ? '' : 'display:none;' ?>">
  <div class="card-title"><?= $edit_sach ? 'Sửa thông tin sách' : 'Thêm sách mới' ?></div>

  <form method="POST" action="/nhasach/admin/products.php"
        enctype="multipart/form-data" novalidate>
    <input type="hidden" name="action" value="<?= $edit_sach ? 'update' : 'create' ?>">
    <?php if ($edit_sach): ?>
      <input type="hidden" name="edit_id" value="<?= $edit_sach['id'] ?>">
      <input type="hidden" name="hinh_cu" value="<?= htmlspecialchars($edit_sach['hinh']) ?>">
    <?php endif; ?>

    <div class="row g-3">
      <!-- Mã sách -->
      <div class="col-md-3">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Mã sách *</label>
        <input type="text" name="ma_sach"
               class="form-control <?= isset($errors['ma_sach']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($edit_sach['ma_sach'] ?? $old['ma_sach'] ?? '') ?>"
               placeholder="VD: S001">
        <?php if (isset($errors['ma_sach'])): ?><div class="invalid-feedback"><?= $errors['ma_sach'] ?></div><?php endif; ?>
      </div>

      <!-- Tên sách -->
      <div class="col-md-6">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Tên sách *</label>
        <input type="text" name="ten"
               class="form-control <?= isset($errors['ten']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($edit_sach['ten'] ?? $old['ten'] ?? '') ?>"
               placeholder="Tên đầy đủ của sách">
        <?php if (isset($errors['ten'])): ?><div class="invalid-feedback"><?= $errors['ten'] ?></div><?php endif; ?>
      </div>

      <!-- Tác giả -->
      <div class="col-md-3">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Tác giả</label>
        <input type="text" name="tac_gia" class="form-control"
               value="<?= htmlspecialchars($edit_sach['tac_gia'] ?? $old['tac_gia'] ?? '') ?>"
               placeholder="Tên tác giả">
      </div>

      <!-- Thể loại -->
      <div class="col-md-3">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Thể loại *</label>
        <select name="the_loai_id" class="form-select <?= isset($errors['the_loai_id']) ? 'is-invalid' : '' ?>">
          <option value="">-- Chọn thể loại --</option>
          <?php foreach ($the_loais as $tl): ?>
            <option value="<?= $tl['id'] ?>"
              <?= ($edit_sach['the_loai_id'] ?? $old['the_loai_id'] ?? 0) == $tl['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($tl['ten']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['the_loai_id'])): ?><div class="invalid-feedback"><?= $errors['the_loai_id'] ?></div><?php endif; ?>
      </div>

      <!-- NXB -->
      <div class="col-md-3">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Nhà xuất bản</label>
        <input type="text" name="nha_xb" class="form-control"
               value="<?= htmlspecialchars($edit_sach['nha_xb'] ?? $old['nha_xb'] ?? '') ?>"
               placeholder="NXB Kim Đồng">
      </div>

      <!-- Đơn vị -->
      <div class="col-md-2">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Đơn vị tính</label>
        <input type="text" name="don_vi_tinh" class="form-control"
               value="<?= htmlspecialchars($edit_sach['don_vi_tinh'] ?? $old['don_vi_tinh'] ?? 'cuốn') ?>">
      </div>

      <!-- Tỷ lệ lợi nhuận -->
      <div class="col-md-2">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Tỷ lệ LN (%)</label>
        <input type="number" name="ty_le_ln" min="0" max="500" step="0.1"
               class="form-control <?= isset($errors['ty_le_ln']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($edit_sach['ty_le_ln'] ?? $old['ty_le_ln'] ?? '30') ?>">
        <?php if (isset($errors['ty_le_ln'])): ?><div class="invalid-feedback"><?= $errors['ty_le_ln'] ?></div><?php endif; ?>
      </div>

      <!-- Ảnh bìa -->
      <div class="col-md-2">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Ảnh bìa</label>
        <input type="file" name="hinh" class="form-control <?= isset($errors['hinh']) ? 'is-invalid' : '' ?>"
               accept="image/*" onchange="previewImg(this)">
        <?php if (isset($errors['hinh'])): ?><div class="invalid-feedback"><?= $errors['hinh'] ?></div><?php endif; ?>
        <?php if (!empty($edit_sach['hinh'])): ?>
          <img src="/nhasach/uploads/<?= htmlspecialchars($edit_sach['hinh']) ?>"
               id="img-preview"
               style="width:60px;height:75px;object-fit:cover;border-radius:6px;margin-top:6px;">
        <?php else: ?>
          <img id="img-preview" style="display:none;width:60px;height:75px;object-fit:cover;border-radius:6px;margin-top:6px;">
        <?php endif; ?>
      </div>

      <!-- Mô tả -->
      <div class="col-12">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Mô tả</label>
        <textarea name="mo_ta" class="form-control" rows="3"
                  placeholder="Giới thiệu ngắn về nội dung sách..."><?= htmlspecialchars($edit_sach['mo_ta'] ?? $old['mo_ta'] ?? '') ?></textarea>
      </div>

      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-sm px-4"
                style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
          <i class="bi bi-check-lg me-1"></i><?= $edit_sach ? 'Cập nhật' : 'Thêm sách' ?>
        </button>
        <a href="/nhasach/admin/products.php" class="btn btn-sm btn-outline-secondary"
           style="border-radius:8px;">Huỷ</a>
      </div>
    </div>
  </form>
</div>

<!-- BỘ LỌC -->
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

<!-- BẢNG SÁCH -->
<div class="admin-card">
  <div class="d-flex justify-content-between mb-3">
    <div class="card-title mb-0">Danh sách sách (<?= count($sachs) ?>)</div>
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
          <th style="width:120px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sachs as $s): ?>
          <tr>
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
            <td style="font-size:.85rem; color:#e63946; font-weight:600;">
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
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <a href="/nhasach/admin/products.php?edit=<?= $s['id'] ?>"
                   class="btn btn-sm btn-outline-primary" style="border-radius:6px;"
                   title="Sửa">
                  <i class="bi bi-pencil"></i>
                </a>
                <?php if ($s['hien_trang']): ?>
                  <a href="/nhasach/admin/products.php?action=delete&id=<?= $s['id'] ?>"
                     class="btn btn-sm btn-outline-danger" style="border-radius:6px;"
                     title="<?= $s['da_nhap_hang'] ? 'Ẩn sách' : 'Xoá sách' ?>"
                     onclick="return confirm('<?= $s['da_nhap_hang'] ? 'Ẩn sách này khỏi cửa hàng?' : 'Xoá hẳn sách này?' ?>')">
                    <i class="bi <?= $s['da_nhap_hang'] ? 'bi-eye-slash' : 'bi-trash3' ?>"></i>
                  </a>
                <?php else: ?>
                  <a href="/nhasach/admin/products.php?action=show&id=<?= $s['id'] ?>"
                     class="btn btn-sm btn-outline-success" style="border-radius:6px;"
                     title="Hiện lại">
                    <i class="bi bi-eye"></i>
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
  const wrap = document.getElementById('form-wrap');
  wrap.style.display = wrap.style.display === 'none' ? 'block' : 'none';
}
function previewImg(input) {
  const preview = document.getElementById('img-preview');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php
require_once 'includes/admin_footer.php';
ob_end_flush();
?>
