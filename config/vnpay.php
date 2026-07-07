<?php
/**
 * Cấu hình VNPay (Sandbox Demo)
 * 
 * Để test, bạn có thể dùng thẻ test của VNPay Sandbox:
 * Ngân hàng: NCB
 * Số thẻ: 9704198526191432198
 * Tên chủ thẻ: NGUYEN VAN A
 * Ngày phát hành: 07/15
 * Mật khẩu OTP: 123456
 */

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Cấu hình từ VNPay Sandbox (Bạn có thể thay đổi bằng thông tin thật)
define('VNP_TMN_CODE', '2YOM0H24'); // TmnCode thử nghiệm
define('VNP_HASH_SECRET', 'MZWQOOWXWKSOTIIDRZZSOGBPRYHKLDRG'); // Secret Key thử nghiệm
define('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');

// URL Return sau khi thanh toán xong
// Ở môi trường dev (localhost), cần chỉ định port/đường dẫn chính xác.
// Domain này phụ thuộc vào cách bạn chạy server. VD: http://localhost/vnpay_return.php
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'];
define('VNP_RETURN_URL', $protocol . $domainName . '/vnpay_return.php');
