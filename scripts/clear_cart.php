<?php
ini_set('session.cookie_httponly', 1);
session_start();
unset($_SESSION['cart']);
echo "<h1>Giỏ hàng đã được dọn sạch!</h1>";
echo "<p>Vui lòng quay lại <a href='/'>Trang chủ</a>.</p>";
