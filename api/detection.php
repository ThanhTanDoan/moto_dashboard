<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// 1. GIẢ LẬP DỮ LIỆU TỪ AI
if ($method === 'POST' && $action === 'simulate') {
    $samplePlates = [
        '65B1-123.45', // Sinh viên
        '65B2-456.78', // Sinh viên
        '65F1-334.56', // Sinh viên
        '65C1-889.12', // Giảng viên
        '65D1-202.68', // Giảng viên
        '64E1-902.11', // Khách
        '95F1-412.88', // Khách
    ];
    $plate = $samplePlates[array_rand($samplePlates)];
    $vehicleGroup = classifyVehicleByPlate($pdo, $plate);

    // Giảng viên thường ưu tiên Cổng A (1), Sinh viên đi cả Cổng A & B
    $cameraId = ($vehicleGroup === 'Xe Giảng viên') ? 1 : rand(1, 2);
    $direction = (rand(0, 10) <= 6) ? 'Vào' : 'Ra';
    $hasHelmet = (rand(1, 10) <= 7) ? 1 : 0;
    $confidence = round(mt_rand(87, 99) / 100, 2);

    $stmt = $pdo->prepare("
        INSERT INTO detections
        (camera_id, track_id, license_plate, vehicle_group, has_helmet, confidence, direction, bbox_x, bbox_y, bbox_w, bbox_h, detection_time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $cameraId, rand(200, 999), $plate, $vehicleGroup, $hasHelmet, $confidence,
        $direction, rand(15, 55), rand(20, 40), rand(22, 30), rand(38, 48)
    ]);

    $gate = ($cameraId === 1) ? 'Cổng A' : 'Cổng B';
    echo json_encode([
        'success' => true,
        'message' => "Đã nhận diện {$plate} ({$vehicleGroup} - " . ($hasHelmet ? 'Có mũ' : 'KHÔNG MŨ') . ") đi {$direction} qua {$gate}.",
        'id' => (int)$pdo->lastInsertId()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. NHẬN DỮ LIỆU THẬT TỪ NHÓM AI / NHÚNG (POST JSON)
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dữ liệu gửi lên phải là JSON hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $settings = getSystemSettings($pdo);
    $headerKey = $_SERVER['HTTP_X_API_KEY'] ?? ($input['api_key'] ?? '');
    if (!empty($headerKey) && $headerKey !== $settings['api_key']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'API Key không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cameraId = (int)($input['camera_id'] ?? 1);
    if ($cameraId !== 1 && $cameraId !== 2) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'camera_id phải là 1 (Cổng A) hoặc 2 (Cổng B).'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $plate = strtoupper(trim((string)($input['license_plate'] ?? 'CHƯA ĐỌC ĐƯỢC')));
    $direction = (isset($input['direction']) && $input['direction'] === 'Ra') ? 'Ra' : 'Vào';
    $hasHelmet = isset($input['has_helmet']) ? ((int)$input['has_helmet'] ? 1 : 0) : 1;
    $confidence = (float)($input['confidence'] ?? 0.90);
    if ($confidence < 0 || $confidence > 1) $confidence = 0.90;

    // Nếu AI không gửi vehicle_group thì tự tra cứu từ biển số
    $vehicleGroup = !empty($input['vehicle_group'])
        ? trim((string)$input['vehicle_group'])
        : classifyVehicleByPlate($pdo, $plate);

    // Lưu ảnh snapshot nếu AI gửi image_base64
    $imagePath = null;
    if (!empty($input['image_base64'])) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $input['image_base64']);
        $imgData = base64_decode($base64);
        if ($imgData !== false) {
            $fileName = 'moto_' . date('Ymd_His') . '_' . uniqid() . '.jpg';
            file_put_contents($uploadDir . $fileName, $imgData);
            $imagePath = 'uploads/' . $fileName;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO detections
        (camera_id, track_id, license_plate, vehicle_group, has_helmet, confidence, direction, bbox_x, bbox_y, bbox_w, bbox_h, image_path, detection_time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $cameraId,
        isset($input['track_id']) ? (int)$input['track_id'] : null,
        $plate,
        $vehicleGroup,
        $hasHelmet,
        $confidence,
        $direction,
        (float)($input['x'] ?? 20),
        (float)($input['y'] ?? 25),
        (float)($input['width'] ?? 26),
        (float)($input['height'] ?? 42),
        $imagePath
    ]);

    echo json_encode([
        'success'       => true,
        'message'       => 'Đã lưu dữ liệu nhận diện xe gắn máy.',
        'id'            => (int)$pdo->lastInsertId(),
        'classified_as' => $vehicleGroup,
        'image_path'    => $imagePath
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. LẤY DỮ LIỆU LIVE (GET)
if ($method === 'GET') {
    $cameraId = isset($_GET['camera_id']) ? (int)$_GET['camera_id'] : 0;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 15;

    if ($cameraId === 1 || $cameraId === 2) {
        $stmt = $pdo->prepare("SELECT * FROM detections WHERE camera_id = ? ORDER BY detection_time DESC LIMIT ?");
        $stmt->bindValue(1, $cameraId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM detections ORDER BY detection_time DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
    }

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);