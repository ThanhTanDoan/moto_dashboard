<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Nhận diện & Xác minh';
$activePage = 'recognition';

$flash = '';

// Xử lý cập nhật / xác minh bản ghi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_id'])) {
    $id = (int)$_POST['verify_id'];
    $plate = strtoupper(trim($_POST['license_plate'] ?? ''));
    $hasHelmet = (int)($_POST['has_helmet'] ?? 1) ? 1 : 0;
    $direction = ($_POST['direction'] ?? 'Vào') === 'Ra' ? 'Ra' : 'Vào';
    $note = trim($_POST['note'] ?? '');

    // Tự đối chiếu lại biển số mới sửa với danh sách Xe Sinh viên / Xe Giảng viên
    $vehicleGroup = classifyVehicleByPlate($pdo, $plate);

    $upd = $pdo->prepare("
        UPDATE detections
        SET license_plate = ?, vehicle_group = ?, has_helmet = ?, direction = ?, note = ?, is_verified = 1
        WHERE id = ?
    ");
    $upd->execute([$plate, $vehicleGroup, $hasHelmet, $direction, $note, $id]);
    $flash = "Đã xác minh và cập nhật bản ghi #{$id} (Biển số: {$plate} - {$vehicleGroup}).";
}

// Bộ lọc tab
$filter = $_GET['filter'] ?? 'all';
$where = "1=1";
if ($filter === 'unverified') $where = "is_verified = 0";
elseif ($filter === 'verified') $where = "is_verified = 1";
elseif ($filter === 'no_helmet') $where = "has_helmet = 0";

$rows = $pdo->query("SELECT * FROM detections WHERE {$where} ORDER BY detection_time DESC LIMIT 60")->fetchAll();

// Đếm nhanh cho các tab
$cntAll = (int)$pdo->query("SELECT COUNT(*) FROM detections")->fetchColumn();
$cntUnv = (int)$pdo->query("SELECT COUNT(*) FROM detections WHERE is_verified = 0")->fetchColumn();
$cntNoH = (int)$pdo->query("SELECT COUNT(*) FROM detections WHERE has_helmet = 0")->fetchColumn();

require_once __DIR__ . '/includes/layout_top.php';
?>

<?php if ($flash): ?>
<div class="mb-5 p-3.5 rounded-xl bg-emerald-950/70 border border-emerald-700/60 text-emerald-200 text-sm flex items-center gap-2">
    <i class="fa-solid fa-circle-check text-emerald-400"></i>
    <span><?= e($flash) ?></span>
</div>
<?php endif; ?>

<!-- Thanh lọc Tab -->
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex flex-wrap gap-2">
        <?php
        $tabs = [
            'all'        => ["Tất cả ({$cntAll})", 'sky'],
            'unverified' => ["Chưa xác minh ({$cntUnv})", 'amber'],
            'no_helmet'  => ["Không đội mũ ({$cntNoH})", 'rose'],
            'verified'   => ["Đã xác minh", 'emerald'],
        ];
        foreach ($tabs as $k => $info):
            $active = ($filter === $k);
            $cls = $active
                ? "bg-sky-600 text-white border-sky-500"
                : "bg-cardbg text-slate-300 border-borderdark hover:bg-slate-800";
        ?>
            <a href="recognition.php?filter=<?= $k ?>" class="px-3.5 py-2 rounded-lg border text-xs font-semibold transition <?= $cls ?>">
                <?= e($info[0]) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <span class="text-xs text-slate-400">Hiển thị tối đa 60 lượt mới nhất</span>
</div>

