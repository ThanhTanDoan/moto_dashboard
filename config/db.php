<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

$db_host = '127.0.0.1';
$db_name = 'moto_dashboard';
$db_user = 'root';
$db_pass = ''; // Laragon mặc định mật khẩu root là rỗng

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Lỗi kết nối cơ sở dữ liệu MySQL: " . htmlspecialchars($e->getMessage()));
}

// Tự động tạo tài khoản quản trị mặc định nếu bảng users đang rỗng
$userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($userCount === 0) {
    $defaultHash = password_hash('123456', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, 1)"
    );
    $stmt->execute(['admin@gmail.com', $defaultHash, 'Quản trị viên Hệ thống', 'Admin']);
}

// Hàm lấy cấu hình hệ thống
function getSystemSettings(PDO $pdo) {
    $row = $pdo->query("SELECT * FROM system_settings ORDER BY id ASC LIMIT 1")->fetch();
    if (!$row) {
        $pdo->exec("INSERT INTO system_settings (id) VALUES (1)");
        $row = $pdo->query("SELECT * FROM system_settings ORDER BY id ASC LIMIT 1")->fetch();
    }
    return $row;
}