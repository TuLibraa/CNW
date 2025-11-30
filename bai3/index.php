<?php
// index.php
// Trang đọc CSV, hiển thị, sinh SQL INSERT và (tùy chọn) import vào MySQL bằng PDO

// --- CẤU HÌNH ---
$defaultCsvFile = __DIR__ . '/65HTTT_Danh_sach_diem_danh.csv'; // file mặc định (nếu có)

// --- HÀM HỖ TRỢ ---
function read_csv_file($path, $delimiter = ',') {
    if (!file_exists($path)) return ['error' => "File không tồn tại: $path"];
    $rows = [];
    // cố gắng xác định encoding UTF-8, nếu không, cố chuyển
    $content = file_get_contents($path);
    if ($content === false) return ['error' => "Không thể đọc file"];
    // normalize newlines
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $tmp = tmpfile();
    fwrite($tmp, $content);
    fseek($tmp, 0);
    $handle = $tmp;
    // sử dụng fgetcsv để parsing an toàn CSV (hỗ trợ dấu ngoặc kép)
    $first = true;
    $headers = [];
    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        // skip empty lines
        $allEmpty = true;
        foreach ($data as $c) { if (trim($c) !== '') { $allEmpty = false; break; } }
        if ($allEmpty) continue;

        if ($first) {
            $headers = array_map('trim', $data);
            $first = false;
        } else {
            // pad to headers length
            if (count($data) < count($headers)) {
                $data = array_merge($data, array_fill(0, count($headers) - count($data), ''));
            }
            $row = [];
            foreach ($headers as $i => $h) {
                $row[$h] = isset($data[$i]) ? $data[$i] : '';
            }
            $rows[] = $row;
        }
    }
    fclose($handle);
    return ['headers' => $headers, 'rows' => $rows];
}

function generate_insert_sql($table, $headers, $rows) {
    $sqls = [];
    foreach ($rows as $r) {
        $cols = array_map(function($h){ return "`".str_replace("`","``",$h)."`"; }, $headers);
        $vals = array_map(function($h) use ($r){
            $v = isset($r[$h]) ? $r[$h] : '';
            // escape single quotes
            $v = str_replace("'", "''", $v);
            return "'" . $v . "'";
        }, $headers);
        $sqls[] = "INSERT INTO `$table` (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $vals) . ");";
    }
    return implode("\n", $sqls);
}

// --- XỬ LÝ UPLOAD FILE (nếu user gửi file mới) ---
$csvPath = null;
$readResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvfile']) && $_FILES['csvfile']['error'] === UPLOAD_ERR_OK) {
    $tmpName = $_FILES['csvfile']['tmp_name'];
    // move to a temp location in project (optional)
    $dest = __DIR__ . '/uploaded_' . time() . '_' . basename($_FILES['csvfile']['name']);
    move_uploaded_file($tmpName, $dest);
    $csvPath = $dest;
}

// nếu không upload, dùng file mặc định nếu tồn tại
if ($csvPath === null && file_exists($defaultCsvFile)) {
    $csvPath = $defaultCsvFile;
}

// nếu có đường dẫn CSV trong query ?file=... (an toàn chỉ nội bộ)
if ($csvPath === null && isset($_GET['file'])) {
    $candidate = realpath(__DIR__ . '/' . basename($_GET['file']));
    if ($candidate && strpos($candidate, realpath(__DIR__)) === 0) {
        $csvPath = $candidate;
    }
}

// đọc CSV nếu có
if ($csvPath) {
    $readResult = read_csv_file($csvPath);
}

