<?php
/**
 * seed_lightnovel.php — Seed 100 đầu Light Novel có ảnh bìa dọc đẹp
 * Đặt ở: C:\xampp\htdocs\nhasach\seed_lightnovel.php
 * Truy cập: localhost/nhasach/seed_lightnovel.php
 * XOÁ FILE SAU KHI CHẠY XONG!
 *
 * ⚠️  Thể loại Light Novel phải có id = 10 trong bảng the_loai
 *    Nếu chưa có, script sẽ tự tạo.
 *
 * Nếu muốn reset light novel cũ trước khi seed:
 *   SET FOREIGN_KEY_CHECKS = 0;
 *   DELETE FROM chi_tiet_nhap WHERE sach_id IN (SELECT id FROM sach WHERE the_loai_id = 10);
 *   DELETE FROM gio_hang WHERE sach_id IN (SELECT id FROM sach WHERE the_loai_id = 10);
 *   DELETE FROM sach WHERE the_loai_id = 10;
 *   SET FOREIGN_KEY_CHECKS = 1;
 */

set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('display_errors', 1);
ob_implicit_flush(true);
if (ob_get_level()) ob_end_flush();

$conn = new mysqli("localhost", "root", "", "nhasach");
$conn->set_charset("utf8mb4");

$uploadDir = __DIR__ . "/uploads/";
if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);

// ============================================================
// CẤU HÌNH
// ============================================================
$ln_cat_id = 10;

// Kiểm tra / tạo thể loại
$check = $conn->query("SELECT id, ten FROM the_loai WHERE id = $ln_cat_id");
if ($check->num_rows === 0) {
    $conn->query("INSERT INTO the_loai (id, ten, mo_ta, trang_thai) VALUES ($ln_cat_id, 'Light Novel', 'Tiểu thuyết nhẹ Nhật Bản', 1)");
    echo "<pre>✅ Đã tự tạo thể loại 'Light Novel' với id = $ln_cat_id\n\n</pre>";
} else {
    $row = $check->fetch_assoc();
    echo "<pre>✅ Thể loại: {$row['ten']} (id=$ln_cat_id)\n\n</pre>";
}

// Danh sách query tìm light novel trên Google Books
$queries = [
    // Isekai / Fantasy
    'sword art online light novel', 'overlord light novel',
    'mushoku tensei light novel', 'konosuba light novel',
    're zero light novel', 'that time i got reincarnated as a slime light novel',
    'the rising of the shield hero light novel', 'no game no life light novel',
    'arifureta light novel', 'so i\'m a spider so what light novel',
    'the beginning after the end light novel', 'goblin slayer light novel',
    'danmachi light novel', 'log horizon light novel',
    'the saga of tanya the evil light novel',

    // Romance / Slice of Life
    'oregairu light novel', 'toradora light novel',
    'bottom tier character tomozaki light novel',
    'rascal does not dream light novel', 'classroom of the elite light novel',
    'my youth romantic comedy light novel', 'spice and wolf light novel',
    'the angel next door spoils me light novel',
    'you like me not my daughter light novel',
    'the dangers in my heart light novel',
    'higehiro light novel', 'horimiya light novel',

    // Action / Sci-fi
    'a certain magical index light novel', 'durarara light novel',
    'baccano light novel', 'accel world light novel',
    '86 eighty six light novel', 'the irregular at magic high school light novel',
    'code geass light novel', 'psycho pass light novel',
    'steins gate light novel', 'full metal panic light novel',

    // Nổi tiếng / Best seller
    'demon slayer light novel', 'attack on titan light novel',
    'jujutsu kaisen light novel', 'spy x family novel',
    'chainsaw man novel', 'death note novel',
    'monogatari light novel', 'fate zero light novel',
    'violet evergarden light novel', 'weathering with you novel makoto shinkai',
    'your name novel makoto shinkai', 'the apothecary diaries light novel',

    // Thêm bổ sung
    'ascendance of a bookworm light novel',
    'wandering witch light novel', 'banished from hero party light novel',
    'trapped in a dating sim light novel', 'reign of the seven spellblades light novel',
    'hell mode light novel', 'failure frame light novel',
    'the eminence in shadow light novel', 'my happy marriage light novel',
    'solo leveling novel', 'omniscient reader viewpoint novel',
    'is it wrong to pick up girls dungeon light novel',
    'rezero starting life another world light novel',
    'combatants will be dispatched light novel',
    'cautious hero light novel',
];

