<?php
/**
 * seed_books.php — Seed 100 sách có ảnh dọc đẹp mỗi thể loại
 * Đặt ở: C:\xampp\htdocs\nhasach\seed_books.php
 * Truy cập: localhost/nhasach/seed_books.php
 * XOÁ FILE SAU KHI CHẠY XONG!
 *
 * ⚠️  RESET DỮ LIỆU trước khi chạy (chạy trong phpMyAdmin):
 * -------------------------------------------------------
 * SET FOREIGN_KEY_CHECKS = 0;
 * TRUNCATE TABLE chi_tiet_don_hang;
 * TRUNCATE TABLE don_hang;
 * TRUNCATE TABLE chi_tiet_nhap;
 * TRUNCATE TABLE phieu_nhap;
 * TRUNCATE TABLE gio_hang;
 * TRUNCATE TABLE sach;
 * SET FOREIGN_KEY_CHECKS = 1;
 * ALTER TABLE sach AUTO_INCREMENT = 1;
 * -------------------------------------------------------
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
// CẤU HÌNH — sửa id cho khớp DB
// ============================================================
$subjects = [
    1 => [
        'ten'     => 'Văn học trong nước',
        'queries' => [
            'nguyễn nhật ánh', 'nam cao truyện', 'tô hoài văn học',
            'vũ trọng phụng', 'ngô tất tố', 'nguyễn tuân',
            'hồ biểu chánh', 'văn học việt nam hiện đại',
            'tiểu thuyết việt nam', 'truyện ngắn việt nam',
            'nguyễn huy thiệp', 'ma văn kháng', 'lê lựu',
        ],
    ],
    2 => [
        'ten'     => 'Văn học nước ngoài',
        'queries' => [
            'tiểu thuyết dịch tiếng việt', 'victor hugo tiếng việt',
            'dostoevsky tiếng việt', 'hemingway tiếng việt',
            'kafka tiếng việt', 'tolstoy tiếng việt',
            'gabriel garcia marquez tiếng việt', 'shakespeare tiếng việt',
            'camus tiếng việt', 'haruki murakami tiếng việt',
            'paulo coelho tiếng việt', 'stefan zweig tiếng việt',
        ],
    ],
    3 => [
        'ten'     => 'Kinh tế - Kinh doanh',
        'queries' => [
            'khởi nghiệp kinh doanh việt nam', 'quản trị doanh nghiệp',
            'marketing tiếng việt', 'đầu tư tài chính tiếng việt',
            'cha giàu cha nghèo tiếng việt', 'kinh tế học tiếng việt',
            'lãnh đạo quản lý tiếng việt', 'chứng khoán đầu tư',
            'bán hàng kỹ năng kinh doanh', 'startup khởi nghiệp',
            'zero to one tiếng việt', 'the lean startup tiếng việt',
        ],
    ],
    4 => [
        'ten'     => 'Kỹ năng sống',
        'queries' => [
            'đắc nhân tâm dale carnegie', 'kỹ năng sống tiếng việt',
            'atomic habits tiếng việt', 'tư duy tích cực tiếng việt',
            'phát triển bản thân tiếng việt', 'giao tiếp ứng xử',
            'quản lý thời gian tiếng việt', 'nghĩ giàu làm giàu tiếng việt',
            'kỹ năng mềm tiếng việt', 'thói quen thành công',
            'deep work tiếng việt', 'mindset tiếng việt',
        ],
    ],
    5 => [
        'ten'     => 'Khoa học - Công nghệ',
        'queries' => [
            'lập trình python tiếng việt', 'trí tuệ nhân tạo tiếng việt',
            'khoa học vật lý tiếng việt', 'toán học tiếng việt',
            'sinh học tiếng việt', 'công nghệ thông tin tiếng việt',
            'hóa học tiếng việt', 'thiên văn học tiếng việt',
            'lập trình web tiếng việt', 'khoa học máy tính',
            'vật lý lượng tử tiếng việt', 'clean code tiếng việt',
        ],
    ],
    6 => [
        'ten'     => 'Sách thiếu nhi',
        'queries' => [
            'doraemon tiếng việt', 'truyện cổ tích việt nam',
            'conan thám tử tiếng việt', 'truyện tranh thiếu nhi',
            'hoàng tử bé tiếng việt', 'shin cậu bé bút chì tiếng việt',
            'truyện thiếu nhi phiêu lưu', 'truyện tranh nhật bản',
            'sách giáo dục kỹ năng trẻ em', 'dragon ball tiếng việt',
            'one piece tiếng việt', 'naruto tiếng việt',
        ],
    ],
    7 => [
        'ten'     => 'Lịch sử - Địa lý',
        'queries' => [
            'lịch sử việt nam', 'lịch sử thế giới tiếng việt',
            'địa lý việt nam', 'chiến tranh việt nam lịch sử',
            'sapiens lịch sử loài người tiếng việt', 'văn minh cổ đại tiếng việt',
            'lịch sử triều đại việt', 'khảo cổ học việt nam',
            'guns germs steel tiếng việt', 'địa lý nhân văn tiếng việt',
            'lịch sử kinh tế thế giới', 'văn hóa lịch sử á đông',
        ],
    ],
    8 => [
        'ten'     => 'Tâm lý học',
        'queries' => [
            'tâm lý học tiếng việt', 'tư duy nhanh và chậm tiếng việt',
            'cảm xúc trí tuệ tiếng việt', 'tâm lý học hành vi tiếng việt',
            'freud tâm lý học tiếng việt', 'thiền định sức khỏe tâm thần',
            'tâm lý học trẻ em tiếng việt', 'nlp lập trình ngôn ngữ tư duy',
            'tâm lý học xã hội tiếng việt', 'predictably irrational tiếng việt',
            'tâm lý học đám đông tiếng việt', 'emotional intelligence tiếng việt',
        ],
    ],
];

$target  = 100;
$gia_min = 40000;
$gia_max = 150000;

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

    // Phải là hình chữ nhật dọc, tỉ lệ 1.2 - 2.0
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
echo "║   SEED SÁCH TIẾNG VIỆT - CÓ ẢNH BÌA ĐẸP     ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";
flush();

$tong = 0;

foreach ($subjects as $cat_id => $cat) {

    // Kiểm tra thể loại tồn tại
    $check = $conn->query("SELECT id FROM the_loai WHERE id = $cat_id");
    if ($check->num_rows === 0) {
        echo "⚠️  Thể loại id=$cat_id không tồn tại trong DB, bỏ qua!\n\n";
        flush();
        continue;
    }

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📚 {$cat['ten']} (id=$cat_id)\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    flush();

    // Tên đã có trong DB
    $exist_res = $conn->query("SELECT ten FROM sach WHERE the_loai_id = $cat_id");
    $ten_da_co = [];
    while ($r = $exist_res->fetch_assoc()) {
        $ten_da_co[mb_strtolower(trim($r['ten']))] = true;
    }

    $buffer = []; // thu thập đủ 100 trước khi insert

    foreach ($cat['queries'] as $query) {
        if (count($buffer) >= $target) break;

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
                    'nha_xb'   => mb_substr($info['publisher'] ?? 'NXB Tổng hợp', 0, 100),
                    'mo_ta'    => mb_substr(strip_tags($info['description'] ?? ''), 0, 1000),
                    'img_data' => $img_data,
                ];

                echo "  🔍 [" . count($buffer) . "/$target] $ten\n";
                flush();
            }

            usleep(200000);
        }
    }

    $collected = count($buffer);
    echo "\n  📦 Thu thập được $collected sách có ảnh dọc đẹp — bắt đầu insert...\n\n";
    flush();

    // INSERT
    $dem = 0;
    foreach ($buffer as $book) {
        $ma_sach  = 'S' . str_pad($counter, 4, '0', STR_PAD_LEFT);
        $filename = 'sach_' . $ma_sach . '.jpg';
        file_put_contents($uploadDir . $filename, $book['img_data']);

        $gia_nhap = round(rand($gia_min, $gia_max) / 1000) * 1000;
        $ty_le    = rand(20, 35);

        $ten_sql     = $conn->real_escape_string($book['ten']);
        $tac_gia_sql = $conn->real_escape_string($book['tac_gia']);
        $nha_xb_sql  = $conn->real_escape_string($book['nha_xb']);
        $mo_ta_sql   = $conn->real_escape_string($book['mo_ta']);

        $sql = "INSERT INTO sach
            (ma_sach, ten, tac_gia, the_loai_id, nha_xb, mo_ta,
             don_vi_tinh, hinh, so_luong, gia_nhap, ty_le_ln,
             hien_trang, da_nhap_hang)
            VALUES
            ('$ma_sach', '$ten_sql', '$tac_gia_sql', $cat_id,
             '$nha_xb_sql', '$mo_ta_sql', 'cuốn', '$filename',
             0, $gia_nhap, $ty_le, 1, 0)";

        if ($conn->query($sql)) {
            $dem++;
            $counter++;
            $tong++;
            echo "  ✅ [$ma_sach] {$book['ten']}\n";
            flush();
        } else {
            echo "  ❌ Lỗi: " . $conn->error . "\n";
            flush();
        }
    }

    echo "\n  ✅ Đã insert $dem sách cho [{$cat['ten']}]\n\n";
    flush();
}

// Tạo phiếu nhập và cập nhật tồn kho
$conn->query("INSERT INTO phieu_nhap (ma_phieu, ngay_nhap, ghi_chu, trang_thai, nguoi_tao) VALUES ('PN0001', CURDATE(), 'Nhập hàng ban đầu', 'done', 1)");
$phieu_id = $conn->insert_id;
$conn->query("INSERT INTO chi_tiet_nhap (phieu_nhap_id, sach_id, so_luong, don_gia) SELECT $phieu_id, id, 100, gia_nhap FROM sach WHERE hien_trang = 1");
$conn->query("UPDATE sach SET so_luong = 100, da_nhap_hang = 1 WHERE hien_trang = 1");

echo "╔══════════════════════════════════════════════╗\n";
echo "║  HOÀN THÀNH! Tổng đã thêm: $tong sách\n";
echo "║  Đã tạo phiếu nhập & cập nhật tồn kho 100/cuốn\n";
echo "╚══════════════════════════════════════════════╝\n";
echo "\n⚠️  NHỚ XOÁ FILE seed_books.php SAU KHI CHẠY!\n";
echo "</pre>";

$conn->close();
?>