<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$flash = '';

// Xử lý xóa toàn bộ dữ liệu nhận diện (phục vụ reset khi demo/kiểm thử)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_all') {
    $deleted = (int)$pdo->exec("DELETE FROM detections");
    $flash = "Đã xóa toàn bộ {$deleted} bản ghi nhận diện trong cơ sở dữ liệu.";
}

// 1. Bộ lọc thời gian & cổng
$fromDate = $_GET['from'] ?? date('Y-m-d');
$toDate   = $_GET['to'] ?? $fromDate;
$cameraId = $_GET['camera_id'] ?? '';

if ($toDate < $fromDate) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}

$conds  = ["DATE(detection_time) >= ?", "DATE(detection_time) <= ?"];
$params = [$fromDate, $toDate];

if ($cameraId === '1' || $cameraId === '2') {
    $conds[]  = "camera_id = ?";
    $params[] = (int)$cameraId;
}

$stmt = $pdo->prepare("SELECT * FROM detections WHERE " . implode(' AND ', $conds) . " ORDER BY detection_time ASC");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// 2. Tổng hợp chỉ số
$total       = count($rows);
$studentCnt  = 0;
$lecturerCnt = 0;
$guestCnt    = 0;
$helmetOk    = 0;
$noHelmet    = 0;
$enterCnt    = 0;
$exitCnt     = 0;
$cam1Cnt     = 0;
$cam2Cnt     = 0;

$hourlyStudent  = array_fill(0, 24, 0);
$hourlyLecturer = array_fill(0, 24, 0);
$hourlyGuest    = array_fill(0, 24, 0);
$hourlyTotal    = array_fill(0, 24, 0);

$morningPeak   = 0; // 07:00 - 07:59
$noonPeak      = 0; // 11:00 - 11:59
$afternoonPeak = 0; // 17:00 - 17:59

foreach ($rows as $r) {
    if ($r['vehicle_group'] === 'Xe Giảng viên') $lecturerCnt++;
    elseif ($r['vehicle_group'] === 'Xe Sinh viên') $studentCnt++;
    else $guestCnt++;

    if ((int)$r['has_helmet'] === 1) $helmetOk++;
    else $noHelmet++;

    if ($r['direction'] === 'Vào') $enterCnt++;
    else $exitCnt++;

    if ((int)$r['camera_id'] === 1) $cam1Cnt++;
    else $cam2Cnt++;

    $h = (int)date('G', strtotime($r['detection_time']));
    $hourlyTotal[$h]++;
    if ($r['vehicle_group'] === 'Xe Giảng viên') $hourlyLecturer[$h]++;
    elseif ($r['vehicle_group'] === 'Xe Sinh viên') $hourlyStudent[$h]++;
    else $hourlyGuest[$h]++;

    if ($h === 7)  $morningPeak++;
    if ($h === 11) $noonPeak++;
    if ($h === 17) $afternoonPeak++;
}

$helmetRate = $total > 0 ? round(($helmetOk / $total) * 100, 1) : 100;
$cam1Pct    = $total > 0 ? round(($cam1Cnt / $total) * 100, 1) : 0;
$cam2Pct    = $total > 0 ? round(($cam2Cnt / $total) * 100, 1) : 0;

// Tìm giờ cao điểm thực tế
$peakHour = null;
$peakHourCount = 0;
foreach ($hourlyTotal as $h => $cnt) {
    if ($cnt > $peakHourCount) {
        $peakHourCount = $cnt;
        $peakHour = $h;
    }
}

$hourlyLabels = [];
for ($i = 0; $i < 24; $i++) $hourlyLabels[] = sprintf('%02d:00', $i);

$pageTitle  = 'Thống kê & Phân tích Lưu lượng';
$activePage = 'statistics';
require_once __DIR__ . '/includes/layout_top.php';
?>

<?php if ($flash): ?>
<div class="mb-5 p-3.5 rounded-xl bg-amber-950/70 border border-amber-700/60 text-amber-200 text-sm flex items-center gap-2">
    <i class="fa-solid fa-circle-info text-amber-400"></i>
    <span><?= e($flash) ?></span>
</div>
<?php endif; ?>

