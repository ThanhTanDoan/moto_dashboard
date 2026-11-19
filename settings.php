<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    // 1. Lưu cấu hình chung & AI
    if ($formType === 'save_system') {
        $systemName = trim($_POST['system_name'] ?? 'MotoGuard AI');
        $schoolName = trim($_POST['school_name'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $yoloModel  = trim($_POST['yolo_model'] ?? 'YOLOv8n-Moto-Helmet-OCR');
        $yoloConf   = max(0.1, min(1.0, (float)($_POST['yolo_confidence'] ?? 0.50)));
        $ocrThresh  = max(0.1, min(1.0, (float)($_POST['ocr_threshold'] ?? 0.80)));
        $helmetAlrt = isset($_POST['helmet_alert_enabled']) ? 1 : 0;
        $guestAlrt  = isset($_POST['guest_alert_enabled']) ? 1 : 0;
        $autoRef    = isset($_POST['auto_refresh']) ? 1 : 0;
        $refInt     = max(1, min(60, (int)($_POST['refresh_interval'] ?? 5)));
        $soundEn    = isset($_POST['sound_enabled']) ? 1 : 0;
        $apiKey     = trim($_POST['api_key'] ?? 'MOTO_AI_KEY_2026');

        $stmt = $pdo->prepare("
            UPDATE system_settings
            SET system_name = ?, school_name = ?, address = ?, yolo_model = ?,
                yolo_confidence = ?, ocr_threshold = ?, helmet_alert_enabled = ?,
                guest_alert_enabled = ?, auto_refresh = ?, refresh_interval = ?,
                sound_enabled = ?, api_key = ?
            WHERE id = 1
        ");
        $stmt->execute([
            $systemName, $schoolName, $address, $yoloModel, $yoloConf, $ocrThresh,
            $helmetAlrt, $guestAlrt, $autoRef, $refInt, $soundEn, $apiKey
        ]);
        $flash = 'Đã lưu cấu hình hệ thống & tham số AI thành công.';
    }

    // 2. Lưu cấu hình Camera Cổng A / Cổng B
    if ($formType === 'save_camera') {
        $camId      = (int)($_POST['camera_id'] ?? 0);
        $camName    = trim($_POST['name'] ?? '');
        $location   = trim($_POST['location'] ?? '');
        $streamType = $_POST['stream_type'] ?? 'demo';
        $streamUrl  = trim($_POST['stream_url'] ?? '');
        $isEnabled  = isset($_POST['is_enabled']) ? 1 : 0;

        if (!in_array($streamType, ['demo', 'hls', 'mjpeg', 'snapshot'], true)) {
            $streamType = 'demo';
        }

        if (!$isEnabled) {
            $status = 'Đã tắt';
        } elseif ($streamType === 'demo') {
            $status = 'Đang hoạt động (Demo)';
        } elseif ($streamType === 'snapshot') {
            $status = 'Chế độ Snapshot API';
        } elseif ($streamUrl === '') {
            $status = 'Chưa cấu hình URL';
            $isEnabled = 0;
        } else {
            $status = 'Đang phát (' . strtoupper($streamType) . ')';
        }

        if ($camId === 1 || $camId === 2) {
            $stmt = $pdo->prepare("UPDATE cameras SET name = ?, location = ?, stream_type = ?, stream_url = ?, is_enabled = ?, status = ? WHERE id = ?");
            $stmt->execute([$camName, $location, $streamType, $streamUrl, $isEnabled, $status, $camId]);
            $flash = "Đã cập nhật cấu hình {$camName}.";
        }
    }

    // 3. Thêm xe Sinh viên / Giảng viên mới
    if ($formType === 'add_vehicle') {
        $plate = strtoupper(trim($_POST['license_plate'] ?? ''));
        $owner = trim($_POST['owner_name'] ?? '');
        $role  = ($_POST['owner_role'] ?? 'Sinh viên') === 'Giảng viên' ? 'Giảng viên' : 'Sinh viên';
        $model = trim($_POST['vehicle_model'] ?? '');

        if ($plate === '' || $owner === '') {
            $error = 'Vui lòng nhập đầy đủ Biển số xe và Họ tên chủ xe.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO registered_vehicles (license_plate, owner_name, owner_role, vehicle_model, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$plate, $owner, $role, $model]);
                $flash = "Đã đăng ký biển số {$plate} ({$role} - {$owner}).";
            } catch (PDOException $ex) {
                $error = "Biển số {$plate} đã tồn tại trong hệ thống.";
            }
        }
    }

    // 4. Xóa biển số đã đăng ký
    if ($formType === 'delete_vehicle') {
        $vid = (int)($_POST['vehicle_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM registered_vehicles WHERE id = ?");
        $stmt->execute([$vid]);
        $flash = 'Đã xóa biển số khỏi danh sách đăng ký.';
    }
}

$settings = getSystemSettings($pdo);
$cameras  = $pdo->query("SELECT * FROM cameras WHERE id IN (1, 2) ORDER BY id ASC")->fetchAll();
$vehicles = $pdo->query("SELECT * FROM registered_vehicles ORDER BY owner_role ASC, id DESC")->fetchAll();

$pageTitle  = 'Cài đặt hệ thống & Quản lý Biển số';
$activePage = 'settings';
require_once __DIR__ . '/includes/layout_top.php';
?>

<?php if ($flash): ?>
<div class="mb-5 p-3.5 rounded-xl bg-emerald-950/70 border border-emerald-700/60 text-emerald-200 text-sm flex items-center gap-2">
    <i class="fa-solid fa-circle-check text-emerald-400"></i>
    <span><?= e($flash) ?></span>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="mb-5 p-3.5 rounded-xl bg-rose-950/70 border border-rose-700/60 text-rose-200 text-sm flex items-center gap-2">
    <i class="fa-solid fa-circle-exclamation text-rose-400"></i>
    <span><?= e($error) ?></span>
</div>
<?php endif; ?>

<!-- PHẦN 1: QUẢN LÝ DANH SÁCH XE SINH VIÊN & GIẢNG VIÊN -->
<div class="bg-cardbg rounded-xl border border-borderdark shadow-lg overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-borderdark flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="font-bold text-white text-sm"><i class="fa-solid fa-id-card text-sky-400 mr-2"></i>Danh sách biển số Xe Sinh viên &amp; Xe Giảng viên</h2>
            <p class="text-xs text-slate-400 mt-0.5">Hệ thống tự động đối chiếu biển số AI gửi lên với danh sách này để phân loại vai trò</p>
        </div>
        <span class="text-xs font-mono text-sky-400"><?= count($vehicles) ?> biển số đã đăng ký</span>
    </div>

    <div class="p-5 border-b border-borderdark bg-[#0f1626]">
        <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs items-end">
            <input type="hidden" name="form_type" value="add_vehicle">
            <div>
                <label class="block text-slate-400 mb-1">Biển số xe *</label>
                <input type="text" name="license_plate" required placeholder="VD: 65B1-999.88"
                       class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-amber-300 font-mono uppercase">
            </div>
            <div>
                <label class="block text-slate-400 mb-1">Họ tên chủ xe *</label>
                <input type="text" name="owner_name" required placeholder="Nguyễn Văn B"
                       class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white">
            </div>
            <div>
                <label class="block text-slate-400 mb-1">Vai trò *</label>
                <select name="owner_role" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-slate-200">
                    <option value="Sinh viên">Sinh viên</option>
                    <option value="Giảng viên">Giảng viên</option>
                </select>
            </div>
            <div>
                <label class="block text-slate-400 mb-1">Dòng xe (tùy chọn)</label>
                <input type="text" name="vehicle_model" placeholder="VD: Honda Vision"
                       class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white">
            </div>
            <div>
                <button type="submit" class="w-full py-2 px-3 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-semibold transition">
                    <i class="fa-solid fa-plus mr-1"></i> Thêm biển số
                </button>
            </div>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0f1626] text-slate-400 text-xs uppercase border-b border-borderdark">
                <tr>
                    <th class="px-5 py-3">Biển số</th>
                    <th class="px-5 py-3">Chủ phương tiện</th>
                    <th class="px-5 py-3">Vai trò</th>
                    <th class="px-5 py-3">Dòng xe</th>
                    <th class="px-5 py-3 text-right">Thao tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/70">
                <?php foreach ($vehicles as $v): ?>
                <tr class="hover:bg-slate-800/40">
                    <td class="px-5 py-2.5"><span class="px-2 py-0.5 rounded bg-slate-950 border border-slate-700 font-mono font-bold text-amber-300 text-xs"><?= e($v['license_plate']) ?></span></td>
                    <td class="px-5 py-2.5 text-slate-200 text-xs font-medium"><?= e($v['owner_name']) ?></td>
                    <td class="px-5 py-2.5">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $v['owner_role'] === 'Giảng viên' ? 'bg-amber-950/80 text-amber-300 border border-amber-700/60' : 'bg-sky-950/80 text-sky-300 border border-sky-700/60' ?>">
                            <?= e($v['owner_role']) ?>
                        </span>
                    </td>
                    <td class="px-5 py-2.5 text-slate-400 text-xs"><?= e($v['vehicle_model'] ?: '—') ?></td>
                    <td class="px-5 py-2.5 text-right">
                        <form method="POST" onsubmit="return confirm('Xóa biển số này?')" class="inline">
                            <input type="hidden" name="form_type" value="delete_vehicle">
                            <input type="hidden" name="vehicle_id" value="<?= (int)$v['id'] ?>">
                            <button type="submit" class="text-slate-500 hover:text-rose-400 text-xs"><i class="fa-solid fa-trash-can"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- PHẦN 2: CẤU HÌNH NGUỒN CAMERA CỔNG A & CỔNG B -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <?php foreach ($cameras as $cam): ?>
    <form method="POST" class="bg-cardbg rounded-xl border border-borderdark p-5 shadow-lg space-y-3.5 text-xs">
        <input type="hidden" name="form_type" value="save_camera">
        <input type="hidden" name="camera_id" value="<?= (int)$cam['id'] ?>">

        <div class="flex items-center justify-between border-b border-borderdark pb-3">
            <h3 class="font-bold text-white text-sm"><i class="fa-solid fa-video text-sky-400 mr-2"></i>Cấu hình Camera 0<?= (int)$cam['id'] ?></h3>
            <span class="px-2.5 py-0.5 rounded-full font-semibold <?= $cam['is_enabled'] ? 'bg-emerald-950/80 text-emerald-300' : 'bg-slate-800 text-slate-400' ?>">
                <?= e($cam['status']) ?>
            </span>
        </div>

        <div>
            <label class="block text-slate-400 mb-1">Tên hiển thị</label>
            <input type="text" name="name" value="<?= e($cam['name']) ?>" required class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white">
        </div>
        <div>
            <label class="block text-slate-400 mb-1">Vị trí lắp đặt</label>
            <input type="text" name="location" value="<?= e($cam['location']) ?>" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-slate-200">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-slate-400 mb-1">Chế độ luồng hình ảnh</label>
                <select name="stream_type" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-sky-300 font-semibold">
                    <option value="demo" <?= $cam['stream_type'] === 'demo' ? 'selected' : '' ?>>Demo (Mô phỏng)</option>
                    <option value="hls" <?= $cam['stream_type'] === 'hls' ? 'selected' : '' ?>>HLS (.m3u8)</option>
                    <option value="mjpeg" <?= $cam['stream_type'] === 'mjpeg' ? 'selected' : '' ?>>MJPEG (Python/ESP32)</option>
                    <option value="snapshot" <?= $cam['stream_type'] === 'snapshot' ? 'selected' : '' ?>>Snapshot (Ảnh chụp API)</option>
                </select>
            </div>
            <div class="flex items-end pb-1.5">
                <label class="inline-flex items-center gap-2 cursor-pointer text-slate-200">
                    <input type="checkbox" name="is_enabled" value="1" <?= $cam['is_enabled'] ? 'checked' : '' ?> class="w-4 h-4 accent-sky-500">
                    <span>Kích hoạt Camera này</span>
                </label>
            </div>
        </div>
        <div>
            <label class="block text-slate-400 mb-1">Đường dẫn Stream URL (HLS / MJPEG)</label>
            <input type="text" name="stream_url" value="<?= e($cam['stream_url']) ?>" placeholder="VD: http://192.168.1.50:5000/video_feed hoặc hls/cam01.m3u8"
                   class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-slate-200 font-mono">
        </div>
        <div class="pt-1 text-right">
            <button type="submit" class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-semibold transition">
                <i class="fa-solid fa-floppy-disk mr-1"></i> Lưu Camera 0<?= (int)$cam['id'] ?>
            </button>
        </div>
    </form>
    <?php endforeach; ?>
</div>

<!-- PHẦN 3: CÀI ĐẶT CHUNG & THÔNG SỐ API GHÉP NỐI -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <!-- Form cài đặt hệ thống (7 cột) -->
    <form method="POST" class="lg:col-span-7 bg-cardbg rounded-xl border border-borderdark p-5 shadow-lg space-y-4 text-xs">
        <input type="hidden" name="form_type" value="save_system">
        <h2 class="font-bold text-white text-sm border-b border-borderdark pb-3"><i class="fa-solid fa-gear text-sky-400 mr-2"></i>Cấu hình Hệ thống &amp; Ngưỡng AI</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-slate-400 mb-1">Tên hệ thống</label>
                <input type="text" name="system_name" value="<?= e($settings['system_name']) ?>" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white">
            </div>
            <div>
                <label class="block text-slate-400 mb-1">Tên đơn vị / Trường</label>
                <input type="text" name="school_name" value="<?= e($settings['school_name']) ?>" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-slate-400 mb-1">Địa chỉ</label>
                <input type="text" name="address" value="<?= e($settings['address']) ?>" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-slate-200">
            </div>
            <div>
                <label class="block text-slate-400 mb-1">Mô hình AI (YOLO + OCR)</label>
                <input type="text" name="yolo_model" value="<?= e($settings['yolo_model']) ?>" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-sky-300 font-mono">
            </div>
            <div>
                <label class="block text-slate-400 mb-1">API Key bảo vệ Endpoint</label>
                <input type="text" name="api_key" value="<?= e($settings['api_key']) ?>" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-amber-300 font-mono">
            </div>
            <div>
                <label class="block text-slate-400 mb-1">Ngưỡng tin cậy YOLO (0.1 – 1.0)</label>
                <input type="number" step="0.05" min="0.1" max="1" name="yolo_confidence" value="<?= e($settings['yolo_confidence']) ?>" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white">
            </div>
            <div>
                <label class="block text-slate-400 mb-1">Ngưỡng đọc biển số OCR (0.1 – 1.0)</label>
                <input type="number" step="0.05" min="0.1" max="1" name="ocr_threshold" value="<?= e($settings['ocr_threshold']) ?>" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white">
            </div>
        </div>

        <div class="flex flex-wrap gap-5 pt-2 text-slate-300">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="helmet_alert_enabled" <?= $settings['helmet_alert_enabled'] ? 'checked' : '' ?> class="accent-sky-500"> Cảnh báo không đội mũ BH</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="guest_alert_enabled" <?= $settings['guest_alert_enabled'] ? 'checked' : '' ?> class="accent-sky-500"> Cảnh báo xe khách lạ</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="auto_refresh" <?= $settings['auto_refresh'] ? 'checked' : '' ?> class="accent-sky-500"> Tự động làm mới</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="sound_enabled" <?= $settings['sound_enabled'] ? 'checked' : '' ?> class="accent-sky-500"> Âm thanh cảnh báo</label>
        </div>

        <div class="pt-2 text-right">
            <button type="submit" class="px-5 py-2 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-semibold transition">
                <i class="fa-solid fa-floppy-disk mr-1"></i> Lưu cài đặt hệ thống
            </button>
        </div>
    </form>

    <!-- Tài liệu ghép nối API cho đội Nhúng / AI (5 cột) -->
    <div class="lg:col-span-5 bg-cardbg rounded-xl border border-borderdark p-5 shadow-lg text-xs flex flex-col justify-between">
        <div>
            <h2 class="font-bold text-white text-sm border-b border-borderdark pb-3 mb-3">
                <i class="fa-solid fa-plug text-emerald-400 mr-2"></i>Thông số ghép nối API (Nhóm AI / Nhúng)
            </h2>
            <div class="space-y-2.5 text-slate-300">
                <div>
                    <span class="text-slate-400">Endpoint (POST):</span>
                    <div class="mt-1 p-2 rounded bg-slate-950 border border-slate-800 font-mono text-emerald-400 break-all">
                        http://localhost:8080/moto_dashboard/api/detection.php
                    </div>
                </div>
                <div>
                    <span class="text-slate-400">Header bảo mật:</span>
                    <div class="mt-1 p-2 rounded bg-slate-950 border border-slate-800 font-mono text-amber-300">
                        X-API-KEY: <?= e($settings['api_key']) ?>
                    </div>
                </div>
                <div>
                    <span class="text-slate-400">Cấu trúc JSON gửi lên:</span>
<pre class="mt-1 p-3 rounded bg-slate-950 border border-slate-800 font-mono text-[11px] text-sky-300 overflow-x-auto">{
  "camera_id": 1,
  "license_plate": "65B1-123.45",
  "has_helmet": 1,
  "direction": "Vào",
  "confidence": 0.96,
  "x": 25, "y": 22, "width": 26, "height": 44
}</pre>
                </div>
            </div>
        </div>
        <p class="text-[11px] text-slate-500 mt-3">
            * Ghi chú: Đội AI không cần tự phân loại Sinh viên/Giảng viên. Chỉ cần gửi đúng <b>license_plate</b>, Dashboard sẽ tự đối chiếu bảng <code>registered_vehicles</code>.
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>