$target  = 100;
$gia_min = 50000;
$gia_max = 120000;

// ============================================================
// HÀM FETCH GOOGLE BOOKS
// ============================================================
function fetchGoogleBooks(string $query, int $startIndex = 0): array {
    $url = 'https://www.googleapis.com/books/v1/volumes?' . http_build_query([
        'q'          => $query,
        'maxResults' => 40,
        'startIndex' => $startIndex,
        'printType'  => 'books',
        'orderBy'    => 'relevance',
    ]);
    $ctx = stream_context_create(['http' => ['timeout' => 15]]);
    $res = @file_get_contents($url, false, $ctx);
    if (!$res) return [];
    $data = json_decode($res, true);
    return $data['items'] ?? [];
}

// ============================================================
// HÀM KIỂM TRA ẢNH DỌC ĐẸP
// ============================================================
function checkAndGetImage(string $url): string {
    $url = str_replace('http://', 'https://', $url);
    $url = str_replace('zoom=1', 'zoom=3', $url);
    $ctx  = stream_context_create(['http' => ['timeout' => 3]]);
    $data = @file_get_contents($url, false, $ctx);
    if (!$data || strlen($data) < 3000) return '';

    $img = @imagecreatefromstring($data);
    if (!$img) return '';

    $w = imagesx($img);
    $h = imagesy($img);
    imagedestroy($img);

    if ($w <= 0 || $h <= 0) return '';

    $ratio = $h / $w;
    if ($ratio < 1.2 || $ratio > 2.0) return '';

    return $data;
}

// ============================================================
// LẤY MÃ SÁCH TIẾP THEO
// ============================================================
$last = $conn->query("SELECT ma_sach FROM sach ORDER BY id DESC LIMIT 1")->fetch_assoc();
$counter = 1;
if ($last && preg_match('/S(\d+)/i', $last['ma_sach'], $m)) {
    $counter = (int)$m[1] + 1;
}

// ============================================================
// CHẠY
// ============================================================
echo "<pre style='font-size:13px; line-height:1.8; font-family:monospace;'>";
echo "╔══════════════════════════════════════════════╗\n";
echo "║  SEED 100 LIGHT NOVEL - CÓ ẢNH BÌA ĐẸP      ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";
flush();

// Tên đã có trong DB (tránh trùng)
$exist_res = $conn->query("SELECT LOWER(ten) AS ten FROM sach WHERE the_loai_id = $ln_cat_id");
$ten_da_co = [];
while ($r = $exist_res->fetch_assoc()) {
    $ten_da_co[mb_strtolower(trim($r['ten']))] = true;
}

$buffer = [];

