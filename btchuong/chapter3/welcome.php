<?php
// TODO 1: Khởi động session
session_start();

// TODO 2: Kiểm tra người dùng đã đăng nhập chưa
if (isset($_SESSION['username'])) {

    // TODO 3: Lấy username
    $loggedInUser = $_SESSION['username'];

    // TODO 4: In lời chào
    echo "<h1>Chào mừng trở lại, $loggedInUser!</h1>";
    echo "<p>Bạn đã đăng nhập thành công.</p>";

    // TODO 5: Link đăng xuất tạm thời
    echo '<a href="login.html">Đăng xuất (Tạm thời)</a>';

} else {

    // TODO 6: Chưa đăng nhập → chuyển về login
    header("Location: login.html");
    exit;
}
?>
