<?php
ob_start();
$page_title = 'Thêm sách mới';
require_once 'includes/admin_header.php';

$the_loais = $pdo->query("SELECT * FROM the_loai WHERE trang_thai = 1 ORDER BY ten")->fetchAll();

// ============================================================
// TẠO MÃ SÁCH TỰ ĐỘNG
// ============================================================
function generate_ma_sach(PDO $pdo): string {
    // Tìm số lớn nhất từ các mã dạng S001, S002, ...
    $stmt = $pdo->query("SELECT ma_sach FROM sach WHERE ma_sach REGEXP '^S[0-9]+$' ORDER BY CAST(SUBSTRING(ma_sach,2) AS UNSIGNED) DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    if ($last) {
        $so = (int)substr($last, 1) + 1;
    } else {
        $so = 1;
    }
    // Thử cho đến khi tìm được mã chưa tồn tại (phòng trường hợp có khoảng trống)
    do {
        $ma = 'S' . str_pad($so, 3, '0', STR_PAD_LEFT);
        $check = $pdo->prepare("SELECT id FROM sach WHERE ma_sach = ?");
        $check->execute([$ma]);
        if (!$check->fetch()) break;
        $so++;
    } while (true);
    return $ma;
}

$ma_sach_auto = generate_ma_sach($pdo);

