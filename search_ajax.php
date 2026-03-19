<?php
require_once 'config/database.php';
function bo_dau(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $from = ['à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ',
             'è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ',
             'ì','í','ị','ỉ','ĩ',
             'ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ',
             'ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ',
             'ỳ','ý','ỵ','ỷ','ỹ','đ'];
    $to   = ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
             'e','e','e','e','e','e','e','e','e','e','e',
             'i','i','i','i','i',
             'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
             'u','u','u','u','u','u','u','u','u','u','u',
             'y','y','y','y','y','d'];
    return str_replace($from, $to, $s);
}

$q     = trim($_GET['q'] ?? '');
$q_raw = bo_dau($q);  // bản không dấu của keyword

if (strlen($q) < 2) { echo json_encode([]); exit; }

// Lấy nhiều hơn để lọc phía PHP
$stmt = $pdo->prepare("
    SELECT s.id, s.ten, s.tac_gia, s.hinh,
           ROUND(s.gia_nhap*(1+s.ty_le_ln/100),0) AS gia_ban
    FROM sach s
    WHERE s.hien_trang = 1
      AND (s.ten LIKE ? COLLATE utf8mb4_general_ci
        OR s.tac_gia LIKE ? COLLATE utf8mb4_general_ci)
    LIMIT 50
");
$stmt->execute(["%$q%", "%$q%"]);
$rows = $stmt->fetchAll();

// Lọc thêm phía PHP theo bản không dấu
$result = array_filter($rows, fn($r) =>
    str_contains(bo_dau($r['ten']),      $q_raw) ||
    str_contains(bo_dau($r['tac_gia'] ?? ''), $q_raw)
);

echo json_encode(array_values(array_slice($result, 0, 6)));