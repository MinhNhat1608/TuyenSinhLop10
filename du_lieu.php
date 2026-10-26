<?php
/**
 * Trang quản lý dữ liệu nền - Hệ thống tuyển sinh lớp 10.
 * Giao diện mới, giữ nguyên cấu trúc bảng và nghiệp vụ thêm dữ liệu.
 */
require_once __DIR__ . '/includes/layout.php';
require_role(['SO']);

$sets = [
    'truong_thpt' => ['Trường THPT', [
        'ma_truong' => 'Mã trường',
        'ten_truong' => 'Tên trường',
        'kha_nang_tiep_nhan' => 'Khả năng tiếp nhận',
    ]],
    'ky_thi' => ['Kỳ thi', [
        'ma_ky_thi' => 'Mã kỳ thi',
        'ten_ky_thi' => 'Tên kỳ thi',
        'nam_tuyen_sinh' => 'Năm tuyển sinh',
    ]],
    'he_xet_tuyen' => ['Hệ xét tuyển', [
        'ma_he' => 'Mã hệ',
        'ten_he' => 'Tên hệ xét tuyển',
    ]],
    'thi_sinh' => ['Thí sinh', [
        'ma_thi_sinh' => 'Mã thí sinh',
        'ho_ten' => 'Họ tên',
        'ma_ky_thi' => 'Mã kỳ thi',
        'ho_so_hop_le' => 'Hồ sơ hợp lệ (0/1)',
    ]],
    'dia_diem_thi' => ['Địa điểm thi', [
        'ma_dia_diem' => 'Mã địa điểm',
        'ten_dia_diem' => 'Tên địa điểm',
        'dia_chi' => 'Địa chỉ',
    ]],
    'phong_thi' => ['Phòng thi', [
        'ma_phong' => 'Mã phòng',
        'ma_ky_thi' => 'Mã kỳ thi',
        'ma_dia_diem' => 'Mã địa điểm',
        'ten_phong' => 'Tên phòng',
        'suc_chua' => 'Sức chứa',
    ]],
    'mon_thi' => ['Môn thi', [
        'ma_mon' => 'Mã môn',
        'ten_mon' => 'Tên môn',
    ]],
    'nhan_su' => ['Nhân sự chấm thi', [
        'ma_ns' => 'Mã nhân sự',
        'ho_ten' => 'Họ tên',
        'loai_nhan_su' => 'Loại nhân sự (CHAM_THI)',
    ]],
    'tui_bai_thi' => ['Túi bài thi', [
        'ma_tui' => 'Mã túi',
        'ma_ky_thi' => 'Mã kỳ thi',
        'ma_mon' => 'Mã môn',
        'ma_phong' => 'Mã phòng',
        'ma_bi' => 'Mã bì',
    ]],
    'bai_thi' => ['Bài thi / mã phách', [
        'ma_bai' => 'Mã bài',
        'ma_tui' => 'Mã túi',
        'ma_phach' => 'Mã phách',
        'ma_thi_sinh' => 'Mã thí sinh',
    ]],
    'nguyen_vong' => ['Nguyện vọng', [
        'ma_thi_sinh' => 'Mã thí sinh',
        'ma_truong' => 'Mã trường',
        'ma_he' => 'Mã hệ',
        'thu_tu' => 'Thứ tự 1–3',
        'da_xac_nhan' => 'Đã xác nhận (0/1)',
    ]],
    'ket_qua_xet_tuyen' => ['Kết quả xét tuyển', [
        'ma_thi_sinh' => 'Mã thí sinh',
        'ma_ky_thi' => 'Mã kỳ thi',
        'ma_truong' => 'Mã trường (để trống nếu trượt)',
        'nguyen_vong_trung_tuyen' => 'NV trúng tuyển (để trống nếu trượt)',
        'tong_diem' => 'Tổng điểm đã xét',
        'ket_qua' => 'TRUNG_TUYEN hoặc KHONG_TRUNG_TUYEN',
    ]],
];

