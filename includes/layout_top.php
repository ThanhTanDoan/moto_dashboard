<?php
$user = currentUser();
$settings = getSystemSettings($pdo);
$activePage =$activePage ?? 'dashboard';

$menuItems = [
    'dashboard'   => ['href' => 'index.php',       'icon' => 'fa-chart-line',        'label' => 'Tổng quan'],
    'camera'      => ['href' => 'camera.php',      'icon' => 'fa-video',             'label' => 'Camera trực tiếp'],
    'recognition' => ['href' => 'recognition.php', 'icon' => 'fa-motorcycle',        'label' => 'Nhận diện & Xác minh'],
    'history'     => ['href' => 'history.php',     'icon' => 'fa-clock-rotate-left', 'label' => 'Lịch sử ra/vào'],
    'statistics'  => ['href' => 'statistics.php',  'icon' => 'fa-chart-pie',         'label' => 'Thống kê'],
    'settings'    => ['href' => 'settings.php',    'icon' => 'fa-gear',              'label' => 'Cài đặt hệ thống'],
];
?>
<!DOCTYPE html>
<html lang="vi" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> - <?= e($settings['system_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.7"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        darkbg: '#0b0f19',
                        cardbg: '#131b2e',
                        sidebarbg: '#0f1626',
                        borderdark: '#1e293b'
                    }
                }
            }
        };
    </script>
    <style>
        body { background-color: #0b0f19; color: #f1f5f9; font-family: 'Inter', sans-serif; }
        ::-webkit-scrollbar { width: 7px; height: 7px; }
        ::-webkit-scrollbar-track { background: #0b0f19; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #334155; }
        @keyframes pulse-border {
            0%, 100% { box-shadow: 0 0 0 0 rgba(244, 63, 94, 0.5); }
            50% { box-shadow: 0 0 0 6px rgba(244, 63, 94, 0); }
        }
        .alert-pulse { animation: pulse-border 1.8s infinite; }
    </style>
</head>
<body class="bg-darkbg text-slate-100 antialiased">
<div class="min-h-screen flex">
    <!-- Sidebar -->
    <aside class="w-64 bg-sidebarbg border-r border-borderdark text-slate-300 flex flex-col shrink-0 hidden md:flex">
        <div class="p-5 border-b border-borderdark flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-500 to-cyan-400 flex items-center justify-center text-slate-950 text-lg shadow-lg shadow-sky-500/25">
                <i class="fa-solid fa-motorcycle"></i>
            </div>
            <div class="overflow-hidden">
                <div class="font-bold text-white leading-tight truncate tracking-wide">MotoGuard AI</div>
                <div class="text-xs text-sky-400 truncate">Giám sát xe gắn máy</div>
            </div>
        </div>

        <nav class="flex-1 p-3 space-y-1">
            <?php foreach ($menuItems as$key => $item):$isActive = ($activePage ===$key);
                $cls =$isActive
                    ? 'bg-sky-500/15 text-sky-400 border border-sky-500/30 shadow-sm'
                    : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-100 border border-transparent';
            ?>
                <a href="<?= e($item['href']) ?>" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition <?= $cls ?>">
                    <i class="fa-solid <?= e($item['icon']) ?> w-5 text-center <?=$isActive ? 'text-sky-400' : 'text-slate-500' ?>"></i>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="p-4 border-t border-borderdark bg-[#0a0f1d]">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-full bg-sky-500/20 border border-sky-500/30 text-sky-300 flex items-center justify-center font-bold">
                    <?= e(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?>
                </div>
                <div class="overflow-hidden">
                    <div class="text-sm font-semibold text-white truncate"><?= e($user['full_name']) ?></div>
                    <div class="text-xs text-slate-400 truncate"><?= e($user['role']) ?></div>
                </div>
            </div>
            <a href="logout.php" class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-lg bg-slate-800/80 hover:bg-rose-600/90 border border-slate-700 text-slate-200 hover:text-white text-xs font-medium transition">
                <i class="fa-solid fa-right-from-bracket"></i> Đăng xuất
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Top Header -->
        <header class="bg-sidebarbg/95 backdrop-blur border-b border-borderdark px-6 py-3.5 flex items-center justify-between sticky top-0 z-20">
            <div>
                <h1 class="text-lg font-bold text-white"><?= e($pageTitle ?? 'Dashboard') ?></h1>
                <p class="text-xs text-slate-400"><?= e($settings['school_name']) ?></p>
            </div>
            <div class="flex items-center gap-3">
                <div class="hidden sm:flex items-center gap-2 text-xs font-medium bg-emerald-950/60 text-emerald-300 px-3 py-1.5 rounded-full border border-emerald-700/60">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>API AI sẵn sàng</span>
                </div>
                <div id="liveClock" class="text-xs font-mono bg-slate-900 text-sky-300 px-3 py-1.5 rounded-lg border border-slate-800">
                    --:--:--
                </div>
            </div>
        </header>

        <main class="p-6 flex-1">