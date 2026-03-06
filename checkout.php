<?php
$page_title = 'Đặt hàng';
require_once 'includes/header.php';

// Bảo vệ: phải đăng nhập
if (!isset($_SESSION['khach_hang_id'])) {
    $_SESSION['redirect_after_login'] = '/nhasach/checkout.php';
    header('Location: /nhasach/login.php');
    exit;
}

// Giỏ hàng trống thì về cart
$gio_hang = $_SESSION['gio_hang'] ?? [];
if (empty($gio_hang)) {
    header('Location: /nhasach/cart.php');
    exit;
}

// Lấy thông tin khách hàng
$stmt = $pdo->prepare("SELECT * FROM khach_hang WHERE id = ?");
$stmt->execute([$_SESSION['khach_hang_id']]);
$kh = $stmt->fetch();

// Lấy dữ liệu giỏ hàng từ DB
$ids      = implode(',', array_map('intval', array_keys($gio_hang)));
$stmt     = $pdo->query("
    SELECT id, ten, hinh, ma_sach, so_luong AS ton_kho,
           ROUND(gia_nhap * (1 + ty_le_ln/100), 0) AS gia_ban
    FROM sach WHERE id IN ($ids) AND hien_trang = 1
");
$sachs_db = $stmt->fetchAll();

$items     = [];
$tong_tien = 0;
foreach ($sachs_db as $s) {
    $sl = min($gio_hang[$s['id']], $s['ton_kho']);
    if ($sl > 0) {
        $thanh_tien = $s['gia_ban'] * $sl;
        $tong_tien += $thanh_tien;
        $items[]    = array_merge($s, ['so_luong' => $sl, 'thanh_tien' => $thanh_tien]);
    }
}

if (empty($items)) {
    header('Location: /nhasach/cart.php');
    exit;
}

// ============================================================
// XỬ LÝ ĐẶT HÀNG
// ============================================================
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dung_dia_chi_tk = $_POST['dung_dia_chi_tk'] ?? '0';

    if ($dung_dia_chi_tk === '1') {
        $ten_nn  = $kh['ho_ten'];
        $sdt     = $kh['so_dien_thoai'];
        $dia_chi = $kh['dia_chi'];
        $phuong  = $kh['phuong_xa'];
        $quan    = $kh['quan_huyen'];
        $tinh    = $kh['tinh_tp'];
    } else {
        $ten_nn  = trim($_POST['ten_nguoi_nhan'] ?? '');
        $sdt     = trim($_POST['so_dien_thoai']  ?? '');
        $dia_chi = trim($_POST['dia_chi']         ?? '');
        $phuong  = trim($_POST['phuong_xa']       ?? '');
        $quan    = trim($_POST['quan_huyen']      ?? '');
        $tinh    = trim($_POST['tinh_tp']         ?? '');

        if (empty($ten_nn)) $errors['ten_nn']  = 'Vui lòng nhập tên người nhận.';
        if (empty($sdt))    $errors['sdt']     = 'Vui lòng nhập số điện thoại.';
        elseif (!preg_match('/^[0-9]{9,11}$/', $sdt))
                            $errors['sdt']     = 'Số điện thoại không hợp lệ.';
        if (empty($dia_chi))$errors['dia_chi'] = 'Vui lòng nhập địa chỉ.';
        if (empty($phuong)) $errors['phuong']  = 'Vui lòng nhập phường/xã.';
        if (empty($quan))   $errors['quan']    = 'Vui lòng nhập quận/huyện.';
        if (empty($tinh))   $errors['tinh']    = 'Vui lòng nhập tỉnh/thành phố.';
    }

    $phuong_thuc = $_POST['phuong_thuc_tt'] ?? 'tien_mat';
    if (!in_array($phuong_thuc, ['tien_mat', 'chuyen_khoan', 'truc_tuyen'])) {
        $errors['phuong_thuc'] = 'Vui lòng chọn phương thức thanh toán.';
    }

    if (empty($errors)) {
        // Tạo mã đơn hàng: DH + timestamp
        $ma_don = 'DH' . date('YmdHis') . rand(10, 99);

        try {
            $pdo->beginTransaction();

            // Kiểm tra tồn kho lần cuối trước khi đặt
            foreach ($items as $item) {
                $stmt = $pdo->prepare("SELECT so_luong FROM sach WHERE id = ? FOR UPDATE");
                $stmt->execute([$item['id']]);
                $ton = $stmt->fetchColumn();
                if ($ton < $item['so_luong']) {
                    throw new Exception("Sách '{$item['ten']}' không đủ số lượng trong kho.");
                }
            }

            // Lưu đơn hàng
            $stmt = $pdo->prepare("
                INSERT INTO don_hang
                    (ma_don, khach_hang_id, ten_nguoi_nhan, so_dien_thoai,
                     dia_chi, phuong_xa, quan_huyen, tinh_tp,
                     phuong_thuc_tt, tong_tien, trang_thai)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'cho_xu_ly')
            ");
            $stmt->execute([
                $ma_don, $kh['id'], $ten_nn, $sdt,
                $dia_chi, $phuong, $quan, $tinh,
                $phuong_thuc, $tong_tien
            ]);
            $don_hang_id = $pdo->lastInsertId();

            // Lưu chi tiết & trừ tồn kho
            foreach ($items as $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO chi_tiet_don_hang
                        (don_hang_id, sach_id, so_luong, gia_ban_luc_dat)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$don_hang_id, $item['id'], $item['so_luong'], $item['gia_ban']]);

                $stmt = $pdo->prepare("UPDATE sach SET so_luong = so_luong - ? WHERE id = ?");
                $stmt->execute([$item['so_luong'], $item['id']]);
            }

            $pdo->commit();

            // Xoá giỏ hàng
            unset($_SESSION['gio_hang']);

            // Lưu mã đơn để hiển thị trang xác nhận
            $_SESSION['don_hang_vua_dat'] = [
                'ma_don'      => $ma_don,
                'don_hang_id' => $don_hang_id,
                'tong_tien'   => $tong_tien,
                'phuong_thuc' => $phuong_thuc,
            ];

            header('Location: /nhasach/order_success.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['chung'] = $e->getMessage();
        }
    }
}
?>

