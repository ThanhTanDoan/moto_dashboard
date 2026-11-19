<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Xóa 1 bản ghi (nếu cần)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del = $pdo->prepare("DELETE FROM detections WHERE id = ?");
    $del->execute([(int)$_POST['delete_id']]);
    header('Location: history.php');
    exit;
}

// Xây dựng bộ lọc
$q         = trim($_GET['q'] ?? '');
$cameraId  = $_GET['camera_id'] ?? '';
$group     = $_GET['vehicle_group'] ?? '';
$helmet    = $_GET['has_helmet'] ?? '';
$direction = $_GET['direction'] ?? '';
$fromDate  = $_GET['from'] ?? '';
$toDate    = $_GET['to'] ?? '';

$conds = ['1=1'];
$params = [];

if ($q !== '') {
    $conds[] = 'license_plate LIKE ?';
    $params[] = '%' . $q . '%';
}
if ($cameraId === '1' || $cameraId === '2') {
    $conds[] = 'camera_id = ?';
    $params[] = (int)$cameraId;
}
if (in_array($group, ['Xe Sinh viên', 'Xe Giảng viên', 'Xe khách'], true)) {
    $conds[] = 'vehicle_group = ?';
    $params[] = $group;
}
if ($helmet === '0' || $helmet === '1') {
    $conds[] = 'has_helmet = ?';
    $params[] = (int)$helmet;
}
if ($direction === 'Vào' || $direction === 'Ra') {
    $conds[] = 'direction = ?';
    $params[] = $direction;
}
if ($fromDate !== '') {
    $conds[] = 'DATE(detection_time) >= ?';
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $conds[] = 'DATE(detection_time) <= ?';
    $params[] = $toDate;
}

$whereSql = implode(' AND ', $conds);

