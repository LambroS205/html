<?php
ini_set('session.cookie_httponly', 1);
session_start();
unset($_SESSION['cart']);
echo "<h1>Gi? hàng dã du?c d?n s?ch!</h1>";
echo "<p>Vui lòng quay l?i <a href='/'>Trang ch?</a>.</p>";