<!-- Thanh lọc thời gian & Camera -->
<form method="GET" class="bg-cardbg rounded-xl border border-borderdark p-4 mb-6 shadow-lg">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs items-end">
        <div>
            <label class="block text-slate-400 mb-1">Từ ngày</label>
            <input type="date" name="from" value="<?= e($fromDate) ?>" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-slate-200">
        </div>
        <div>
            <label class="block text-slate-400 mb-1">Đến ngày</label>
            <input type="date" name="to" value="<?= e($toDate) ?>" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-slate-200">
        </div>
        <div>
            <label class="block text-slate-400 mb-1">Vị trí cổng</label>
            <select name="camera_id" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-slate-200">
                <option value="">Tất cả (Cổng A &amp; Cổng B)</option>
                <option value="1" <?= $cameraId === '1' ? 'selected' : '' ?>>Camera 01 - Cổng chính (Cổng A)</option>
                <option value="2" <?= $cameraId === '2' ? 'selected' : '' ?>>Camera 02 - Cổng phụ (Cổng B)</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 py-2 px-3 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-semibold transition">
                <i class="fa-solid fa-filter mr-1"></i> Thống kê
            </button>
            <a href="statistics.php" class="py-2 px-3 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold transition">Hôm nay</a>
        </div>
        <div class="text-right">
            <button type="button" onclick="confirmDeleteAll()" class="w-full py-2 px-3 rounded-lg bg-rose-950/60 hover:bg-rose-600 border border-rose-700/60 text-rose-300 hover:text-white font-semibold transition">
                <i class="fa-solid fa-trash-can mr-1"></i> Xóa dữ liệu test
            </button>
        </div>
    </div>
</form>

<form id="deleteAllForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="delete_all">
</form>

<!-- 4 Thẻ KPI Thống kê -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-cardbg rounded-xl p-5 border border-borderdark shadow-lg">
        <div class="text-xs font-semibold uppercase text-slate-400 mb-2">Tổng lượt qua cổng</div>
        <div class="text-3xl font-bold text-white"><?= $total ?></div>
        <div class="mt-2 text-xs text-slate-400 flex gap-3">
            <span class="text-emerald-400">Vào: <b><?= $enterCnt ?></b></span>
            <span class="text-amber-400">Ra: <b><?= $exitCnt ?></b></span>
        </div>
    </div>

    <div class="bg-cardbg rounded-xl p-5 border border-borderdark shadow-lg">
        <div class="text-xs font-semibold uppercase text-slate-400 mb-2">Sinh viên / Giảng viên / Khách</div>
        <div class="text-2xl font-bold">
            <span class="text-sky-400"><?= $studentCnt ?></span>
            <span class="text-slate-600 mx-1">/</span>
            <span class="text-amber-400"><?= $lecturerCnt ?></span>
            <span class="text-slate-600 mx-1">/</span>
            <span class="text-purple-400"><?= $guestCnt ?></span>
        </div>
        <div class="mt-2 text-xs text-slate-400">Phân loại tự động qua OCR biển số</div>
    </div>

    <div class="bg-cardbg rounded-xl p-5 border border-borderdark shadow-lg">
        <div class="text-xs font-semibold uppercase text-slate-400 mb-2">Tuân thủ đội mũ BH</div>
        <div class="text-3xl font-bold <?= $helmetRate >= 85 ? 'text-emerald-400' : 'text-rose-400' ?>"><?= $helmetRate ?>%</div>
        <div class="mt-2 text-xs text-slate-400">
            Có mũ: <b class="text-emerald-400"><?= $helmetOk ?></b> • Vi phạm: <b class="text-rose-400"><?= $noHelmet ?></b>
        </div>
    </div>

    <div class="bg-cardbg rounded-xl p-5 border border-borderdark shadow-lg">
        <div class="text-xs font-semibold uppercase text-slate-400 mb-2">Giờ cao điểm thực tế</div>
        <div class="text-3xl font-bold text-sky-400">
            <?= $peakHour !== null ? sprintf('%02d:00', $peakHour) : '--:--' ?>
        </div>
        <div class="mt-2 text-xs text-slate-400">
            <?= $peakHour !== null ? "Đạt đỉnh {$peakHourCount} lượt xe/giờ" : 'Chưa có dữ liệu' ?>
        </div>
    </div>
</div>

<!-- Biểu đồ 24 giờ + Biểu đồ tròn tỷ lệ -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
    <div class="lg:col-span-8 bg-cardbg rounded-xl border border-borderdark p-5 shadow-lg">
        <h2 class="font-bold text-white text-sm mb-1">Biểu đồ lưu lượng xe gắn máy theo 24 giờ</h2>
        <p class="text-xs text-slate-400 mb-4">Kỳ thống kê: <?= date('d/m/Y', strtotime($fromDate)) ?> &ndash; <?= date('d/m/Y', strtotime($toDate)) ?></p>
        <div class="h-[290px] relative"><canvas id="statsHourlyChart"></canvas></div>
    </div>

    <div class="lg:col-span-4 bg-cardbg rounded-xl border border-borderdark p-5 shadow-lg flex flex-col">
        <h2 class="font-bold text-white text-sm mb-1">Tỷ lệ nhóm đối tượng</h2>
        <p class="text-xs text-slate-400 mb-4">Cơ cấu xe 2 bánh qua cổng trường</p>
        <div class="flex-1 min-h-[240px] relative flex items-center justify-center">
            <canvas id="statsPieChart"></canvas>
        </div>
    </div>
</div>

