<?php
require_once __DIR__.'/includes/layout.php';
require_once __DIR__.'/includes/import_csv.php';

// Khởi động session để lưu trạng thái phân công tạm thời (Preview & Xác nhận)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_role(['SO','HOI_DONG']);

$error = null;
$success = null;
$dbConn = db();

// =========================================================================
// XỬ LÝ UPLOAD / IMPORT FILE CSV TÚI BÀI THI CHƯA PHÂN CÔNG (5 CỘT CSDL)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'import_tui_bai_thi') {
    try {
        if (!isset($_FILES['file_tui_bai']) || $_FILES['file_tui_bai']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Vui lòng chọn file CSV hợp lệ để tải lên.');
        }

        $fileTmpPath = $_FILES['file_tui_bai']['tmp_name'];
        $handle = fopen($fileTmpPath, 'r');
        if ($handle === false) {
            throw new RuntimeException('Không thể mở file được tải lên.');
        }

        // Đọc dòng tiêu đề (Header)
        $header = fgetcsv($handle, 1000, ',');
        
        $dbConn->beginTransaction();

        // Câu lệnh SQL chuẩn khớp với bảng tui_bai_thi trong CSDL:
        // ma_tui, ma_ky_thi, ma_mon, ma_phong, ma_bi
        $stmtInsertTui = $dbConn->prepare("
            INSERT INTO tui_bai_thi (ma_tui, ma_ky_thi, ma_mon, ma_phong, ma_bi) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                ma_ky_thi = VALUES(ma_ky_thi), 
                ma_mon = VALUES(ma_mon), 
                ma_phong = VALUES(ma_phong), 
                ma_bi = VALUES(ma_bi)
        ");

        $countImported = 0;
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            if (!empty($data[0]) && !empty($data[2])) {
                $maTui   = trim($data[0]);
                $maKyThi = trim($data[1]);
                $maMon   = trim($data[2]);
                $maPhong = trim($data[3]);
                $maBi    = trim($data[4]);

                $stmtInsertTui->execute([$maTui, $maKyThi, $maMon, $maPhong, $maBi]);
                $countImported++;
            }
        }
        fclose($handle);

        $dbConn->commit();
        $success = "Đã nhập thành công {$countImported} túi bài thi chưa phân công từ file!";
    } catch (Throwable $e) {
        if ($dbConn->inTransaction()) {
            $dbConn->rollBack();
        }
        $error = 'Lỗi nhập dữ liệu từ file: ' . $e->getMessage();
    }
}

