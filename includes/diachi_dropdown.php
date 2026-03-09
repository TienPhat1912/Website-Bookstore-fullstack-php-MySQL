<!-- Địa chỉ cụ thể -->
<div class="mb-3">
  <label class="form-label fw-semibold" style="font-size:.88rem;">
    Số nhà, tên đường <span class="text-danger">*</span>
  </label>
  <input type="text"
         name="dia_chi"
         class="form-control <?= isset($errors['dia_chi']) ? 'is-invalid' : '' ?>"
         placeholder="VD: 123 Đường Nguyễn Văn Cừ"
         value="<?= htmlspecialchars($dc_diachi ?? '') ?>">
  <?php if (isset($errors['dia_chi'])): ?>
    <div class="invalid-feedback"><?= $errors['dia_chi'] ?></div>
  <?php endif; ?>
</div>

<!-- Tỉnh / Thành phố -->
<div class="mb-3">
  <label class="form-label fw-semibold" style="font-size:.88rem;">
    Tỉnh / Thành phố <span class="text-danger">*</span>
  </label>
  <select name="tinh_tp"
          id="sel_tinh_<?= $dc_prefix ?>"
          class="form-select <?= isset($errors['tinh']) ? 'is-invalid' : '' ?>"
          onchange="loadPhuong('<?= $dc_prefix ?>')">
    <option value="">-- Chọn Tỉnh / Thành phố --</option>
  </select>
  <?php if (isset($errors['tinh'])): ?>
    <div class="invalid-feedback"><?= $errors['tinh'] ?></div>
  <?php endif; ?>
</div>

<!-- Phường / Xã -->
<div class="mb-3">
  <label class="form-label fw-semibold" style="font-size:.88rem;">
    Phường / Xã <span class="text-danger">*</span>
  </label>
  <select name="phuong_xa"
          id="sel_phuong_<?= $dc_prefix ?>"
          class="form-select <?= isset($errors['phuong']) ? 'is-invalid' : '' ?>"
          <?= empty($dc_tinh ?? '') ? 'disabled' : '' ?>>
    <option value="">-- Chọn Phường / Xã --</option>
  </select>
  <?php if (isset($errors['phuong'])): ?>
    <div class="invalid-feedback"><?= $errors['phuong'] ?></div>
  <?php endif; ?>
</div>

<!-- Giá trị cũ để JS tự chọn lại khi reload -->
<script>
(function() {
  const PREFIX   = '<?= $dc_prefix ?>';
  const OLD_TINH = <?= json_encode($dc_tinh   ?? '') ?>;
  const OLD_PHUONG = <?= json_encode($dc_phuong ?? '') ?>;

  // Load JSON 1 lần, cache vào window
  function getDiaChi(cb) {
    if (window._diaChiData) return cb(window._diaChiData);
    fetch('/nhasach/assets/js/diachi.json')
      .then(r => r.json())
      .then(data => { window._diaChiData = data; cb(data); });
  }

  // Điền tỉnh vào select
  function fillTinh(data) {
    const sel = document.getElementById('sel_tinh_' + PREFIX);
    Object.keys(data).sort().forEach(tinh => {
      const opt = document.createElement('option');
      opt.value = tinh;
      opt.textContent = tinh;
      if (tinh === OLD_TINH) opt.selected = true;
      sel.appendChild(opt);
    });
    // Nếu có giá trị cũ thì load phường ngay
    if (OLD_TINH) fillPhuong(data, OLD_TINH, OLD_PHUONG);
  }

  // Điền phường vào select
  function fillPhuong(data, tinh, oldPhuong = '') {
    const sel = document.getElementById('sel_phuong_' + PREFIX);
    sel.innerHTML = '<option value="">-- Chọn Phường / Xã --</option>';
    sel.disabled = false;
    const phuongs = data[tinh] || [];
    phuongs.sort().forEach(p => {
      const opt = document.createElement('option');
      opt.value = p;
      opt.textContent = p;
      if (p === oldPhuong) opt.selected = true;
      sel.appendChild(opt);
    });
  }

  // Gọi khi đổi tỉnh
  
  window['loadPhuong_' + PREFIX] = function() {
    const tinh = document.getElementById('sel_tinh_' + PREFIX).value;
    getDiaChi(data => fillPhuong(data, tinh));
  };

  // Override hàm chung loadPhuong để hỗ trợ nhiều form trên cùng trang
  document.getElementById('sel_tinh_' + PREFIX)
    .addEventListener('change', function() {
      window['loadPhuong_' + PREFIX]();
    });

  // Khởi tạo
  getDiaChi(fillTinh);
})();
</script>
