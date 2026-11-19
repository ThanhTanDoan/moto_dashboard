<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Tổng quan Giám sát Xe gắn máy';
$activePage = 'dashboard';

$cameras = $pdo->query("SELECT * FROM cameras WHERE id IN (1, 2) ORDER BY id ASC")->fetchAll();
$cam1 = $cameras[0] ?? ['id' => 1, 'name' => 'Camera 01 - Cổng chính (Cổng A)', 'stream_type' => 'demo', 'stream_url' => '', 'status' => 'Demo'];

$todayRows = $pdo->query("SELECT * FROM detections WHERE DATE(detection_time) = CURDATE() ORDER BY detection_time DESC")->fetchAll();

$totalToday  = count($todayRows);
$enterCount  = 0;
$exitCount   = 0;
$studentCnt  = 0;
$lecturerCnt = 0;
$guestCnt    = 0;
$noHelmetCnt = 0;

$hourlyStudent  = array_fill(0, 24, 0);
$hourlyLecturer = array_fill(0, 24, 0);
$hourlyGuest    = array_fill(0, 24, 0);

foreach ($todayRows as $r) {
    if ($r['direction'] === 'Vào') $enterCount++;
    if ($r['direction'] === 'Ra')  $exitCount++;

    $h = (int)date('G', strtotime($r['detection_time']));
    if ($r['vehicle_group'] === 'Xe Giảng viên') {
        $lecturerCnt++;
        $hourlyLecturer[$h]++;
    } elseif ($r['vehicle_group'] === 'Xe Sinh viên') {
        $studentCnt++;
        $hourlyStudent[$h]++;
    } else {
        $guestCnt++;
        $hourlyGuest[$h]++;
    }

    if ((int)$r['has_helmet'] === 0) $noHelmetCnt++;
}

$inParking = max(0, $enterCount - $exitCount);
$recent5   = array_slice($todayRows, 0, 5);
$latest    = $todayRows[0] ?? null;

$hourlyLabels = [];
for ($i = 0; $i < 24; $i++) $hourlyLabels[] = sprintf('%02d:00', $i);

require_once __DIR__ . '/includes/layout_top.php';
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <span class="text-sm text-slate-400">Dữ liệu thống kê ngày <b class="text-white"><?= date('d/m/Y') ?></b> (Cổng A &amp; Cổng B)</span>
    <div class="flex items-center gap-2">
        <button id="btnSimulate" onclick="simulateDetection()" class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-sm font-medium shadow-lg shadow-sky-600/20 transition flex items-center gap-2">
            <i class="fa-solid fa-bolt"></i> Giả lập AI phát hiện xe
        </button>
        <button onclick="location.reload()" class="px-3.5 py-2 rounded-lg bg-cardbg hover:bg-slate-800 border border-borderdark text-slate-200 text-sm font-medium transition">
            <i class="fa-solid fa-rotate"></i> Làm mới
        </button>
    </div>
</div>

<div id="simToast" class="hidden mb-4 p-3.5 rounded-xl bg-sky-950/70 border border-sky-700/60 text-sky-200 text-sm font-medium flex items-center justify-between">
    <span id="simToastText"></span>
    <button onclick="this.parentElement.classList.add('hidden')" class="text-sky-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
</div>

