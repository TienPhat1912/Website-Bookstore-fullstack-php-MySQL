<?php
$page_title = 'Chi tiết sách';
require_once 'includes/header.php';

// Lấy ID sách từ URL
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /nhasach/books.php');
    exit;
}

// Lấy thông tin sách
$stmt = $pdo->prepare("
    SELECT s.*, tl.ten AS ten_the_loai,
           ROUND(s.gia_nhap * (1 + s.ty_le_ln/100), 0) AS gia_ban
    FROM sach s
    JOIN the_loai tl ON s.the_loai_id = tl.id
    WHERE s.id = ? AND s.hien_trang = 1
");
$stmt->execute([$id]);
$sach = $stmt->fetch();

if (!$sach) {
    header('Location: /nhasach/books.php');
    exit;
}

$page_title = $sach['ten'];

// Lấy sách cùng thể loại (gợi ý)
$stmt = $pdo->prepare("
    SELECT s.*, ROUND(s.gia_nhap * (1 + s.ty_le_ln/100), 0) AS gia_ban,
           tl.ten AS ten_the_loai
    FROM sach s
    JOIN the_loai tl ON s.the_loai_id = tl.id
    WHERE s.the_loai_id = ? AND s.id != ? AND s.hien_trang = 1 AND s.so_luong > 0
    ORDER BY RAND()
    LIMIT 4
");
$stmt->execute([$sach['the_loai_id'], $id]);
$sach_lien_quan = $stmt->fetchAll();
?>

<div class="container py-4">

  <!-- BREADCRUMB -->
  <nav class="mb-4">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/nhasach/index.php">Trang chủ</a></li>
      <li class="breadcrumb-item">
        <a href="/nhasach/books.php?the_loai=<?= $sach['the_loai_id'] ?>">
          <?= htmlspecialchars($sach['ten_the_loai']) ?>
        </a>
      </li>
      <li class="breadcrumb-item active"><?= htmlspecialchars($sach['ten']) ?></li>
    </ol>
  </nav>

  <!-- CHI TIẾT SÁCH -->
  <div class="row g-5">

    <!-- ẢNH BÌA -->
    <div class="col-md-4 text-center">
      <?php if (!empty($sach['hinh']) && file_exists("uploads/" . $sach['hinh'])): ?>
        <img src="/nhasach/uploads/<?= htmlspecialchars($sach['hinh']) ?>"
             alt="<?= htmlspecialchars($sach['ten']) ?>"
             class="book-detail-img">
      <?php else: ?>
        <div class="d-flex align-items-center justify-content-center"
             style="height:380px; background:linear-gradient(135deg,#e9ecef,#dee2e6);
                    border-radius:12px; color:#adb5bd; font-size:5rem;">
          <i class="bi bi-book"></i>
        </div>
      <?php endif; ?>

      <!-- Trạng thái tồn kho -->
      <div class="mt-3">
        <?php if ($sach['so_luong'] > 5): ?>
          <span class="badge bg-success px-3 py-2">
            <i class="bi bi-check-circle me-1"></i>Còn hàng
          </span>
        <?php elseif ($sach['so_luong'] > 0): ?>
          <span class="badge bg-warning text-dark px-3 py-2">
            <i class="bi bi-exclamation-circle me-1"></i>Sắp hết (còn <?= $sach['so_luong'] ?> cuốn)
          </span>
        <?php else: ?>
          <span class="badge bg-danger px-3 py-2">
            <i class="bi bi-x-circle me-1"></i>Hết hàng
          </span>
        <?php endif; ?>
      </div>
    </div>

    <!-- THÔNG TIN -->
    <div class="col-md-8">
      <span class="badge mb-2 px-3 py-1"
            style="background:#fff3e8; color:#f4a261; font-size:.8rem; border-radius:20px;">
        <?= htmlspecialchars($sach['ten_the_loai']) ?>
      </span>

      <h2 class="fw-bold mb-1" style="color:#1a1a2e; line-height:1.3;">
        <?= htmlspecialchars($sach['ten']) ?>
      </h2>

      <?php if (!empty($sach['tac_gia'])): ?>
        <p class="text-muted mb-3">
          <i class="bi bi-person me-1"></i>
          <strong><?= htmlspecialchars($sach['tac_gia']) ?></strong>
        </p>
      <?php endif; ?>

      <!-- Giá -->
      <div class="mb-4 p-3" style="background:#fff8f3; border-radius:12px; display:inline-block;">
        <div class="book-detail-price">
          <?= number_format($sach['gia_ban'], 0, ',', '.') ?>₫
        </div>
        <small class="text-muted">/ <?= htmlspecialchars($sach['don_vi_tinh']) ?></small>
      </div>

      <!-- Thông tin chi tiết -->
      <table class="table table-borderless mb-4" style="font-size:.92rem; max-width:460px;">
        <tr>
          <td class="text-muted ps-0" style="width:130px;">Mã sách</td>
          <td><strong><?= htmlspecialchars($sach['ma_sach']) ?></strong></td>
        </tr>
        <?php if (!empty($sach['nha_xb'])): ?>
        <tr>
          <td class="text-muted ps-0">Nhà xuất bản</td>
          <td><?= htmlspecialchars($sach['nha_xb']) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
          <td class="text-muted ps-0">Thể loại</td>
          <td>
            <a href="/nhasach/books.php?the_loai=<?= $sach['the_loai_id'] ?>"
               style="color:#f4a261;">
              <?= htmlspecialchars($sach['ten_the_loai']) ?>
            </a>
          </td>
        </tr>
        <tr>
          <td class="text-muted ps-0">Đơn vị tính</td>
          <td><?= htmlspecialchars($sach['don_vi_tinh']) ?></td>
        </tr>
      </table>

      <!-- THÊM VÀO GIỎ -->
      <?php if ($sach['so_luong'] > 0): ?>
        <?php if (isset($_SESSION['khach_hang_id'])): ?>
          <form action="/nhasach/cart.php" method="POST" class="d-flex align-items-center gap-3 flex-wrap mb-3">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="sach_id" value="<?= $sach['id'] ?>">

            <div class="d-flex align-items-center gap-2">
              <label class="text-muted" style="font-size:.9rem;">Số lượng:</label>
              <div class="d-flex align-items-center gap-1">
                <button type="button" class="cart-qty-btn" onclick="changeQty(-1)">−</button>
                <input type="number" name="so_luong" id="qty-input" class="qty-input"
                       value="1" min="1" max="<?= $sach['so_luong'] ?>">
                <button type="button" class="cart-qty-btn" onclick="changeQty(1)">+</button>
              </div>
            </div>

            <button type="submit" class="btn px-4 py-2 fw-semibold"
                    style="background:#f4a261; color:#fff; border:none; border-radius:25px;">
              <i class="bi bi-cart-plus me-2"></i>Thêm vào giỏ
            </button>
          </form>
        <?php else: ?>
          <div class="alert alert-warning d-flex align-items-center gap-3 mb-3"
               style="border-radius:12px; max-width:460px;">
            <i class="bi bi-lock fs-4"></i>
            <div>
              Bạn cần <a href="/nhasach/login.php" class="fw-bold" style="color:#f4a261;">đăng nhập</a>
              để thêm vào giỏ hàng.
              <a href="/nhasach/register.php" class="ms-2 text-muted" style="font-size:.85rem;">
                Chưa có tài khoản? Đăng ký ngay
              </a>
            </div>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <button class="btn btn-secondary px-4 py-2" disabled style="border-radius:25px;">
          <i class="bi bi-cart-x me-2"></i>Hết hàng
        </button>
      <?php endif; ?>

      <!-- Nút tiếp tục mua -->
      <div class="mt-2">
        <a href="/nhasach/books.php" class="text-muted" style="font-size:.88rem;">
          <i class="bi bi-arrow-left me-1"></i>Tiếp tục mua sắm
        </a>
      </div>
    </div>
  </div>

  <!-- MÔ TẢ -->
  <?php if (!empty($sach['mo_ta'])): ?>
  <div class="mt-5">
    <div class="p-4" style="background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.05);">
      <h5 class="fw-bold mb-3" style="color:#1a1a2e;">
        <i class="bi bi-journal-text me-2" style="color:#f4a261;"></i>Giới thiệu sách
      </h5>
      <div style="font-size:.95rem; line-height:1.9; color:#444; white-space:pre-line;">
        <?= nl2br(htmlspecialchars($sach['mo_ta'])) ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- SÁCH LIÊN QUAN -->
  <?php if (count($sach_lien_quan) > 0): ?>
  <div class="mt-5">
    <h5 class="section-title mb-4">Sách cùng thể loại</h5>
    <div class="row g-3">
      <?php foreach ($sach_lien_quan as $sach): ?>
        <?php include 'includes/card_sach.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<script>
function changeQty(delta) {
  const input = document.getElementById('qty-input');
  const max   = parseInt(input.max);
  const val   = parseInt(input.value) + delta;
  input.value = Math.min(max, Math.max(1, val));
}
</script>

<?php require_once 'includes/footer.php'; ?>