$groups = [
    'Danh mục cơ bản' => ['truong_thpt', 'ky_thi', 'he_xet_tuyen', 'mon_thi'],
    'Tổ chức kỳ thi' => ['thi_sinh', 'dia_diem_thi', 'phong_thi', 'nhan_su'],
    'Bài thi và tuyển sinh' => ['tui_bai_thi', 'bai_thi', 'nguyen_vong', 'ket_qua_xet_tuyen'],
];

$numericFields = [
    'kha_nang_tiep_nhan', 'nam_tuyen_sinh', 'ho_so_hop_le',
    'suc_chua', 'thu_tu', 'da_xac_nhan',
    'nguyen_vong_trung_tuyen', 'tong_diem',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    task('du_lieu.php', function (PDO $db, array $user) use ($sets) {
        if ($user['vai_tro'] !== 'SO') {
            throw new RuntimeException('Chỉ tài khoản Sở được nhập dữ liệu nền.');
        }

        $table = val('table');
        if (!isset($sets[$table])) {
            throw new RuntimeException('Bảng không hợp lệ.');
        }

        $columns = array_keys($sets[$table][1]);
        $values = [];

        foreach ($columns as $column) {
            $value = val($column);
            $optional = $table === 'ket_qua_xet_tuyen'
                && in_array($column, ['ma_truong', 'nguyen_vong_trung_tuyen'], true);

            $values[] = ($optional && $value === '') ? null : required_val($column);
        }

        if ($table === 'ket_qua_xet_tuyen') {
            $result = val('ket_qua');
            if (!in_array($result, ['TRUNG_TUYEN', 'KHONG_TRUNG_TUYEN'], true)) {
                throw new RuntimeException('Kết quả xét tuyển không hợp lệ.');
            }
            if ($result === 'TRUNG_TUYEN'
                && (!val('ma_truong')
                    || !in_array(val('nguyen_vong_trung_tuyen'), ['1', '2', '3'], true))) {
                throw new RuntimeException('Trúng tuyển phải có trường và nguyện vọng.');
            }
        }

        if ($table === 'nguyen_vong'
            && !in_array(val('thu_tu'), ['1', '2', '3'], true)) {
            throw new RuntimeException('Thứ tự nguyện vọng phải từ 1 đến 3.');
        }

        $safeColumns = implode(', ', array_map(
            static fn (string $name): string => '`' . $name . '`',
            $columns
        ));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $statement = $db->prepare(
            'INSERT INTO `' . $table . '` (' . $safeColumns . ') VALUES (' . $placeholders . ')'
        );
        $statement->execute($values);

        audit(
            $db,
            $user['id'],
            'NHAP_DU_LIEU',
            $table . ' / ' . implode(' - ', array_map(
                static fn ($value): string => (string) ($value ?? ''),
                $values
            ))
        );
    });
}

$selected = (string) ($_GET['table'] ?? 'truong_thpt');
if (!isset($sets[$selected])) {
    $selected = 'truong_thpt';
}

$selectedLabel = $sets[$selected][0];
$fields = $sets[$selected][1];
$rows = db()->query(
    'SELECT * FROM `' . $selected . '` ORDER BY 1 DESC LIMIT 50'
)->fetchAll(PDO::FETCH_ASSOC);