<!-- 4 Thẻ KPI -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-cardbg rounded-xl p-5 border border-borderdark shadow-lg">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Tổng lượt xe hôm nay</span>
            <div class="w-9 h-9 rounded-lg bg-sky-500/15 text-sky-400 border border-sky-500/25 flex items-center justify-center"><i class="fa-solid fa-motorcycle"></i></div>
        </div>
        <div class="text-3xl font-bold text-white"><?= $totalToday ?></div>
        <div class="mt-2 text-xs text-slate-400 flex items-center gap-3">
            <span class="text-emerald-400 font-semibold"><i class="fa-solid fa-arrow-right-to-bracket"></i> Vào: <?= $enterCount ?></span>
            <span class="text-amber-400 font-semibold"><i class="fa-solid fa-arrow-right-from-bracket"></i> Ra: <?= $exitCount ?></span>
        </div>
    </div>

    <div class="bg-cardbg rounded-xl p-5 border border-borderdark shadow-lg">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Ước tính trong bãi</span>
            <div class="w-9 h-9 rounded-lg bg-emerald-500/15 text-emerald-400 border border-emerald-500/25 flex items-center justify-center"><i class="fa-solid fa-square-parking"></i></div>
        </div>
        <div class="text-3xl font-bold text-emerald-400"><?= $inParking ?></div>
        <div class="mt-2 text-xs text-slate-400">Chênh lệch lượt Vào - Ra tại 2 cổng</div>
    </div>

    <div class="bg-cardbg rounded-xl p-5 border border-borderdark shadow-lg">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Sinh viên / Giảng viên</span>
            <div class="w-9 h-9 rounded-lg bg-amber-500/15 text-amber-400 border border-amber-500/25 flex items-center justify-center"><i class="fa-solid fa-users"></i></div>
        </div>
        <div class="text-3xl font-bold text-sky-400"><?= $studentCnt ?> <span class="text-lg font-normal text-slate-500">/</span> <span class="text-amber-400"><?= $lecturerCnt ?></span></div>
        <div class="mt-2 text-xs text-slate-400">Xe khách vãng lai: <b class="text-purple-400"><?= $guestCnt ?></b> lượt</div>
    </div>

    <div class="bg-cardbg rounded-xl p-5 border <?= $noHelmetCnt > 0 ? 'border-rose-500/60 alert-pulse' : 'border-borderdark' ?> shadow-lg">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Không đội mũ BH</span>
            <div class="w-9 h-9 rounded-lg bg-rose-500/15 text-rose-400 border border-rose-500/25 flex items-center justify-center"><i class="fa-solid fa-triangle-exclamation"></i></div>
        </div>
        <div class="text-3xl font-bold text-rose-400"><?= $noHelmetCnt ?></div>
        <div class="mt-2 text-xs text-rose-400/90 font-medium"><?= $noHelmetCnt > 0 ? 'Cần bảo vệ nhắc nhở tại cổng' : 'Chưa ghi nhận vi phạm hôm nay' ?></div>
    </div>
</div>

