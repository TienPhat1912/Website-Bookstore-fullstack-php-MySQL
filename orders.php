<?php
$page_title = 'Lịch sử đơn hàng';
require_once 'includes/header.php';

if (!isset($_SESSION['khach_hang_id'])) {
    $_SESSION['redirect_after_login'] = '/nhasach/orders.php';
    header('Location: /nhasach/login.php');
    exit;
}

$kh_id = $_SESSION['khach_hang_id'];

// Xem chi tiết 1 đơn
$xem_id = (int)($_GET['id'] ?? 0);
if ($xem_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM don_hang WHERE id = ? AND khach_hang_id = ?");
    $stmt->execute([$xem_id, $kh_id]);
    $don = $stmt->fetch();
    if (!$don) {
        header('Location: /nhasach/orders.php');
        exit;
    }
    $stmt = $pdo->prepare("
        SELECT ct.*, s.ten AS ten_sach, s.hinh, s.ma_sach, s.don_vi_tinh
        FROM chi_tiet_don_hang ct
        JOIN sach s ON s.id = ct.sach_id
        WHERE ct.don_hang_id = ?
    ");
    $stmt->execute([$xem_id]);
    $chi_tiets = $stmt->fetchAll();
}

// Lấy tất cả đơn hàng — mới nhất trên
$stmt = $pdo->prepare("
    SELECT dh.*,
           COUNT(ct.id) AS so_sach
    FROM don_hang dh
    LEFT JOIN chi_tiet_don_hang ct ON ct.don_hang_id = dh.id
    WHERE dh.khach_hang_id = ?
    GROUP BY dh.id
    ORDER BY dh.ngay_dat DESC
");
$stmt->execute([$kh_id]);
$don_hangs = $stmt->fetchAll();

$trang_thai_info = [
    'cho_xu_ly'   => ['label' => 'Chờ xử lý',       'class' => 'bg-warning text-dark'],
    'da_xac_nhan' => ['label' => 'Đã xác nhận',      'class' => 'bg-info text-white'],
    'da_giao'     => ['label' => 'Đã giao thành công','class' => 'bg-success text-white'],
    'da_huy'      => ['label' => 'Đã huỷ',           'class' => 'bg-danger text-white'],
];
$pt_label = [
    'tien_mat'     => 'Tiền mặt (COD)',
    'chuyen_khoan' => 'Chuyển khoản',
    'truc_tuyen'   => 'Trực tuyến',
];
?>

<div class="container py-4">
  <h4 class="section-title mb-4">Lịch sử đơn hàng</h4>

  <?php if (isset($don)): ?>
  <!-- ==================== CHI TIẾT 1 ĐƠN ==================== -->
  <div class="mb-3">
    <a href="/nhasach/orders.php" class="text-muted" style="font-size:.88rem;">
      <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
    </a>
  </div>

  <div style="background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); overflow:hidden;">

    <!-- Header đơn -->
    <div class="px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2"
         style="background:#f8f9fa; border-bottom:1px solid #eee;">
      <div>
        <span class="fw-bold" style="color:#1a1a2e;">Mã đơn: <?= htmlspecialchars($don['ma_don']) ?></span>
        <small class="text-muted ms-3">
          <?= date('d/m/Y H:i', strtotime($don['ngay_dat'])) ?>
        </small>
      </div>
      <span class="badge <?= $trang_thai_info[$don['trang_thai']]['class'] ?> px-3 py-2">
        <?= $trang_thai_info[$don['trang_thai']]['label'] ?>
      </span>
    </div>

    <!-- Sách trong đơn -->
    <div class="px-4 py-3">
      <?php foreach ($chi_tiets as $ct): ?>
        <div class="d-flex align-items-center gap-3 mb-3">
          <?php if (!empty($ct['hinh']) && file_exists("uploads/" . $ct['hinh'])): ?>
            <img src="/nhasach/uploads/<?= htmlspecialchars($ct['hinh']) ?>"
                 style="width:55px; height:70px; object-fit:cover; border-radius:8px; flex-shrink:0;">
          <?php else: ?>
            <div style="width:55px; height:70px; background:#f0f0f0; border-radius:8px;
                        display:flex; align-items:center; justify-content:center; color:#ccc; flex-shrink:0;">
              <i class="bi bi-book fs-4"></i>
            </div>
          <?php endif; ?>
          <div style="flex:1;">
            <a href="/nhasach/book.php?id=<?= $ct['sach_id'] ?>"
               class="fw-semibold d-block" style="color:#1a1a2e; font-size:.9rem;">
              <?= htmlspecialchars($ct['ten_sach']) ?>
            </a>
            <small class="text-muted"><?= htmlspecialchars($ct['ma_sach']) ?></small>
          </div>
          <div class="text-end" style="flex-shrink:0;">
            <div style="font-size:.88rem; color:#666;">
              <?= number_format($ct['gia_ban_luc_dat'], 0, ',', '.') ?>₫ × <?= $ct['so_luong'] ?> <?= htmlspecialchars($ct['don_vi_tinh']) ?>
            </div>
            <div class="fw-bold" style="color:#e63946;">
              <?= number_format($ct['gia_ban_luc_dat'] * $ct['so_luong'], 0, ',', '.') ?>₫
            </div>
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
        <div class="col-12 fw-semibold mb-1" style="color:#1a1a2e;">Thông tin giao hàng</div>
        <div class="col-5 text-muted">Người nhận</div>
        <div class="col-7"><?= htmlspecialchars($don['ten_nguoi_nhan']) ?></div>
        <div class="col-5 text-muted">Số điện thoại</div>
        <div class="col-7"><?= htmlspecialchars($don['so_dien_thoai']) ?></div>
        <div class="col-5 text-muted">Địa chỉ</div>
        <div class="col-7">
          <?= htmlspecialchars($don['dia_chi']) ?>,
          <?= htmlspecialchars($don['phuong_xa']) ?>,
          <?= htmlspecialchars($don['tinh_tp']) ?>
        </div>
        <div class="col-5 text-muted">Thanh toán</div>
        <div class="col-7"><?= $pt_label[$don['phuong_thuc_tt']] ?? '' ?></div>
        <?php if (!empty($don['ghi_chu'])): ?>
        <div class="col-5 text-muted">Ghi chú</div>
        <div class="col-7"><?= htmlspecialchars($don['ghi_chu']) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php else: ?>
  <!-- ==================== DANH SÁCH ĐƠN ==================== -->
  <?php if (empty($don_hangs)): ?>
    <div class="empty-state">
      <i class="bi bi-bag-x"></i>
      <h5>Bạn chưa có đơn hàng nào</h5>
      <p class="text-muted">Hãy chọn sách và đặt hàng ngay!</p>
      <a href="/nhasach/books.php" class="btn btn-accent px-4 mt-2">
        <i class="bi bi-shop me-2"></i>Mua sắm ngay
      </a>
    </div>

  <?php else: ?>
    <div style="background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); overflow:hidden;">

      <?php foreach ($don_hangs as $i => $dh): ?>
        <div class="px-4 py-3 d-flex align-items-center gap-3 flex-wrap"
             style="border-bottom:<?= $i < count($don_hangs)-1 ? '1px solid #f0f0f0' : 'none' ?>;">

          <!-- Thông tin chính -->
          <div style="flex:2; min-width:180px;">
            <div class="fw-bold" style="color:#1a1a2e; font-size:.92rem;">
              <?= htmlspecialchars($dh['ma_don']) ?>
            </div>
            <small class="text-muted">
              <?= date('d/m/Y H:i', strtotime($dh['ngay_dat'])) ?>
              &nbsp;·&nbsp; <?= $dh['so_sach'] ?> sản phẩm
            </small>
          </div>

          <!-- Tổng tiền -->
          <div style="flex:1; text-align:center;">
            <div class="fw-bold" style="color:#e63946;">
              <?= number_format($dh['tong_tien'], 0, ',', '.') ?>₫
            </div>
            <small class="text-muted"><?= $pt_label[$dh['phuong_thuc_tt']] ?? '' ?></small>
          </div>

          <!-- Trạng thái -->
          <div style="flex:1; text-align:center;">
            <span class="badge <?= $trang_thai_info[$dh['trang_thai']]['class'] ?> px-3 py-1">
              <?= $trang_thai_info[$dh['trang_thai']]['label'] ?>
            </span>
          </div>

          <!-- Nút xem -->
          <div style="flex-shrink:0;">
            <a href="/nhasach/orders.php?id=<?= $dh['id'] ?>"
               class="btn btn-sm btn-outline-secondary px-3" style="border-radius:20px;">
              Xem chi tiết
            </a>
          </div>
        </div>
      <?php endforeach; ?>

    </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>