// --- XỬ LÝ NHẬP VÀO DB (nếu user submit DB form) ---
$dbImportMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_db') {
    // Lấy dữ liệu form
    $dbHost = $_POST['db_host'] ?? '127.0.0.1';
    $dbName = $_POST['db_name'] ?? '';
    $dbUser = $_POST['db_user'] ?? '';
    $dbPass = $_POST['db_pass'] ?? '';
    $dbTable = $_POST['db_table'] ?? 'accounts';

    // yêu cầu CSV đã được đọc
    if (!$readResult || !isset($readResult['headers'])) {
        $dbImportMessage = "Chưa có file CSV để import.";
    } else {
        try {
            $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $headers = $readResult['headers'];
            $rows = $readResult['rows'];

            // tạo prepared statement
            $cols = array_map(function($h){ return "`".str_replace("`","``",$h)."`"; }, $headers);
            $placeholders = implode(',', array_fill(0, count($headers), '?'));
            $sql = "INSERT INTO `$dbTable` (" . implode(',', $cols) . ") VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);

            $count = 0;
            foreach ($rows as $r) {
                $params = [];
                foreach ($headers as $h) {
                    $params[] = $r[$h];
                }
                $stmt->execute($params);
                $count++;
            }
            $dbImportMessage = "Import thành công $count bản ghi vào $dbTable.";
        } catch (PDOException $ex) {
            $dbImportMessage = "Lỗi khi kết nối/insert: " . htmlspecialchars($ex->getMessage());
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Hiển thị CSV tài khoản</title>
<style>
    body{font-family: Arial; margin:20px;}
    table{border-collapse:collapse; width:100%; margin-top:12px;}
    th, td{border:1px solid #ccc; padding:8px; text-align:left;}
    th{background:#f3f3f3;}
    .controls{margin:10px 0;}
    .sqlbox{white-space:pre; background:#f8f8f8; padding:10px; border:1px solid #ddd; max-height:300px; overflow:auto;}
    .msg{margin-top:12px; padding:8px; border-radius:4px;}
    .success{background:#e6ffe6; border:1px solid #b7e0b7;}
    .error{background:#ffe6e6; border:1px solid #e0b7b7;}
</style>
</head>
<body>

<h2>Hiển thị tệp CSV danh sách tài khoản</h2>

<div class="controls">
    <form method="post" enctype="multipart/form-data" style="display:inline-block;">
        <label>Chọn file CSV: <input type="file" name="csvfile" accept=".csv,text/csv"></label>
        <button type="submit">Tải lên & Hiển thị</button>
    </form>

    <?php if ($csvPath): ?>
        <span style="margin-left:20px;">Đang dùng file: <strong><?= htmlspecialchars(basename($csvPath)) ?></strong></span>
    <?php endif; ?>
</div>

<?php
if (!$readResult) {
    echo "<div class='msg error'>Chưa có file CSV trong thư mục hoặc bạn chưa upload. Đặt file 'accounts.csv' vào cùng thư mục hoặc upload ở trên.</div>";
} else if (isset($readResult['error'])) {
    echo "<div class='msg error'>Lỗi đọc CSV: " . htmlspecialchars($readResult['error']) . "</div>";
} else {
    $headers = $readResult['headers'];
    $rows = $readResult['rows'];
    // hiển thị bảng
    echo "<div>Tổng bản ghi: <strong>".count($rows)."</strong></div>";
    if (count($rows) === 0) {
        echo "<div class='msg'>File có header nhưng không có dữ liệu.</div>";
    } else {
        echo "<table><thead><tr>";
        foreach ($headers as $h) {
            echo "<th>" . htmlspecialchars($h) . "</th>";
        }
        echo "</tr></thead><tbody>";
        foreach ($rows as $r) {
            echo "<tr>";
            foreach ($headers as $h) {
                echo "<td>" . htmlspecialchars($r[$h]) . "</td>";
            }
            echo "</tr>";
        }
        echo "</tbody></table>";
    }

    // form sinh SQL
    $defaultTable = 'accounts';
    $sqlPreview = generate_insert_sql($defaultTable, $headers, $rows);
    ?>
    <h3>Sinh SQL INSERT (preview)</h3>
    <form method="get" style="margin-bottom:8px;">
        <label>Tên bảng: <input type="text" name="table" value="<?= htmlspecialchars($defaultTable) ?>"></label>
        <button type="button" onclick="document.getElementById('sqlbox').style.display='block'">Hiển thị SQL</button>
    </form>
    <div id="sqlbox" class="sqlbox" style="display:none;"><?= htmlspecialchars(generate_insert_sql(isset($_GET['table'])?$_GET['table']:$defaultTable, $headers, $rows)) ?></div>

    <h3>Nhập trực tiếp vào MySQL (tùy chọn)</h3>
    <div class="small">*Lưu ý: nếu muốn sử dụng chức năng này, hãy đảm bảo bạn nhập đúng thông tin DB và bảng đã tồn tại với các cột tương ứng.</div>
    <form method="post" style="margin-top:8px;">
        <input type="hidden" name="action" value="import_db">
        <label>Host: <input type="text" name="db_host" value="127.0.0.1"></label>
        <label> Database: <input type="text" name="db_name" value=""></label>
        <label> User: <input type="text" name="db_user" value=""></label>
        <label> Password: <input type="password" name="db_pass" value=""></label>
        <label> Table: <input type="text" name="db_table" value="accounts"></label>
        <div style="margin-top:8px;">
            <button type="submit">Import vào DB</button>
        </div>
    </form>

    <?php
    if ($dbImportMessage !== '') {
        $cls = strpos($dbImportMessage, 'Lỗi') === 0 || strpos($dbImportMessage, 'Không') === 0 ? 'error' : 'success';
        echo "<div class='msg $cls'>" . htmlspecialchars($dbImportMessage) . "</div>";
    }
}
?>

</body>
</html>
