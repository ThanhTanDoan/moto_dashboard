<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Camera trực tiếp';
$activePage = 'camera';

$cameras = $pdo->query("SELECT * FROM cameras WHERE id IN (1, 2) ORDER BY id ASC")->fetchAll();

// Lấy 5 lượt gần nhất cho mỗi camera
$camRecent = [];
foreach ($cameras as $c) {
    $stmt = $pdo->prepare("SELECT * FROM detections WHERE camera_id = ? ORDER BY detection_time DESC LIMIT 5");
    $stmt->execute([$c['id']]);
    $camRecent[$c['id']] = $stmt->fetchAll();
}

require_once __DIR__ . '/includes/layout_top.php';
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <p class="text-sm text-slate-400">Giám sát đồng thời 2 làn xe gắn máy và nhận tọa độ nhận diện AI theo thời gian thực.</p>
    </div>
    <div class="flex items-center gap-2">
        <button onclick="simulateCam()" id="btnSimCam" class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-sm font-medium shadow-lg shadow-sky-600/20 transition flex items-center gap-2">
            <i class="fa-solid fa-bolt"></i> Giả lập xe qua cổng
        </button>
        <a href="settings.php" class="px-3.5 py-2 rounded-lg bg-cardbg hover:bg-slate-800 border border-borderdark text-slate-200 text-sm font-medium transition">
            <i class="fa-solid fa-sliders mr-1"></i> Cấu hình nguồn Camera
        </a>
    </div>
</div>

<!-- Lưới 2 Camera -->
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <?php foreach ($cameras as $cam):
        $cid = (int)$cam['id'];
        $latest = $camRecent[$cid][0] ?? null;
    ?>
    <div class="bg-cardbg rounded-xl border border-borderdark shadow-lg overflow-hidden flex flex-col">
        <!-- Header Camera -->
        <div class="px-5 py-3.5 border-b border-borderdark flex items-center justify-between">
            <div>
                <h2 class="font-bold text-white text-sm flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full <?= $cam['is_enabled'] ? 'bg-rose-500 animate-pulse' : 'bg-slate-500' ?>"></span>
                    <?= e($cam['name']) ?>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5"><?= e($cam['location'] ?: 'Chưa cập nhật vị trí') ?></p>
            </div>
            <div class="text-right">
                <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold <?= $cam['is_enabled'] ? 'bg-emerald-950/70 text-emerald-300 border border-emerald-700/60' : 'bg-slate-800 text-slate-400' ?>">
                    <?= e($cam['status']) ?>
                </span>
                <div class="text-[11px] text-sky-400 font-mono mt-1 uppercase">Nguồn: <?= e($cam['stream_type']) ?></div>
            </div>
        </div>

        <!-- Khung Video / Ảnh -->
        <div class="relative bg-[#070a12] aspect-video w-full overflow-hidden flex items-center justify-center">
            <?php if (!$cam['is_enabled']): ?>
                <div class="text-slate-500 text-center p-4">
                    <i class="fa-solid fa-video-slash text-3xl mb-2"></i>
                    <div class="text-xs font-mono">CAMERA ĐANG TẮT</div>
                </div>
            <?php elseif ($cam['stream_type'] === 'hls' && !empty($cam['stream_url'])): ?>
                <video id="hlsVideo<?= $cid ?>" data-hls="<?= e($cam['stream_url']) ?>" class="w-full h-full object-cover" autoplay muted playsinline></video>
            <?php elseif ($cam['stream_type'] === 'mjpeg' && !empty($cam['stream_url'])): ?>
                <img src="<?= e($cam['stream_url']) ?>" class="w-full h-full object-cover" alt="MJPEG Stream <?= $cid ?>">
            <?php elseif (!empty($latest['image_path']) && file_exists(__DIR__ . '/' . $latest['image_path'])): ?>
                <img id="snapCam<?= $cid ?>" src="<?= e($latest['image_path']) ?>" class="w-full h-full object-cover" alt="Snapshot <?= $cid ?>">
            <?php else: ?>
                <div class="absolute inset-0 bg-gradient-to-br from-[#0b101e] via-[#10182b] to-[#080c16] flex flex-col items-center justify-center text-slate-400 p-4 text-center">
                    <i class="fa-solid fa-camera text-4xl mb-2 text-slate-700"></i>
                    <div class="text-xs font-mono text-sky-400/90">CAM-0<?= $cid ?> • CHẾ ĐỘ MÔ PHỎNG / CHỜ LUỒNG AI</div>
                    <div class="text-[11px] text-slate-500 mt-1">Đổi sang HLS / MJPEG / Snapshot trong mục Cài đặt hệ thống</div>
                </div>
            <?php endif; ?>

            <!-- Lớp phủ Bounding Box -->
            <div id="overlayCam<?= $cid ?>" class="absolute inset-0 pointer-events-none"></div>

            <div class="absolute top-2.5 left-2.5 bg-black/70 border border-slate-700 text-sky-300 text-[11px] font-mono px-2.5 py-1 rounded">
                CAM-0<?= $cid ?> • LIVE
            </div>
        </div>

        <!-- 5 lượt mới nhất của Camera này -->
        <div class="p-4 bg-[#0f1626] border-t border-borderdark flex-1">
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">
                Nhận diện gần đây tại Camera 0<?= $cid ?>
            </div>
            <div id="listCam<?= $cid ?>" class="space-y-2 text-xs">
                <?php if (empty($camRecent[$cid])): ?>
                    <div class="text-slate-500 py-2">Chưa có dữ liệu từ camera này.</div>
                <?php else: foreach ($camRecent[$cid] as $item): ?>
                    <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg bg-cardbg border border-borderdark">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-2 py-0.5 rounded bg-slate-950 border border-slate-700 font-mono font-bold text-amber-300"><?= e($item['license_plate']) ?></span>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold <?=badgeVehicleClass($item['vehicle_group']) ?>">
                                <?= e($item['vehicle_group']) ?>
                            </span>
                            <span class="text-[11px] font-semibold <?= $item['has_helmet'] ? 'text-emerald-400' : 'text-rose-400' ?>">
                                <?= $item['has_helmet'] ? 'Có mũ' : 'KHÔNG MŨ' ?>
                            </span>
                            <span class="text-[11px] <?= $item['direction'] === 'Vào' ? 'text-emerald-400' : 'text-amber-400' ?>">• <?= e($item['direction']) ?></span>
                        </div>
                        <span class="font-mono text-slate-400"><?= date('H:i:s', strtotime($item['detection_time'])) ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
