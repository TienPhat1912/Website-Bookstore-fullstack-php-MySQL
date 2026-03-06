<?php
session_start();

// Xoá session khách hàng (giữ lại giỏ hàng nếu muốn, hoặc xoá luôn)
unset($_SESSION['khach_hang_id']);
unset($_SESSION['khach_hang_ten']);
unset($_SESSION['gio_hang']);

$_SESSION['flash'] = ['type' => 'info', 'msg' => 'Bạn đã đăng xuất thành công.'];

header('Location: /nhasach/index.php');
exit;