page_start('Quản lý dữ liệu hệ thống', 'data');
?>
<style>
    /* Chỉ áp dụng cho trang du_lieu.php, không ảnh hưởng các trang khác. */
    .data-ui { --data-blue: #315bd5; --data-navy: #152c58; --data-border: #e1e9f5; color: #172c50; }
    .data-ui * { box-sizing: border-box; }
    .data-ui .data-hero { background: linear-gradient(115deg, #193772, #3465d8); border-radius: 20px; padding: 28px 32px; color: white; margin-bottom: 22px; box-shadow: 0 10px 30px rgba(37,76,155,.12); }
    .data-ui .data-hero .eyebrow { font-size: 11px; letter-spacing: 1.7px; text-transform: uppercase; font-weight: 800; opacity: .75; }
    .data-ui .data-hero h2 { color: white; margin: 9px 0; font-size: clamp(23px, 2.1vw, 30px); }
    .data-ui .data-hero p { color: #dce7ff; margin: 0; line-height: 1.6; }
    .data-ui .data-section { background: #fff; border: 1px solid var(--data-border); border-radius: 18px; padding: 24px; margin-bottom: 20px; box-shadow: 0 7px 25px rgba(19,45,95,.035); }
    .data-ui .data-section-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
    .data-ui .data-section-heading h3 { margin: 0 0 6px; font-size: 19px; color: var(--data-navy); }
    .data-ui .data-muted { color: #72819c; font-size: 13px; line-height: 1.6; margin: 0; }
    .data-ui .data-number { background: #eaf0ff; color: #315bd5; font-size: 12px; font-weight: 800; padding: 6px 10px; border-radius: 9px; white-space: nowrap; }
    .data-ui .data-group { margin-top: 17px; }
    .data-ui .data-group:first-child { margin-top: 0; }
    .data-ui .data-group-title { color: #75849e; font-size: 11px; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; margin-bottom: 10px; }
    .data-ui .data-tabs { display: flex; flex-wrap: wrap; gap: 9px; }
    .data-ui .data-tab { text-decoration: none; color: #456087; background: #f6f8fd; border: 1px solid #e6ecf6; border-radius: 11px; padding: 11px 14px; font-size: 13px; font-weight: 700; transition: .15s ease; }
    .data-ui .data-tab:hover { background: #eaf0ff; border-color: #a5baff; color: #315bd5; }
    .data-ui .data-tab.is-active { background: #315bd5; color: #fff; border-color: #315bd5; box-shadow: 0 5px 12px rgba(49,91,213,.17); }
    .data-ui .data-entry { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 17px 20px; }
    .data-ui .data-field { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
    .data-ui .data-field label { font-size: 13px; font-weight: 750; color: #304768; }
    .data-ui .data-field input, .data-ui .data-field select { width: 100%; min-height: 46px; border: 1px solid #dce5f3; border-radius: 11px; padding: 11px 13px; outline: none; color: #203455; background: #fafcff; font: inherit; }
    .data-ui .data-field input:focus, .data-ui .data-field select:focus { border-color: #4771dd; box-shadow: 0 0 0 3px rgba(66,112,220,.12); background: #fff; }
    .data-ui .data-field input::placeholder { color: #a7b3c4; }
    .data-ui .data-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 23px; padding-top: 19px; border-top: 1px solid #edf1f7; }
    .data-ui .data-submit { appearance: none; border: 0; background: #315bd5; border-radius: 11px; color: white; font: inherit; font-size: 14px; font-weight: 750; padding: 12px 20px; cursor: pointer; }
    .data-ui .data-submit:hover { background: #2149b9; }
    .data-ui .data-reset { appearance: none; border: 1px solid #dde5f1; background: #fff; border-radius: 11px; color: #546780; font: inherit; font-size: 14px; font-weight: 650; padding: 11px 18px; cursor: pointer; }
    .data-ui .data-reset:hover { background: #f7f9fd; }
    .data-ui .data-search { width: min(100%, 310px); padding: 11px 13px; border: 1px solid #dce5f3; border-radius: 11px; outline: none; background: #fafcff; font: inherit; font-size: 13px; }
    .data-ui .data-search:focus { border-color: #4771dd; }
    .data-ui .data-table-scroll { width: 100%; overflow-x: auto; border: 1px solid #e7edf5; border-radius: 12px; }
    .data-ui .data-table { width: 100%; border-collapse: collapse; font-size: 13px; white-space: nowrap; }
    .data-ui .data-table th { background: #f5f8fd; color: #516784; font-weight: 800; text-align: left; font-size: 12px; padding: 15px 17px; border-bottom: 1px solid #e8eef6; }
    .data-ui .data-table td { padding: 14px 17px; border-bottom: 1px solid #edf1f7; color: #334761; }
    .data-ui .data-table tbody tr:last-child td { border-bottom: none; }
    .data-ui .data-table tbody tr:hover { background: #f9fbff; }
    .data-ui .data-table td:first-child { font-weight: 750; color: #2557b6; }
    .data-ui .data-empty { padding: 35px 18px; text-align: center; color: #8190a7; }
    .data-ui .data-alert { margin: 0 0 20px; border: 1px solid #f3dfa6; background: #fffbf0; border-radius: 12px; padding: 14px 16px; color: #8e6720; font-size: 13px; line-height: 1.6; }
    .data-ui .data-help { color: #8b98aa; font-size: 12px; margin-top: 12px; }
    @media (max-width: 720px) {
        .data-ui .data-hero { padding: 23px 20px; }
        .data-ui .data-section { padding: 19px 16px; }
        .data-ui .data-entry { grid-template-columns: 1fr; }
        .data-ui .data-section-heading { flex-direction: column; }
        .data-ui .data-search { width: 100%; }
        .data-ui .data-tab { padding: 10px 11px; }
    }
</style>

<div class="data-ui">
    <section class="data-hero">
        <div class="eyebrow">Quản trị dữ liệu tuyển sinh</div>
        <h2>Quản lý dữ liệu hệ thống</h2>
        <p>Quản lý danh mục, thông tin kỳ thi, thí sinh và dữ liệu phục vụ xét tuyển.</p>
    </section>

    <section class="data-section">
        <div class="data-section-heading">
            <div>
                <h3>Danh mục dữ liệu</h3>
                <p class="data-muted">Chọn nhóm dữ liệu bạn muốn xem hoặc bổ sung.</p>
            </div>
            <span class="data-number"><?php echo count($sets); ?> danh mục</span>
        </div>

        <?php foreach ($groups as $groupName => $tableNames): ?>
            <div class="data-group">
                <div class="data-group-title"><?php echo esc($groupName); ?></div>
                <div class="data-tabs">
                    <?php foreach ($tableNames as $tableName): ?>
                        <a class="data-tab <?php echo $selected === $tableName ? 'is-active' : ''; ?>"
                           href="du_lieu.php?table=<?php echo urlencode($tableName); ?>">
                            <?php echo esc($sets[$tableName][0]); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="data-section">
        <div class="data-section-heading">
            <div>
                <h3>Thêm <?php echo esc(mb_strtolower($selectedLabel, 'UTF-8')); ?></h3>
                <p class="data-muted">Nhập đầy đủ thông tin rồi nhấn Lưu dữ liệu để thêm bản ghi mới.</p>
            </div>
            <span class="data-number"><?php echo count($fields); ?> trường thông tin</span>
        </div>

        <?php if ($selected === 'bai_thi'): ?>
            <div class="data-alert">Dữ liệu mã phách và danh tính thí sinh là thông tin nhạy cảm, chỉ dành cho người có quyền quản trị phù hợp.</div>
        <?php elseif ($selected === 'ket_qua_xet_tuyen'): ?>
            <div class="data-alert">Kết quả xét tuyển chính thức nên được tạo và phê duyệt qua chức năng xét tuyển, không nhập trực tiếp tại đây khi vận hành thực tế.</div>
        <?php endif; ?>

        <form method="post" action="du_lieu.php?table=<?php echo urlencode($selected); ?>">
            <input type="hidden" name="_csrf" value="<?php echo esc(csrf()); ?>">
            <input type="hidden" name="table" value="<?php echo esc($selected); ?>">

            <div class="data-entry">
                <?php foreach ($fields as $column => $label): ?>
                    <?php
                    $optional = $selected === 'ket_qua_xet_tuyen'
                        && in_array($column, ['ma_truong', 'nguyen_vong_trung_tuyen'], true);
                    $isNumeric = in_array($column, $numericFields, true);
                    ?>
                    <div class="data-field">
                        <label for="field_<?php echo esc($column); ?>">
                            <?php echo esc($label); ?><?php echo $optional ? ' (không bắt buộc)' : ''; ?>
                        </label>

                        <?php if (in_array($column, ['ho_so_hop_le', 'da_xac_nhan'], true)): ?>
                            <select id="field_<?php echo esc($column); ?>" name="<?php echo esc($column); ?>" required>
                                <option value="">-- Chọn trạng thái --</option>
                                <option value="1">Có / Đã xác nhận (1)</option>
                                <option value="0">Không / Chưa xác nhận (0)</option>
                            </select>
                        <?php elseif ($column === 'ket_qua'): ?>
                            <select id="field_ket_qua" name="ket_qua" required>
                                <option value="">-- Chọn kết quả --</option>
                                <option value="TRUNG_TUYEN">Trúng tuyển</option>
                                <option value="KHONG_TRUNG_TUYEN">Không trúng tuyển</option>
                            </select>
                        <?php elseif ($column === 'thu_tu' || $column === 'nguyen_vong_trung_tuyen'): ?>
                            <select id="field_<?php echo esc($column); ?>" name="<?php echo esc($column); ?>" <?php echo $optional ? '' : 'required'; ?>>
                                <option value="">-- Chọn nguyện vọng --</option>
                                <option value="1">Nguyện vọng 1</option>
                                <option value="2">Nguyện vọng 2</option>
                                <option value="3">Nguyện vọng 3</option>
                            </select>
                        <?php else: ?>
                            <input
                                id="field_<?php echo esc($column); ?>"
                                name="<?php echo esc($column); ?>"
                                type="<?php echo $isNumeric ? 'number' : 'text'; ?>"
                                <?php echo $optional ? '' : 'required'; ?>
                                <?php echo $isNumeric ? 'step="any"' : ''; ?>
                                placeholder="Nhập <?php echo esc(mb_strtolower($label, 'UTF-8')); ?>"
                            >
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="data-actions">
                <button type="submit" class="data-submit">+ Lưu dữ liệu</button>
                <button type="reset" class="data-reset">Nhập lại</button>
            </div>
        </form>
    </section>

    <section class="data-section">
        <div class="data-section-heading">
            <div>
                <h3>Danh sách <?php echo esc(mb_strtolower($selectedLabel, 'UTF-8')); ?></h3>
                <p class="data-muted">Hiển thị tối đa 50 bản ghi gần nhất từ cơ sở dữ liệu.</p>
            </div>
            <input
                type="search"
                class="data-search"
                id="data-filter"
                placeholder="Tìm trong danh sách..."
                aria-label="Tìm trong danh sách"
            >
        </div>

        <?php if (!$rows): ?>
            <div class="data-empty">Chưa có dữ liệu trong danh mục này.</div>
        <?php else: ?>
            <div class="data-table-scroll">
                <table class="data-table" id="data-list">
                    <thead>
                        <tr>
                            <?php foreach (array_keys($rows[0]) as $column): ?>
                                <th><?php echo esc($fields[$column] ?? $column); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($row as $value): ?>
                                    <td><?php echo esc($value ?? '—'); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="data-help" id="data-count">Đang hiển thị <?php echo count($rows); ?> bản ghi.</p>
        <?php endif; ?>
    </section>
</div>

<script>
    (() => {
        const input = document.getElementById('data-filter');
        const rows = [...document.querySelectorAll('#data-list tbody tr')];
        const counter = document.getElementById('data-count');
        if (!input || !rows.length) return;

        input.addEventListener('input', () => {
            const keyword = input.value.trim().toLocaleLowerCase('vi');
            let visible = 0;
            rows.forEach((row) => {
                const match = row.textContent.toLocaleLowerCase('vi').includes(keyword);
                row.hidden = !match;
                if (match) visible++;
            });
            if (counter) counter.textContent = `Đang hiển thị ${visible}/${rows.length} bản ghi.`;
        });
    })();
</script>
<?php page_end(); ?>
