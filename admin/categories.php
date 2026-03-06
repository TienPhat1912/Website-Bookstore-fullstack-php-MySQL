<?php
ob_start();
$page_title = 'Quản lý thể loại';
require_once 'includes/admin_header.php';

$errors  = [];
$old     = [];
$edit_tl = null;

// ---- SỬA: load dữ liệu ----
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM the_loai WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_tl = $stmt->fetch();
}

// ---- XOÁ ----
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    // Kiểm tra có sách thuộc thể loại này không
    $so_sach = $pdo->prepare("SELECT COUNT(*) FROM sach WHERE the_loai_id = ?");
    $so_sach->execute([$id]);
    if ($so_sach->fetchColumn() > 0) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Không thể xoá — thể loại này đang có sách.'];
    } else {
        $pdo->prepare("DELETE FROM the_loai WHERE id = ?")->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã xoá thể loại.'];
    }
    header('Location: /nhasach/admin/categories.php');
    exit;
}

// ---- ẨN / HIỆN ----
if (isset($_GET['action']) && in_array($_GET['action'], ['hide','show']) && isset($_GET['id'])) {
    $tt = $_GET['action'] === 'hide' ? 0 : 1;
    $pdo->prepare("UPDATE the_loai SET trang_thai = ? WHERE id = ?")->execute([$tt, (int)$_GET['id']]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => $tt ? 'Đã hiện thể loại.' : 'Đã ẩn thể loại.'];
    header('Location: /nhasach/admin/categories.php');
    exit;
}