// =========================================================================
// XỬ LÝ CÁC ACTION PHÂN CÔNG CHẤM THI THEO USE CASE
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    // Bước 3 & 4: Tự động phân công tạm thời (Preview)
    if ($action === 'tieu_chuan_phan_cong') {
        try {
            $maMon = required_val('ma_mon');

            // 1. Lấy danh sách túi bài thi CHƯA phân công THUỘC MÔN THI ĐÓ
            $stmtTui = $dbConn->prepare("
                SELECT t.ma_tui 
                FROM tui_bai_thi t 
                WHERE t.ma_mon = ? 
                  AND t.ma_tui NOT IN (SELECT ma_tui FROM phan_cong_cham_thi)
                ORDER BY t.ma_tui
            ");
            $stmtTui->execute([$maMon]);
            $danhSachTui = $stmtTui->fetchAll(PDO::FETCH_COLUMN);

            if (empty($danhSachTui)) {
                throw new RuntimeException('Không còn túi bài thi nào chưa được phân công cho môn thi này.');
            }

            // 2. Lấy danh sách nhân sự chấm thi hợp lệ
            $stmtNS = $dbConn->query("
                SELECT ma_ns 
                FROM nhan_su 
                WHERE loai_nhan_su = 'CHAM_THI'
            ");
            $danhSachCB = $stmtNS->fetchAll(PDO::FETCH_COLUMN);
            $tongCB = count($danhSachCB);
            
            // Alternative flow 4.1: Không đủ cán bộ khả dụng (cần ít nhất 2 cán bộ)
            if ($tongCB < 2) {
                throw new RuntimeException('Không đủ Cán bộ chấm thi hợp lệ để phân công cho môn này. Vui lòng bổ sung nhân sự!');
            }

            // 3. Thuật toán phân cặp vòng tròn tạm thời
            $index = 0;
            $previewPairs = [];

            foreach ($danhSachTui as $maTui) {
                $cb1 = $danhSachCB[$index % $tongCB];
                $index++;
                $cb2 = $danhSachCB[$index % $tongCB];
                $index++;

                if ($cb1 === $cb2) {
                    $cb2 = $danhSachCB[($index++) % $tongCB];
                }

                $previewPairs[] = [
                    'ma_tui' => $maTui,
                    'ma_cbct1' => $cb1,
                    'ma_cbct2' => $cb2
                ];
            }

            // Lưu vào session để hiển thị màn hình preview bước 5
            $_SESSION['preview_phan_cong'] = $previewPairs;
            $_SESSION['preview_ma_mon'] = $maMon;

        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    // Bước 6, 7, 8, 10: Xác nhận phân công và lưu chính thức vào CSDL
    if ($action === 'xac_nhan_phan_cong') {
        try {
            if (empty($_SESSION['preview_phan_cong'])) {
                throw new RuntimeException('Không có dữ liệu phân công tạm thời để xác nhận.');
            }

            $maMon = $_SESSION['preview_ma_mon'] ?? '';
            $previewPairs = $_SESSION['preview_phan_cong'];

            $dbConn->beginTransaction();

            $stmtInsert = $dbConn->prepare('INSERT INTO phan_cong_cham_thi(ma_tui, ma_cbct1, ma_cbct2, trang_thai) VALUES(?, ?, ?, ?)');
            
            // Bước 8: Lưu bản ghi phân công & tự động cập nhật trạng thái
            foreach ($previewPairs as $pair) {
                $stmtInsert->execute([$pair['ma_tui'], $pair['ma_cbct1'], $pair['ma_cbct2'], 'CHO_CHAM']);
            }

            // Ghi audit log
            audit($dbConn, $u['id'] ?? 1, 'TU_DONG_PHAN_CONG_CHAM_THI', 'Đã xác nhận phân công cho ' . count($previewPairs) . ' túi bài thi môn mã: ' . $maMon);
            
            $dbConn->commit();

            // Xóa dữ liệu tạm trong session
            unset($_SESSION['preview_phan_cong']);
            unset($_SESSION['preview_ma_mon']);

            // Bước 10: Hiển thị thông báo thành công
            $success = 'Phân công Hội đồng Chấm thi thành công';

        } catch (Throwable $e) {
            if ($dbConn->inTransaction()) {
                $dbConn->rollBack();
            }
            // Exception flow 8.1: Lỗi giao tác / ngoại lệ CSDL
            $error = 'Lỗi kết nối cơ sở dữ liệu, thao tác không thành công.';
        }
    }

    // Alternative flow 6.1: Hủy / Phân công lại
    if ($action === 'huy_phan_cong') {
        unset($_SESSION['preview_phan_cong']);
        unset($_SESSION['preview_ma_mon']);
    }
}

// Lấy dữ liệu danh sách để hiển thị giao diện
$danhSachMon = $dbConn->query("SELECT ma_mon, ten_mon FROM mon_thi ORDER BY ten_mon")->fetchAll();
$danhSachCBKhaDung = $dbConn->query("SELECT ma_ns, ho_ten FROM nhan_su WHERE loai_nhan_su = 'CHAM_THI'")->fetchAll();
$danhSachTuiChuaPhanCong = $dbConn->query("
    SELECT t.ma_tui, m.ten_mon 
    FROM tui_bai_thi t 
    JOIN mon_thi m ON t.ma_mon = m.ma_mon 
    WHERE t.ma_tui NOT IN (SELECT ma_tui FROM phan_cong_cham_thi)
    ORDER BY t.ma_tui
")->fetchAll();

page_start('Phân công Hội đồng chấm thi (Tự động theo môn)', 'nghia');
?>

<div class="note">
    Hệ thống hỗ trợ tự động phân công Hội đồng chấm thi theo đặc tả Use Case. Tuyệt đối không hiển thị SBD hoặc thông tin thí sinh trong phân hệ chấm thi.
</div>

<!-- THÔNG BÁO LỖI VÀ THÀNH CÔNG -->
<?php if (!empty($error)): ?>
    <div class="error-message" style="background: #ffebee; color: #c62828; padding: 12px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #ef9a9a;">
        <strong>Thông báo lỗi:</strong> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="success-message" style="background: #e8f5e9; color: #2e7d32; padding: 12px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #a5d6a7;">
        <strong>Thành công:</strong> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<!-- FORM IMPORT FILE CSV TÚI BÀI THI -->
<div class="panel" style="margin-bottom: 20px; background: #fff8e1; border: 1px solid #ffe082; padding: 15px; border-radius: 4px;">
    <h2>Upload / Import file danh sách túi bài thi chưa phân công</h2>
    <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 15px; align-items: center; margin-top: 10px;">
        <input type="hidden" name="form_action" value="import_tui_bai_thi">
        <input type="file" name="file_tui_bai" accept=".csv" required style="padding: 6px;">
        <button type="submit" class="btn-primary" style="background: #f57c00; border: none; padding: 8px 16px; color: white; border-radius: 4px; cursor: pointer;">Tải lên & Nạp túi bài thi</button>
    </form>
    <small style="color: #666; margin-top: 5px; display: block;">* Định dạng file CSV (5 cột): <code>ma_tui, ma_ky_thi, ma_mon, ma_phong, ma_bi</code></small>
</div>

<!-- BƯỚC 1, 2, 3: HIỂN THỊ DANH SÁCH MÔN THI, TÚI BÀI THI, CBKHÂ DỤNG VÀ CHỌN MÔN -->
<?php if (empty($_SESSION['preview_phan_cong'])): ?>
    <div class="panel">
        <h2>Bước 2 & 3: Chọn Môn thi và Tự động phân công</h2>
        
        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div style="flex: 1;">
                <h4>Cán bộ chấm thi khả dụng (Tổng: <?php echo count($danhSachCBKhaDung); ?>)</h4>
                <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 8px; background: #fafafa;">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach($danhSachCBKhaDung as $cb): ?>
                            <li><?php echo htmlspecialchars($cb['ma_ns'] . ' - ' . $cb['ho_ten']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div style="flex: 1;">
                <h4>Túi bài thi chưa phân công (Tổng: <?php echo count($danhSachTuiChuaPhanCong); ?>)</h4>
                <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 8px; background: #fafafa;">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach($danhSachTuiChuaPhanCong as $tui): ?>
                            <li>Túi: <?php echo htmlspecialchars($tui['ma_tui']); ?> (Môn: <?php echo htmlspecialchars($tui['ten_mon']); ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="form_action" value="tieu_chuan_phan_cong">
            <?php select_field('ma_mon', 'Chọn môn thi', 'SELECT ma_mon, ten_mon FROM mon_thi ORDER BY ten_mon', 'ma_mon', 'ten_mon'); ?>
            <p style="margin-top: 15px;">Sau khi chọn môn, nhấn nút bên dưới để hệ thống tự động chạy thuật toán phân công giám khảo.</p>
            <button type="submit" class="btn-primary">Tự động phân công CBCT</button>
        </form>
    </div>

<?php else: ?>
    <!-- BƯỚC 5 & 6: XEM TRƯỚC VÀ XÁC NHẬN PHÂN CÔNG -->
    <div class="panel" style="border: 2px dashed #1976d2; padding: 20px; background: #f0f7ff; border-radius: 4px;">
        <h2>Bước 5: Xem trước kết quả Phân công tạm thời (Môn: <?php echo htmlspecialchars($_SESSION['preview_ma_mon']); ?>)</h2>
        <p style="margin-bottom: 15px;">Kiểm tra danh sách ghép cặp cán bộ chấm thi trước khi lưu chính thức vào cơ sở dữ liệu:</p>
        
        <table class="table" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;" border="1">
            <thead>
                <tr style="background: #e3f2fd;">
                    <th style="padding: 8px;">Mã túi bài thi</th>
                    <th style="padding: 8px;">Cán bộ chấm thi 1</th>
                    <th style="padding: 8px;">Cán bộ chấm thi 2</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_SESSION['preview_phan_cong'] as $pair): ?>
                    <tr>
                        <td style="padding: 8px; text-align: center;"><?php echo htmlspecialchars($pair['ma_tui']); ?></td>
                        <td style="padding: 8px; text-align: center;"><?php echo htmlspecialchars($pair['ma_cbct1']); ?></td>
                        <td style="padding: 8px; text-align: center;"><?php echo htmlspecialchars($pair['ma_cbct2']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="display: flex; gap: 10px;">
            <!-- Bước 6: Chọn "Xác nhận phân công" -->
            <form method="POST" style="display:inline;">
                <input type="hidden" name="form_action" value="xac_nhan_phan_cong">
                <button type="submit" class="btn-success" style="background: #2e7d32; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">Xác nhận phân công</button>
            </form>

            <!-- Alternative flow 6.1: Chọn "Hủy / Phân công lại" -->
            <form method="POST" style="display:inline;">
                <input type="hidden" name="form_action" value="huy_phan_cong">
                <button type="submit" class="btn-danger" style="background: #c62828; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;" onclick="return confirm('Bạn có chắc muốn hủy kết quả phân công này không?');">Hủy / Phân công lại</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- DANH SÁCH BẢNG PHÂN CÔNG CHÍNH THỨC HIỆN CÓ TRONG CSDL -->
<div class="panel" style="margin-top: 20px;">
    <h2>Danh sách phân công hiện tại trong CSDL</h2>
    <?php 
    table_rows($dbConn->query('
        SELECT pc.ma_tui, mon.ten_mon, ns1.ho_ten as cb1, ns2.ho_ten as cb2, pc.trang_thai 
        FROM phan_cong_cham_thi pc 
        JOIN tui_bai_thi t ON pc.ma_tui=t.ma_tui 
        JOIN mon_thi mon ON t.ma_mon=mon.ma_mon 
        JOIN nhan_su ns1 ON ns1.ma_ns=pc.ma_cbct1 
        JOIN nhan_su ns2 ON ns2.ma_ns=pc.ma_cbct2
    ')->fetchAll(), ['Túi bài', 'Môn', 'Cán bộ 1', 'Cán bộ 2', 'Trạng thái']);
    ?>
</div>

<?php
import_panel('nghia', 'Phân công chấm thi');
page_end();
?>