<!-- Camera + Biểu đồ -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
    <div class="lg:col-span-5 bg-cardbg rounded-xl border border-borderdark shadow-lg overflow-hidden flex flex-col">
        <div class="px-5 py-3.5 border-b border-borderdark flex items-center justify-between">
            <div>
                <h2 class="font-bold text-white text-sm flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                    <?= e($cam1['name']) ?>
                </h2>
                <p class="text-xs text-slate-400">Nguồn: <span class="uppercase font-semibold text-sky-400"><?= e($cam1['stream_type']) ?></span></p>
            </div>
            <a href="camera.php" class="text-xs text-sky-400 hover:text-sky-300 font-medium">Xem 2 cổng &rarr;</a>
        </div>

        <div class="relative bg-[#070a12] aspect-video w-full overflow-hidden flex items-center justify-center">
            <?php if ($cam1['stream_type'] === 'hls' && !empty($cam1['stream_url'])): ?>
                <video id="hlsVideo1" class="w-full h-full object-cover" autoplay muted playsinline></video>
            <?php elseif ($cam1['stream_type'] === 'mjpeg' && !empty($cam1['stream_url'])): ?>
                <img src="<?= e($cam1['stream_url']) ?>" class="w-full h-full object-cover" alt="MJPEG Live">
            <?php elseif (!empty($latest['image_path']) && file_exists(__DIR__ . '/' . $latest['image_path'])): ?>
                <img src="<?= e($latest['image_path']) ?>" class="w-full h-full object-cover" alt="Latest Snapshot">
            <?php else: ?>
                <div class="absolute inset-0 bg-gradient-to-br from-[#0b101e] via-[#10182b] to-[#080c16] flex flex-col items-center justify-center text-slate-400 p-4 text-center">
                    <i class="fa-solid fa-video text-4xl mb-2 text-slate-700"></i>
                    <div class="text-xs font-mono text-sky-400/90">CỔNG A • CHẾ ĐỘ GIÁM SÁT LINH HOẠT</div>
                    <div class="text-[11px] text-slate-500 mt-1">Hỗ trợ: HLS (.m3u8) • MJPEG • Snapshot Base64</div>
                </div>
            <?php endif; ?>
            <div id="bboxOverlay" class="absolute inset-0 pointer-events-none"></div>
            <div class="absolute top-2.5 left-2.5 bg-black/70 border border-slate-700 text-sky-300 text-[11px] font-mono px-2.5 py-1 rounded">CỔNG A • LIVE</div>
        </div>

        <div class="p-4 bg-[#0f1626] border-t border-borderdark text-xs">
            <div class="font-semibold text-slate-400 mb-2">Nhận diện gần nhất:</div>
            <?php if ($latest): ?>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="px-2.5 py-1 rounded bg-slate-950 border border-slate-700 text-amber-300 font-mono font-bold text-sm"><?= e($latest['license_plate']) ?></span>
                        <span class="px-2 py-0.5 rounded-full font-semibold <?= badgeVehicleClass($latest['vehicle_group']) ?>"><?= e($latest['vehicle_group']) ?></span>
                        <span class="px-2 py-0.5 rounded-full font-semibold <?= $latest['has_helmet'] ? 'bg-emerald-950/80 text-emerald-300 border border-emerald-700/60' : 'bg-rose-950/80 text-rose-300 border border-rose-700/60' ?>">
                            <?= $latest['has_helmet'] ? 'Có đội mũ' : 'KHÔNG MŨ!' ?>
                        </span>
                    </div>
                    <span class="text-slate-400 font-mono"><?= date('H:i:s', strtotime($latest['detection_time'])) ?></span>
                </div>
            <?php else: ?>
                <span class="text-slate-500">Chưa có dữ liệu hôm nay.</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="lg:col-span-7 bg-cardbg rounded-xl border border-borderdark shadow-lg p-5 flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="font-bold text-white text-sm">Lưu lượng xe gắn máy theo 24 giờ</h2>
                <p class="text-xs text-slate-400">Phân tách theo Xe Sinh viên, Xe Giảng viên và Xe khách</p>
            </div>
            <a href="statistics.php" class="text-xs text-sky-400 hover:text-sky-300 font-medium">Thống kê chi tiết &rarr;</a>
        </div>
        <div class="flex-1 min-h-[260px] relative"><canvas id="trafficChart"></canvas></div>
    </div>
</div>

<!-- 5 lượt gần nhất -->
<div class="bg-cardbg rounded-xl border border-borderdark shadow-lg overflow-hidden">
    <div class="px-5 py-3.5 border-b border-borderdark flex items-center justify-between">
        <h2 class="font-bold text-white text-sm">5 lượt xe gắn máy qua Cổng A &amp; Cổng B gần nhất</h2>
        <a href="history.php" class="text-xs text-sky-400 hover:text-sky-300 font-medium">Xem toàn bộ lịch sử &rarr;</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0f1626] text-slate-400 text-xs uppercase border-b border-borderdark">
                <tr>
                    <th class="px-5 py-3">Thời gian</th>
                    <th class="px-5 py-3">Biển số xe</th>
                    <th class="px-5 py-3">Đối tượng</th>
                    <th class="px-5 py-3">Mũ bảo hiểm</th>
                    <th class="px-5 py-3">Hướng</th>
                    <th class="px-5 py-3">Vị trí cổng</th>
                    <th class="px-5 py-3">Độ tin cậy</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/70">
                <?php if (empty($recent5)): ?>
                    <tr><td colspan="7" class="px-5 py-6 text-center text-slate-500">Chưa có dữ liệu hôm nay.</td></tr>
                <?php else: foreach ($recent5 as $row): ?>
                    <tr class="hover:bg-slate-800/40 transition">
                        <td class="px-5 py-3 font-mono text-xs text-slate-400"><?= date('H:i:s d/m', strtotime($row['detection_time'])) ?></td>
                        <td class="px-5 py-3"><span class="px-2.5 py-0.5 rounded bg-slate-950 border border-slate-700 font-mono font-bold text-amber-300 text-xs"><?= e($row['license_plate']) ?></span></td>
                        <td class="px-5 py-3"><span class="px-2.5 py-0.5 rounded-full text-xs font-semibold <?= badgeVehicleClass($row['vehicle_group']) ?>"><?= e($row['vehicle_group']) ?></span></td>
                        <td class="px-5 py-3">
                            <?= $row['has_helmet']
                                ? '<span class="text-emerald-400 font-medium text-xs"><i class="fa-solid fa-shield-halved"></i> Có đội mũ</span>'
                                : '<span class="px-2 py-0.5 rounded bg-rose-950/80 border border-rose-700/70 text-rose-300 font-bold text-xs"><i class="fa-solid fa-triangle-exclamation"></i> Không đội mũ</span>' ?>
                        </td>
                        <td class="px-5 py-3 font-semibold text-xs <?= $row['direction'] === 'Vào' ? 'text-emerald-400' : 'text-amber-400' ?>"><?= e($row['direction']) ?></td>
                        <td class="px-5 py-3 text-xs text-slate-300"><?= e(getCameraShortName($row['camera_id'])) ?></td>
                        <td class="px-5 py-3 text-xs font-mono text-sky-300"><?= round($row['confidence'] * 100, 1) ?>%</td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
