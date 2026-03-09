<?php
$page_title = 'Giỏ hàng';
require_once 'includes/header.php';

// Bảo vệ: phải đăng nhập mới dùng giỏ hàng
if (!isset($_SESSION['khach_hang_id'])) {
    $_SESSION['redirect_after_login'] = '/nhasach/cart.php';
    $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Vui lòng đăng nhập để sử dụng giỏ hàng.'];
    header('Location: /nhasach/login.php');
    exit;
}

$kh_id = $_SESSION['khach_hang_id'];

// ============================================================
// HELPER: Sync giỏ hàng session → DB
// ============================================================
function syncGioHangDB($pdo, $kh_id, $gio_hang) {
    $pdo->prepare("DELETE FROM gio_hang WHERE khach_hang_id=?")->execute([$kh_id]);
    foreach ($gio_hang as $sach_id => $sl) {
        if ($sl > 0) {
            $pdo->prepare("INSERT INTO gio_hang (khach_hang_id, sach_id, so_luong) VALUES (?,?,?)")
                ->execute([$kh_id, $sach_id, $sl]);
        }
    }
}

// ============================================================
// Load giỏ hàng từ DB vào session
// ============================================================
$rows = $pdo->prepare("SELECT sach_id, so_luong FROM gio_hang WHERE khach_hang_id=?");
$rows->execute([$kh_id]);
$_SESSION['gio_hang'] = [];
foreach ($rows->fetchAll() as $r) {
    $_SESSION['gio_hang'][$r['sach_id']] = $r['so_luong'];
}

// ============================================================
// XỬ LÝ HÀNH ĐỘNG GIỎ HÀNG
// ============================================================
$action  = $_REQUEST['action']  ?? '';
$sach_id = (int)($_REQUEST['sach_id'] ?? $_REQUEST['id'] ?? 0);

if ($action === 'add' && $sach_id > 0) {
    $stmt = $pdo->prepare("SELECT id, so_luong FROM sach WHERE id = ? AND hien_trang = 1");
    $stmt->execute([$sach_id]);
    $sach = $stmt->fetch();
    if ($sach) {
        $sl_them = max(1, (int)($_POST['so_luong'] ?? 1));
        $sl_hien = $_SESSION['gio_hang'][$sach_id] ?? 0;
        $sl_moi  = min($sl_hien + $sl_them, $sach['so_luong']);
        $_SESSION['gio_hang'][$sach_id] = $sl_moi;
        syncGioHangDB($pdo, $kh_id, $_SESSION['gio_hang']);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã thêm sách vào giỏ hàng!'];
    }
    header('Location: /nhasach/cart.php');
    exit;
}

if ($action === 'update' && $sach_id > 0) {
    $so_luong = (int)($_POST['so_luong'] ?? 0);
    if ($so_luong <= 0) {
        unset($_SESSION['gio_hang'][$sach_id]);
    } else {
        $stmt = $pdo->prepare("SELECT so_luong FROM sach WHERE id = ?");
        $stmt->execute([$sach_id]);
        $sach = $stmt->fetch();
        $_SESSION['gio_hang'][$sach_id] = min($so_luong, $sach['so_luong']);
    }
    syncGioHangDB($pdo, $kh_id, $_SESSION['gio_hang']);
    header('Location: /nhasach/cart.php');
    exit;
}

if ($action === 'remove' && $sach_id > 0) {
    unset($_SESSION['gio_hang'][$sach_id]);
    syncGioHangDB($pdo, $kh_id, $_SESSION['gio_hang']);
    $_SESSION['flash'] = ['type' => 'info', 'msg' => 'Đã xoá sách khỏi giỏ hàng.'];
    header('Location: /nhasach/cart.php');
    exit;
}

if ($action === 'clear') {
    $_SESSION['gio_hang'] = [];
    syncGioHangDB($pdo, $kh_id, $_SESSION['gio_hang']);
    header('Location: /nhasach/cart.php');
    exit;
}

