<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function currentUser() {
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? 'Người dùng',
        'role'      => $_SESSION['role'] ?? 'Nhân viên'
    ];
}

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// Tự động tra cứu biển số để phân loại: Xe Giảng viên / Xe Sinh viên / Xe khách
function classifyVehicleByPlate(PDO $pdo, $plate) {
    $plate = strtoupper(trim((string)$plate));
    $stmt = $pdo->prepare("SELECT owner_role FROM registered_vehicles WHERE license_plate = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$plate]);
    $row = $stmt->fetch();

    if (!$row) {
        return 'Xe khách';
    }
    return ($row['owner_role'] === 'Giảng viên') ? 'Xe Giảng viên' : 'Xe Sinh viên';
}

// Trả về class CSS màu sắc đồng nhất cho từng nhóm xe (Dark Mode)
function badgeVehicleClass($group) {
    if ($group === 'Xe Giảng viên') {
        return 'bg-amber-950/80 text-amber-300 border border-amber-700/60';
    }
    if ($group === 'Xe Sinh viên') {
        return 'bg-sky-950/80 text-sky-300 border border-sky-700/60';
    }
    return 'bg-purple-950/80 text-purple-300 border border-purple-700/60';
}

function getCameraShortName($cameraId) {
    return ((int)$cameraId === 1) ? 'Cổng A (CAM-01)' : 'Cổng B (CAM-02)';
}