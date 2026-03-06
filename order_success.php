<?php
$page_title = 'Đặt hàng thành công';
require_once 'includes/header.php';

// Phải có thông tin đơn vừa đặt
if (!isset($_SESSION['don_hang_vua_dat'])) {
    header('Location: /nhasach/index.php');
    exit;
}

$info        = $_SESSION['don_hang_vua_dat'];
$don_hang_id = $info['don_hang_id'];

// Lấy chi tiết đơn hàng
$stmt = $pdo->prepare("
    SELECT dh.*, kh.ho_ten AS ten_kh
    FROM don_hang dh
    JOIN khach_hang kh ON kh.id = dh.khach_hang_id
    WHERE dh.id = ?
");
$stmt->execute([$don_hang_id]);
$don = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT ct.*, s.ten AS ten_sach, s.hinh
    FROM chi_tiet_don_hang ct
    JOIN sach s ON s.id = ct.sach_id
    WHERE ct.don_hang_id = ?
");
$stmt->execute([$don_hang_id]);
$chi_tiets = $stmt->fetchAll();

// Xoá session sau khi hiển thị
unset($_SESSION['don_hang_vua_dat']);

$pt_label = [
    'tien_mat'     => 'Tiền mặt khi nhận hàng (COD)',
    'chuyen_khoan' => 'Chuyển khoản ngân hàng',
    'truc_tuyen'   => 'Thanh toán trực tuyến',
];
?>

<div class="container py-5">
  <div style="max-width:640px; margin:0 auto;">

    <!-- Thông báo thành công -->
    <div class="text-center mb-5">
      <div style="width:80px; height:80px; background:#d4edda; border-radius:50%;
                  display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
        <i class="bi bi-check-lg" style="font-size:2.5rem; color:#28a745;"></i>
      </div>
      <h4 class="fw-bold" style="color:#1a1a2e;">Đặt hàng thành công!</h4>
      <p class="text-muted">Cảm ơn bạn đã mua hàng. Chúng tôi sẽ xử lý đơn hàng sớm nhất có thể.</p>
      <div class="badge px-4 py-2 mt-1"
           style="background:#fff3e8; color:#f4a261; font-size:.95rem; border-radius:20px;">
        Mã đơn hàng: <strong><?= htmlspecialchars($don['ma_don']) ?></strong>
      </div>
    </div>

    <!-- Chi tiết đơn hàng -->
    <div style="background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); overflow:hidden;">

      <!-- Header -->
      <div class="px-4 py-3" style="background:#f8f9fa; border-bottom:1px solid #eee;">
        <h6 class="fw-bold mb-0" style="color:#1a1a2e;">
          <i class="bi bi-receipt me-2" style="color:#f4a261;"></i>Tóm tắt đơn hàng
        </h6>
      </div>

      <!-- Sách đã mua -->
      <div class="px-4 py-3">
        <?php foreach ($chi_tiets as $ct): ?>
          <div class="d-flex align-items-center gap-3 mb-3">
            <?php if (!empty($ct['hinh']) && file_exists("uploads/" . $ct['hinh'])): ?>
              <img src="/nhasach/uploads/<?= htmlspecialchars($ct['hinh']) ?>"
                   style="width:50px; height:65px; object-fit:cover; border-radius:6px; flex-shrink:0;">
            <?php else: ?>
              <div style="width:50px; height:65px; background:#f0f0f0; border-radius:6px;
                          display:flex; align-items:center; justify-content:center; color:#ccc; flex-shrink:0;">
                <i class="bi bi-book"></i>
              </div>
            <?php endif; ?>
            <div style="flex:1;">
              <div class="fw-semibold" style="font-size:.9rem; color:#1a1a2e;">
                <?= htmlspecialchars($ct['ten_sach']) ?>
              </div>
              <small class="text-muted">
                <?= number_format($ct['gia_ban_luc_dat'], 0, ',', '.') ?>₫ × <?= $ct['so_luong'] ?>
              </small>
            </div>
            <div class="fw-bold" style="color:#e63946; font-size:.9rem;">
              <?= number_format($ct['gia_ban_luc_dat'] * $ct['so_luong'], 0, ',', '.') ?>₫
            </div>
          </div>
        <?php endforeach; ?>

        <hr>

        <div class="d-flex justify-content-between fw-bold">
          <span>Tổng thanh toán</span>
          <span style="color:#e63946; font-size:1.1rem;">
            <?= number_format($don['tong_tien'], 0, ',', '.') ?>₫
          </span>
        </div>
      </div>

      <!-- Thông tin giao hàng -->
      <div class="px-4 py-3" style="border-top:1px solid #f0f0f0; background:#fafafa;">
        <div class="row g-2" style="font-size:.88rem;">
          <div class="col-5 text-muted">Người nhận</div>
          <div class="col-7 fw-semibold"><?= htmlspecialchars($don['ten_nguoi_nhan']) ?></div>

          <div class="col-5 text-muted">Số điện thoại</div>
          <div class="col-7"><?= htmlspecialchars($don['so_dien_thoai']) ?></div>

          <div class="col-5 text-muted">Địa chỉ</div>
          <div class="col-7">
            <?= htmlspecialchars($don['dia_chi']) ?>,
            <?= htmlspecialchars($don['phuong_xa']) ?>,
            <?= htmlspecialchars($don['tinh_tp']) ?>
          </div>

          <div class="col-5 text-muted">Thanh toán</div>
          <div class="col-7">
            <?= $pt_label[$don['phuong_thuc_tt']] ?? $don['phuong_thuc_tt'] ?>
          </div>

          <?php if ($don['phuong_thuc_tt'] === 'chuyen_khoan'): ?>
          <div class="col-12 mt-2">
            <div class="p-3" style="background:#e8f4fd; border-radius:8px; font-size:.85rem;">
              <i class="bi bi-info-circle me-2 text-primary"></i>
              <strong>Thông tin chuyển khoản:</strong><br>
              STK: <strong>1234 5678 90</strong> — Ngân hàng Vietcombank<br>
              Chủ tài khoản: <strong>NHA SACH ONLINE</strong><br>
              Nội dung: <strong><?= htmlspecialchars($don['ma_don']) ?></strong>
            </div>
          </div>
          <?php endif; ?>

          <div class="col-5 text-muted">Trạng thái</div>
          <div class="col-7">
            <span class="badge bg-warning text-dark">Chờ xử lý</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Nút điều hướng -->
    <div class="d-flex gap-3 mt-4 justify-content-center flex-wrap">
      <a href="/nhasach/orders.php" class="btn btn-outline-secondary px-4"
         style="border-radius:25px;">
        <i class="bi bi-bag-check me-2"></i>Xem lịch sử đơn hàng
      </a>
      <a href="/nhasach/books.php" class="btn px-4"
         style="background:#f4a261; color:#fff; border:none; border-radius:25px;">
        <i class="bi bi-shop me-2"></i>Tiếp tục mua sắm
      </a>
    </div>

  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