<div class="container py-4">
  <h4 class="section-title mb-4">Đặt hàng</h4>

  <?php if (!empty($errors['chung'])): ?>
    <div class="alert alert-danger" style="border-radius:10px;">
      <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($errors['chung']) ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="/nhasach/checkout.php" id="checkout-form" novalidate>
  <div class="row g-4">

    <!-- CỘT TRÁI: Địa chỉ + Thanh toán -->
    <div class="col-lg-7">

      <!-- ĐỊA CHỈ GIAO HÀNG -->
      <div style="background:#fff; border-radius:14px; padding:24px;
                  box-shadow:0 2px 10px rgba(0,0,0,.06); margin-bottom:20px;">
        <h6 class="fw-bold mb-4" style="color:#1a1a2e;">
          <i class="bi bi-geo-alt me-2" style="color:#f4a261;"></i>Địa chỉ giao hàng
        </h6>

        <!-- Tuỳ chọn dùng địa chỉ tài khoản -->
        <?php if (!empty($kh['dia_chi'])): ?>
          <div class="mb-4">
            <div class="form-check p-3 mb-2"
                 style="border:2px solid #f4a261; border-radius:10px; background:#fff8f3; cursor:pointer;"
                 onclick="chonDiaChi('1')">
              <input class="form-check-input" type="radio" name="dung_dia_chi_tk"
                     id="dc_taikhoan" value="1"
                     <?= ($_POST['dung_dia_chi_tk'] ?? '1') === '1' ? 'checked' : '' ?>
                     onchange="chonDiaChi('1')">
              <label class="form-check-label w-100" for="dc_taikhoan" style="cursor:pointer;">
                <span class="fw-semibold" style="font-size:.9rem;">Dùng địa chỉ tài khoản</span><br>
                <small class="text-muted">
                  <?= htmlspecialchars($kh['ho_ten']) ?> —
                  <?= htmlspecialchars($kh['dia_chi']) ?>,
                  <?= htmlspecialchars($kh['phuong_xa']) ?>,
                  <?= htmlspecialchars($kh['quan_huyen']) ?>,
                  <?= htmlspecialchars($kh['tinh_tp']) ?>
                </small>
              </label>
            </div>

            <div class="form-check p-3"
                 style="border:2px solid #dee2e6; border-radius:10px; cursor:pointer;"
                 onclick="chonDiaChi('0')" id="dc_moi_wrap">
              <input class="form-check-input" type="radio" name="dung_dia_chi_tk"
                     id="dc_moi" value="0"
                     <?= ($_POST['dung_dia_chi_tk'] ?? '1') === '0' ? 'checked' : '' ?>
                     onchange="chonDiaChi('0')">
              <label class="form-check-label" for="dc_moi" style="cursor:pointer; font-size:.9rem;">
                <span class="fw-semibold">Nhập địa chỉ giao hàng mới</span>
              </label>
            </div>
          </div>
        <?php else: ?>
          <input type="hidden" name="dung_dia_chi_tk" value="0">
        <?php endif; ?>

        <!-- Form địa chỉ mới -->
        <div id="form_dia_chi_moi"
             style="<?= (($_POST['dung_dia_chi_tk'] ?? '1') === '1' && !empty($kh['dia_chi'])) ? 'display:none;' : '' ?>">

          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem;">
              Tên người nhận <span class="text-danger">*</span>
            </label>
            <input type="text" name="ten_nguoi_nhan"
                   class="form-control <?= isset($errors['ten_nn']) ? 'is-invalid' : '' ?>"
                   placeholder="Nguyễn Văn A"
                   value="<?= htmlspecialchars($_POST['ten_nguoi_nhan'] ?? $kh['ho_ten']) ?>">
            <?php if (isset($errors['ten_nn'])): ?>
              <div class="invalid-feedback"><?= $errors['ten_nn'] ?></div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem;">
              Số điện thoại <span class="text-danger">*</span>
            </label>
            <input type="tel" name="so_dien_thoai"
                   class="form-control <?= isset($errors['sdt']) ? 'is-invalid' : '' ?>"
                   placeholder="0909 123 456"
                   value="<?= htmlspecialchars($_POST['so_dien_thoai'] ?? $kh['so_dien_thoai']) ?>">
            <?php if (isset($errors['sdt'])): ?>
              <div class="invalid-feedback"><?= $errors['sdt'] ?></div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem;">
              Số nhà, tên đường <span class="text-danger">*</span>
            </label>
            <input type="text" name="dia_chi"
                   class="form-control <?= isset($errors['dia_chi']) ? 'is-invalid' : '' ?>"
                   placeholder="123 Đường Nguyễn Văn Cừ"
                   value="<?= htmlspecialchars($_POST['dia_chi'] ?? '') ?>">
            <?php if (isset($errors['dia_chi'])): ?>
              <div class="invalid-feedback"><?= $errors['dia_chi'] ?></div>
            <?php endif; ?>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label fw-semibold" style="font-size:.85rem;">
                Phường/Xã <span class="text-danger">*</span>
              </label>
              <input type="text" name="phuong_xa"
                     class="form-control <?= isset($errors['phuong']) ? 'is-invalid' : '' ?>"
                     placeholder="Phường 5"
                     value="<?= htmlspecialchars($_POST['phuong_xa'] ?? '') ?>">
              <?php if (isset($errors['phuong'])): ?>
                <div class="invalid-feedback"><?= $errors['phuong'] ?></div>
              <?php endif; ?>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold" style="font-size:.85rem;">
                Quận/Huyện <span class="text-danger">*</span>
              </label>
              <input type="text" name="quan_huyen"
                     class="form-control <?= isset($errors['quan']) ? 'is-invalid' : '' ?>"
                     placeholder="Quận 5"
                     value="<?= htmlspecialchars($_POST['quan_huyen'] ?? '') ?>">
              <?php if (isset($errors['quan'])): ?>
                <div class="invalid-feedback"><?= $errors['quan'] ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label fw-semibold" style="font-size:.85rem;">
              Tỉnh/Thành phố <span class="text-danger">*</span>
            </label>
            <input type="text" name="tinh_tp"
                   class="form-control <?= isset($errors['tinh']) ? 'is-invalid' : '' ?>"
                   placeholder="TP. Hồ Chí Minh"
                   value="<?= htmlspecialchars($_POST['tinh_tp'] ?? '') ?>">
            <?php if (isset($errors['tinh'])): ?>
              <div class="invalid-feedback"><?= $errors['tinh'] ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- PHƯƠNG THỨC THANH TOÁN -->
      <div style="background:#fff; border-radius:14px; padding:24px;
                  box-shadow:0 2px 10px rgba(0,0,0,.06);">
        <h6 class="fw-bold mb-4" style="color:#1a1a2e;">
          <i class="bi bi-credit-card me-2" style="color:#f4a261;"></i>Phương thức thanh toán
        </h6>

        <?php
        $phuong_thucs = [
            'tien_mat'     => ['icon' => 'bi-cash-coin',        'label' => 'Tiền mặt khi nhận hàng (COD)',  'desc' => 'Thanh toán khi shipper giao hàng tới tay bạn.'],
            'chuyen_khoan' => ['icon' => 'bi-bank',             'label' => 'Chuyển khoản ngân hàng',         'desc' => 'STK: 1234 5678 90 — NH Vietcombank — Nhà Sách Online'],
            'truc_tuyen'   => ['icon' => 'bi-phone',            'label' => 'Thanh toán trực tuyến',          'desc' => 'Tính năng đang được phát triển, sẽ sớm ra mắt.'],
        ];
        $pt_chon = $_POST['phuong_thuc_tt'] ?? 'tien_mat';
        foreach ($phuong_thucs as $val => $pt):
        ?>
          <div class="form-check p-3 mb-2 <?= $pt_chon === $val ? 'pt-active' : '' ?>"
               style="border:2px solid <?= $pt_chon === $val ? '#f4a261' : '#dee2e6' ?>;
                      border-radius:10px; cursor:pointer;
                      background:<?= $pt_chon === $val ? '#fff8f3' : '#fff' ?>;"
               onclick="chonPhuongThuc('<?= $val ?>')">
            <input class="form-check-input" type="radio"
                   name="phuong_thuc_tt" id="pt_<?= $val ?>" value="<?= $val ?>"
                   <?= $pt_chon === $val ? 'checked' : '' ?>
                   onchange="chonPhuongThuc('<?= $val ?>')">
            <label class="form-check-label w-100" for="pt_<?= $val ?>" style="cursor:pointer;">
              <span class="fw-semibold" style="font-size:.9rem;">
                <i class="bi <?= $pt['icon'] ?> me-2" style="color:#f4a261;"></i>
                <?= $pt['label'] ?>
              </span>
              <div class="mt-1" style="font-size:.82rem; color:#888; padding-left:24px;">
                <?= $pt['desc'] ?>
              </div>
            </label>
          </div>
        <?php endforeach; ?>

        <?php if (isset($errors['phuong_thuc'])): ?>
          <div class="text-danger" style="font-size:.85rem;"><?= $errors['phuong_thuc'] ?></div>
        <?php endif; ?>
      </div>

    </div>

    <!-- CỘT PHẢI: Tóm tắt đơn hàng -->
    <div class="col-lg-5">
      <div class="order-summary">
        <h6 class="fw-bold mb-3" style="color:#1a1a2e; font-size:1rem;">
          <i class="bi bi-bag-check me-2" style="color:#f4a261;"></i>
          Đơn hàng (<?= count($items) ?> sách)
        </h6>

        <!-- Danh sách sách -->
        <div style="max-height:280px; overflow-y:auto; margin-bottom:16px;">
          <?php foreach ($items as $item): ?>
            <div class="d-flex gap-3 mb-3 align-items-center">
              <?php if (!empty($item['hinh']) && file_exists("uploads/" . $item['hinh'])): ?>
                <img src="/nhasach/uploads/<?= htmlspecialchars($item['hinh']) ?>"
                     style="width:50px; height:65px; object-fit:cover; border-radius:6px; flex-shrink:0;">
              <?php else: ?>
                <div style="width:50px; height:65px; background:#f0f0f0; border-radius:6px;
                            display:flex; align-items:center; justify-content:center; flex-shrink:0; color:#ccc;">
                  <i class="bi bi-book"></i>
                </div>
              <?php endif; ?>
              <div style="flex:1; min-width:0;">
                <div class="fw-semibold text-truncate" style="font-size:.85rem; color:#1a1a2e;">
                  <?= htmlspecialchars($item['ten']) ?>
                </div>
                <small class="text-muted">x<?= $item['so_luong'] ?></small>
              </div>
              <div class="fw-bold text-nowrap" style="font-size:.88rem; color:#e63946;">
                <?= number_format($item['thanh_tien'], 0, ',', '.') ?>₫
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <hr>

        <div class="d-flex justify-content-between mb-2" style="font-size:.88rem;">
          <span class="text-muted">Tạm tính</span>
          <span><?= number_format($tong_tien, 0, ',', '.') ?>₫</span>
        </div>
        <div class="d-flex justify-content-between mb-3" style="font-size:.88rem;">
          <span class="text-muted">Phí vận chuyển</span>
          <span class="text-success fw-semibold">Miễn phí</span>
        </div>

        <hr>

        <div class="d-flex justify-content-between mb-4">
          <span class="fw-bold">Tổng thanh toán</span>
          <span class="fw-bold" style="font-size:1.2rem; color:#e63946;">
            <?= number_format($tong_tien, 0, ',', '.') ?>₫
          </span>
        </div>

        <button type="submit" class="btn w-100 py-2 fw-semibold"
                style="background:#f4a261; color:#fff; border:none; border-radius:10px; font-size:.95rem;">
          <i class="bi bi-bag-check me-2"></i>Xác nhận đặt hàng
        </button>

        <a href="/nhasach/cart.php" class="btn btn-outline-secondary w-100 mt-2 py-2"
           style="border-radius:10px; font-size:.88rem;">
          <i class="bi bi-arrow-left me-1"></i>Quay lại giỏ hàng
        </a>
      </div>
    </div>

  </div>
  </form>
</div>

<script>
function chonDiaChi(val) {
  document.querySelectorAll('[name="dung_dia_chi_tk"]').forEach(r => r.value === val ? r.checked = true : null);
  const formMoi = document.getElementById('form_dia_chi_moi');
  const wrapMoi = document.getElementById('dc_moi_wrap');
  if (val === '0') {
    formMoi.style.display = 'block';
    if (wrapMoi) wrapMoi.style.borderColor = '#f4a261';
  } else {
    formMoi.style.display = 'none';
    if (wrapMoi) wrapMoi.style.borderColor = '#dee2e6';
  }
}

function chonPhuongThuc(val) {
  document.querySelectorAll('[name="phuong_thuc_tt"]').forEach(r => {
    const wrap = r.closest('.form-check');
    if (r.value === val) {
      r.checked = true;
      wrap.style.borderColor = '#f4a261';
      wrap.style.background  = '#fff8f3';
    } else {
      wrap.style.borderColor = '#dee2e6';
      wrap.style.background  = '#fff';
    }
  });
}
</script>

<?php require_once 'includes/footer.php'; ?>