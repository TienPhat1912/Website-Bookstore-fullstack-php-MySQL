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
  <!-- Toast thông báo giỏ hàng -->
<div id="cart-toast" style="
  position:fixed; bottom:24px; right:24px; z-index:9999;
  background:#1a1a2e; color:#fff; padding:14px 20px;
  border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,.2);
  display:none; align-items:center; gap:12px; font-size:.9rem;
  min-width:260px;">
  <i class="bi bi-cart-check" style="color:#f4a261; font-size:1.2rem;"></i>
  <span id="cart-toast-msg">Đã thêm vào giỏ hàng!</span>
  <a href="/nhasach/cart.php" style="color:#f4a261; margin-left:auto; font-size:.82rem;">Xem giỏ</a>
</div>

<script>
document.addEventListener('click', function(e) {
  const btn = e.target.closest('.btn-them-gio');
  if (!btn) return;

  const id = btn.dataset.id;
  fetch('/nhasach/cart_ajax.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'id=' + id
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      // Cập nhật badge giỏ hàng
      const badge = document.querySelector('.cart-badge');
      if (badge) {
        badge.textContent = data.tong;
        badge.style.display = 'inline';
      } else {
        const icon = document.querySelector('.cart-icon');
        if (icon) {
          const b = document.createElement('span');
          b.className = 'cart-badge';
          b.textContent = data.tong;
          icon.appendChild(b);
        }
      }

      // Hiện toast
      const toast = document.getElementById('cart-toast');
      const msg   = document.getElementById('cart-toast-msg');
      msg.textContent = '✓ Đã thêm: ' + data.ten;
      toast.style.display = 'flex';
      clearTimeout(window._cartToast);
      window._cartToast = setTimeout(() => toast.style.display = 'none', 3000);
    } else {
      alert(data.msg || 'Có lỗi xảy ra!');
    }
  });
});
(function() {
  const input   = document.getElementById('search-input');
  const suggest = document.getElementById('search-suggest');
  if (!input) return;

  let timer;
  input.addEventListener('input', function() {
    clearTimeout(timer);
    const q = this.value.trim();
    if (q.length < 2) { suggest.style.display = 'none'; return; }

    timer = setTimeout(() => {
      fetch('/nhasach/search_ajax.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
          if (!data.length) { suggest.style.display = 'none'; return; }
          suggest.innerHTML = data.map(s => `
            <a href="/nhasach/book.php?id=${s.id}" class="suggest-item">
              ${s.hinh
                ? `<img src="/nhasach/uploads/${s.hinh}" alt="">`
                : `<div class="suggest-item-no-img"><i class="bi bi-book"></i></div>`}
              <div>
                <div class="suggest-title">${s.ten}</div>
                <div class="suggest-author">${s.tac_gia || ''}</div>
              </div>
              <span class="suggest-price">${parseInt(s.gia_ban).toLocaleString('vi-VN')}₫</span>
            </a>
          `).join('');
          suggest.style.display = 'block';
        });
    }, 300);
  });

  // Ẩn khi click ra ngoài
  document.addEventListener('click', function(e) {
    if (!input.contains(e.target) && !suggest.contains(e.target))
      suggest.style.display = 'none';
  });
})();
</script>
<?php ob_end_flush(); ?>
</body>
</html>