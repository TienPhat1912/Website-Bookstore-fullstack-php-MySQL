<?php
ob_start();
$page_title = 'Chi tiết sách';
require_once 'includes/admin_header.php';

$the_loais = $pdo->query("SELECT * FROM the_loai WHERE trang_thai = 1 ORDER BY ten")->fetchAll();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /nhasach/admin/products.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM sach WHERE id = ?");
$stmt->execute([$id]);
$edit_sach = $stmt->fetch();
if (!$edit_sach) {
    header('Location: /nhasach/admin/products.php');
    exit;
}

$errors = [];
$old    = [];

// ---- XOÁ / ẨN ----
$action = $_REQUEST['action'] ?? '';

if ($action === 'hide') {
    $pdo->prepare("UPDATE sach SET hien_trang = 0 WHERE id = ?")->execute([$id]);
    $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Sách đã được ẩn khỏi cửa hàng.'];
    header("Location: /nhasach/admin/product_edit.php?id=$id");
    exit;
}

if ($action === 'delete') {
    if ($edit_sach['da_nhap_hang']) {
        $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Không thể xoá hẳn sách đã nhập hàng. Hãy ẩn sách thay vì xoá.'];
        header("Location: /nhasach/admin/product_edit.php?id=$id");
        exit;
    }

    if ($edit_sach['da_nhap_hang']) {
        $pdo->prepare("UPDATE sach SET hien_trang = 0 WHERE id = ?")->execute([$id]);
        $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Sách đã được ẩn khỏi cửa hàng (đã từng nhập hàng).'];
    } else {
        if (!empty($edit_sach['hinh']) && file_exists('../uploads/' . $edit_sach['hinh'])) {
            unlink('../uploads/' . $edit_sach['hinh']);
        }
        $pdo->prepare("DELETE FROM sach WHERE id = ?")->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã xoá sách thành công.'];
    }
    header('Location: /nhasach/admin/products.php');
    exit;
}

if ($action === 'show') {
    $pdo->prepare("UPDATE sach SET hien_trang = 1 WHERE id = ?")->execute([$id]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã hiện sách trở lại.'];
    header("Location: /nhasach/admin/product_edit.php?id=$id");
    exit;
}

// ---- CẬP NHẬT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    $old = $_POST;

    $ten         = trim($_POST['ten']          ?? '');
    $tac_gia     = trim($_POST['tac_gia']      ?? '');
    $the_loai_id = (int)($_POST['the_loai_id'] ?? 0);
    $nha_xb      = trim($_POST['nha_xb']       ?? '');
    $mo_ta       = trim($_POST['mo_ta']        ?? '');
    $don_vi      = trim($_POST['don_vi_tinh']  ?? 'cuốn');
    $ty_le_ln    = (float)($_POST['ty_le_ln']  ?? 0);

    if (empty($ten))       $errors['ten']         = 'Vui lòng nhập tên sách.';
    if ($the_loai_id <= 0) $errors['the_loai_id'] = 'Vui lòng chọn thể loại.';
    if ($ty_le_ln < 0)     $errors['ty_le_ln']    = 'Tỷ lệ lợi nhuận không hợp lệ.';

    $hinh     = $old['hinh_cu'] ?? '';
    $xoa_hinh = isset($_POST['xoa_hinh']) && $_POST['xoa_hinh'] == '1';

    if (!empty($_FILES['hinh']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['hinh']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowed)) {
            $errors['hinh'] = 'Chỉ chấp nhận file jpg, jpeg, png, webp.';
        } elseif ($_FILES['hinh']['size'] > 2 * 1024 * 1024) {
            $errors['hinh'] = 'Ảnh tối đa 2MB.';
        } else {
            $ten_file   = 'sach_' . time() . '_' . rand(100,999) . '.' . $ext;
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            if (move_uploaded_file($_FILES['hinh']['tmp_name'], $upload_dir . $ten_file)) {
                if (!empty($old['hinh_cu']) && file_exists($upload_dir . $old['hinh_cu'])) {
                    unlink($upload_dir . $old['hinh_cu']);
                }
                $hinh = $ten_file;
            }
        }
    } elseif ($xoa_hinh) {
        $upload_dir = '../uploads/';
        if (!empty($old['hinh_cu']) && file_exists($upload_dir . $old['hinh_cu'])) {
            unlink($upload_dir . $old['hinh_cu']);
        }
        $hinh = '';
    }

    if (empty($errors)) {
        $pdo->prepare("
            UPDATE sach SET ten=?, tac_gia=?, the_loai_id=?, nha_xb=?,
                            mo_ta=?, don_vi_tinh=?, hinh=?, ty_le_ln=?
            WHERE id = ?
        ")->execute([$ten, $tac_gia, $the_loai_id, $nha_xb, $mo_ta, $don_vi, $hinh, $ty_le_ln, $id]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Cập nhật sách thành công!'];
        header("Location: /nhasach/admin/product_edit.php?id=$id");
        exit;
    }

    // Reload lại sách nếu có lỗi
    $stmt = $pdo->prepare("SELECT * FROM sach WHERE id = ?");
    $stmt->execute([$id]);
    $edit_sach = $stmt->fetch();
}
?>

<div class="page-header">
  <h5>
    <i class="bi bi-book me-2" style="color:#f4a261;"></i>
    Chi tiết sách —
    <span style="color:#f4a261;"><?= htmlspecialchars($edit_sach['ma_sach']) ?></span>
  </h5>
  <div class="d-flex gap-2">
    <a href="/nhasach/admin/products.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
      <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
  </div>
</div>

