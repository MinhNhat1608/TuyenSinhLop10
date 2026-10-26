
<?php

require_once __DIR__ . '/bootstrap.php';

function page_start(string $title, string $active): void
{
    $u = must_login();

    $nav = [
        ['index.php', 'Tổng quan', 'home'],
        ['thai.php', 'Phân bổ chỉ tiêu', 'thai'],
        ['tan.php', 'Phân bổ phòng thi', 'tan'],
        ['nghia.php', 'Phân công chấm thi', 'nghia'],
        ['loc.php', 'Cập nhật điểm thi', 'loc'],
        ['nhat.php', 'Phê duyệt xét tuyển', 'nhat'],
        ['du_lieu.php', 'Nhập dữ liệu gốc', 'data']
    ];

    echo '
    <!doctype html>
    <html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>' . esc($title) . ' | TS10 Một Nhà</title>
        <link rel="stylesheet" href="assets/style.css">
    </head>

    <body>
        <div class="shell">
            <aside class="aside">
                <div class="brand">
                    TS10 <span style="color:#85abff">Một Nhà</span>
                    <small>HỆ THỐNG TUYỂN SINH LỚP 10</small>
                </div>

                <div class="nav-label">CHỨC NĂNG</div>

                <nav class="nav">';

    foreach ($nav as [$href, $name, $key]) {
        echo '
            <a
                class="' . ($active === $key ? 'active' : '') . '"
                href="' . esc($href) . '"
            >
                ' . esc($name) . '
            </a>';
    }

    echo '
                </nav>

       

                
            </aside>

            <main class="main">
                <header class="topbar">
                    <strong>Quản lý tuyển sinh lớp 10</strong>

                    <div class="topright">
                        <span class="avatar">
                            ' . esc($u['ho_ten']) . '
                        </span>

                        <form method="post" action="logout.php">
                            <input
                                type="hidden"
                                name="_csrf"
                                value="' . esc(csrf()) . '"
                            >

                            <button class="btn secondary">
                                Đăng xuất
                            </button>
                        </form>
                    </div>
                </header>

                <section class="content">
                    <div class="headline">
                        <div>
                            <h1>' . esc($title) . '</h1>

                        </div>

                       
                    </div>';

    if ($msg = flash()) {
        echo '
            <div class="note">
                ' . esc($msg) . '
            </div>';
    }
}

function page_end(): void
{
    echo '
                </section>

                <div class="content footer">
  
                </div>
            </main>
        </div>
    </body>
    </html>';
}

function field(
    string $name,
    string $label,
    string $type = 'text',
    bool $needed = true,
    string $extra = ''
): void {
    echo '
    <div class="field">
        <label for="' . esc($name) . '">
            ' . esc($label) . '
        </label>

        <input
            id="' . esc($name) . '"
            name="' . esc($name) . '"
            type="' . esc($type) . '"
            ' . ($needed ? 'required' : '') . '
            ' . $extra . '
        >
    </div>';
}

function select_field(
    string $name,
    string $label,
    string $sql,
    string $value,
    string $text
): void {
    echo '
    <div class="field">
        <label for="' . esc($name) . '">
            ' . esc($label) . '
        </label>

        <select
            id="' . esc($name) . '"
            name="' . esc($name) . '"
            required
        >
            <option value="">-- Chọn --</option>

            ' . options($sql, $value, $text) . '
        </select>
    </div>';
}

function form_open(string $action = ''): void
{
    echo '
    <form method="post" action="' . esc($action) . '">
        <input
            type="hidden"
            name="_csrf"
            value="' . esc(csrf()) . '"
        >

        <div class="controls">';
}

function form_close(string $label): void
{
    echo '
        </div>

        <div class="actions">
            <button class="btn" type="submit">
                ' . esc($label) . '
            </button>
        </div>
    </form>';
}

function table_rows(array $rows, array $headers): void
{
    echo '
    <div class="tablewrap">
        <table>
            <thead>
                <tr>';

    foreach ($headers as $label) {
        echo '
                    <th>' . esc($label) . '</th>';
    }

    echo '
                </tr>
            </thead>

            <tbody>';

    foreach ($rows as $r) {
        echo '
                <tr>';

        foreach ($r as $v) {
            echo '
                    <td>' . esc($v) . '</td>';
        }

        echo '
                </tr>';
    }

    if (!$rows) {
        echo '
                <tr>
                    <td
                        class="empty"
                        colspan="' . count($headers) . '"
                    >
                        Chưa có dữ liệu trong database.
                    </td>
                </tr>';
    }

    echo '
            </tbody>
        </table>
    </div>';
}
