</div><!-- end .admin-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/nhasach/assets/js/admin-search.js"></script>
<script>
// Tự ẩn flash sau 3 giây
setTimeout(() => {
  const flash = document.querySelector('.admin-flash .alert');
  if (flash) { flash.classList.remove('show'); setTimeout(() => flash.closest('.admin-flash')?.remove(), 300); }
}, 3000);
</script>
</body>
</html>