// ---- THÊM / CẬP NHẬT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old     = $_POST;
    $action  = $_POST['action'] ?? 'create';
    $ten     = trim($_POST['ten']    ?? '');
    $mo_ta   = trim($_POST['mo_ta'] ?? '');
    $edit_id = (int)($_POST['edit_id'] ?? 0);

    if (empty($ten)) $errors['ten'] = 'Vui lòng nhập tên thể loại.';

    // Kiểm tra tên trùng
    if (empty($errors['ten'])) {
        $stmt = $pdo->prepare("SELECT id FROM the_loai WHERE ten = ? AND id != ?");
        $stmt->execute([$ten, $edit_id]);
        if ($stmt->fetch()) $errors['ten'] = 'Tên thể loại đã tồn tại.';
    }

    if (empty($errors)) {
        if ($action === 'create') {
            $pdo->prepare("INSERT INTO the_loai (ten, mo_ta) VALUES (?, ?)")
                ->execute([$ten, $mo_ta]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Thêm thể loại thành công!'];
        } else {
            $pdo->prepare("UPDATE the_loai SET ten = ?, mo_ta = ? WHERE id = ?")
                ->execute([$ten, $mo_ta, $edit_id]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Cập nhật thể loại thành công!'];
        }
        header('Location: /nhasach/admin/categories.php');
        exit;
    }

    // Có lỗi khi sửa
    if ($action === 'update') {
        $stmt = $pdo->prepare("SELECT * FROM the_loai WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_tl = $stmt->fetch();
    }
}

// Lấy danh sách thể loại kèm số sách
$the_loais = $pdo->query("
    SELECT tl.*, COUNT(s.id) AS so_sach
    FROM the_loai tl
    LEFT JOIN sach s ON s.the_loai_id = tl.id
    GROUP BY tl.id
    ORDER BY tl.ten
")->fetchAll();
?>

<div class="page-header">
  <h5><i class="bi bi-tag me-2" style="color:#f4a261;"></i>Quản lý thể loại</h5>
</div>

<div class="row g-4">

  <!-- FORM THÊM / SỬA -->
  <div class="col-lg-4">
    <div class="admin-card">
      <div class="card-title"><?= $edit_tl ? 'Sửa thể loại' : 'Thêm thể loại mới' ?></div>

      <form method="POST" action="/nhasach/admin/categories.php" novalidate>
        <input type="hidden" name="action" value="<?= $edit_tl ? 'update' : 'create' ?>">
        <?php if ($edit_tl): ?>
          <input type="hidden" name="edit_id" value="<?= $edit_tl['id'] ?>">
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.85rem;">
            Tên thể loại <span class="text-danger">*</span>
          </label>
          <input type="text" name="ten"
                 class="form-control <?= isset($errors['ten']) ? 'is-invalid' : '' ?>"
                 placeholder="VD: Văn học, Kinh tế..."
                 value="<?= htmlspecialchars($edit_tl['ten'] ?? $old['ten'] ?? '') ?>"
                 autofocus>
          <?php if (isset($errors['ten'])): ?>
            <div class="invalid-feedback"><?= $errors['ten'] ?></div>
          <?php endif; ?>
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold" style="font-size:.85rem;">Mô tả</label>
          <textarea name="mo_ta" class="form-control" rows="3"
                    placeholder="Mô tả ngắn về thể loại..."><?= htmlspecialchars($edit_tl['mo_ta'] ?? $old['mo_ta'] ?? '') ?></textarea>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-sm px-4"
                  style="background:#f4a261;color:#fff;border:none;border-radius:8px;">
            <i class="bi bi-check-lg me-1"></i><?= $edit_tl ? 'Cập nhật' : 'Thêm mới' ?>
          </button>
          <?php if ($edit_tl): ?>
            <a href="/nhasach/admin/categories.php"
               class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">Huỷ</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- DANH SÁCH -->
  <div class="col-lg-8">
    <div class="admin-card">
      <div class="card-title">Danh sách thể loại (<?= count($the_loais) ?>)</div>

      <?php if (empty($the_loais)): ?>
        <p class="text-muted text-center py-4">Chưa có thể loại nào.</p>
      <?php else: ?>
        <table class="table admin-table table-hover">
          <thead>
            <tr>
              <th>#</th>
              <th>Tên thể loại</th>
              <th>Mô tả</th>
              <th>Số sách</th>
              <th>Trạng thái</th>
              <th style="width:110px;"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($the_loais as $i => $tl): ?>
              <tr>
                <td style="color:#888; font-size:.82rem;"><?= $i + 1 ?></td>
                <td class="fw-semibold" style="font-size:.88rem;">
                  <?= htmlspecialchars($tl['ten']) ?>
                </td>
                <td style="font-size:.82rem; color:#888; max-width:200px;">
                  <?= htmlspecialchars($tl['mo_ta'] ?? '') ?>
                </td>
                <td>
                  <span class="badge bg-light text-dark border"><?= $tl['so_sach'] ?> sách</span>
                </td>
                <td>
                  <?php if ($tl['trang_thai']): ?>
                    <span class="badge bg-success">Đang hiện</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Đã ẩn</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="d-flex gap-1">
                    <!-- Sửa -->
                    <a href="/nhasach/admin/categories.php?edit=<?= $tl['id'] ?>"
                       class="btn btn-sm btn-outline-primary" style="border-radius:6px;" title="Sửa">
                      <i class="bi bi-pencil"></i>
                    </a>

                    <!-- Ẩn / Hiện -->
                    <?php if ($tl['trang_thai']): ?>
                      <a href="/nhasach/admin/categories.php?action=hide&id=<?= $tl['id'] ?>"
                         class="btn btn-sm btn-outline-warning" style="border-radius:6px;" title="Ẩn">
                        <i class="bi bi-eye-slash"></i>
                      </a>
                    <?php else: ?>
                      <a href="/nhasach/admin/categories.php?action=show&id=<?= $tl['id'] ?>"
                         class="btn btn-sm btn-outline-success" style="border-radius:6px;" title="Hiện">
                        <i class="bi bi-eye"></i>
                      </a>
                    <?php endif; ?>

                    <!-- Xoá -->
                    <?php if ($tl['so_sach'] == 0): ?>
                      <a href="/nhasach/admin/categories.php?action=delete&id=<?= $tl['id'] ?>"
                         class="btn btn-sm btn-outline-danger" style="border-radius:6px;" title="Xoá"
                         onclick="return confirm('Xoá thể loại này?')">
                        <i class="bi bi-trash3"></i>
                      </a>
                    <?php else: ?>
                      <button class="btn btn-sm btn-outline-danger" style="border-radius:6px; opacity:.4;"
                              title="Không thể xoá — đang có <?= $tl['so_sach'] ?> sách" disabled>
                        <i class="bi bi-trash3"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php
require_once 'includes/admin_footer.php';
ob_end_flush();
?>
