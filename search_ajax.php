<?php
require_once 'config/database.php';
require_once 'includes/search_helper.php';

header('Content-Type: application/json; charset=UTF-8');

$query = trim((string) ($_GET['q'] ?? ''));

if (strlen(str_replace(' ', '', normalize_search_text($query))) < 2) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->query("
    SELECT s.id, s.ten, s.tac_gia, s.hinh,
           ROUND(s.gia_nhap * (1 + s.ty_le_ln / 100), 0) AS gia_ban
    FROM sach s
    WHERE s.hien_trang = 1
");
$rows = $stmt->fetchAll();

$rankedRows = rank_book_search_rows($rows, $query);
$result = array_map(static function (array $row): array {
    unset($row['_search_score'], $row['_title_norm']);
    return $row;
}, array_slice($rankedRows, 0, 6));

echo json_encode($result, JSON_UNESCAPED_UNICODE);
