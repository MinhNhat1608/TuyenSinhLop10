<?php
declare(strict_types=1);
require_once __DIR__.'/includes/layout.php';

$user = require_role(['SO']);
$pdo = db();
$exams = $pdo->query('SELECT * FROM ky_thi ORDER BY nam_tuyen_sinh DESC, ma_ky_thi')->fetchAll();
$schools = $pdo->query('SELECT * FROM truong_thpt ORDER BY ten_truong, ma_truong')->fetchAll();
$systems = $pdo->query('SELECT * FROM he_xet_tuyen ORDER BY ma_he')->fetchAll();
$examMap = array_column($exams, null, 'ma_ky_thi');
$schoolMap = array_column($schools, null, 'ma_truong');
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$source = $post ? $_POST : $_GET;
function ct_text(array $source, string $key): string {
    return isset($source[$key]) && is_string($source[$key]) ? trim($source[$key]) : '';
}
$exam = ct_text($source, 'ma_ky_thi');
$school = ct_text($source, 'ma_truong');
$errors = [];
$generalError = '';
$values = [];
$existing = [];
$locked = false;
// Không đoán mã hệ: dùng đúng mã và tên đã khởi tạo trong database.
$ready = count($systems) === 3 && count($exams) > 0 && count($schools) > 0;

if (isset($examMap[$exam], $schoolMap[$school])) {
    $q = $pdo->prepare('SELECT * FROM chi_tieu WHERE ma_ky_thi=? AND ma_truong=?');
    $q->execute([$exam, $school]);
    foreach ($q->fetchAll() as $row) {
        $existing[$row['ma_he']] = $row;
        $locked = $locked || $row['trang_thai'] === 'DA_DUYET';
    }
}
foreach ($systems as $system) {
    $key = (string)$system['ma_he'];
    $input = isset($_POST['chi_tieu']) && is_array($_POST['chi_tieu']) ? $_POST['chi_tieu'] : [];
    $values[$key] = $post ? ct_text($input, $key) : (string)($existing[$key]['so_luong'] ?? '');
}

