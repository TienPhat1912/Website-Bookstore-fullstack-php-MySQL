<?php
// Component card sách — dùng lại ở index.php, books.php, kết quả tìm kiếm
// Yêu cầu biến $sach đã được set trước khi include
// $sach cần có: id, ten, tac_gia, hinh, gia_ban, so_luong, ten_the_loai (nếu có)
?>
<div class="col-lg-3 col-md-4 col-sm-6 d-flex">
    <div class="card-sach w-100">

    <!-- Ảnh bìa -->
    <a href="/nhasach/book.php?id=<?= $sach['id'] ?>" 
   class="card-sach-img-wrap"
   style="display:block; height:200px; overflow:hidden;">
      <?php if (!empty($sach['hinh'])): ?>
<?php
$is_url  = str_starts_with($sach['hinh'], 'http');
$img_src = $is_url ? $sach['hinh'] : '/nhasach/uploads/' . $sach['hinh'];
?>
<img src="<?= htmlspecialchars($img_src) ?>"
     alt="<?= htmlspecialchars($sach['ten']) ?>"
     class="card-sach-img"
     style="width:100%; height:100%; object-fit:contain; object-position:center;"
     onerror="this.style.display='none'">
<?php else: ?>
        <div class="card-sach-no-img">
          <i class="bi bi-book"></i>
          <span>Chưa có bìa</span>
        </div>
      <?php endif; ?>

      <!-- Badge hết hàng -->
      <?php if ($sach['so_luong'] <= 0): ?>
        <span class="card-sach-badge-out">Hết hàng</span>
      <?php elseif ($sach['so_luong'] <= 5): ?>
        <span class="card-sach-badge-low">Sắp hết</span>
      <?php endif; ?>
    </a>

    <!-- Thông tin -->
    <div class="card-sach-body">
      <?php if (!empty($sach['ten_the_loai'])): ?>
        <span class="card-sach-tag"><?= htmlspecialchars($sach['ten_the_loai']) ?></span>
      <?php endif; ?>

      <a href="/nhasach/book.php?id=<?= $sach['id'] ?>" class="card-sach-title">
        <?= htmlspecialchars($sach['ten']) ?>
      </a>

      <?php if (!empty($sach['tac_gia'])): ?>
        <div class="card-sach-author">
          <i class="bi bi-person me-1"></i><?= htmlspecialchars($sach['tac_gia']) ?>
        </div>
      <?php endif; ?>

      <div class="card-sach-footer">
        <span class="card-sach-price">
          <?= number_format($sach['gia_ban'], 0, ',', '.') ?>₫
        </span>
        <?php if ($sach['so_luong'] > 0): ?>
          <button class="card-sach-btn-cart btn-them-gio"
          data-id="<?= $sach['id'] ?>"
          title="Thêm vào giỏ">
        <i class="bi bi-cart-plus"></i>
  </button>
<?php else: ?>
          <span class="card-sach-btn-disabled" title="Hết hàng">
            <i class="bi bi-cart-x"></i>
          </span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>