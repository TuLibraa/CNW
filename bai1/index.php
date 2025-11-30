<?php
// index.php - Bài 1 (PHP) - Danh sách hoa với CRUD lưu vào data.json

// cấu hình
$dataFile = __DIR__ . '/data.json';
$imageFolder = 'hoadep'; // tương đối so với index.php

// đảm bảo file data.json tồn tại
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    // set permisssion nếu cần (bỏ comment nếu cần)
//    @chmod($dataFile, 0666);
}

// hàm đọc/ghi
function loadFlowers($file) {
    $json = @file_get_contents($file);
    if ($json === false) return [];
    $data = json_decode($json, true);
    if (!is_array($data)) return [];
    return $data;
}

function saveFlowers($file, $data) {
    // ghi atomically: ghi vào temp rồi rename
    $tmp = $file . '.tmp';
    $ok = file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($ok === false) {
        return false;
    }
    return rename($tmp, $file);
}

$flowers = loadFlowers($dataFile);
$error = '';
$success = '';

// xử lý POST (CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        $desc = trim((string)($_POST['desc'] ?? ''));
        $img  = trim((string)($_POST['img'] ?? ''));

        if ($name === '' || $desc === '' || $img === '') {
            $error = 'Vui lòng nhập đầy đủ tên, mô tả và tên file ảnh.';
        } else {
            $flowers[] = ['name' => $name, 'desc' => $desc, 'img' => $img];
            if (saveFlowers($dataFile, $flowers)) {
                // redirect để tránh gửi lại form khi reload
                header('Location: ' . basename(__FILE__));
                exit;
            } else $error = 'Không lưu được dữ liệu (quyền ghi?).';
        }
    }

    else if ($action === 'delete') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : -1;
        if (isset($flowers[$id])) {
            array_splice($flowers, $id, 1);
            if (saveFlowers($dataFile, $flowers)) {
                header('Location: ' . basename(__FILE__));
                exit;
            } else $error = 'Không lưu được dữ liệu sau khi xóa.';
        } else {
            $error = 'Mục cần xóa không tồn tại.';
        }
    }

    else if ($action === 'edit') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : -1;
        if (!isset($flowers[$id])) {
            $error = 'Mục cần sửa không tồn tại.';
        } else {
            $name = trim((string)($_POST['name'] ?? ''));
            $desc = trim((string)($_POST['desc'] ?? ''));
            $img  = trim((string)($_POST['img'] ?? ''));
            if ($name === '' || $desc === '' || $img === '') {
                $error = 'Vui lòng nhập đầy đủ tên, mô tả và tên file ảnh.';
            } else {
                $flowers[$id] = ['name' => $name, 'desc' => $desc, 'img' => $img];
                if (saveFlowers($dataFile, $flowers)) {
                    header('Location: ' . basename(__FILE__));
                    exit;
                } else $error = 'Không lưu được dữ liệu sau khi sửa.';
            }
        }
    }
    else {
        $error = 'Hành động không hợp lệ.';
    }
}

// reload data (nếu cần)
$flowers = loadFlowers($dataFile);
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Bài 1 - Danh sách hoa (PHP)</title>
<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .flowers { display: grid; grid-template-columns: repeat(auto-fill,minmax(200px,1fr)); gap: 15px; }
    .item { border:1px solid #ddd; padding:10px; border-radius:8px; background:#fff; }
    .item img{ width:100%; height:150px; object-fit:cover; border-radius:6px; }
    table{ width:100%; border-collapse:collapse; margin-top:12px;}
    th,td{ border:1px solid #ccc; padding:8px; text-align:left; }
    th{ background:#f4f4f4; }
    form.inline{ display:inline-block; margin:0; }
    .msg{ padding:10px; margin-bottom:12px; border-radius:6px; }
    .err{ background:#ffecec; border:1px solid #ffbdbd; color:#900; }
    .ok{ background:#ecffec; border:1px solid #bdf0bd; color:#060; }
    input[type=text]{ padding:6px; }
    button{ padding:6px 10px; cursor:pointer; }
</style>
</head>
<body>

<h2>Chế độ khách (Guest) — Danh sách hoa</h2>

<div class="flowers">
    <?php if (count($flowers) === 0): ?>
        <div>Hiện chưa có hoa nào. Vui lòng chuyển sang phần Admin để thêm.</div>
    <?php endif; ?>
    <?php foreach ($flowers as $f): ?>
        <div class="item">
            <?php
                $imgPath = htmlspecialchars($imageFolder . '/' . $f['img']);
                // kiểm tra file ảnh tồn tại, nếu không hiện placeholder
                if (file_exists(__DIR__ . '/' . $imageFolder . '/' . $f['img'])) {
                    echo '<img src="' . $imgPath . '" alt="">';
                } else {
                    echo '<div style="width:100%;height:150px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#888;border-radius:6px">Chưa có ảnh</div>';
                }
            ?>
            <h4><?= htmlspecialchars($f['name']) ?></h4>
            <p><?= htmlspecialchars($f['desc']) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<hr>

<h2>Chế độ Admin (CRUD)</h2>

<?php if ($error): ?>
    <div class="msg err"><?= htmlspecialchars($error) ?></div>
<?php elseif ($success): ?>
    <div class="msg ok"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<h3>Thêm hoa mới</h3>
<form method="post" style="margin-bottom:12px;">
    <input type="hidden" name="action" value="add">
    Tên: <input type="text" name="name" required>
    Mô tả: <input type="text" name="desc" required>
    Ảnh (tên file trong <?= htmlspecialchars($imageFolder) ?>): <input type="text" name="img" required placeholder="vd: haiduong.jpg">
    <button type="submit">Thêm</button>
</form>

<h3>Danh sách (Admin)</h3>
<table>
    <thead>
        <tr><th>#</th><th>Tên</th><th>Mô tả</th><th>Ảnh</th><th>Hành động</th></tr>
    </thead>
    <tbody>
    <?php foreach ($flowers as $i => $f): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($f['name']) ?></td>
            <td><?= htmlspecialchars($f['desc']) ?></td>
            <td>
                <?php if (file_exists(__DIR__ . '/' . $imageFolder . '/' . $f['img'])): ?>
                    <img src="<?= htmlspecialchars($imageFolder . '/' . $f['img']) ?>" alt="" style="width:80px;">
                <?php else: ?>
                    <span style="color:#888">Không tìm thấy ảnh</span>
                <?php endif; ?>
            </td>
            <td>
                <!-- Xóa -->
                <form method="post" class="inline" onsubmit="return confirm('Bạn có chắc muốn xóa?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $i ?>">
                    <button type="submit" style="background:#d9534f;color:white;border:none;">Xóa</button>
                </form>

                <!-- Sửa: hiện form inline (POST) -->
                <form method="post" class="inline" style="margin-left:8px;">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= $i ?>">
                    <input type="text" name="name" value="<?= htmlspecialchars($f['name']) ?>" required>
                    <input type="text" name="desc" value="<?= htmlspecialchars($f['desc']) ?>" required>
                    <input type="text" name="img" value="<?= htmlspecialchars($f['img']) ?>" required>
                    <button type="submit">Cập nhật</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
