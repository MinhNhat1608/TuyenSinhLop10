
<?php
require_once __DIR__ . '/includes/layout.php';

page_start('Tổng quan tuyển sinh lớp 10', 'home');

$modules = [
    [
        'url' => 'thai.php',
        'title' => 'Phân bổ chỉ tiêu',
        'desc' => 'Quản lý và phân bổ chỉ tiêu tuyển sinh cho các trường THPT.',
        'owner' => 'Đoàn Quốc Thái',
        'icon' => '📊',
        'color' => 'blue',
        'number' => '01'
    ],
    [
        'url' => 'tan.php',
        'title' => 'Phân bổ thí sinh vào phòng thi',
        'desc' => 'Sắp xếp thí sinh, phân bổ phòng thi và tổ chức kỳ thi.',
        'owner' => 'Nguyễn Minh Tân',
        'icon' => '👥',
        'color' => 'teal',
        'number' => '02'
    ],
    [
        'url' => 'nghia.php',
        'title' => 'Phân công Hội đồng chấm thi',
        'desc' => 'Phân công cán bộ và tổ chức hoạt động chấm thi.',
        'owner' => 'Nguyễn Minh Nghĩa',
        'icon' => '📋',
        'color' => 'purple',
        'number' => '03'
    ],
    [
        'url' => 'loc.php',
        'title' => 'Nhập điểm chính thức theo phách',
        'desc' => 'Nhập, kiểm tra và quản lý điểm thi theo mã phách.',
        'owner' => 'Ngô Hữu Lộc',
        'icon' => '📝',
        'color' => 'orange',
        'number' => '04'
    ],
    [
        'url' => 'nhat.php',
        'title' => 'Phê duyệt kết quả xét tuyển',
        'desc' => 'Kiểm tra, xét duyệt và công bố kết quả tuyển sinh.',
        'owner' => 'Trần Huỳnh Minh Nhật',
        'icon' => '🏅',
        'color' => 'green',
        'number' => '05'
    ]
];
?>

<style>
/* Dashboard tuyển sinh */
.ts-dashboard {
    font-family: inherit;
    color: #172b4d;
}

.ts-dashboard * {
    box-sizing: border-box;
}

.ts-hero {
    background: linear-gradient(115deg, #102a4c, #23558c);
    color: white;
    border-radius: 16px;
    padding: 36px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}

.ts-hero::after {
    content: "";
    position: absolute;
    width: 240px;
    height: 240px;
    border: 45px solid rgba(255,255,255,.055);
    border-radius: 50%;
    right: -65px;
    top: -100px;
    pointer-events: none;
}

.ts-hero-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
    color: #a9d5ff;
    margin-bottom: 12px;
}

.ts-hero h1 {
    font-size: clamp(24px, 3vw, 34px);
    color: white;
    margin: 0 0 12px;
    line-height: 1.3;
}

.ts-hero p {
    max-width: 660px;
    color: #dce9f8;
    line-height: 1.7;
    margin: 0;
    font-size: 14px;
}

.ts-hero-status {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    margin-top: 22px;
    padding: 9px 14px;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 8px;
    font-size: 12px;
}

.ts-status-dot {
    width: 8px;
    height: 8px;
    background: #6ee7b7;
    border-radius: 50%;
}

.ts-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 28px;
}

.ts-stat {
    background: #fff;
    border: 1px solid #e4eaf2;
    border-radius: 13px;
    padding: 23px;
    box-shadow: 0 3px 12px rgba(20,40,70,.035);
}

.ts-stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
}

.ts-stat-label {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
}

.ts-stat-icon {
    font-size: 22px;
    background: #edf4ff;
    border-radius: 10px;
    padding: 10px;
}

.ts-stat-value {
    font-size: 29px;
    font-weight: 750;
    color: #152e51;
    line-height: 1.2;
}

.ts-stat-foot {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 8px;
}

.ts-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 18px;
}

.ts-section-head h2 {
    font-size: 20px;
    font-weight: 700;
    margin: 0;
    color: #172b4d;
}

.ts-section-head p {
    font-size: 13px;
    color: #718096;
    margin: 6px 0 0;
}