// ============================================================
// XỬ LÝ THÊM SÁCH
// ============================================================
$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $ten         = trim($_POST['ten']          ?? '');
    $tac_gia     = trim($_POST['tac_gia']      ?? '');
    $the_loai_id = (int)($_POST['the_loai_id'] ?? 0);
    $nha_xb      = trim($_POST['nha_xb']       ?? '');
    $mo_ta       = trim($_POST['mo_ta']        ?? '');
    $don_vi      = trim($_POST['don_vi_tinh']  ?? 'cuon');
    $ty_le_ln    = (float)($_POST['ty_le_ln']  ?? 30);
    // Mã sách: dùng giá trị gửi lên (đã được set từ hidden input)
    $ma_sach = trim($_POST['ma_sach'] ?? '');

    // Validate
    if (empty($ten))       $errors['ten']         = 'Vui long nhap ten sach.';
    if ($the_loai_id <= 0) $errors['the_loai_id'] = 'Vui long chon the loai.';
    if ($ty_le_ln < 0)     $errors['ty_le_ln']    = 'Ty le loi nhuan khong hop le.';

    // Kiểm tra mã sách không bị trùng (phòng race condition)
    if (!empty($ma_sach)) {
        $ck = $pdo->prepare("SELECT id FROM sach WHERE ma_sach = ?");
        $ck->execute([$ma_sach]);
        if ($ck->fetch()) {
            // Tạo lại mã mới
            $ma_sach = generate_ma_sach($pdo);
        }
    }

    // Upload ảnh
    $hinh = '';
    if (!empty($_FILES['hinh']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['hinh']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowed)) {
            $errors['hinh'] = 'Chi chap nhan file jpg, jpeg, png, webp.';
        } elseif ($_FILES['hinh']['size'] > 2 * 1024 * 1024) {
            $errors['hinh'] = 'Anh toi da 2MB.';
        } else {
            $ten_file   = 'sach_' . time() . '_' . rand(100,999) . '.' . $ext;
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            if (move_uploaded_file($_FILES['hinh']['tmp_name'], $upload_dir . $ten_file)) {
                $hinh = $ten_file;
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO sach (ma_sach, ten, tac_gia, the_loai_id, nha_xb, mo_ta, don_vi_tinh, hinh, ty_le_ln, hien_trang)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([$ma_sach, $ten, $tac_gia, $the_loai_id, $nha_xb, $mo_ta, $don_vi, $hinh, $ty_le_ln]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Them sach <strong>" . htmlspecialchars($ten) . "</strong> thanh cong! Ma sach: <strong>$ma_sach</strong>"];
        header('Location: /nhasach/admin/products.php');
        exit;
    }

    // Có lỗi → tạo lại mã mới để hiển thị
    $ma_sach_auto = $ma_sach;
}
?>

<div class="page-header">
  <h5><i class="bi bi-plus-circle me-2" style="color:#f4a261;"></i>Thêm sách mới</h5>
  <a href="/nhasach/admin/products.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
    <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
  </a>
</div>

<div class="admin-card">
  <form method="POST" action="/nhasach/admin/product_add.php"
        enctype="multipart/form-data" novalidate>

    <!-- Mã sách tự động (hidden) -->
    <input type="hidden" name="ma_sach" value="<?= htmlspecialchars($ma_sach_auto) ?>">

    <div class="row g-3">

      <!-- Thông báo mã sách -->
      <div class="col-12">
        <div class="d-flex align-items-center gap-2 p-3 rounded"
             style="background:#fff8f3;border:1px solid #f4a26180;">
          <i class="bi bi-tag-fill" style="color:#f4a261;font-size:1.1rem;"></i>
          <span style="font-size:.9rem;">
            Mã sách được tạo tự động:
            <strong style="color:#f4a261;font-size:1.05rem;"><?= htmlspecialchars($ma_sach_auto) ?></strong>
          </span>
        </div>
      </div>

      <!-- Tên sách -->
      <div class="col-md-7">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Tên sách *</label>
        <input type="text" name="ten"
               class="form-control <?= isset($errors['ten']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($old['ten'] ?? '') ?>"
               placeholder="Tên đầy đủ của sách" autofocus>
        <?php if (isset($errors['ten'])): ?><div class="invalid-feedback"><?= $errors['ten'] ?></div><?php endif; ?>
      </div>

      <!-- Tác giả -->
      <div class="col-md-5">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Tác giả</label>
        <input type="text" name="tac_gia" class="form-control"
               value="<?= htmlspecialchars($old['tac_gia'] ?? '') ?>"
               placeholder="Tên tác giả">
      </div>

      <!-- Thể loại -->
      <div class="col-md-4">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Thể loại *</label>
        <select name="the_loai_id" class="form-select <?= isset($errors['the_loai_id']) ? 'is-invalid' : '' ?>">
          <option value="">-- Chọn thể loại --</option>
          <?php foreach ($the_loais as $tl): ?>
            <option value="<?= $tl['id'] ?>"
              <?= ($old['the_loai_id'] ?? 0) == $tl['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($tl['ten']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['the_loai_id'])): ?><div class="invalid-feedback"><?= $errors['the_loai_id'] ?></div><?php endif; ?>
      </div>

      <!-- NXB -->
      <div class="col-md-4">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Nhà xuất bản</label>
        <input type="text" name="nha_xb" class="form-control"
               value="<?= htmlspecialchars($old['nha_xb'] ?? '') ?>"
               placeholder="VD: NXB Kim Đồng">
      </div>

      <!-- Đơn vị tính -->
      <div class="col-md-2">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Đơn vị tính</label>
        <input type="text" name="don_vi_tinh" class="form-control"
               value="<?= htmlspecialchars($old['don_vi_tinh'] ?? 'cuốn') ?>">
      </div>

      <!-- Tỷ lệ lợi nhuận -->
      <div class="col-md-2">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Tỷ lệ LN (%)</label>
        <input type="number" name="ty_le_ln" min="0" max="500" step="0.1"
               class="form-control <?= isset($errors['ty_le_ln']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($old['ty_le_ln'] ?? '30') ?>">
        <?php if (isset($errors['ty_le_ln'])): ?><div class="invalid-feedback"><?= $errors['ty_le_ln'] ?></div><?php endif; ?>
        <small class="text-muted">Mặc định 30%</small>
      </div>

      <!-- Ảnh bìa -->
      <div class="col-md-4">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Ảnh bìa</label>
        <input type="file" name="hinh"
               class="form-control <?= isset($errors['hinh']) ? 'is-invalid' : '' ?>"
               accept="image/*" onchange="previewImg(this)">
        <?php if (isset($errors['hinh'])): ?><div class="invalid-feedback"><?= $errors['hinh'] ?></div><?php endif; ?>
        <small class="text-muted">JPG/PNG/WebP, tối đa 2MB</small>
        <div class="mt-2">
          <img id="img-preview"
               style="display:none;width:80px;height:100px;object-fit:cover;border-radius:8px;border:1px solid #eee;">
        </div>
      </div>

      <!-- Mô tả -->
      <div class="col-12">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Mô tả</label>
        <textarea name="mo_ta" class="form-control" rows="4"
                  placeholder="Giới thiệu ngắn về nội dung sách..."><?= htmlspecialchars($old['mo_ta'] ?? '') ?></textarea>
      </div>

      <!-- Lưu ý -->
      <div class="col-12">
        <div class="p-3 rounded" style="background:#f0f9ff;border:1px solid #bee3f8;font-size:.83rem;color:#4a7c9e;">
          <i class="bi bi-info-circle me-1"></i>
          Sách mới thêm sẽ ở trạng thái <strong>ẩn</strong> và <strong>tồn kho = 0</strong>.
          Vào <strong>Nhập hàng</strong> để nhập hàng và kích hoạt sách.
        </div>
      </div>

      <!-- Nút -->
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-sm px-4"
                style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
          <i class="bi bi-check-lg me-1"></i>Thêm sách
        </button>
        <a href="/nhasach/admin/products.php" class="btn btn-sm btn-outline-secondary"
           style="border-radius:8px;">Huỷ</a>
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