<?php
require_once 'config/database.php';
$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }

$stmt = $pdo->prepare("
    SELECT s.id, s.ten, s.tac_gia, s.hinh,
           ROUND(s.gia_nhap*(1+s.ty_le_ln/100),0) AS gia_ban
    FROM sach s
    WHERE s.hien_trang = 1
      AND (s.ten LIKE ? COLLATE utf8mb4_general_ci
        OR s.tac_gia LIKE ? COLLATE utf8mb4_general_ci)
    LIMIT 6
");
$stmt->execute(["%$q%", "%$q%"]);
echo json_encode($stmt->fetchAll());