.ts-section-count {
    background: #eaf2ff;
    color: #285ea5;
    font-size: 12px;
    font-weight: 700;
    border-radius: 7px;
    padding: 7px 12px;
    white-space: nowrap;
}

.ts-modules {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.ts-module {
    display: flex;
    flex-direction: column;
    background: white;
    border: 1px solid #e4eaf2;
    border-radius: 14px;
    padding: 23px;
    text-decoration: none !important;
    color: inherit;
    transition: transform .2s, box-shadow .2s, border-color .2s;
}

.ts-module:hover {
    transform: translateY(-3px);
    border-color: #b6ccef;
    box-shadow: 0 10px 28px rgba(30,65,110,.1);
}

.ts-module-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.ts-module-icon {
    display: grid;
    place-items: center;
    width: 50px;
    height: 50px;
    font-size: 24px;
    border-radius: 12px;
}

.ts-blue { background: #eaf2ff; }
.ts-teal { background: #e4f7f3; }
.ts-purple { background: #f1ebff; }
.ts-orange { background: #fff2e4; }
.ts-green { background: #e7f8ed; }

.ts-module-number {
    font-size: 12px;
    font-weight: 700;
    color: #a0aec0;
}

.ts-module h3 {
    font-size: 16px;
    font-weight: 700;
    color: #1d3556;
    margin: 0 0 9px;
    line-height: 1.5;
}

.ts-module-desc {
    font-size: 13px;
    color: #718096;
    line-height: 1.7;
    margin: 0 0 22px;
    flex: 1;
}

.ts-module-footer {
    border-top: 1px solid #edf0f5;
    padding-top: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.ts-owner {
    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
}

.ts-avatar {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #e9eff7;
    color: #325b87;
    font-weight: 700;
    font-size: 12px;
    flex-shrink: 0;
}

.ts-owner-name {
    font-size: 12px;
    color: #64748b;
}

.ts-module-arrow {
    font-size: 20px;
    color: #3974b9;
}

.ts-workflow {
    margin-top: 28px;
    background: #fff;
    border: 1px solid #e4eaf2;
    border-radius: 14px;
    padding: 25px;
}

.ts-workflow h2 {
    font-size: 18px;
    color: #172b4d;
    margin: 0 0 9px;
}

.ts-workflow > p {
    font-size: 13px;
    color: #718096;
    margin-bottom: 23px;
}

.ts-steps {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
}

.ts-step {
    flex: 1;
    text-align: center;
    min-width: 0;
}

.ts-step-circle {
    display: grid;
    place-items: center;
    width: 37px;
    height: 37px;
    margin: 0 auto 12px;
    border-radius: 50%;
    background: #eaf2ff;
    color: #2563a7;
    font-weight: 700;
    font-size: 13px;
}

.ts-step span {
    font-size: 12px;
    color: #52647c;
    line-height: 1.5;
    display: block;
}

.ts-step-line {
    height: 1px;
    background: #dbe5f0;
    flex: .3;
    margin-top: 18px;
}

.ts-notice {
    margin-top: 22px;
    padding: 16px 19px;
    background: #eff6ff;
    border: 1px solid #d9e9ff;
    border-left: 4px solid #3b82f6;
    border-radius: 9px;
    color: #486586;
    font-size: 13px;
    line-height: 1.7;
}

@media (max-width: 850px) {
    .ts-stats { grid-template-columns: 1fr; }
    .ts-modules { grid-template-columns: 1fr; }
    .ts-hero { padding: 25px; }
}

@media (max-width: 600px) {
    .ts-steps { flex-direction: column; gap: 14px; }
    .ts-step-line { display: none; }
    .ts-step {
        display: flex;
        align-items: center;
        gap: 12px;
        text-align: left;
    }
    .ts-step-circle { margin: 0; flex-shrink: 0; }
    .ts-section-head { align-items: flex-start; }
}
</style>

<div class="ts-dashboard">

    <!-- Banner -->
    <section class="ts-hero">
        <div class="ts-hero-label">
            HỆ THỐNG QUẢN LÝ TUYỂN SINH
        </div>

        <h1>Tổng quan tuyển sinh lớp 10</h1>

        <p>
            Nền tảng quản lý tập trung các nghiệp vụ
            tuyển sinh, phân bổ phòng thi, tổ chức chấm thi
            và phê duyệt kết quả xét tuyển.
        </p>

        <div class="ts-hero-status">
            <span class="ts-status-dot"></span>
            Hệ thống quản lý nội bộ
        </div>
    </section>

    <!-- Thông tin hệ thống -->
    <section class="ts-stats">

        <div class="ts-stat">
            <div class="ts-stat-top">
                <span class="ts-stat-label">Phân hệ nghiệp vụ</span>
                <span class="ts-stat-icon">📁</span>
            </div>
            <div class="ts-stat-value">05</div>
            <div class="ts-stat-foot">
                Các chức năng quản lý tuyển sinh
            </div>
        </div>

        <div class="ts-stat">
            <div class="ts-stat-top">
                <span class="ts-stat-label">Cơ sở dữ liệu</span>
                <span class="ts-stat-icon">🗄️</span>
            </div>
            <div class="ts-stat-value">MySQL</div>
            <div class="ts-stat-foot">
                Lưu trữ và quản lý dữ liệu tập trung
            </div>
        </div>

        <div class="ts-stat">
            <div class="ts-stat-top">
                <span class="ts-stat-label">Nền tảng hệ thống</span>
                <span class="ts-stat-icon">💻</span>
            </div>
            <div class="ts-stat-value">PHP</div>
            <div class="ts-stat-foot">
                Xử lý các nghiệp vụ tuyển sinh
            </div>
        </div>

    </section>

    <!-- Danh sách chức năng -->
    <section>
        <div class="ts-section-head">
            <div>
                <h2>Các phân hệ nghiệp vụ</h2>
                <p>
                    Truy cập và quản lý các chức năng
                    theo quy trình tuyển sinh.
                </p>
            </div>
            <span class="ts-section-count">05 phân hệ</span>
        </div>

        <div class="ts-modules">
            <?php foreach ($modules as $module): ?>

                <a class="ts-module"
                   href="<?= esc($module['url']) ?>">

                    <div class="ts-module-top">
                        <div class="ts-module-icon ts-<?= esc($module['color']) ?>">
                            <?= esc($module['icon']) ?>
                        </div>
                        <span class="ts-module-number">
                            MODULE <?= esc($module['number']) ?>
                        </span>
                    </div>

                    <h3><?= esc($module['title']) ?></h3>

                    <p class="ts-module-desc">
                        <?= esc($module['desc']) ?>
                    </p>

                    <div class="ts-module-footer">
                        <div class="ts-owner">
                            <span class="ts-avatar">
                                <?= esc($module['number']) ?>
                            </span>
                            <span class="ts-owner-name">
                                <?= esc($module['owner']) ?>
                            </span>
                        </div>
                        <span class="ts-module-arrow">→</span>
                    </div>

                </a>

            <?php endforeach; ?>
        </div>
    </section>

    <!-- Quy trình -->
    <section class="ts-workflow">
        <h2>Quy trình nghiệp vụ tuyển sinh</h2>
        <p>
            Tổng quan các giai đoạn xử lý trong hệ thống.
        </p>

        <div class="ts-steps">
            <?php
            $steps = [
                'Phân bổ chỉ tiêu',
                'Phân phòng thi',
                'Phân công chấm thi',
                'Nhập điểm',
                'Phê duyệt xét tuyển'
            ];
            ?>

            <?php foreach ($steps as $i => $step): ?>
                <?php if ($i > 0): ?>
                    <div class="ts-step-line"></div>
                <?php endif; ?>

                <div class="ts-step">
                    <div class="ts-step-circle">
                        <?= $i + 1 ?>
                    </div>
                    <span><?= esc($step) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Thông báo -->
    <div class="ts-notice">
        <strong>Thông tin hệ thống:</strong>
        Dữ liệu nghiệp vụ được quản lý trong MySQL.
        Các thao tác cập nhật và phê duyệt cần được
        kiểm tra quyền truy cập theo vai trò người dùng.
    </div>

</div>

<?php page_end(); ?>
