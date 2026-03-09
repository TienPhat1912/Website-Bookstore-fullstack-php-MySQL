<?php
session_start();
require_once 'config/database.php';

$sach_id = (int)($_POST['id'] ?? 0);
if ($sach_id <= 0) { echo json_encode(['ok' => false]); exit; }

$stmt = $pdo->prepare("SELECT id, ten, so_luong FROM sach WHERE id = ? AND hien_trang = 1");
$stmt->execute([$sach_id]);
$sach = $stmt->fetch();

if (!$sach || $sach['so_luong'] <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Sách không còn hàng!']);
    exit;
}

$them_sl = max(1, (int)($_POST['so_luong'] ?? 1));  // ← phải nằm ở đây, trước if

if (isset($_SESSION['khach_hang_id'])) {
    $kh_id = $_SESSION['khach_hang_id'];
    $exist = $pdo->prepare("SELECT id, so_luong FROM gio_hang WHERE khach_hang_id=? AND sach_id=?");
    $exist->execute([$kh_id, $sach_id]);
    $row = $exist->fetch();
    if ($row) {
        $new_sl = min($row['so_luong'] + $them_sl, $sach['so_luong']);
        $pdo->prepare("UPDATE gio_hang SET so_luong=? WHERE id=?")->execute([$new_sl, $row['id']]);
    } else {
        $pdo->prepare("INSERT INTO gio_hang (khach_hang_id, sach_id, so_luong) VALUES (?,?,?)")->execute([$kh_id, $sach_id, $them_sl]);
    }
} else {
    $sl_hien = $_SESSION['gio_hang'][$sach_id] ?? 0;
    $_SESSION['gio_hang'][$sach_id] = min($sl_hien + $them_sl, $sach['so_luong']);
}

if (isset($_SESSION['khach_hang_id'])) {
    $tong = $pdo->prepare("SELECT COALESCE(SUM(so_luong),0) FROM gio_hang WHERE khach_hang_id=?");
    $tong->execute([$_SESSION['khach_hang_id']]);
    $tong = (int)$tong->fetchColumn();
} else {
    $tong = array_sum($_SESSION['gio_hang'] ?? []);
}

echo json_encode(['ok' => true, 'ten' => $sach['ten'], 'tong' => $tong]);