foreach ($queries as $query) {
    if (count($buffer) >= $target) break;

    echo "🔎 Tìm: $query\n";
    flush();

    for ($start = 0; $start <= 80 && count($buffer) < $target; $start += 40) {
        $items = fetchGoogleBooks($query, $start);
        if (empty($items)) break;

        foreach ($items as $item) {
            if (count($buffer) >= $target) break;

            $info = $item['volumeInfo'] ?? [];
            $ten  = trim($info['title'] ?? '');
            if (empty($ten) || mb_strlen($ten) > 200) continue;

            $ten_lower = mb_strtolower($ten);
            if (isset($ten_da_co[$ten_lower])) continue;

            // === LỌC CHỈ LẤY LIGHT NOVEL THẬT ===
            $categories  = $info['categories'] ?? [];
            $description = mb_strtolower($info['description'] ?? '');
            $allCats     = mb_strtolower(implode(' ', $categories));
            $titleLower  = mb_strtolower($ten);
            $publisher   = mb_strtolower($info['publisher'] ?? '');

            $isLN = false;

            // 1) Google Books phân loại là Fiction / Young Adult / Comics (LN thường vào đây)
            $catKeywords = ['fiction', 'young adult', 'light novel', 'comic', 'manga',
                            'fantasy', 'science fiction', 'juvenile'];
            foreach ($catKeywords as $kw) {
                if (strpos($allCats, $kw) !== false) { $isLN = true; break; }
            }

            // 2) Tiêu đề chứa dấu hiệu light novel
            if (!$isLN) {
                $titleHints = ['light novel', 'novel', 'vol.', 'vol ', 'volume',
                               'tập ', 'tome', '(light novel)'];
                foreach ($titleHints as $kw) {
                    if (strpos($titleLower, $kw) !== false) { $isLN = true; break; }
                }
            }

            // 3) NXB light novel nổi tiếng
            if (!$isLN) {
                $lnPublishers = ['yen press', 'yen on', 'seven seas', 'j-novel club',
                    'j-novel', 'one peace', 'vertical', 'viz', 'kodansha',
                    'kadokawa', 'ascii media works', 'dengeki', 'fujimi shobo',
                    'media factory', 'overlap', 'square enix',
                    'kim đồng', 'kim dong', 'ipm', 'hikari', 'tsuki'];
                foreach ($lnPublishers as $kw) {
                    if (strpos($publisher, $kw) !== false) { $isLN = true; break; }
                }
            }

            // 4) Mô tả chứa từ khoá light novel rõ ràng
            if (!$isLN) {
                $descHints = ['light novel', 'anime', 'isekai', 'manga adaptation',
                    'japanese novel', 'web novel', 'ranobe', 'light-novel',
                    'otaku', 'summoned', 'reincarnated', 'another world',
                    'dungeon', 'adventurer', 'hero party', 'demon lord',
                    'skill', 'level up', 'manga series'];
                foreach ($descHints as $kw) {
                    if (strpos($description, $kw) !== false) { $isLN = true; break; }
                }
            }

            if (!$isLN) continue;

            // Loại bỏ manga thuần (nếu categories CHỈ có comic/manga mà không có fiction/novel)
            $hasFiction = false;
            $onlyComic  = true;
            foreach ($categories as $cat) {
                $catLow = mb_strtolower($cat);
                if (strpos($catLow, 'fiction') !== false || strpos($catLow, 'novel') !== false
                    || strpos($catLow, 'young adult') !== false || strpos($catLow, 'juvenile') !== false) {
                    $hasFiction = true;
                }
                if (strpos($catLow, 'comic') === false && strpos($catLow, 'manga') === false) {
                    $onlyComic = false;
                }
            }
            // Nếu Google gán CHỈ là comic/manga, và tiêu đề không có "novel" → bỏ
            if (!empty($categories) && $onlyComic && !$hasFiction
                && strpos($titleLower, 'novel') === false) {
                continue;
            }
            // === HẾT LỌC ===

            // Phải có ảnh bìa
            $img_url = $info['imageLinks']['thumbnail']
                    ?? $info['imageLinks']['smallThumbnail']
                    ?? '';
            if (empty($img_url)) continue;

            // Kiểm tra ảnh dọc đẹp
            $img_data = checkAndGetImage($img_url);
            if (empty($img_data)) continue;

            $ten_da_co[$ten_lower] = true;

            $buffer[] = [
                'ten'      => $ten,
                'tac_gia'  => mb_substr(implode(', ', array_slice($info['authors'] ?? ['Nhiều tác giả'], 0, 2)), 0, 100),
                'nha_xb'   => mb_substr($info['publisher'] ?? 'NXB Trẻ', 0, 100),
                'mo_ta'    => mb_substr(strip_tags($info['description'] ?? ''), 0, 1000),
                'img_data' => $img_data,
            ];

            echo "  📖 [" . count($buffer) . "/$target] $ten\n";
            flush();
        }

        usleep(300000);
    }
}

