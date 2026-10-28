
<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/import_csv.php';

$user = require_role(['SO', 'HOI_DONG']);
$db = db();

$canEdit = in_array(
    $user['vai_tro'],
    ['SO', 'HOI_DONG'],
    true
);

$error = '';

function loc_url(
    string $diem = '',
    string $mon = '',
    string $tui = ''
): string {
    return 'loc.php?' . http_build_query([
        'diem_thi' => $diem,
        'ma_mon' => $mon,
        'ma_tui' => $tui
    ]);
}

function loc_get_bag(
    PDO $db,
    string $diem,
    string $mon,
    string $tui
): ?array {
    $q = $db->prepare("
        SELECT
            t.ma_tui,
            t.ma_bi,
            t.ma_mon,
            t.ma_ky_thi,
            p.ma_dia_diem,
            p.ten_phong
        FROM tui_bai_thi t
        JOIN phong_thi p
            ON p.ma_phong = t.ma_phong
        WHERE p.ma_dia_diem = ?
          AND t.ma_mon = ?
          AND t.ma_tui = ?
          AND p.ma_ky_thi = t.ma_ky_thi
    ");

    $q->execute([$diem, $mon, $tui]);

    return $q->fetch() ?: null;
}

function loc_get_papers(
    PDO $db,
    string $tui,
    bool $lock = false
): array {
    $sql = "
        SELECT
            b.ma_bai,
            b.ma_phach,
            b.da_thong_nhat_diem,
            d.diem,
            d.trang_thai AS trang_thai_diem,
            pc.id AS ma_phan_cong
        FROM bai_thi b
        LEFT JOIN diem_thi d
            ON d.ma_bai = b.ma_bai
        LEFT JOIN phan_cong_cham_thi pc
            ON pc.ma_tui = b.ma_tui
        WHERE b.ma_tui = ?
        ORDER BY b.ma_phach
    ";

    if ($lock) {
        $sql .= " FOR UPDATE";
    }

    $q = $db->prepare($sql);
    $q->execute([$tui]);

    return $q->fetchAll();
}

/*
 * KIỂM TRA DỮ LIỆU CSV
 * Chưa lưu vào database ở bước này.
 */
function loc_check_csv(
    array $csv,
    array $papers
): array {
    $byPhach = [];

    foreach ($papers as $paper) {
        $byPhach[
            (string)$paper['ma_phach']
        ] = $paper;
    }

    $seen = [];
    $scores = [];
    $issues = [];
    $errors = [];

    if (empty($csv)) {
        return [
            'scores' => [],
            'issues' => [],
            'errors' => ['File điểm chưa có dữ liệu.']
        ];
    }

    foreach ($csv as $row) {
        $phach = trim(
            (string)($row['ma_phach'] ?? '')
        );

        $score = str_replace(
            ',',
            '.',
            trim((string)($row['diem'] ?? ''))
        );

        $line = (int)($row['_line'] ?? 0);

        $problems = [];

        // Lấy điểm để hiển thị trong bảng.
        if (
            $phach !== '' &&
            !array_key_exists($phach, $scores)
        ) {
            $scores[$phach] = $score;
        }

        // Mã phách không tồn tại trong túi.
        if (
            $phach === '' ||
            !isset($byPhach[$phach])
        ) {
            $problems[] =
                'Mã phách không thuộc túi bài đã chọn';
        }

        // Mã phách trùng trong file.
        if (
            $phach !== '' &&
            isset($seen[$phach])
        ) {
            $problems[] =
                'Mã phách bị trùng trong file điểm';
        }

        if ($phach !== '') {
            $seen[$phach] = true;
        }

        // Kiểm tra điểm từ 0 đến 10.
        if (!preg_match(
            '/^(?:10(?:\.0{1,2})?|[0-9](?:\.\d{1,2})?)$/D',
            $score
        )) {
            $problems[] =
                'Điểm không hợp lệ (0–10)';
        }

        if (isset($byPhach[$phach])) {
            $paper = $byPhach[$phach];

            // Kiểm tra phân công chấm thi.
            if (
                $paper['ma_phan_cong'] === null
            ) {
                $problems[] =
                    'Túi bài chưa được phân công chấm thi';
            }

            // Điểm đã được hai cán bộ thống nhất ngoài hệ thống.
            // Xác nhận upload CSV là thao tác chốt điểm chính thức.

            // Không cập nhật điểm đã chốt.
            if (
                $paper['trang_thai_diem'] === 'DA_CHOT'
            ) {
                $problems[] =
                    'Điểm đã chốt, không được sửa';
            }
        }

        if (!empty($problems)) {
            $description = implode(
                '; ',
                $problems
            );

            $errors[] =
                'Dòng ' . $line .
                ' (' . $phach . '): ' .
                $description;

            if ($phach !== '') {
                $issues[$phach] = isset($issues[$phach])
                    ? $issues[$phach] . '; ' . $description
                    : $description;
            }
        }
    }

    // Kiểm tra tính đầy đủ: mỗi bài thi trong túi phải có đúng một mã phách trong file.
    foreach ($byPhach as $phach => $paper) {
        if (!isset($seen[$phach])) {
            $errors[] = 'Thiếu mã phách ' . $phach . ' trong file điểm.';
            $issues[$phach] = 'Thiếu điểm trong file';
        }
    }

    return [
        'scores' => $scores,
        'issues' => $issues,
        'errors' => $errors
    ];
}

/*
 * XỬ LÝ UPLOAD VÀ CẬP NHẬT
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_post();

        if (!$canEdit) {
            throw new RuntimeException(
                'Tài khoản không có quyền nhập điểm.'
            );
        }

        $action = val('action');

        $diemPost = val('diem_thi');
        $monPost = val('ma_mon');
        $tuiPost = val('ma_tui');

        $back = loc_url(
            $diemPost,
            $monPost,
            $tuiPost
        );

        /*
         * HỦY CẬP NHẬT
         */
        if ($action === 'cancel') {
            unset($_SESSION['loc_preview']);

            flash(
                'Đã hủy cập nhật. Dữ liệu điểm không thay đổi.'
            );

            go($back);
        }

        $bag = loc_get_bag(
            $db,
            $diemPost,
            $monPost,
            $tuiPost
        );

        if (!$bag) {
            throw new RuntimeException(
                'Điểm thi, môn thi hoặc túi bài không hợp lệ.'
            );
        }

        /*
         * UPLOAD CSV
         * Chỉ xem trước điểm, chưa lưu database.
         */
        if ($action === 'preview') {
            unset(
                $_SESSION['loc_preview'],
                $_SESSION['loc_saved']
            );

            // CSV phải có hai cột: ma_phach,diem.
            $csv = csv_records('loc');

            $_SESSION['loc_preview'] = [
                'uid' => (int)$user['id'],
                'diem' => $diemPost,
                'mon' => $monPost,
                'tui' => $tuiPost,
                'rows' => $csv,
                'token' => bin2hex(random_bytes(16))
            ];

            go($back);
        }

        /*
         * XÁC NHẬN VÀ LƯU VÀO MYSQL
         */
        if ($action === 'confirm') {
            $pending = $_SESSION['loc_preview'] ?? null;

            if (
                !is_array($pending) ||
                (int)($pending['uid'] ?? 0) !==
                    (int)$user['id'] ||
                ($pending['diem'] ?? '') !== $diemPost ||
                ($pending['mon'] ?? '') !== $monPost ||
                ($pending['tui'] ?? '') !== $tuiPost ||
                !hash_equals(
                    (string)($pending['token'] ?? ''),
                    val('token')
                )
            ) {
                throw new RuntimeException(
                    'Dữ liệu xác nhận không hợp lệ. Hãy tải lại file điểm.'
                );
            }

            $db->beginTransaction();

            try {
                // Lấy và khóa dữ liệu bài thi.
                $papers = loc_get_papers(
                    $db,
                    $tuiPost,
                    true
                );

                // Kiểm tra lại trước khi lưu.
                $check = loc_check_csv(
                    $pending['rows'],
                    $papers
                );

                if (!empty($check['errors'])) {
                    throw new RuntimeException(
                        'Không thể lưu: ' .
                        $check['errors'][0]
                    );
                }

                $byPhach = [];

                foreach ($papers as $paper) {
                    $byPhach[
                        $paper['ma_phach']
                    ] = $paper;
                }

                /*
                 * Nếu bài chưa có điểm thì thêm mới.
                 */
                $insert = $db->prepare("
                    INSERT INTO diem_thi
                    (
                        ma_bai,
                        diem,
                        nguoi_nhap,
                        ngay_nhap,
                        trang_thai
                    )
                    VALUES (?, ?, ?, NOW(), 'DA_CHOT')
                ");

                /*
                 * Nếu bài đã nhập nhưng chưa chốt
                 * thì cập nhật giá trị điểm.
                 */
                $update = $db->prepare("
    UPDATE diem_thi
    SET
        diem = ?,
        nguoi_nhap = ?,
        ngay_nhap = NOW(),
        trang_thai = 'DA_CHOT'
    WHERE ma_bai = ?
      AND trang_thai = 'DA_NHAP'
");
    $updateThongNhat = $db->prepare("
    UPDATE bai_thi
    SET da_thong_nhat_diem = 1
    WHERE ma_bai = ?
");
                foreach (
                    $check['scores'] as $phach => $score
                ) {
                    $paper = $byPhach[$phach];

                    if (
                        $paper['trang_thai_diem'] === null
                    ) {
                        $insert->execute([
                            $paper['ma_bai'],
                            $score,
                            (int)$user['id']
                        ]);

                    } elseif (
                        $paper['trang_thai_diem'] === 'DA_NHAP'
                    ) {
                        $update->execute([
                            $score,
                            (int)$user['id'],
                            $paper['ma_bai']
                        ]);

                        // Phải cập nhật được đúng một bản ghi điểm chưa chốt.
                        if ($update->rowCount() !== 1) {
                            throw new RuntimeException(
                                'Không thể chốt điểm bài ' . $phach . '.'
                            );
                        }
                    } else {
                        throw new RuntimeException(
                            'Điểm đã chốt, không được sửa.'
                        );
                    }

                    // Điểm trong CSV đã được hai cán bộ thống nhất bên ngoài.
                    // Xác nhận cùng giao dịch với thao tác chốt điểm.
                    $updateThongNhat->execute([$paper['ma_bai']]);
                    if ($updateThongNhat->rowCount() === 0 &&
                        (int)$paper['da_thong_nhat_diem'] !== 1) {
                        throw new RuntimeException(
                            'Không thể xác nhận thống nhất điểm bài ' . $phach . '.'
                        );
                    }
                }

                /*
                 * Ghi nhật ký người thực hiện.
                 */
                audit(
                    $db,
                    (int)$user['id'],
                    'CAP_NHAT_DIEM',
                    'Túi ' . $tuiPost .
                    '; Mã bì ' . $bag['ma_bi'] .
                    '; Số bài ' . count($check['scores'])
                );

                // Lưu toàn bộ dữ liệu.
                $db->commit();

            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                throw $e;
            }

            // Đánh dấu để hiển thị điểm vừa lưu.
            $_SESSION['loc_saved'] = [
                'uid' => (int)$user['id'],
                'diem' => $diemPost,
                'mon' => $monPost,
                'tui' => $tuiPost
            ];

            unset($_SESSION['loc_preview']);

            flash(
                'Cập nhật điểm thi thành công. Đã lưu ' .
                count($check['scores']) .
                ' bài vào MySQL.'
            );

            go($back);
        }

        throw new RuntimeException(
            'Thao tác không hợp lệ.'
        );

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

/*
 * LẤY DỮ LIỆU HIỂN THỊ
 */
$diem = trim(
    (string)($_GET['diem_thi'] ?? '')
);

$mon = trim(
    (string)($_GET['ma_mon'] ?? '')
);

$tui = trim(
    (string)($_GET['ma_tui'] ?? '')
);

// Danh sách địa điểm thi.
$diemList = $db->query("
    SELECT
        ma_dia_diem,
        ten_dia_diem
    FROM dia_diem_thi
    ORDER BY ten_dia_diem
")->fetchAll();

$monList = [];
$tuiList = [];
$bag = null;
$papers = [];

// Lấy môn thi theo địa điểm.
if ($diem !== '') {
    $q = $db->prepare("
        SELECT DISTINCT
            m.ma_mon,
            m.ten_mon
        FROM mon_thi m
        JOIN tui_bai_thi t
            ON t.ma_mon = m.ma_mon
        JOIN phong_thi p
            ON p.ma_phong = t.ma_phong
        WHERE p.ma_dia_diem = ?
        ORDER BY m.ten_mon
    ");

    $q->execute([$diem]);

    $monList = $q->fetchAll();
}

// Lấy túi bài theo điểm thi và môn thi.
if ($diem !== '' && $mon !== '') {
    $q = $db->prepare("
        SELECT
            t.ma_tui,
            t.ma_bi,
            t.ma_ky_thi,
            p.ten_phong
        FROM tui_bai_thi t
        JOIN phong_thi p
            ON p.ma_phong = t.ma_phong
        WHERE p.ma_dia_diem = ?
          AND t.ma_mon = ?
        ORDER BY t.ma_tui
    ");

    $q->execute([$diem, $mon]);

    $tuiList = $q->fetchAll();
}

// Lấy danh sách bài thi.
if (
    $diem !== '' &&
    $mon !== '' &&
    $tui !== ''
) {
    $bag = loc_get_bag(
        $db,
        $diem,
        $mon,
        $tui
    );

    if ($bag) {
        $papers = loc_get_papers(
            $db,
            $tui
        );
    }
}

/*
 * ĐỌC DỮ LIỆU CSV ĐANG XEM TRƯỚC
 */
$preview = $_SESSION['loc_preview'] ?? null;

$showPreview =
    $bag !== null &&
    is_array($preview) &&
    (int)($preview['uid'] ?? 0) === (int)$user['id'] &&
    ($preview['diem'] ?? '') === $diem &&
    ($preview['mon'] ?? '') === $mon &&
    ($preview['tui'] ?? '') === $tui;

$check = $showPreview
    ? loc_check_csv($preview['rows'], $papers)
    : null;

/*
 * KIỂM TRA ĐIỂM VỪA LƯU
 */
$saved = $_SESSION['loc_saved'] ?? null;

$showSaved =
    !$showPreview &&
    $bag !== null &&
    is_array($saved) &&
    (int)($saved['uid'] ?? 0) === (int)$user['id'] &&
    ($saved['diem'] ?? '') === $diem &&
    ($saved['mon'] ?? '') === $mon &&
    ($saved['tui'] ?? '') === $tui;

/*
 * GIAO DIỆN GỐC CỦA PROJECT
 */
page_start(
    'Cập nhật điểm thi theo mã phách',
    'loc'
);

if ($error !== '') {
    echo '<div class="note"
        style="color:#9b1c1c;background:#fff0f0">' .
        esc($error) .
    '</div>';
}

/*
 * 1. CHỌN ĐIỂM THI, MÔN THI, TÚI BÀI
 */
echo '<div class="panel">';
echo '<h2>1. Chọn điểm thi, môn thi và túi bài</h2>';

echo '<form method="get" action="loc.php">';
echo '<div class="controls">';

// Chọn điểm thi.
echo '<div class="field">';
echo '<label for="diem_thi">Điểm thi</label>';

echo '<select
    id="diem_thi"
    name="diem_thi"
    required
    onchange="
        this.form.elements[\'ma_mon\'].value=\'\';
        this.form.elements[\'ma_tui\'].value=\'\';
        this.form.submit()
    ">';

echo '<option value="">-- Chọn điểm thi --</option>';

foreach ($diemList as $r) {
    echo '<option value="' .
        esc($r['ma_dia_diem']) . '"' .
        (
            $diem === $r['ma_dia_diem']
            ? ' selected'
            : ''
        ) .
        '>' .
        esc($r['ten_dia_diem']) .
        '</option>';
}

echo '</select>';
echo '</div>';

// Chọn môn thi.
echo '<div class="field">';
echo '<label for="ma_mon">Môn thi</label>';

echo '<select
    id="ma_mon"
    name="ma_mon"
    onchange="
        this.form.elements[\'ma_tui\'].value=\'\';
        this.form.submit()
    ">';

echo '<option value="">-- Chọn môn thi --</option>';

foreach ($monList as $r) {
    echo '<option value="' .
        esc($r['ma_mon']) . '"' .
        (
            $mon === $r['ma_mon']
            ? ' selected'
            : ''
        ) .
        '>' .
        esc($r['ten_mon']) .
        '</option>';
}

echo '</select>';
echo '</div>';

// Chọn mã túi bài.
echo '<div class="field">';
echo '<label for="ma_tui">Mã túi bài</label>';

echo '<select
    id="ma_tui"
    name="ma_tui"
    onchange="this.form.submit()">';

echo '<option value="">-- Chọn túi bài --</option>';

foreach ($tuiList as $r) {
    echo '<option value="' .
        esc($r['ma_tui']) . '"' .
        (
            $tui === $r['ma_tui']
            ? ' selected'
            : ''
        ) .
        '>' .
        esc(
            $r['ma_tui'] .
            ' (' . $r['ma_ky_thi'] .
            ' - ' . $r['ten_phong'] . ')'
        ) .
        '</option>';
}

echo '</select>';
echo '</div>';

echo '</div>';

echo '<div class="actions">
    <button
        class="btn secondary"
        type="submit">
        Xem danh sách
    </button>
</div>';

echo '</form>';
echo '</div>';

/*
 * 2. DANH SÁCH BÀI THI
 * CHỈ MỘT CỘT ĐIỂM
 */
if ($bag !== null) {
    echo '<div class="panel">';
    echo '<h2>2. Danh sách bài thi</h2>';

    echo '<p class="hint">
        Mã túi bài:
        <b>' . esc($tui) . '</b>
        · Mã bì:
        <b>' . esc($bag['ma_bi']) . '</b>
        · Kỳ thi:
        <b>' . esc($bag['ma_ky_thi']) . '</b>
        · Số bài:
        <b>' . count($papers) . '</b>
    </p>';

    $table = [];

    foreach ($papers as $paper) {
        $phach = (string)$paper['ma_phach'];

        // Ban đầu cột Điểm để trống.
        $score = '';

        // Sau upload: hiển thị điểm CSV.
        if ($showPreview) {
            $score = $check['scores'][$phach] ?? '';

        } elseif ($showSaved) {
            // Sau khi lưu: lấy lại điểm từ MySQL.
            $score = $paper['diem'] !== null
                ? number_format(
                    (float)$paper['diem'],
                    2,
                    '.',
                    ''
                )
                : '';
        }

        // Xác định trạng thái.
        if (
            $paper['trang_thai_diem'] === 'DA_CHOT'
        ) {
            $status = 'Đã chốt';

        } elseif (
            $paper['trang_thai_diem'] === 'DA_NHAP'
        ) {
            $status = 'Đã cập nhật điểm';

        } else {
            $status = 'Chưa nhập';
        }

        $table[] = [
            $diem,
            $mon,
            $tui,
            $phach,
            $bag['ma_bi'],
            $score,
            $status
        ];
    }

    // Đúng bước 8 của đặc tả: chỉ hiển thị 7 cột phục vụ đối chiếu.
    table_rows(
        $table,
        [
            'Điểm thi',
            'Môn thi',
            'Mã túi bài',
            'Mã phách',
            'Mã bì',
            'Điểm',
            'Trạng thái'
        ]
    );

    /*
     * HIỂN THỊ KẾT QUẢ KIỂM TRA CSV
     */
    if ($showPreview) {
        if (!empty($check['errors'])) {
            echo '<div class="note"
                style="color:#9b1c1c;background:#fff0f0">
                Có ' . count($check['errors']) . '
                lỗi trong file điểm.
                Chưa lưu thay đổi vào database.
            </div>';

            echo '<ul style="
                color:#9b1c1c;
                font-size:12px;
                line-height:1.8
            ">';

            foreach ($check['errors'] as $reason) {
                echo '<li>' .
                    esc($reason) .
                    '</li>';
            }

            echo '</ul>';

        } else {
            echo '<div class="note">
                File có ' .
                count($preview['rows']) .
                ' dòng hợp lệ.
                Nhấn xác nhận để lưu vào MySQL.
            </div>';

            /*
             * NÚT XÁC NHẬN CẬP NHẬT
             */
            echo '<form
                method="post"
                action="' .
                esc(loc_url($diem, $mon, $tui)) .
                '">';

            echo '<input type="hidden"
                name="_csrf"
                value="' . esc(csrf()) . '">';

            echo '<input type="hidden"
                name="action"
                value="confirm">';

            echo '<input type="hidden"
                name="token"
                value="' .
                esc($preview['token']) .
                '">';

            echo '<input type="hidden"
                name="diem_thi"
                value="' . esc($diem) . '">';

            echo '<input type="hidden"
                name="ma_mon"
                value="' . esc($mon) . '">';

            echo '<input type="hidden"
                name="ma_tui"
                value="' . esc($tui) . '">';

            echo '<div class="actions">
                <button
                    class="btn"
                    type="submit">
                    Xác nhận cập nhật
                </button>
            </div>';

            echo '</form>';
        }

        /*
         * NÚT HỦY CẬP NHẬT
         */
        echo '<form
            method="post"
            action="' .
            esc(loc_url($diem, $mon, $tui)) .
            '">';

        echo '<input type="hidden"
            name="_csrf"
            value="' . esc(csrf()) . '">';

        echo '<input type="hidden"
            name="action"
            value="cancel">';

        echo '<input type="hidden"
            name="diem_thi"
            value="' . esc($diem) . '">';

        echo '<input type="hidden"
            name="ma_mon"
            value="' . esc($mon) . '">';

        echo '<input type="hidden"
            name="ma_tui"
            value="' . esc($tui) . '">';

        echo '<div class="actions">
            <button
                class="btn secondary"
                type="submit">
                Hủy cập nhật
            </button>
        </div>';

        echo '</form>';
    }

    echo '</div>';

    /*
     * 3. UPLOAD FILE CSV
     */
    echo '<div class="panel">';
    echo '<h2>3. Upload file điểm</h2>';

    if ($canEdit) {
        echo '<p class="hint">
            Chọn file CSV từ máy tính.
            File gồm hai cột:
            <b>ma_phach,diem</b>.
        </p>';

        echo '<form
            method="post"
            enctype="multipart/form-data"
            action="' .
            esc(loc_url($diem, $mon, $tui)) .
            '">';

        echo '<input type="hidden"
            name="_csrf"
            value="' . esc(csrf()) . '">';

        echo '<input type="hidden"
            name="action"
            value="preview">';

        echo '<input type="hidden"
            name="diem_thi"
            value="' . esc($diem) . '">';

        echo '<input type="hidden"
            name="ma_mon"
            value="' . esc($mon) . '">';

        echo '<input type="hidden"
            name="ma_tui"
            value="' . esc($tui) . '">';

        echo '<div class="controls">';
        echo '<div class="field">';

        echo '<label for="import_file">
            Chọn file điểm
        </label>';

        echo '<input
            id="import_file"
            type="file"
            name="import_file"
            accept=".csv,text/csv"
            required>';

        echo '</div>';
        echo '</div>';

        echo '<div class="actions">
            <button
                class="btn"
                type="submit">
                Upload file điểm và xem kết quả
            </button>
        </div>';

        echo '</form>';

    } else {
        echo '<div class="note">
            Tài khoản hiện tại không có quyền
            cập nhật điểm thi.
        </div>';
    }

    echo '</div>';
}

page_end();
