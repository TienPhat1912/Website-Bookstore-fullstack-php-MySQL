<!-- FOOTER -->
<footer class="mt-5" style="background:#1a1a2e; color: rgba(255,255,255,0.75);">
  <div class="container py-4">
    <div class="row g-4">

      <!-- Giới thiệu -->
      <div class="col-md-4">
        <h6 class="text-white fw-bold mb-3">
          <i class="bi bi-book-half me-2" style="color:#f4a261;"></i>Nhà Sách Online
        </h6>
        <p style="font-size:0.85rem; line-height:1.7;">
          Cung cấp sách chất lượng, đa dạng thể loại.<br>
          Giao hàng nhanh, uy tín toàn quốc.
        </p>
      </div>

      <!-- Danh mục nhanh -->
      <div class="col-md-4">
        <h6 class="text-white fw-bold mb-3">Danh mục</h6>
        <ul class="list-unstyled" style="font-size:0.85rem;">
          <?php
          // Lấy thể loại từ DB để hiển thị ở footer
          try {
              $stmt = $pdo->query("SELECT id, ten FROM the_loai WHERE trang_thai = 1 LIMIT 6");
              while ($tl = $stmt->fetch()):
          ?>
            <li class="mb-1">
              <a href="/nhasach/books.php?the_loai=<?= $tl['id'] ?>"
                 style="color:rgba(255,255,255,0.65); text-decoration:none;"
                 onmouseover="this.style.color='#f4a261'" onmouseout="this.style.color='rgba(255,255,255,0.65)'">
                <i class="bi bi-chevron-right me-1" style="font-size:0.7rem;"></i>
                <?= htmlspecialchars($tl['ten']) ?>
              </a>
            </li>
          <?php
              endwhile;
          } catch (Exception $e) { /* bỏ qua nếu lỗi */ }
          ?>
        </ul>
      </div>

      <!-- Liên hệ -->
      <div class="col-md-4">
        <h6 class="text-white fw-bold mb-3">Liên hệ</h6>
        <ul class="list-unstyled" style="font-size:0.85rem; line-height:2;">
          <li><i class="bi bi-geo-alt me-2" style="color:#f4a261;"></i>123 Đường Sách, Q.1, TP.HCM</li>
          <li><i class="bi bi-telephone me-2" style="color:#f4a261;"></i>0909 123 456</li>
          <li><i class="bi bi-envelope me-2" style="color:#f4a261;"></i>lienhe@nhasach.com</li>
        </ul>
      </div>

    </div>

    <hr style="border-color: rgba(255,255,255,0.1); margin: 16px 0 12px;">
    <p class="text-center mb-0" style="font-size:0.8rem; color:rgba(255,255,255,0.4);">
      © <?= date('Y') ?> Nhà Sách Online — Đồ Án Cuối Kỳ Lập Trình Web
    </p>
  </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Tự ẩn flash message sau 3 giây -->
<script>
  setTimeout(() => {
    const flash = document.querySelector('.flash-message .alert');
    if (flash) {
      flash.classList.remove('show');
      setTimeout(() => flash.closest('.flash-message').remove(), 300);
    }
  }, 3000);
</script>
<?php ob_end_flush(); ?>
</body>
</html>