new Chart(document.getElementById('trafficChart').getContext('2d'), {
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
        plugins: { legend: { labels: { color: '#cbd5e1', font: { size: 12 } } } },
        scales: {
            x: { ticks: { color: '#94a3b8', font: { size: 11 } }, grid: { color: 'rgba(148,163,184,0.08)' } },
            y: { beginAtZero: true, ticks: { stepSize: 1, color: '#94a3b8', font: { size: 11 } }, grid: { color: 'rgba(148,163,184,0.1)' } }
        }
    }
});

<?php if ($cam1['stream_type'] === 'hls' && !empty($cam1['stream_url'])): ?>
const video = document.getElementById('hlsVideo1');
const hlsSrc = <?= json_encode($cam1['stream_url']) ?>;
if (Hls.isSupported()) { const hls = new Hls(); hls.loadSource(hlsSrc); hls.attachMedia(video); }
else if (video.canPlayType('application/vnd.apple.mpegurl')) { video.src = hlsSrc; }
<?php endif; ?>

async function fetchLiveBox() {
    try {
        const res = await fetch('api/detection.php?camera_id=1&limit=1');
        const json = await res.json();
        const overlay = document.getElementById('bboxOverlay');
        if (!json.success || !json.data.length) return;
        const d = json.data[0];
        const color = Number(d.has_helmet) === 1 ? '#38bdf8' : '#f43f5e';
        const badgeBg = Number(d.has_helmet) === 1 ? 'bg-sky-600' : 'bg-rose-600';
        const helmetTxt = Number(d.has_helmet) === 1 ? 'Có mũ' : 'KHÔNG MŨ';
        overlay.innerHTML = `<div style="position:absolute;left:${d.bbox_x}%;top:${d.bbox_y}%;width:${d.bbox_w}%;height:${d.bbox_h}%;border:2px solid ${color};border-radius:6px;box-shadow:0 0 14px ${color}88;">
            <div class="${badgeBg} text-white text-[10px] font-bold px-1.5 py-0.5 rounded-t whitespace-nowrap inline-block -mt-5">
                ${d.license_plate} • ${d.vehicle_group} • ${helmetTxt} (${Math.round(d.confidence * 100)}%)
            </div></div>`;
    } catch (e) {}
}
fetchLiveBox();
setInterval(fetchLiveBox, 4000);

async function simulateDetection() {
    const btn = document.getElementById('btnSimulate');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang gửi...';
    try {
        const res = await fetch('api/detection.php?action=simulate', { method: 'POST' });
        const json = await res.json();
        document.getElementById('simToastText').textContent = json.message || 'Đã thêm dữ liệu mới.';
        document.getElementById('simToast').classList.remove('hidden');
        setTimeout(() => location.reload(), 900);
    } catch (e) {
        alert('Lỗi kết nối API!');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Giả lập AI phát hiện xe';
    }
}
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>