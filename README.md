# BestBuy Store (WEMP Stack)

Dự án eCommerce bán hàng điện tử chính hãng (Laptop, Điện thoại, TV, Phụ kiện) với đầy đủ tính năng Giỏ hàng, Checkout, Mã giảm giá, Đánh giá, và Quản trị viên (Admin Panel).

Dự án đã được tối ưu hóa toàn diện, bao gồm:
- **Bảo mật (Security)**: Chống SQL Injection (PDO Prepared Statements), XSS (`htmlspecialchars`), và CSRF Protection cho các Form. Thiết lập Session an toàn (`HttpOnly`).
- **Tối ưu hóa (Optimization)**: Index Database cho các trường hay truy vấn, Nginx Cache tĩnh, Frontend gọn nhẹ.

## 🛠 Yêu cầu Hệ thống (WEMP Stack)
- **Hệ điều hành**: Windows 10/11
- **Web Server**: Nginx
- **Database**: MariaDB (hoặc MySQL)
- **PHP**: PHP 8.0+

## 🚀 Hướng dẫn Cài đặt & Khởi động Server

### Bước 1: Khởi động WEMP
Do đây là môi trường Windows, bạn có thể sử dụng các phần mềm đóng gói như XAMPP (Apache có thể thay bằng Nginx) hoặc WNMP (Windows Nginx MySQL PHP).
*Nếu dùng lệnh thủ công thông thường:*
1. Khởi động MariaDB (MySQL):
   ```cmd
   net start mariadb
   # Hoặc mysql: net start mysql
   ```
2. Khởi động PHP-CGI (FastCGI):
   ```cmd
   RunHiddenConsole.exe C:\php\php-cgi.exe -b 127.0.0.1:9000
   ```
3. Khởi động Nginx:
   ```cmd
   cd C:\nginx
   start nginx
   ```

### Bước 2: Cấu hình Database
Sửa thông tin kết nối DB trong file `config/app.php` (hoặc `config/db.php`):
```php
'database' => [
    'host' => '127.0.0.1',
    'dbname' => 'bestbuy_store',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4'
]
```

### Bước 3: Import Database (SQL Migrations)
Bạn phải chạy các file SQL theo đúng thứ tự để thiết lập schema và các bản nâng cấp:
Bạn có thể chạy tự động thông qua script:
```cmd
php database/run_migration_script.php
```
*Hoặc import thủ công qua phpMyAdmin/DBeaver theo thứ tự sau:*
1. `database/schema.sql` (Cấu trúc gốc)
2. `database/migration_step2.sql` (Seed Data)
3. `database/migration_step3.sql` (Coupons & Cart UI)
4. `database/migration_step5.sql` (Wishlist)
5. `database/migration_step6_reviews.sql` (Review & Rating)
6. `database/migration_step7_variants.sql` (Product Variants)
7. `database/migration_step8_audit.sql` (Deep Audit & Indexing)

### Bước 4: Tối ưu Nginx
Mở file `C:\nginx\conf\nginx.conf`, ở block `server { ... }`, hãy include file tối ưu Cache:
```nginx
include D:/Projects/Wemp/nginx/html/nginx_asset_cache.conf;
```
Sau đó reload lại Nginx:
```cmd
nginx -s reload
```

## 📊 Monitoring / Health Check
Đã tích hợp API Healthcheck cho hệ thống giám sát (Zabbix, Prometheus, Datadog...):
- **URL**: `http://localhost/healthcheck.php`
- Trả về JSON bao gồm tình trạng: Kết nối Database, Phiên bản PHP, và Dung lượng ổ đĩa khả dụng.