// ============================================================
// LẤY DỮ LIỆU GIỎ HÀNG
// ============================================================
$gio_hang  = $_SESSION['gio_hang'] ?? [];
$items     = [];
$tong_tien = 0;

if (!empty($gio_hang)) {
    $ids      = implode(',', array_map('intval', array_keys($gio_hang)));
    $stmt     = $pdo->query("
        SELECT id, ten, hinh, so_luong AS ton_kho, ma_sach,
               ROUND(gia_nhap * (1 + ty_le_ln/100), 0) AS gia_ban
        FROM sach
        WHERE id IN ($ids) AND hien_trang = 1
    ");
    $sachs_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sachs_db as $s) {
        $sl = min($gio_hang[$s['id']], $s['ton_kho']);
        if ($sl > 0) {
            $thanh_tien = $s['gia_ban'] * $sl;
            $tong_tien += $thanh_tien;
            $items[]    = array_merge($s, ['so_luong' => $sl, 'thanh_tien' => $thanh_tien]);
            $_SESSION['gio_hang'][$s['id']] = $sl;
        }
    }
}
?>

<div class="container py-4">
  <h4 class="section-title mb-4">Giỏ hàng của tôi</h4>

  <?php if (empty($items)): ?>
    <div class="empty-state py-5">
      <i class="bi bi-cart3" style="font-size:4rem; color:#dee2e6; display:block; margin-bottom:16px;"></i>
      <h5 class="text-muted">Giỏ hàng của bạn đang trống</h5>
      <p class="text-muted">Hãy chọn thêm sách để tiếp tục mua sắm nhé!</p>
      <a href="/nhasach/books.php" class="btn btn-accent px-4 mt-2">
        <i class="bi bi-arrow-left me-2"></i>Tiếp tục mua sắm
      </a>
    </div>

  <?php else: ?>
    <div class="row g-4">

      <!-- DANH SÁCH SẢN PHẨM -->
      <div class="col-lg-8">
        <div style="background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); overflow:hidden;">

          <div class="d-none d-md-flex px-4 py-3"
               style="background:#f8f9fa; border-bottom:1px solid #eee;
                      font-size:.78rem; text-transform:uppercase;
                      letter-spacing:.08em; color:#888; font-weight:600;">
            <div style="flex:2;">Sách</div>
            <div style="flex:1; text-align:center;">Đơn giá</div>
            <div style="flex:1; text-align:center;">Số lượng</div>
            <div style="flex:1; text-align:right;">Thành tiền</div>
            <div style="width:40px;"></div>
          </div>

          <?php foreach ($items as $item): ?>
            <div class="d-flex align-items-center gap-3 px-4 py-3"
                 style="border-bottom:1px solid #f0f0f0;">

              <a href="/nhasach/book.php?id=<?= $item['id'] ?>" style="flex-shrink:0;">
                <?php if (!empty($item['hinh']) && file_exists("uploads/" . $item['hinh'])): ?>
                  <img src="/nhasach/uploads/<?= htmlspecialchars($item['hinh']) ?>"
                       alt="<?= htmlspecialchars($item['ten']) ?>"
                       style="width:70px; height:90px; object-fit:cover; border-radius:8px;">
                <?php else: ?>
                  <div style="width:70px; height:90px; background:#f0f0f0; border-radius:8px;
                              display:flex; align-items:center; justify-content:center; color:#ccc;">
                    <i class="bi bi-book fs-3"></i>
                  </div>
                <?php endif; ?>
              </a>

              <div style="flex:2; min-width:0;">
                <a href="/nhasach/book.php?id=<?= $item['id'] ?>"
                   class="fw-semibold text-truncate d-block"
                   style="color:#1a1a2e; font-size:.92rem; max-width:240px;">
                  <?= htmlspecialchars($item['ten']) ?>
                </a>
                <small class="text-muted"><?= htmlspecialchars($item['ma_sach']) ?></small>
                <?php if ($item['ton_kho'] <= 5): ?>
                  <div><small class="text-warning"><i class="bi bi-exclamation-circle me-1"></i>Sắp hết hàng</small></div>
                <?php endif; ?>
                <div class="d-md-none mt-1" style="color:#e63946; font-weight:700; font-size:.95rem;">
                  <?= number_format($item['gia_ban'], 0, ',', '.') ?>₫
                </div>
              </div>

              <div class="d-none d-md-block" style="flex:1; text-align:center; color:#666; font-size:.9rem;">
                <?= number_format($item['gia_ban'], 0, ',', '.') ?>₫
              </div>

              <div style="flex:1; text-align:center;">
                <form method="POST" action="/nhasach/cart.php"
                      class="d-flex align-items-center justify-content-center gap-1">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="sach_id" value="<?= $item['id'] ?>">
                  <button type="submit" name="so_luong"
                          value="<?= $item['so_luong'] - 1 ?>"
                          class="cart-qty-btn">−</button>
                  <span class="fw-bold" style="min-width:28px; text-align:center;">
                    <?= $item['so_luong'] ?>
                  </span>
                  <button type="submit" name="so_luong"
                          value="<?= $item['so_luong'] + 1 ?>"
                          class="cart-qty-btn"
                          <?= $item['so_luong'] >= $item['ton_kho'] ? 'disabled' : '' ?>>+</button>
                </form>
              </div>

              <div style="flex:1; text-align:right; font-weight:700; color:#e63946; font-size:.95rem;">
                <?= number_format($item['thanh_tien'], 0, ',', '.') ?>₫
              </div>

              <div style="width:40px; text-align:right;">
                <a href="/nhasach/cart.php?action=remove&sach_id=<?= $item['id'] ?>"
                   class="text-muted"
                   title="Xoá"
                   onclick="return confirm('Xoá sách này khỏi giỏ hàng?')"
                   style="font-size:1.1rem;">
                  <i class="bi bi-trash3"></i>
                </a>
              </div>

            </div>
          <?php endforeach; ?>

          <div class="px-4 py-3 d-flex justify-content-between align-items-center"
               style="background:#fafafa;">
            <a href="/nhasach/books.php" class="text-muted" style="font-size:.88rem;">
              <i class="bi bi-arrow-left me-1"></i>Tiếp tục mua sắm
            </a>
            <a href="/nhasach/cart.php?action=clear"
               class="text-danger" style="font-size:.85rem;"
               onclick="return confirm('Xoá toàn bộ giỏ hàng?')">
              <i class="bi bi-trash3 me-1"></i>Xoá tất cả
            </a>
          </div>

        </div>
      </div>

      <!-- ORDER SUMMARY -->
      <div class="col-lg-4">
        <div class="order-summary">
          <h6 class="fw-bold mb-4" style="color:#1a1a2e; font-size:1rem;">
            <i class="bi bi-receipt me-2" style="color:#f4a261;"></i>Tóm tắt đơn hàng
          </h6>

          <div class="d-flex justify-content-between mb-2" style="font-size:.9rem;">
            <span class="text-muted">Tạm tính (<?= count($items) ?> sách)</span>
            <span><?= number_format($tong_tien, 0, ',', '.') ?>₫</span>
          </div>
          <div class="d-flex justify-content-between mb-3" style="font-size:.9rem;">
            <span class="text-muted">Phí vận chuyển</span>
            <span class="text-success">Miễn phí</span>
          </div>

          <hr>

          <div class="d-flex justify-content-between mb-4">
            <span class="fw-bold">Tổng cộng</span>
            <span class="fw-bold" style="font-size:1.2rem; color:#e63946;">
              <?= number_format($tong_tien, 0, ',', '.') ?>₫
            </span>
          </div>

          <a href="/nhasach/checkout.php" class="btn w-100 py-2 fw-semibold"
             style="background:#f4a261; color:#fff; border:none; border-radius:10px; font-size:.95rem;">
            Tiến hành đặt hàng <i class="bi bi-arrow-right ms-1"></i>
          </a>

          <div class="mt-3 text-center" style="font-size:.78rem; color:#aaa;">
            <i class="bi bi-shield-check me-1"></i>Thanh toán an toàn &amp; bảo mật
          </div>
        </div>
      </div>

    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>