<div class="admin-card">
  <form method="POST" action="/nhasach/admin/product_edit.php?id=<?= $id ?>"
        enctype="multipart/form-data" novalidate>
    <input type="hidden" name="action"  value="update">
    <input type="hidden" name="hinh_cu" value="<?= htmlspecialchars($edit_sach['hinh']) ?>">

    <div class="row g-3">
      <div class="col-md-2">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Mã sách</label>
        <input type="text" class="form-control"
               value="<?= htmlspecialchars($edit_sach['ma_sach']) ?>"
               readonly style="background:#f8f9fa;color:#888;">
        <small class="text-muted">Không thể thay đổi.</small>
      </div>

      <div class="col-md-5">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Tên sách *</label>
        <input type="text" name="ten"
               class="form-control <?= isset($errors['ten']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($old['ten'] ?? $edit_sach['ten']) ?>">
        <?php if (isset($errors['ten'])): ?><div class="invalid-feedback"><?= $errors['ten'] ?></div><?php endif; ?>
      </div>

      <div class="col-md-3">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Tác giả</label>
        <input type="text" name="tac_gia" class="form-control"
               value="<?= htmlspecialchars($old['tac_gia'] ?? $edit_sach['tac_gia']) ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Thể loại *</label>
        <select name="the_loai_id" class="form-select <?= isset($errors['the_loai_id']) ? 'is-invalid' : '' ?>">
          <option value="">-- Chọn --</option>
          <?php foreach ($the_loais as $tl): ?>
            <option value="<?= $tl['id'] ?>"
              <?= ($old['the_loai_id'] ?? $edit_sach['the_loai_id']) == $tl['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($tl['ten']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['the_loai_id'])): ?><div class="invalid-feedback"><?= $errors['the_loai_id'] ?></div><?php endif; ?>
      </div>

      <div class="col-md-3">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Nhà xuất bản</label>
        <input type="text" name="nha_xb" class="form-control"
               value="<?= htmlspecialchars($old['nha_xb'] ?? $edit_sach['nha_xb']) ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Đơn vị tính</label>
        <input type="text" name="don_vi_tinh" class="form-control"
               value="<?= htmlspecialchars($old['don_vi_tinh'] ?? $edit_sach['don_vi_tinh']) ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Tỷ lệ LN (%)</label>
        <input type="number" name="ty_le_ln" min="0" max="500" step="0.1"
               class="form-control <?= isset($errors['ty_le_ln']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($old['ty_le_ln'] ?? $edit_sach['ty_le_ln']) ?>">
        <?php if (isset($errors['ty_le_ln'])): ?><div class="invalid-feedback"><?= $errors['ty_le_ln'] ?></div><?php endif; ?>
      </div>

      <div class="col-md-3">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Ảnh bìa</label>
        <input type="file" name="hinh" class="form-control <?= isset($errors['hinh']) ? 'is-invalid' : '' ?>"
               accept="image/*" onchange="previewImg(this)">
        <?php if (isset($errors['hinh'])): ?><div class="invalid-feedback"><?= $errors['hinh'] ?></div><?php endif; ?>
        <div class="mt-2 d-flex align-items-center gap-2">
          <?php if (!empty($edit_sach['hinh'])): ?>
            <img src="/nhasach/uploads/<?= htmlspecialchars($edit_sach['hinh']) ?>"
                 id="img-preview"
                 style="width:50px;height:64px;object-fit:cover;border-radius:6px;">
            <label class="d-flex align-items-center gap-1" style="font-size:.82rem;color:#888;cursor:pointer;">
              <input type="checkbox" name="xoa_hinh" value="1"> Xoá ảnh
            </label>
          <?php else: ?>
            <img id="img-preview" style="display:none;width:50px;height:64px;object-fit:cover;border-radius:6px;">
          <?php endif; ?>
        </div>
      </div>

      <div class="col-12">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Mô tả</label>
        <textarea name="mo_ta" class="form-control" rows="4"><?= htmlspecialchars($old['mo_ta'] ?? $edit_sach['mo_ta']) ?></textarea>
      </div>

      <div class="col-12 d-flex gap-2 align-items-center flex-wrap">
        <button type="submit" class="btn btn-sm px-4"
                style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
          <i class="bi bi-check-lg me-1"></i>Cập nhật
        </button>
        <a href="/nhasach/admin/products.php" class="btn btn-sm btn-outline-secondary"
           style="border-radius:8px;">Huỷ</a>

        <span class="ms-auto d-flex gap-1 flex-wrap">
          <?php if ($edit_sach['hien_trang']): ?>
            <a href="/nhasach/admin/product_edit.php?id=<?= $id ?>&action=hide"
               class="btn btn-sm btn-outline-warning" style="border-radius:8px;"
               onclick="return confirm('Ẩn sách này khỏi cửa hàng?')">
              <i class="bi bi-eye-slash me-1"></i>Ẩn sách
            </a>
          <?php else: ?>
            <a href="/nhasach/admin/product_edit.php?id=<?= $id ?>&action=show"
               class="btn btn-sm btn-outline-success" style="border-radius:8px;">
              <i class="bi bi-eye me-1"></i>Hiện sách
            </a>
          <?php endif; ?>
          <?php if (!$edit_sach['da_nhap_hang']): ?>
            <a href="/nhasach/admin/product_edit.php?id=<?= $id ?>&action=delete"
               class="btn btn-sm btn-outline-danger" style="border-radius:8px;"
               onclick="return confirm('Xoá hẳn sách này? Không thể khôi phục!')">
              <i class="bi bi-trash3 me-1"></i>Xoá hẳn
            </a>
          <?php endif; ?>
        </span>
      </div>
    </div>
  </form>
</div>

<script>
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
