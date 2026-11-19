<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ tài khoản và mật khẩu.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Tài khoản hoặc mật khẩu không chính xác.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - MotoGuard AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 text-white text-center">
            <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-3 text-2xl">
                <i class="fa-solid fa-motorcycle"></i>
            </div>
            <h1 class="text-xl font-bold">MotoGuard AI Dashboard</h1>
            <p class="text-xs text-blue-100 mt-1">Hệ thống giám sát &amp; nhận diện xe gắn máy</p>
        </div>

        <form method="POST" class="p-6 space-y-4">
            <?php if ($error): ?>
                <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email đăng nhập</label>
                <input type="email" name="username" required value="<?= e($_POST['username'] ?? 'admin@gmail.com') ?>"
                       class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm"
                       placeholder="admin@gmail.com">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Mật khẩu</label>
                <input type="password" name="password" required
                       class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm"
                       placeholder="Nhập mật khẩu (mặc định: 123456)">
            </div>

            <button type="submit" class="w-full py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm transition shadow">
                <i class="fa-solid fa-right-to-bracket mr-1"></i> Đăng nhập
            </button>

            <div class="text-center text-xs text-slate-500 pt-2">
                Chưa có tài khoản? <a href="register.php" class="text-blue-600 font-semibold hover:underline">Đăng ký ngay</a>
            </div>
            <div class="text-center text-[11px] text-slate-400 bg-slate-50 rounded-lg p-2">
                Tài khoản mặc định: <b>admin@gmail.com</b> / Mật khẩu: <b>123456</b>
            </div>
        </form>
    </div>
</body>
</html>