<?php
/** Module 2 – Phân bổ thí sinh vào phòng thi, tương thích CSDL MySQL của nhóm. */
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_role(['SO']);

function tan_rows(PDO $db, string $sql, array $params = []): array
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function tan_error(string $message): never
{
    throw new RuntimeException($message);
}

/** Sắp xếp họ tên để việc đánh SBD ổn định và có thể kiểm tra lại. */
function tan_sort_students(array &$students): void
{
    $collator = class_exists('Collator') ? new Collator('vi_VN') : null;
    usort($students, static function (array $a, array $b) use ($collator): int {
        $x = (string)$a['ho_ten'];
        $y = (string)$b['ho_ten'];
        $cmp = $collator ? $collator->compare($x, $y) : strcasecmp($x, $y);
        return $cmp ?: strcmp((string)$a['ma_thi_sinh'], (string)$b['ma_thi_sinh']);
    });
}

/** Lập kế hoạch từ tất cả phòng còn chỗ trong điểm thi đã chọn, không ghi CSDL. */
function tan_plan(PDO $db, string $exam, string $venue): array
{
    $rooms = tan_rows($db, '
        SELECT r.ma_phong, r.ten_phong, r.suc_chua,
               COUNT(p.id) AS da_xep
        FROM phong_thi r
        LEFT JOIN phan_bo_thi_sinh p ON p.ma_phong = r.ma_phong
          AND p.ma_ky_thi = r.ma_ky_thi
        WHERE r.ma_ky_thi = ? AND r.ma_dia_diem = ?
        GROUP BY r.ma_phong, r.ten_phong, r.suc_chua
        ORDER BY r.ten_phong, r.ma_phong
    ', [$exam, $venue]);

    if (!$rooms) tan_error('Địa điểm này chưa có phòng cho kỳ thi đã chọn.');

    $students = tan_rows($db, '
        SELECT t.ma_thi_sinh, t.ho_ten
        FROM thi_sinh t
        WHERE t.ma_ky_thi = ? AND t.ho_so_hop_le = 1
          AND NOT EXISTS (
              SELECT 1 FROM phan_bo_thi_sinh p
              WHERE p.ma_ky_thi = ? AND p.ma_thi_sinh = t.ma_thi_sinh
          )
    ', [$exam, $exam]);
    tan_sort_students($students);

    $capacity = 0;
    foreach ($rooms as $room) {
        $capacity += max(0, min(24, (int)$room['suc_chua']) - (int)$room['da_xep']);
    }
    if (!$capacity) tan_error('Các phòng tại địa điểm này đã đủ chỗ.');
    if (!$students) tan_error('Không còn thí sinh có hồ sơ hợp lệ chưa được phân bổ.');

    $next = tan_rows($db, "
        SELECT COALESCE(MAX(CAST(SUBSTRING(so_bao_danh, 4) AS UNSIGNED)), 0) AS max_sbd
        FROM phan_bo_thi_sinh
        WHERE ma_ky_thi = ? AND so_bao_danh REGEXP '^SBD[0-9]+$'
    ", [$exam]);
    $serial = (int)$next[0]['max_sbd'] + 1;
    $result = [];
    $cursor = 0;

    foreach ($rooms as $room) {
        $space = max(0, min(24, (int)$room['suc_chua']) - (int)$room['da_xep']);
        for ($i = 0; $i < $space && isset($students[$cursor]); $i++, $cursor++) {
            $student = $students[$cursor];
            $result[] = [
                'ma_ky_thi' => $exam,
                'ma_thi_sinh' => $student['ma_thi_sinh'],
                'ho_ten' => $student['ho_ten'],
                'ma_phong' => $room['ma_phong'],
                'ten_phong' => $room['ten_phong'],
                'so_bao_danh' => 'SBD' . str_pad((string)$serial++, 5, '0', STR_PAD_LEFT),
                'trang_thai' => 'DU_KIEN',
            ];
        }
    }
    return $result;
}

function tan_allocate(PDO $db, string $exam, string $venue, array $user): int
{
    // Khóa kỳ thi để tránh hai yêu cầu cấp SBD đồng thời trong ứng dụng.
    $stmt = $db->prepare('SELECT ma_ky_thi FROM ky_thi WHERE ma_ky_thi = ? FOR UPDATE');
    $stmt->execute([$exam]);
    if (!$stmt->fetchColumn()) tan_error('Kỳ thi không tồn tại.');

    $plan = tan_plan($db, $exam, $venue);
    $insert = $db->prepare('
        INSERT INTO phan_bo_thi_sinh(ma_ky_thi, ma_thi_sinh, ma_phong, so_bao_danh)
        VALUES (?, ?, ?, ?)
    ');
    foreach ($plan as $row) {
        $insert->execute([
            $row['ma_ky_thi'],
            $row['ma_thi_sinh'],
            $row['ma_phong'],
            $row['so_bao_danh'],
        ]);
    }
    audit($db, (int)$user['id'], 'PHAN_BO_THI_SINH',
        'Kỳ thi ' . $exam . ', điểm ' . $venue . ': ' . count($plan) . ' dự kiến');
    return count($plan);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    task('tan.php', function (PDO $db, array $user): void {
        $action = required_val('action');
        $exam = required_val('ma_ky_thi');
        $venue = required_val('ma_dia_diem');

        $db->beginTransaction();
        try {
            if ($action === 'allocate') {
                $count = tan_allocate($db, $exam, $venue, $user);
                $message = "Đã phân bổ dự kiến $count thí sinh tại địa điểm $venue.";
            } elseif ($action === 'confirm') {
                $query = $db->prepare('
                    UPDATE phan_bo_thi_sinh p
                    JOIN phong_thi r ON r.ma_phong = p.ma_phong
                    SET p.trang_thai = \'DA_XAC_NHAN\'
                    WHERE p.ma_ky_thi = ? AND r.ma_dia_diem = ?
                      AND p.trang_thai = \'DU_KIEN\'
                ');
                $query->execute([$exam, $venue]);
                $count = $query->rowCount();
                if ($count === 0) tan_error('Không có phân bổ Dự kiến để xác nhận tại địa điểm này.');
                audit($db, (int)$user['id'], 'XAC_NHAN_PHAN_BO', "$exam / $venue / $count thí sinh");
                $message = "Đã xác nhận $count thí sinh tại địa điểm $venue.";
            } else {
                tan_error('Thao tác không hợp lệ.');
            }
            $db->commit();
            flash($message);
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }
    });
}

$db = db();
$exams = tan_rows($db, 'SELECT ma_ky_thi, ten_ky_thi FROM ky_thi ORDER BY nam_tuyen_sinh DESC');
$exam = (string)($_GET['ma_ky_thi'] ?? ($exams[0]['ma_ky_thi'] ?? ''));
$venues = $exam === '' ? [] : tan_rows($db, '
    SELECT DISTINCT d.ma_dia_diem, d.ten_dia_diem
    FROM dia_diem_thi d JOIN phong_thi r ON r.ma_dia_diem = d.ma_dia_diem
    WHERE r.ma_ky_thi = ? ORDER BY d.ten_dia_diem
', [$exam]);
$venue = (string)($_GET['ma_dia_diem'] ?? ($venues[0]['ma_dia_diem'] ?? ''));
$allocations = $exam === '' ? [] : tan_rows($db, '
    SELECT p.so_bao_danh, t.ho_ten, d.ten_dia_diem, r.ten_phong, p.trang_thai
    FROM phan_bo_thi_sinh p
    JOIN thi_sinh t ON t.ma_thi_sinh = p.ma_thi_sinh
    JOIN phong_thi r ON r.ma_phong = p.ma_phong
    JOIN dia_diem_thi d ON d.ma_dia_diem = r.ma_dia_diem
    WHERE p.ma_ky_thi = ? ORDER BY d.ten_dia_diem, r.ten_phong, p.so_bao_danh
', [$exam]);
// Dữ liệu trong MySQL vẫn giữ nguyên mã trạng thái; chỉ đổi nhãn khi hiển thị.
function tan_status_label(string $status): string
{
    return match ($status) {
        'DA_XAC_NHAN' => 'Đã xác nhận',
        'DU_KIEN' => 'Dự kiến',
        default => $status,
    };
}

$eligibleCount = $exam === '' ? 0 : (int)tan_rows($db,
    'SELECT COUNT(*) AS total FROM thi_sinh WHERE ma_ky_thi = ? AND ho_so_hop_le = 1', [$exam])[0]['total'];
$confirmed = count(array_filter($allocations, fn($r) => $r['trang_thai'] === 'DA_XAC_NHAN'));
$preview = [];
$errorMessage = '';
if ($exam !== '' && $venue !== '' && ($_GET['preview'] ?? '') === '1') {
    try {
        $preview = tan_plan($db, $exam, $venue);
    } catch (Throwable $error) {
        $errorMessage = $error->getMessage();
    }
}

page_start('Phân bổ thí sinh vào phòng thi', 'tan');
?>
<style>
.tan-hero{padding:24px;border-radius:18px;background:linear-gradient(125deg,#163875,#2857ae);color:#fff;margin-bottom:20px}
.tan-hero h2{color:#fff;margin:0 0 8px;font-size:23px}.tan-hero p{margin:0;color:#e3ecff}
.tan-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:20px}
.tan-stat{padding:18px;border-radius:14px;background:#fff;border:1px solid #e1e9f4}
.tan-stat strong{display:block;font-size:28px;color:#184286}.tan-stat span{color:#61738c;font-size:13px}
.tan-form{display:flex;gap:14px;flex-wrap:wrap;align-items:end}.tan-form .field{min-width:220px;flex:1}
.tan-pills{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}.tan-pill{border-radius:40px;background:#eaf1ff;padding:8px 14px;color:#24549e;font-size:13px}
.tan-table-scroll{overflow-x:auto}.tan-table-scroll table{width:100%}.tan-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
.tan-muted{color:#65748a}.tan-alert{padding:12px 16px;border-radius:10px;background:#fff5e9;color:#894600;margin-bottom:12px}
@media(max-width:700px){.tan-stats{grid-template-columns:1fr}.tan-hero{padding:18px}}
</style>
<section class="tan-hero">
    <h2>Tổ chức phân bổ phòng thi</h2>
    <p>Chọn kỳ thi và địa điểm → xem trước phương án → lưu dự kiến → xác nhận chính thức.</p>
</section>

<div class="tan-stats">
    <div class="tan-stat"><strong><?= $eligibleCount ?></strong><span>Thí sinh có hồ sơ hợp lệ</span></div>
    <div class="tan-stat"><strong><?= count($allocations) ?></strong><span>Thí sinh đã được xếp phòng</span></div>
    <div class="tan-stat"><strong><?= $confirmed ?></strong><span>Thí sinh đã xác nhận phân bổ</span></div>
</div>

<div class="panel">
    <h2>1. Chọn kỳ thi và địa điểm thi</h2>
    <form method="get" class="tan-form">
        <div class="field">
            <label for="ma_ky_thi">Kỳ thi</label>
            <select name="ma_ky_thi" id="ma_ky_thi" onchange="this.form.submit()" required>
                <?php foreach ($exams as $item): ?>
                    <option value="<?= esc($item['ma_ky_thi']) ?>" <?= $exam === $item['ma_ky_thi'] ? 'selected' : '' ?>>
                        <?= esc($item['ten_ky_thi']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="ma_dia_diem">Địa điểm thi</label>
            <select name="ma_dia_diem" id="ma_dia_diem" required>
                <?php foreach ($venues as $item): ?>
                    <option value="<?= esc($item['ma_dia_diem']) ?>" <?= $venue === $item['ma_dia_diem'] ? 'selected' : '' ?>>
                        <?= esc($item['ten_dia_diem']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn" name="preview" value="1" type="submit">Xem trước phân bổ</button>
    </form>
    <p class="tan-muted">Một phòng có tối đa 24 thí sinh, không vượt sức chứa; số báo danh duy nhất trong kỳ thi.</p>
    <?php if ($errorMessage !== ''): ?><div class="tan-alert"><?= esc($errorMessage) ?></div><?php endif; ?>
    <?php if ($preview): ?>
        <div class="tan-pills"><span class="tan-pill"><?= count($preview) ?> thí sinh dự kiến</span><span class="tan-pill">Chưa ghi dữ liệu</span></div>
        <div class="tan-table-scroll">
           <?php
    $previewDisplay = array_slice($preview, 0, 25);

    foreach ($previewDisplay as &$row) {
        $row['trang_thai'] = tan_status_label($row['trang_thai']);
    }
    unset($row);

    table_rows($previewDisplay, [
        'Kỳ thi', 'Mã thí sinh', 'Họ tên',
        'Mã phòng', 'Phòng thi', 'SBD', 'Trạng thái'
    ]);
?>
        </div>
        <?php if (count($preview) > 25): ?><p class="tan-muted">Đang xem 25 dòng đầu. Danh sách đầy đủ được hiển thị ở phía dưới sau khi lưu.</p><?php endif; ?>
    <?php endif; ?>
    <div class="tan-actions">
        <form method="post" onsubmit="return confirm('Lưu phân bổ dự kiến cho các phòng tại địa điểm này?')">
            <input type="hidden" name="_csrf" value="<?= esc(csrf()) ?>">
            <input type="hidden" name="action" value="allocate">
            <input type="hidden" name="ma_ky_thi" value="<?= esc($exam) ?>">
            <input type="hidden" name="ma_dia_diem" value="<?= esc($venue) ?>">
            <button class="btn" type="submit" <?= $exam === '' || $venue === '' ? 'disabled' : '' ?>>Lưu phân bổ dự kiến</button>
        </form>
        <form method="post" onsubmit="return confirm('Xác nhận chính thức toàn bộ phân bổ dự kiến ở địa điểm này?')">
            <input type="hidden" name="_csrf" value="<?= esc(csrf()) ?>">
            <input type="hidden" name="action" value="confirm">
            <input type="hidden" name="ma_ky_thi" value="<?= esc($exam) ?>">
            <input type="hidden" name="ma_dia_diem" value="<?= esc($venue) ?>">
            <button class="btn secondary" type="submit" <?= $exam === '' || $venue === '' ? 'disabled' : '' ?>>Xác nhận phân bổ</button>
        </form>
    </div>
</div>

<div class="panel">
    <h2>2. Danh sách thí sinh được phân bổ</h2>
    <div class="tan-actions">
        <input type="search" id="tan-search" placeholder="Tìm tên, SBD, phòng thi..." oninput="tanFilter()" aria-label="Tìm thí sinh">
    </div>
    <div id="tan-results" class="tan-table-scroll">
        <?php
        $displayAllocations = [];
        foreach ($allocations as $item) {
            $displayAllocations[] = [
                $item['so_bao_danh'],
                $item['ho_ten'],
                $item['ten_dia_diem'],
                $item['ten_phong'],
                tan_status_label((string)$item['trang_thai']),
            ];
        }

        table_rows($displayAllocations, ['SBD', 'Họ tên', 'Địa điểm thi', 'Phòng thi', 'Trạng thái']);
        ?>
    </div>
    <div class="tan-actions"><span id="tan-count" class="tan-muted"></span><div id="tan-pages"></div></div>
</div>
<script>
(function () {
    const wrap = document.getElementById('tan-results');
    const rows = [...wrap.querySelectorAll('tbody tr')];
    const count = document.getElementById('tan-count');
    const pages = document.getElementById('tan-pages');
    const perPage = 20;
    let page = 1;

    window.tanFilter = function () { page = 1; render(); };
    function render() {
        const keyword = (document.getElementById('tan-search').value || '').toLocaleLowerCase('vi');
        const filtered = rows.filter(row => row.textContent.toLocaleLowerCase('vi').includes(keyword));
        const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
        page = Math.min(page, totalPages);
        rows.forEach(row => { row.style.display = 'none'; });
        filtered.slice((page - 1) * perPage, page * perPage).forEach(row => { row.style.display = ''; });
        count.textContent = 'Hiển thị ' + (filtered.length ? (page - 1) * perPage + 1 : 0) + '–' + Math.min(page * perPage, filtered.length) + ' / ' + filtered.length + ' thí sinh';
        pages.innerHTML = '';
        [['Trước', Math.max(1, page - 1)], ...Array.from({length: totalPages}, (_, i) => [String(i + 1), i + 1]), ['Sau', Math.min(totalPages, page + 1)]].forEach(([label, target]) => {
            const button = document.createElement('button');
            button.type = 'button'; button.className = 'btn secondary';
            button.style.margin = '2px'; button.textContent = label;
            button.disabled = target === page || (label !== 'Trước' && label !== 'Sau' && Number(label) === page);
            button.addEventListener('click', () => { page = target; render(); });
            pages.appendChild(button);
        });
    }
    render();
})();
</script>
<?php page_end(); ?>
