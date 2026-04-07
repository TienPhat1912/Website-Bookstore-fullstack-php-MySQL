<?php
$page_title = 'Trang chủ';
require_once 'includes/header.php';

// Lấy danh sách thể loại
$stmt = $pdo->query("SELECT * FROM the_loai WHERE trang_thai = 1");
$the_loais = $stmt->fetchAll();

// Lấy sách nổi bật (8 cuốn mới nhất có hàng)
$stmt = $pdo->query("
    SELECT s.*, tl.ten AS ten_the_loai,
           ROUND(s.gia_nhap * (1 + s.ty_le_ln/100), 0) AS gia_ban
    FROM sach s
    JOIN the_loai tl ON s.the_loai_id = tl.id
    WHERE s.hien_trang = 1 AND s.so_luong > 0 AND tl.trang_thai = 1
    ORDER BY s.ngay_tao DESC
    LIMIT 8
");
$sach_moi = $stmt->fetchAll();

// Lấy sách theo từng thể loại (4 cuốn mỗi loại)
$sach_theo_loai = [];
foreach ($the_loais as $tl) {
    $stmt = $pdo->prepare("
        SELECT s.*, ROUND(s.gia_nhap * (1 + s.ty_le_ln/100), 0) AS gia_ban
        FROM sach s
        WHERE s.the_loai_id = ? AND s.hien_trang = 1 AND s.so_luong > 0
        ORDER BY s.ngay_tao DESC
        LIMIT 4
    ");
    $stmt->execute([$tl['id']]);
    $sachs = $stmt->fetchAll();
    if (count($sachs) > 0) {
        $sach_theo_loai[] = ['the_loai' => $tl, 'sachs' => $sachs];
    }
}
?>

<!-- BANNER -->
<div class="hero-banner d-flex align-items-center"
     style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
            min-height: 380px; padding: 60px 0;">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6">
        <span class="badge mb-3 px-3 py-2" style="background:#f4a261; font-size:.85rem; border-radius:20px;">
          📚 Kho sách phong phú
        </span>
        <h1 class="display-5 fw-bold text-white mb-3">
          Khám phá thế giới<br>
          <span style="color:#f4a261;">tri thức</span> qua từng trang sách
        </h1>
        <p class="text-white-50 mb-4" style="font-size:1.05rem;">
          Hàng nghìn đầu sách đa thể loại — văn học, kinh tế, kỹ năng, khoa học và nhiều hơn nữa.
        </p>
        <div class="d-flex gap-3 flex-wrap">
          <a href="/nhasach/books.php" class="btn px-4 py-2 fw-semibold"
             style="background:#f4a261; color:#fff; border-radius:25px; border:none;">
            Xem tất cả sách <i class="bi bi-arrow-right ms-1"></i>
          </a>
          <a href="/nhasach/books.php?search=" class="btn btn-outline-light px-4 py-2 fw-semibold"
             style="border-radius:25px;">
            Tìm kiếm sách
          </a>
        </div>
      </div>
      <div class="col-lg-6 text-center d-none d-lg-block">
        <i class="bi bi-book-half" style="font-size: 180px; color: rgba(244,162,97,0.2);"></i>
      </div>
    </div>
  </div>
</div>

<!-- THỂ LOẠI NHANH -->
<div style="background:#fff; border-bottom: 1px solid #eee; padding: 20px 0;">
  <div class="container">
    <div class="d-flex gap-2 flex-wrap">
      <a href="/nhasach/books.php"
         class="btn btn-sm px-3"
         style="border-radius:20px; background:#1a1a2e; color:#fff; border:none;">
        Tất cả
      </a>
      <?php foreach ($the_loais as $tl): ?>
        <a href="/nhasach/books.php?the_loai=<?= $tl['id'] ?>"
           class="btn btn-sm btn-outline-secondary px-3"
           style="border-radius:20px; font-size:.85rem;">
          <?= htmlspecialchars($tl['ten']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="container py-5">

  <!-- SÁCH MỚI NHẤT -->
  <?php if (count($sach_moi) > 0): ?>
  <section class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-bold mb-0" style="color:#1a1a2e;">
          <span style="border-left:4px solid #f4a261; padding-left:12px;">Sách mới nhất</span>
        </h4>
        <small class="text-muted ms-3">Vừa cập nhật</small>
      </div>
      <a href="/nhasach/books.php" class="btn btn-sm btn-outline-secondary" style="border-radius:20px;">
        Xem thêm <i class="bi bi-chevron-right"></i>
      </a>
    </div>
    <div class="row g-3">
      <?php foreach ($sach_moi as $sach): ?>
        <?php include 'includes/card_sach.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- SÁCH THEO THỂ LOẠI -->
  <?php foreach ($sach_theo_loai as $nhom): ?>
  <section class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-bold mb-0" style="color:#1a1a2e;">
          <span style="border-left:4px solid #f4a261; padding-left:12px;">
            <?= htmlspecialchars($nhom['the_loai']['ten']) ?>
          </span>
        </h4>
      </div>
      <a href="/nhasach/books.php?the_loai=<?= $nhom['the_loai']['id'] ?>"
         class="btn btn-sm btn-outline-secondary" style="border-radius:20px;">
        Xem thêm <i class="bi bi-chevron-right"></i>
      </a>
    </div>
    <div class="row g-3">
      <?php foreach ($nhom['sachs'] as $sach): ?>
        <?php include 'includes/card_sach.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>

  <!-- Trường hợp chưa có sách nào -->
  <?php if (count($sach_moi) === 0): ?>
  <div class="text-center py-5">
    <i class="bi bi-inbox" style="font-size:4rem; color:#dee2e6;"></i>
    <h5 class="text-muted mt-3">Chưa có sách nào trong kho</h5>
    <p class="text-muted">Hệ thống đang cập nhật sách mới, vui lòng quay lại sau.</p>
<a href="/nhasach/books.php" class="btn btn-outline-secondary mt-2">Khám phá danh mục</a>
  </div>
  <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