<!-- Danh sách thẻ nhận diện -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
    <?php if (empty($rows)): ?>
        <div class="col-span-full bg-cardbg border border-borderdark rounded-xl p-10 text-center text-slate-500">
            <i class="fa-solid fa-motorcycle text-3xl mb-2"></i>
            <p class="text-sm">Không có bản ghi nào phù hợp bộ lọc.</p>
        </div>
    <?php else: foreach ($rows as $r): ?>
        <div class="bg-cardbg rounded-xl border <?= $r['has_helmet'] ? 'border-borderdark' : 'border-rose-500/50' ?> shadow-lg overflow-hidden flex flex-col">
            <!-- Ảnh chụp / Khung mô phỏng -->
            <div class="relative h-40 bg-[#070a12] flex items-center justify-center overflow-hidden border-b border-borderdark">
                <?php if (!empty($r['image_path']) && file_exists(__DIR__ . '/' . $r['image_path'])): ?>
                    <img src="<?= e($r['image_path']) ?>" class="w-full h-full object-cover" alt="Snapshot">
                <?php else: ?>
                    <div class="text-center text-slate-500">
                        <i class="fa-solid fa-motorcycle text-3xl text-slate-700 mb-1"></i>
                        <div class="text-[11px] font-mono">Track #<?= e($r['track_id'] ?? $r['id']) ?> • CAM-0<?= (int)$r['camera_id'] ?></div>
                    </div>
                <?php endif; ?>

                <div class="absolute top-2 left-2 px-2 py-0.5 rounded bg-black/75 border border-slate-700 font-mono text-xs font-bold text-amber-300">
                    <?= e($r['license_plate']) ?>
                </div>
                <div class="absolute top-2 right-2 px-2 py-0.5 rounded text-[11px] font-semibold <?= $r['is_verified'] ? 'bg-emerald-600/90 text-white' : 'bg-amber-600/90 text-white' ?>">
                    <?= $r['is_verified'] ? 'Đã xác minh' : 'Chờ duyệt' ?>
                </div>
            </div>

            <!-- Thông tin chi tiết -->
            <div class="p-4 flex-1 flex flex-col justify-between space-y-3 text-xs">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="px-2.5 py-0.5 rounded-full font-semibold <?= badgeVehicleClass($r['vehicle_group']) ?>">
                            <?= e($r['vehicle_group']) ?>
                        </span>
                        <span class="font-semibold <?= $r['has_helmet'] ? 'text-emerald-400' : 'text-rose-400' ?>">
                            <i class="fa-solid <?= $r['has_helmet'] ? 'fa-shield-halved' : 'fa-triangle-exclamation' ?>"></i>
                            <?= $r['has_helmet'] ? 'Có đội mũ' : 'Không đội mũ' ?>
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-slate-400 pt-1">
                        <div>Hướng: <b class="<?= $r['direction'] === 'Vào' ? 'text-emerald-400' : 'text-amber-400' ?>"><?= e($r['direction']) ?></b></div>
                        <div>Độ tin cậy: <b class="text-sky-300"><?= round($r['confidence'] * 100, 1) ?>%</b></div>
                        <div class="col-span-2 font-mono text-[11px] text-slate-400">
                            <i class="fa-regular fa-clock mr-1"></i><?= date('H:i:s - d/m/Y', strtotime($r['detection_time'])) ?>
                        </div>
                        <?php if (!empty($r['note'])): ?>
                            <div class="col-span-2 text-amber-300/90 italic">Ghi chú: <?= e($r['note']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="button"
                    onclick='openVerifyModal(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                    class="w-full py-2 rounded-lg bg-slate-800 hover:bg-sky-600 border border-slate-700 hover:border-sky-500 text-slate-200 hover:text-white font-semibold transition">
                    <i class="fa-solid fa-pen-to-square mr-1"></i> <?= $r['is_verified'] ? 'Chỉnh sửa xác minh' : 'Xác minh & Hiệu chỉnh' ?>
                </button>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>

<!-- Modal Xác minh / Sửa biển số -->
<div id="verifyModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-cardbg border border-borderdark rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-borderdark flex items-center justify-between">
            <h3 class="font-bold text-white text-sm">Xác minh &amp; Hiệu chỉnh nhận diện</h3>
            <button onclick="closeVerifyModal()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" class="p-5 space-y-4 text-sm">
            <input type="hidden" name="verify_id" id="m_id">

            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Biển số xe (tự động kiểm tra lại Xe nội bộ)</label>
                <input type="text" name="license_plate" id="m_plate" required
                       class="w-full px-3.5 py-2 rounded-lg bg-slate-950 border border-slate-700 text-amber-300 font-mono font-bold uppercase outline-none focus:border-sky-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Mũ bảo hiểm</label>
                    <select name="has_helmet" id="m_helmet" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-slate-200 outline-none focus:border-sky-500">
                        <option value="1">Có đội mũ</option>
                        <option value="0">Không đội mũ</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Hướng di chuyển</label>
                    <select name="direction" id="m_dir" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-slate-200 outline-none focus:border-sky-500">
                        <option value="Vào">Vào</option>
                        <option value="Ra">Ra</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Ghi chú của bảo vệ</label>
                <input type="text" name="note" id="m_note" placeholder="VD: Đã nhắc nhở đội mũ, khách liên hệ phòng Đào tạo..."
                       class="w-full px-3.5 py-2 rounded-lg bg-slate-950 border border-slate-700 text-slate-200 outline-none focus:border-sky-500">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeVerifyModal()" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold">Hủy</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-xs font-semibold">
                    <i class="fa-solid fa-check mr-1"></i> Lưu &amp; Xác minh
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openVerifyModal(row) {
    document.getElementById('m_id').value = row.id;
    document.getElementById('m_plate').value = row.license_plate;
    document.getElementById('m_helmet').value = row.has_helmet;
    document.getElementById('m_dir').value = row.direction;
    document.getElementById('m_note').value = row.note || '';
    const m = document.getElementById('verifyModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeVerifyModal() {
    const m = document.getElementById('verifyModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>