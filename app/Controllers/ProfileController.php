<?php
namespace App\Controllers;
use App\Core\Controller;

class ProfileController extends Controller
{
    public function index()
    {
        $this->view('pages/profile');
    }

    public function changePassword()
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1); session_start();
        }

        if (empty($_SESSION['user'])) {
            header('Location: /auth/login.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_once __DIR__ . '/../../config/db.php';
            require_once __DIR__ . '/../../includes/helpers.php';
            verifyCsrfToken();

            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $_SESSION['profile_error'] = 'Vui lòng nhập đầy đủ thông tin.';
            } elseif ($newPassword !== $confirmPassword) {
                $_SESSION['profile_error'] = 'Mật khẩu mới không khớp.';
            } elseif (strlen($newPassword) < 6) {
                $_SESSION['profile_error'] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
            } else {
                $pdo = \Database::getConnection();
                $userId = (int) $_SESSION['user']['id'];
                
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
                $stmt->execute([':id' => $userId]);
                $user = $stmt->fetch();

                if ($user && password_verify($currentPassword, $user['password_hash'])) {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $updateStmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
                    $updateStmt->execute([':hash' => $newHash, ':id' => $userId]);
                    
                    $_SESSION['profile_success'] = 'Đổi mật khẩu thành công.';
                } else {
                    $_SESSION['profile_error'] = 'Mật khẩu hiện tại không đúng.';
                }
            }
        }
        
        header('Location: /profile');
        exit;
    }
}
