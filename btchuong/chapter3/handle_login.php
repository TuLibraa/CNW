<?php

// TODO 1: Khởi động session
session_start();

// TODO 2: Kiểm tra đã gửi form chưa
if (isset($_POST['username']) && isset($_POST['password'])) {

    // TODO 3: Lấy dữ liệu
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // TODO 4: Kiểm tra login
    if ($user == 'admin' && $pass == '123') {

        // TODO 5: Lưu SESSION
        $_SESSION['username'] = $user;

        // TODO 6: Chuyển sang trang welcome
        header('Location: welcome.php');
        exit;
    } else {
        // Sai → quay lại form
        header('Location: login.html?error=1');
        exit;
    }

} else {
    // TODO 7: Truy cập trực tiếp → đẩy về login
    header('Location: login.html');
    exit;
}
?>