$collected = count($buffer);
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📦 Thu thập được $collected light novel có ảnh dọc đẹp\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

if ($collected === 0) {
    echo "❌ Không tìm được light novel nào có ảnh đạt yêu cầu. Thử lại sau.\n</pre>";
    $conn->close();
    exit;
}

echo "📥 Bắt đầu insert vào DB...\n\n";
flush();

// INSERT
$dem = 0;
foreach ($buffer as $book) {
    $ma_sach  = 'S' . str_pad($counter, 4, '0', STR_PAD_LEFT);
    $filename = 'sach_' . $ma_sach . '.jpg';
    file_put_contents($uploadDir . $filename, $book['img_data']);

    $gia_nhap = round(rand($gia_min, $gia_max) / 1000) * 1000;
    $ty_le    = rand(25, 40);

    $ten_sql     = $conn->real_escape_string($book['ten']);
    $tac_gia_sql = $conn->real_escape_string($book['tac_gia']);
    $nha_xb_sql  = $conn->real_escape_string($book['nha_xb']);
    $mo_ta_sql   = $conn->real_escape_string($book['mo_ta']);

    $sql = "INSERT INTO sach
        (ma_sach, ten, tac_gia, the_loai_id, nha_xb, mo_ta,
         don_vi_tinh, hinh, so_luong, gia_nhap, ty_le_ln,
         hien_trang, da_nhap_hang)
        VALUES
        ('$ma_sach', '$ten_sql', '$tac_gia_sql', $ln_cat_id,
         '$nha_xb_sql', '$mo_ta_sql', 'cuốn', '$filename',
         0, $gia_nhap, $ty_le, 1, 0)";

    if ($conn->query($sql)) {
        $dem++;
        $counter++;
        echo "  ✅ [$ma_sach] {$book['ten']}\n";
        flush();
    } else {
        echo "  ❌ Lỗi: " . $conn->error . "\n";
        flush();
    }
}

// Tạo phiếu nhập và cập nhật tồn kho
echo "\n📋 Tạo phiếu nhập & cập nhật tồn kho...\n";

$ma_phieu = 'PN-LN-' . date('Ymd-His');
$conn->query("INSERT INTO phieu_nhap (ma_phieu, ngay_nhap, ghi_chu, trang_thai, nguoi_tao)
              VALUES ('$ma_phieu', CURDATE(), 'Nhập hàng Light Novel ban đầu', 'done', 1)");
$phieu_id = $conn->insert_id;

$conn->query("INSERT INTO chi_tiet_nhap (phieu_nhap_id, sach_id, so_luong, don_gia)
              SELECT $phieu_id, id, 100, gia_nhap
              FROM sach
              WHERE the_loai_id = $ln_cat_id AND da_nhap_hang = 0 AND hien_trang = 1");

$conn->query("UPDATE sach SET so_luong = 100, da_nhap_hang = 1
              WHERE the_loai_id = $ln_cat_id AND da_nhap_hang = 0 AND hien_trang = 1");

echo "\n╔══════════════════════════════════════════════╗\n";
echo "║  🎉 HOÀN THÀNH!                                ║\n";
echo "║  Light Novel đã thêm: $dem / $target               \n";
echo "║  Phiếu nhập: $ma_phieu                         \n";
echo "║  Tồn kho: 100 cuốn / đầu sách                    \n";
echo "║  Giá nhập: " . number_format($gia_min) . "₫ - " . number_format($gia_max) . "₫     \n";
echo "╚══════════════════════════════════════════════╝\n";
echo "\n⚠️  NHỚ XOÁ FILE seed_lightnovel.php SAU KHI CHẠY!\n";
echo "</pre>";

$conn->close();
?>