if ($post) {
    try {
        verify_post();
        if (ct_text($_POST, 'action') !== 'approve') {
            throw new RuntimeException('Thao tác không hợp lệ.');
        }
        if (!$ready) {
            throw new RuntimeException('Cần khởi tạo kỳ thi, trường THPT và đúng 3 hệ: Thường, Chuyên, Tích hợp trong mục Nhập dữ liệu gốc.');
        }
        if (!isset($examMap[$exam])) $errors['ma_ky_thi'] = 'Vui lòng chọn kỳ thi hợp lệ.';
        if (!isset($schoolMap[$school])) $errors['ma_truong'] = 'Vui lòng chọn trường THPT hợp lệ.';
        $numbers = [];
        foreach ($systems as $system) {
            $key = (string)$system['ma_he'];
            $raw = $values[$key];
            if ($raw === '') {
                $errors[$key] = 'Không được bỏ trống. Nếu không tuyển, nhập 0.';
            } elseif (!preg_match('/^[0-9]+$/D', $raw)) {
                $errors[$key] = 'Chỉ tiêu phải là số nguyên không âm.';
            } else {
                $canonical = ltrim($raw, '0');
                $canonical = $canonical === '' ? '0' : $canonical;
                if (strlen($canonical) > 10 || (float)$canonical > 2147483647) {
                    $errors[$key] = 'Chỉ tiêu quá lớn; tối đa 2147483647.';
                } else {
                    $numbers[$key] = (int)$canonical;
                }
            }
        }
        if (!$errors) {
            $total = array_sum($numbers);
            $capacity = (int)$schoolMap[$school]['kha_nang_tiep_nhan'];
            if ($total > $capacity) {
                foreach ($systems as $system) {
                    $errors[$system['ma_he']] = 'Điều chỉnh chỉ tiêu để tổng không vượt '.$capacity.' học sinh.';
                }
                $generalError = 'Tổng chỉ tiêu là '.$total.', vượt mức trần tiếp nhận '.$capacity.' học sinh của trường.';
            }
        }
        if (!$errors) {
            $pdo->beginTransaction();
            // Khóa kỳ thi và trường trước khi đọc lại: tránh hai lượt duyệt đồng thời.
            $q = $pdo->prepare('SELECT ma_ky_thi FROM ky_thi WHERE ma_ky_thi=? FOR UPDATE');
            $q->execute([$exam]);
            if ($q->fetchColumn() === false) throw new RuntimeException('Kỳ thi không còn tồn tại.');
            $q = $pdo->prepare('SELECT kha_nang_tiep_nhan FROM truong_thpt WHERE ma_truong=? FOR UPDATE');
            $q->execute([$school]);
            $capacity = $q->fetchColumn();
            if ($capacity === false) throw new RuntimeException('Trường THPT không còn tồn tại.');
            if ($total > (int)$capacity) throw new RuntimeException('Khả năng tiếp nhận vừa thay đổi. Mức trần hiện tại: '.$capacity.'. Vui lòng tải lại và điều chỉnh.');
            $q = $pdo->prepare('SELECT ma_he,trang_thai FROM chi_tieu WHERE ma_ky_thi=? AND ma_truong=? FOR UPDATE');
            $q->execute([$exam, $school]);
            $current = $q->fetchAll();
            $allowed = array_column($systems, 'ma_he');
            foreach ($current as $row) {
                if ($row['trang_thai'] === 'DA_DUYET') {
                    $locked = true;
                    throw new RuntimeException('Chỉ tiêu đã được phê duyệt và khóa. Không thể chỉnh sửa hoặc phê duyệt lại.');
                }
                if (!in_array($row['ma_he'], $allowed, true)) throw new RuntimeException('Danh mục hệ đã thay đổi. Vui lòng kiểm tra dữ liệu gốc.');
            }
            $q = $pdo->prepare("INSERT INTO chi_tieu
                (ma_ky_thi,ma_truong,ma_he,so_luong,trang_thai,nguoi_duyet,ngay_duyet)
                VALUES(?,?,?,?,'DA_DUYET',?,NOW())
                ON DUPLICATE KEY UPDATE so_luong=VALUES(so_luong),
                trang_thai='DA_DUYET',nguoi_duyet=VALUES(nguoi_duyet),ngay_duyet=NOW()");
            foreach ($numbers as $key => $number) $q->execute([$exam,$school,$key,$number,(int)$user['id']]);
            audit($pdo, (int)$user['id'], 'PHE_DUYET_CHI_TIEU',
                'Kỳ thi: '.$exam.'; trường: '.$school.'; chỉ tiêu: '.json_encode($numbers, JSON_UNESCAPED_UNICODE).'; tổng: '.$total);
            $pdo->commit();
            flash('Phê duyệt chỉ tiêu thành công. Hệ thống đã lưu dữ liệu, cập nhật nhật ký và khóa chỉnh sửa.');
            go('thai.php?'.http_build_query(['ma_ky_thi'=>$exam,'ma_truong'=>$school]));
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException) {
            error_log((string)$e);
            $generalError = 'Không thể lưu dữ liệu. Chưa phê duyệt chỉ tiêu; vui lòng kiểm tra kết nối và dữ liệu rồi thử lại.';
        } else {
            $generalError = $e->getMessage();
        }
    }
}

page_start('Phân bổ chỉ tiêu tuyển sinh', 'thai');
?>
<style>
.ct-error{color:#b91c1c;font-size:13px;margin:6px 0}
.ct-invalid{border:2px solid #dc2626!important;background:#fff1f2!important}
.ct-summary{border-left:4px solid #dc2626;padding:14px;background:#fff1f2;color:#991b1b;margin-bottom:16px}
.ct-inputs{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}
.ct-inputs input{width:100%;box-sizing:border-box}
.ct-status{color:#166534;font-weight:600}
</style>
<div class="note">Chỉ cán bộ Sở GD&amp;ĐT được phân bổ và phê duyệt. Tổng chỉ tiêu Thường, Chuyên và Tích hợp không được vượt khả năng tiếp nhận của trường.</div>
<?php if (!$ready): ?>
<div class="ct-summary">Chưa đủ dữ liệu gốc. Cần kỳ thi, trường THPT và đúng 3 hệ xét tuyển có tên Thường, Chuyên, Tích hợp. Hãy kiểm tra mục <a href="du_lieu.php">Nhập dữ liệu gốc</a>.</div>
<?php endif; ?>
<?php if ($generalError !== '' || $errors): ?>
<div class="ct-summary" role="alert">
    <strong><?= esc($generalError !== '' ? $generalError : 'Thông tin chưa hợp lệ. Vui lòng sửa các ô được đánh dấu đỏ.') ?></strong>
    <?php foreach (['ma_ky_thi','ma_truong'] as $key): if (isset($errors[$key])): ?>
        <div><?= esc($errors[$key]) ?></div>
    <?php endif; endforeach; ?>
</div>
<?php endif; ?>

<div class="panel">
<h2>1. Chọn kỳ thi và trường THPT</h2>
<form method="get" action="thai.php">
<div class="controls">
<?php foreach ([['ma_ky_thi','Kỳ thi',$exams,'ma_ky_thi','ten_ky_thi',$exam],['ma_truong','Trường THPT',$schools,'ma_truong','ten_truong',$school]] as [$name,$label,$items,$id,$text,$selected]): ?>
<div class="field">
<label for="<?= esc($name) ?>"><?= esc($label) ?></label>
<select id="<?= esc($name) ?>" name="<?= esc($name) ?>" required class="<?= isset($errors[$name]) ? 'ct-invalid' : '' ?>">
<option value="">-- Chọn --</option>
<?php foreach ($items as $item): ?>
<option value="<?= esc($item[$id]) ?>" <?= (string)$item[$id] === $selected ? 'selected' : '' ?>><?= esc($item[$text]) ?><?= $name === 'ma_truong' ? ' — tiếp nhận tối đa '.esc($item['kha_nang_tiep_nhan']).' học sinh' : '' ?></option>
<?php endforeach; ?>
</select>
</div>
<?php endforeach; ?>
</div>
<div class="actions"><button class="btn" type="submit">Mở khung nhập chỉ tiêu</button></div>
</form>
</div>

<?php if (isset($examMap[$exam], $schoolMap[$school])): ?>
<div class="panel">
<h2>2. Phân bổ chỉ tiêu — <?= esc($schoolMap[$school]['ten_truong']) ?></h2>
<p>Kỳ thi: <strong><?= esc($examMap[$exam]['ten_ky_thi']) ?></strong> · Năm tuyển sinh: <?= esc($examMap[$exam]['nam_tuyen_sinh']) ?></p>
<p>Khả năng tiếp nhận tối đa: <strong id="ct-capacity" data-capacity="<?= esc($schoolMap[$school]['kha_nang_tiep_nhan']) ?>"><?= esc($schoolMap[$school]['kha_nang_tiep_nhan']) ?> học sinh</strong>.</p>
<?php if ($locked): ?>
<div class="note ct-status">Đã có chỉ tiêu được phê duyệt cho trường trong kỳ thi này. Dữ liệu bị khóa chỉnh sửa.</div>
<?php endif; ?>
<form method="post" action="thai.php" id="ct-form" novalidate>
<input type="hidden" name="_csrf" value="<?= esc(csrf()) ?>">
<input type="hidden" name="action" value="approve">
<input type="hidden" name="ma_ky_thi" value="<?= esc($exam) ?>">
<input type="hidden" name="ma_truong" value="<?= esc($school) ?>">
<div class="ct-inputs">
<?php foreach ($systems as $i => $system): $key = (string)$system['ma_he']; ?>
<div class="field">
<label for="ct-<?= $i ?>">Chỉ tiêu <?= esc($system['ten_he']) ?></label>
<input type="text" inputmode="numeric" id="ct-<?= $i ?>" name="chi_tieu[<?= esc($key) ?>]"
    value="<?= esc($values[$key]) ?>" required pattern="[0-9]+" maxlength="10"
    class="ct-number <?= isset($errors[$key]) ? 'ct-invalid' : '' ?>"
    aria-invalid="<?= isset($errors[$key]) ? 'true' : 'false' ?>" aria-describedby="ct-error-<?= $i ?>"
    <?= $locked || !$ready ? 'disabled' : '' ?>>
<div id="ct-error-<?= $i ?>" class="ct-error" aria-live="polite"><?= esc($errors[$key] ?? '') ?></div>
</div>
<?php endforeach; ?>
</div>
<p id="ct-total" aria-live="polite"></p>
<div class="actions">
<?php if (!$locked && $ready): ?><button type="submit" class="btn">Phê duyệt chỉ tiêu tuyển sinh</button><?php endif; ?>
<button type="button" class="btn secondary" id="ct-cancel">Hủy</button>
</div>
</form>
</div>
<?php endif; ?>

<div class="panel"><h2>Danh sách trường và khả năng tiếp nhận</h2>
<?php table_rows(array_map(static fn($r)=>[$r['ma_truong'],$r['ten_truong'],$r['kha_nang_tiep_nhan']], $schools), ['Mã trường','Trường THPT','Khả năng tiếp nhận']); ?>
</div>
<div class="panel"><h2>Chỉ tiêu đã ghi nhận</h2>
<?php
$q = $pdo->prepare('SELECT k.ten_ky_thi,t.ten_truong,h.ten_he,c.so_luong,c.trang_thai,c.ngay_duyet
    FROM chi_tieu c JOIN ky_thi k ON c.ma_ky_thi=k.ma_ky_thi
    JOIN truong_thpt t ON c.ma_truong=t.ma_truong JOIN he_xet_tuyen h ON c.ma_he=h.ma_he
    WHERE (? = \'\' OR c.ma_ky_thi=?) ORDER BY k.nam_tuyen_sinh DESC,t.ten_truong,h.ma_he');
$q->execute([$exam,$exam]);
$rows = $q->fetchAll();
foreach ($rows as &$row) $row['trang_thai'] = $row['trang_thai'] === 'DA_DUYET' ? 'Đã duyệt — đã khóa' : 'Dự kiến';
unset($row);
table_rows($rows, ['Kỳ thi','Trường','Hệ','Chỉ tiêu','Trạng thái','Ngày duyệt']);
?>
</div>
<script>
const quotaForm = document.getElementById('ct-form');
if (quotaForm) {
    const inputs = [...quotaForm.querySelectorAll('.ct-number')];
    const editable = inputs.filter(input => !input.disabled);
    const capacity = Number(document.getElementById('ct-capacity').dataset.capacity);
    const summary = document.getElementById('ct-total');
    function validate(showErrors) {
        let total = 0, valid = true;
        const problems = new Map();
        for (const input of editable) {
            const raw = input.value.trim();
            let error = '';
            if (!raw) error = 'Không được bỏ trống. Nếu không tuyển, nhập 0.';
            else if (!/^[0-9]+$/.test(raw)) error = 'Chỉ tiêu phải là số nguyên không âm.';
            else if (Number(raw) > 2147483647) error = 'Chỉ tiêu quá lớn.';
            else total += Number(raw);
            if (error) valid = false;
            problems.set(input, error);
        }
        if (valid && total > capacity) {
            valid = false;
            editable.forEach(input => problems.set(input, 'Tổng vượt mức trần '+capacity+' học sinh. Vui lòng điều chỉnh.'));
        }
        summary.textContent = editable.length ? 'Tổng chỉ tiêu: '+total+' / '+capacity+' học sinh.' : '';
        summary.classList.toggle('ct-error', total > capacity);
        if (showErrors) {
            for (const [input, error] of problems) {
                input.classList.toggle('ct-invalid', Boolean(error));
                input.setAttribute('aria-invalid', error ? 'true' : 'false');
                document.getElementById(input.getAttribute('aria-describedby')).textContent = error;
            }
        }
        return valid;
    }
    editable.forEach(input => input.addEventListener('input', () => validate(true)));
    quotaForm.addEventListener('submit', event => {
        if (!validate(true)) {
            event.preventDefault();
            quotaForm.querySelector('.ct-invalid')?.focus();
        }
    });
    document.getElementById('ct-cancel').addEventListener('click', () => {
        if (window.confirm('Xác nhận hủy phân bổ chỉ tiêu? Dữ liệu đang nhập chưa phê duyệt sẽ không được lưu.')) {
            window.location.href = 'index.php';
        }
    });
    // Giữ nguyên lỗi từ máy chủ khi vừa nhận phản hồi POST.
    validate(false);
}
</script>
<?php page_end(); ?>