// Xuất CSV (Excel) có UTF-8 BOM
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->prepare("SELECT * FROM detections WHERE {$whereSql} ORDER BY detection_time DESC");
    $stmt->execute($params);
    $exportRows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lich_su_xe_gan_may_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM cho Excel
    fputcsv($out, ['ID', 'Thời gian', 'Camera', 'Biển số xe', 'Phân loại', 'Mũ bảo hiểm', 'Hướng', 'Độ tin cậy (%)', 'Xác minh', 'Ghi chú']);
    foreach ($exportRows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['detection_time'],
            'Camera 0' . $r['camera_id'],
            $r['license_plate'],
            $r['vehicle_group'],
            $r['has_helmet'] ? 'Có đội mũ' : 'Không đội mũ',
            $r['direction'],
            round($r['confidence'] * 100, 1),
            $r['is_verified'] ? 'Đã xác minh' : 'Chưa xác minh',
            $r['note'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// Phân trang
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM detections WHERE {$whereSql}");
$cntStmt->execute($params);
$totalRows = (int)$cntStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$dataStmt = $pdo->prepare("SELECT * FROM detections WHERE {$whereSql} ORDER BY detection_time DESC LIMIT {$perPage} OFFSET {$offset}");
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll();

$pageTitle = 'Lịch sử ra/vào xe gắn máy';
$activePage = 'history';
require_once __DIR__ . '/includes/layout_top.php';
?>

<!-- Bộ lọc tìm kiếm -->
<form method="GET" class="bg-cardbg rounded-xl border border-borderdark p-4 mb-6 shadow-lg">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
        <div>
            <label class="block text-slate-400 mb-1">Tìm biển số xe</label>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="VD: 65B1..."
                   class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white outline-none focus:border-sky-500">
        </div>
        <div>
            <label class="block text-slate-400 mb-1">Camera</label>
            <select name="camera_id" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-slate-200">
                <option value="">Tất cả Camera</option>
                <!-- Select Camera -->
                <option value="1" <?= $cameraId === '1' ? 'selected' : '' ?>>Camera 01 - Cổng chính (Cổng A)</option>
                <option value="2" <?= $cameraId === '2' ? 'selected' : '' ?>>Camera 02 - Cổng phụ (Cổng B)</option>
            </select>
        </div>
        <div>
            <label class="block text-slate-400 mb-1">Phân loại xe</label>
            <!-- Select Phân loại xe -->
            <option value="Xe Sinh viên" <?= $group === 'Xe Sinh viên' ? 'selected' : '' ?>>Xe Sinh viên</option>
            <option value="Xe Giảng viên" <?= $group === 'Xe Giảng viên' ? 'selected' : '' ?>>Xe Giảng viên</option>
            <option value="Xe khách" <?= $group === 'Xe khách' ? 'selected' : '' ?>>Xe khách</option>
            </select>
        </div>
        <div>
            <label class="block text-slate-400 mb-1">Mũ bảo hiểm</label>
            <select name="has_helmet" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-slate-200">
                <option value="">Tất cả</option>
                <option value="1" <?= $helmet === '1' ? 'selected' : '' ?>>Có đội mũ</option>
                <option value="0" <?= $helmet === '0' ? 'selected' : '' ?>>Không đội mũ (Vi phạm)</option>
            </select>
        </div>
        <div>
            <label class="block text-slate-400 mb-1">Hướng</label>
            <select name="direction" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-slate-200">
                <option value="">Vào &amp; Ra</option>
                <option value="Vào" <?= $direction === 'Vào' ? 'selected' : '' ?>>Vào</option>
                <option value="Ra" <?= $direction === 'Ra' ? 'selected' : '' ?>>Ra</option>
            </select>
        </div>
        <div>
            <label class="block text-slate-400 mb-1">Từ ngày</label>
            <input type="date" name="from" value="<?= e($fromDate) ?>" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-slate-200">
        </div>
        <div>
            <label class="block text-slate-400 mb-1">Đến ngày</label>
            <input type="date" name="to" value="<?= e($toDate) ?>" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-slate-200">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 py-2 px-3 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-semibold transition">
                <i class="fa-solid fa-magnifying-glass mr-1"></i> Lọc
            </button>
            <a href="history.php" class="py-2 px-3 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold transition">Xóa lọc</a>
        </div>
    </div>
</form>

<!-- Bảng kết quả -->
<div class="bg-cardbg rounded-xl border border-borderdark shadow-lg overflow-hidden">
    <div class="px-5 py-3.5 border-b border-borderdark flex flex-wrap items-center justify-between gap-2">
        <div class="text-sm font-bold text-white">
            Nhật ký ra/vào <span class="text-xs font-normal text-slate-400 ml-1">(Tổng <?= $totalRows ?> bản ghi)</span>
        </div>
        <?php
        $exportQuery = $_GET;
        $exportQuery['export'] = 'csv';
        ?>
        <a href="history.php?<?= e(http_build_query($exportQuery)) ?>" class="px-3.5 py-1.5 rounded-lg bg-emerald-600/20 hover:bg-emerald-600/35 border border-emerald-500/40 text-emerald-300 text-xs font-semibold transition">
            <i class="fa-solid fa-file-csv mr-1"></i> Xuất file Excel (CSV)
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0f1626] text-slate-400 text-xs uppercase border-b border-borderdark">
                <tr>
                    <th class="px-4 py-3">Thời gian</th>
                    <th class="px-4 py-3">Biển số</th>
                    <th class="px-4 py-3">Phân loại</th>
                    <th class="px-4 py-3">Mũ bảo hiểm</th>
                    <th class="px-4 py-3">Hướng</th>
                    <th class="px-4 py-3">Camera</th>
                    <th class="px-4 py-3">Tin cậy</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3 text-right">Thao tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/70">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9" class="px-5 py-8 text-center text-slate-500">Không tìm thấy dữ liệu phù hợp.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr class="hover:bg-slate-800/40 transition">
                        <td class="px-4 py-3 font-mono text-xs text-slate-400 whitespace-nowrap"><?= date('H:i:s d/m/Y', strtotime($r['detection_time'])) ?></td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded bg-slate-950 border border-slate-700 font-mono font-bold text-amber-300 text-xs"><?= e($r['license_plate']) ?></span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold <?= badgeVehicleClass($r['vehicle_group']) ?>">
                                <?= e($r['vehicle_group']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <?php if ($r['has_helmet']): ?>
                                <span class="text-emerald-400 text-xs font-medium"><i class="fa-solid fa-shield-halved"></i> Có mũ</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded bg-rose-950/80 border border-rose-700/70 text-rose-300 text-xs font-bold"><i class="fa-solid fa-triangle-exclamation"></i> Không mũ</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs font-semibold <?= $r['direction'] === 'Vào' ? 'text-emerald-400' : 'text-amber-400' ?>"><?= e($r['direction']) ?></td>
                        <td class="px-4 py-3 text-xs text-slate-300 whitespace-nowrap">Camera 0<?= e(getCameraShortName($r['camera_id'])) ?></td>
                        <td class="px-4 py-3 font-mono text-xs text-sky-300"><?= round($r['confidence'] * 100, 1) ?>%</td>
                        <td class="px-4 py-3 text-xs">
                            <?= $r['is_verified'] ? '<span class="text-emerald-400">Đã duyệt</span>' : '<span class="text-slate-500">Chưa duyệt</span>' ?>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <form method="POST" onsubmit="return confirm('Xóa bản ghi này?')" class="inline">
                                <input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="text-slate-500 hover:text-rose-400 transition" title="Xóa">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Phân trang -->
    <?php if ($totalPages > 1): ?>
    <div class="px-5 py-3 border-t border-borderdark flex items-center justify-between text-xs text-slate-400">
        <span>Trang <?= $page ?> / <?= $totalPages ?></span>
        <div class="flex gap-1.5">
            <?php for ($p = 1; $p <= $totalPages; $p++):
                $pq = $_GET;
                $pq['page'] = $p;
                $activeP = ($p === $page);
            ?>
                <a href="history.php?<?= e(http_build_query($pq)) ?>"
                   class="px-3 py-1 rounded <?= $activeP ? 'bg-sky-600 text-white font-bold' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>