<!-- Phân bổ theo cổng & 3 khung giờ cao điểm -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Phân bổ Cổng A vs Cổng B -->
    <div class="bg-cardbg rounded-xl border border-borderdark p-5 shadow-lg">
        <h2 class="font-bold text-white text-sm mb-4">Lưu lượng phân bổ theo Cổng</h2>
        <div class="space-y-4 text-xs">
            <div>
                <div class="flex justify-between mb-1.5">
                    <span class="font-semibold text-slate-200">Camera 01 - Cổng chính (Cổng A)</span>
                    <span class="font-mono text-sky-400 font-bold"><?= $cam1Cnt ?> lượt (<?= $cam1Pct ?>%)</span>
                </div>
                <div class="w-full bg-slate-900 rounded-full h-2.5 overflow-hidden border border-slate-800">
                    <div class="bg-sky-500 h-2.5 rounded-full" style="width: <?= $cam1Pct ?>%"></div>
                </div>
            </div>

            <div>
                <div class="flex justify-between mb-1.5">
                    <span class="font-semibold text-slate-200">Camera 02 - Cổng phụ (Cổng B)</span>
                    <span class="font-mono text-purple-400 font-bold"><?= $cam2Cnt ?> lượt (<?= $cam2Pct ?>%)</span>
                </div>
                <div class="w-full bg-slate-900 rounded-full h-2.5 overflow-hidden border border-slate-800">
                    <div class="bg-purple-500 h-2.5 rounded-full" style="width: <?= $cam2Pct ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Phân tích 3 khung giờ cao điểm -->
    <div class="bg-cardbg rounded-xl border border-borderdark p-5 shadow-lg">
        <h2 class="font-bold text-white text-sm mb-4">Phân tích 3 khung giờ trọng điểm</h2>
        <div class="grid grid-cols-3 gap-3 text-center">
            <div class="p-4 rounded-xl bg-[#0f1626] border border-borderdark">
                <div class="text-xs text-amber-400 font-semibold mb-1"><i class="fa-solid fa-sun mr-1"></i>Sáng</div>
                <div class="text-[11px] text-slate-500 mb-2">07:00 – 08:00</div>
                <div class="text-2xl font-bold text-white"><?= $morningPeak ?></div>
                <div class="text-[11px] text-slate-400">lượt xe</div>
            </div>
            <div class="p-4 rounded-xl bg-[#0f1626] border border-borderdark">
                <div class="text-xs text-sky-400 font-semibold mb-1"><i class="fa-solid fa-cloud-sun mr-1"></i>Trưa</div>
                <div class="text-[11px] text-slate-500 mb-2">11:00 – 12:00</div>
                <div class="text-2xl font-bold text-white"><?= $noonPeak ?></div>
                <div class="text-[11px] text-slate-400">lượt xe</div>
            </div>
            <div class="p-4 rounded-xl bg-[#0f1626] border border-borderdark">
                <div class="text-xs text-purple-400 font-semibold mb-1"><i class="fa-solid fa-moon mr-1"></i>Chiều</div>
                <div class="text-[11px] text-slate-500 mb-2">17:00 – 18:00</div>
                <div class="text-2xl font-bold text-white"><?= $afternoonPeak ?></div>
                <div class="text-[11px] text-slate-400">lượt xe</div>
            </div>
        </div>
    </div>
</div>

<script>
new Chart(document.getElementById('statsHourlyChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($hourlyLabels) ?>,
        datasets: [
            { label: 'Xe Sinh viên',  data: <?= json_encode($hourlyStudent) ?>,  backgroundColor: 'rgba(56, 189, 248, 0.85)', borderRadius: 4 },
            { label: 'Xe Giảng viên', data: <?= json_encode($hourlyLecturer) ?>, backgroundColor: 'rgba(251, 191, 36, 0.85)', borderRadius: 4 },
            { label: 'Xe khách',      data: <?= json_encode($hourlyGuest) ?>,    backgroundColor: 'rgba(192, 132, 252, 0.80)', borderRadius: 4 }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { color: '#cbd5e1' } } },
        scales: {
            x: { ticks: { color: '#94a3b8', font: { size: 11 } }, grid: { color: 'rgba(148,163,184,0.08)' } },
            y: { beginAtZero: true, ticks: { stepSize: 1, color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.1)' } }
        }
    }
});

new Chart(document.getElementById('statsPieChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: ['Xe Sinh viên', 'Xe Giảng viên', 'Xe khách'],
        datasets: [{
            data: [<?= $studentCnt ?>, <?= $lecturerCnt ?>, <?= $guestCnt ?>],
            backgroundColor: ['#38bdf8', '#fbbf24', '#c084fc'],
            borderColor: '#131b2e',
            borderWidth: 3
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { color: '#cbd5e1', padding: 16 } } }
    }
});

function confirmDeleteAll() {
    if (confirm('Bạn có chắc chắn muốn xóa toàn bộ dữ liệu nhận diện trong bảng detections không?')) {
        document.getElementById('deleteAllForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>