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
            $pdo->prepare("UPDATE sach SET hien_trang = 0 WHERE id = ?")->execute([$id]);
            $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Sách đã được ẩn khỏi cửa hàng (đã từng nhập hàng).'];
        } else {
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

// ---- HIỆN LẠI ----
if ($action === 'show' && isset($_GET['id'])) {
    $pdo->prepare("UPDATE sach SET hien_trang = 1 WHERE id = ?")->execute([(int)$_GET['id']]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã hiện sách trở lại.'];
    header('Location: /nhasach/admin/products.php');
    exit;
}

// ---- SỬA ----
$edit_sach = null;
$errors    = [];
$old       = [];

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM sach WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_sach = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    $old = $_POST;

    $ten         = trim($_POST['ten']          ?? '');
    $tac_gia     = trim($_POST['tac_gia']      ?? '');
    $the_loai_id = (int)($_POST['the_loai_id'] ?? 0);
    $nha_xb      = trim($_POST['nha_xb']       ?? '');
    $mo_ta       = trim($_POST['mo_ta']        ?? '');
    $don_vi      = trim($_POST['don_vi_tinh']  ?? 'cuon');
    $ty_le_ln    = (float)($_POST['ty_le_ln']  ?? 0);
    $edit_id     = (int)($_POST['edit_id']     ?? 0);

    if (empty($ten))       $errors['ten']         = 'Vui long nhap ten sach.';
    if ($the_loai_id <= 0) $errors['the_loai_id'] = 'Vui long chon the loai.';
    if ($ty_le_ln < 0)     $errors['ty_le_ln']    = 'Ty le loi nhuan khong hop le.';

    // Upload anh
    $hinh     = $old['hinh_cu'] ?? '';
    $xoa_hinh = isset($_POST['xoa_hinh']) && $_POST['xoa_hinh'] == '1';

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
        $stmt = $pdo->prepare("
            UPDATE sach SET ten=?, tac_gia=?, the_loai_id=?, nha_xb=?,
                            mo_ta=?, don_vi_tinh=?, hinh=?, ty_le_ln=?
            WHERE id = ?
        ");
        $stmt->execute([$ten, $tac_gia, $the_loai_id, $nha_xb, $mo_ta, $don_vi, $hinh, $ty_le_ln, $edit_id]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Cap nhat sach thanh cong!'];
        header('Location: /nhasach/admin/products.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM sach WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_sach = $stmt->fetch();
}

// ============================================================
// DANH SACH SACH + PHAN TRANG
// ============================================================
$filter_tl     = (int)($_GET['the_loai'] ?? 0);
$filter_search = trim($_GET['search'] ?? '');
$filter_tt     = $_GET['trang_thai'] ?? 'tat_ca';
$per_page      = 10;
$trang_hien    = max(1, (int)($_GET['trang'] ?? 1));

$where  = ["1=1"];
$params = [];
if ($filter_tl > 0)        { $where[] = "s.the_loai_id = ?"; $params[] = $filter_tl; }
if ($filter_search !== '') { $where[] = "(s.ten LIKE ? OR s.ma_sach LIKE ?)"; $params[] = "%$filter_search%"; $params[] = "%$filter_search%"; }
if ($filter_tt === 'hien')  { $where[] = "s.hien_trang = 1"; }
if ($filter_tt === 'an')    { $where[] = "s.hien_trang = 0"; }

$where_sql = implode(' AND ', $where);

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM sach s WHERE $where_sql");
$count_stmt->execute($params);
$total      = (int)$count_stmt->fetchColumn();
$total_page = max(1, (int)ceil($total / $per_page));
$trang_hien = min($trang_hien, $total_page);
$offset     = ($trang_hien - 1) * $per_page;

$sachs_stmt = $pdo->prepare("
    SELECT s.*, tl.ten AS ten_the_loai,
           ROUND(s.gia_nhap * (1 + s.ty_le_ln/100), 0) AS gia_ban
    FROM sach s JOIN the_loai tl ON tl.id = s.the_loai_id
    WHERE $where_sql
    ORDER BY s.ngay_tao DESC
    LIMIT $per_page OFFSET $offset
");
$sachs_stmt->execute($params);
$sachs = $sachs_stmt->fetchAll();

function page_url(int $p): string {
    $q = $_GET;
    $q['trang'] = $p;
    unset($q['edit'], $q['action']);
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

<?php if ($edit_sach): ?>
<div class="admin-card mb-4">
  <div class="card-title">Sửa thông tin sách — <span style="color:#f4a261;"><?= htmlspecialchars($edit_sach['ma_sach']) ?></span></div>

  <form method="POST" action="/nhasach/admin/products.php"
        enctype="multipart/form-data" novalidate>
    <input type="hidden" name="action"  value="update">
    <input type="hidden" name="edit_id" value="<?= $edit_sach['id'] ?>">
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
               value="<?= htmlspecialchars($edit_sach['ten'] ?? $old['ten'] ?? '') ?>">
        <?php if (isset($errors['ten'])): ?><div class="invalid-feedback"><?= $errors['ten'] ?></div><?php endif; ?>
      </div>

      <div class="col-md-3">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Tác giả</label>
        <input type="text" name="tac_gia" class="form-control"
               value="<?= htmlspecialchars($edit_sach['tac_gia'] ?? $old['tac_gia'] ?? '') ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Thể loại *</label>
        <select name="the_loai_id" class="form-select <?= isset($errors['the_loai_id']) ? 'is-invalid' : '' ?>">
          <option value="">-- Chọn --</option>
          <?php foreach ($the_loais as $tl): ?>
            <option value="<?= $tl['id'] ?>"
              <?= ($edit_sach['the_loai_id'] ?? $old['the_loai_id'] ?? 0) == $tl['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($tl['ten']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['the_loai_id'])): ?><div class="invalid-feedback"><?= $errors['the_loai_id'] ?></div><?php endif; ?>
      </div>

      <div class="col-md-3">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Nhà xuất bản</label>
        <input type="text" name="nha_xb" class="form-control"
               value="<?= htmlspecialchars($edit_sach['nha_xb'] ?? $old['nha_xb'] ?? '') ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Đơn vị tính</label>
        <input type="text" name="don_vi_tinh" class="form-control"
               value="<?= htmlspecialchars($edit_sach['don_vi_tinh'] ?? $old['don_vi_tinh'] ?? 'cuốn') ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label fw-semibold" style="font-size:.85rem;">Tỷ lệ LN (%)</label>
        <input type="number" name="ty_le_ln" min="0" max="500" step="0.1"
               class="form-control <?= isset($errors['ty_le_ln']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($edit_sach['ty_le_ln'] ?? $old['ty_le_ln'] ?? '30') ?>">
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
        <textarea name="mo_ta" class="form-control" rows="3"><?= htmlspecialchars($edit_sach['mo_ta'] ?? $old['mo_ta'] ?? '') ?></textarea>
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
            <a href="/nhasach/admin/products.php?action=delete&id=<?= $edit_sach['id'] ?>"
               class="btn btn-sm btn-outline-warning" style="border-radius:8px;"
               onclick="return confirm('An sach nay khoi cua hang?')">
              <i class="bi bi-eye-slash me-1"></i>Ẩn sách
            </a>
          <?php else: ?>
            <a href="/nhasach/admin/products.php?action=show&id=<?= $edit_sach['id'] ?>"
               class="btn btn-sm btn-outline-success" style="border-radius:8px;">
              <i class="bi bi-eye me-1"></i>Hiện sách
            </a>
          <?php endif; ?>
          <?php if (!$edit_sach['da_nhap_hang']): ?>
            <a href="/nhasach/admin/products.php?action=delete&id=<?= $edit_sach['id'] ?>"
               class="btn btn-sm btn-outline-danger" style="border-radius:8px;"
               onclick="return confirm('Xoa han sach nay? Khong the khoi phuc!')">
              <i class="bi bi-trash3 me-1"></i>Xoá hẳn
            </a>
          <?php endif; ?>
        </span>
      </div>
    </div>
  </form>
</div>
<?php endif; ?>

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
          <th style="width:80px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sachs as $s): ?>
          <tr style="cursor:pointer;"
              onclick="window.location='/nhasach/admin/products.php?edit=<?= $s['id'] ?>'"
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
            <td>
              <div class="d-flex gap-1" onclick="event.stopPropagation()">
                <?php if ($s['hien_trang']): ?>
                  <a href="/nhasach/admin/products.php?action=delete&id=<?= $s['id'] ?>"
                     class="btn btn-sm btn-outline-warning" style="border-radius:6px;"
                     title="An sach"
                     onclick="return confirm('An sach nay khoi cua hang?')">
                    <i class="bi bi-eye-slash"></i>
                  </a>
                <?php else: ?>
                  <a href="/nhasach/admin/products.php?action=show&id=<?= $s['id'] ?>"
                     class="btn btn-sm btn-outline-success" style="border-radius:6px;"
                     title="Hien lai">
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