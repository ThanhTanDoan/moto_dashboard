<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($fullName === '' || $username === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    } elseif (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không đúng định dạng.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } elseif ($password !== $confirm) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            $error = 'Email này đã được đăng ký.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, is_active) VALUES (?, ?, ?, 'Nhân viên', 1)");
            $stmt->execute([$username, $hash, $fullName]);
            $success = 'Đăng ký thành công! Bạn có thể đăng nhập ngay.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - MotoGuard AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-6">
        <h1 class="text-xl font-bold text-slate-800 text-center mb-1">Tạo tài khoản nhân viên</h1>
        <p class="text-xs text-slate-500 text-center mb-5">Hệ thống giám sát xe gắn máy MotoGuard AI</p>

        <?php if ($error): ?>
            <div class="p-3 mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="p-3 mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">
                <?= e($success) ?> <a href="login.php" class="font-bold underline">Đăng nhập</a>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-3.5">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Họ và tên</label>
                <input type="text" name="full_name" required class="w-full px-3.5 py-2 rounded-lg border border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" name="username" required class="w-full px-3.5 py-2 rounded-lg border border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Mật khẩu (tối thiểu 6 ký tự)</label>
                <input type="password" name="password" required class="w-full px-3.5 py-2 rounded-lg border border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Xác nhận mật khẩu</label>
                <input type="password" name="confirm_password" required class="w-full px-3.5 py-2 rounded-lg border border-slate-300 text-sm">
            </div>
            <button type="submit" class="w-full py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm">
                Đăng ký tài khoản
            </button>
            <div class="text-center text-xs text-slate-500 pt-2">
                Đã có tài khoản? <a href="login.php" class="text-blue-600 font-semibold hover:underline">Quay lại đăng nhập</a>
            </div>
        </form>
    </div>
</body>
</html>