// Kích hoạt HLS.js nếu camera đang cấu hình nguồn HLS
document.querySelectorAll('video[data-hls]').forEach(video => {
    const src = video.getAttribute('data-hls');
    if (Hls.isSupported()) {
        const hls = new Hls();
        hls.loadSource(src);
        hls.attachMedia(video);
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = src;
    }
});

// Tự động cập nhật Bounding Box cho cả 2 camera mỗi 3 giây
async function refreshCameraOverlay(camId) {
    try {
        const res = await fetch('api/detection.php?camera_id=' + camId + '&limit=1');
        const json = await res.json();
        const overlay = document.getElementById('overlayCam' + camId);
        if (!overlay || !json.success || !json.data.length) return;

        const d = json.data[0];
        const color = Number(d.has_helmet) === 1 ? '#38bdf8' : '#f43f5e';
        const badgeBg = Number(d.has_helmet) === 1 ? 'bg-sky-600' : 'bg-rose-600';
        const helmetTxt = Number(d.has_helmet) === 1 ? 'Có mũ' : 'KHÔNG MŨ';

        overlay.innerHTML = `
            <div style="position:absolute;left:${d.bbox_x}%;top:${d.bbox_y}%;width:${d.bbox_w}%;height:${d.bbox_h}%;border:2px solid ${color};border-radius:6px;box-shadow:0 0 14px ${color}88;">
                <div class="${badgeBg} text-white text-[10px] font-bold px-1.5 py-0.5 rounded-t whitespace-nowrap inline-block -mt-5">
                    ${d.license_plate} • ${helmetTxt} (${Math.round(d.confidence * 100)}%)
                </div>
            </div>
        `;
    } catch (e) { console.error(e); }
}

function pollAllCameras() {
    refreshCameraOverlay(1);
    refreshCameraOverlay(2);
}
pollAllCameras();
setInterval(pollAllCameras, 3000);

async function simulateCam() {
    const btn = document.getElementById('btnSimCam');
    btn.disabled = true;
    try {
        await fetch('api/detection.php?action=simulate', { method: 'POST' });
        location.reload();
    } catch (e) {
        alert('Lỗi kết nối API!');
        btn.disabled = false;
    }
}
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>