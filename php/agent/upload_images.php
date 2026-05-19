<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/check_auth.php';
require_once __DIR__ . '/../../includes/auth/csrf_json.php';
require_once __DIR__ . '/../../includes/upload/ImageProcessor.php';

requireAgent();
requireJsonCsrf();

$propertyId = (int)($_POST['property_id'] ?? 0);
if ($propertyId <= 0 || empty($_FILES['images'])) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare('SELECT id FROM properties WHERE id = ? AND agent_id = ?');
$stmt->execute([$propertyId, getCurrentUserId()]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

$urls = [];
$sort = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM property_images WHERE property_id = {$propertyId}")->fetchColumn();

foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
    if (!$tmp || !is_uploaded_file($tmp)) continue;
    $base = 'property_' . $propertyId . '_' . uniqid();
    try {
        $processed = ImageProcessor::processUpload($tmp, '/uploads/properties', $base);
        $url = $processed['medium'] ?? $processed['original'] ?? null;
        if ($url) {
            $ins = $pdo->prepare('INSERT INTO property_images (property_id, image_url, is_primary, sort_order) VALUES (?, ?, 0, ?)');
            $ins->execute([$propertyId, $url, ++$sort]);
            $urls[] = $url;
        }
    } catch (Throwable $e) {
        error_log('upload_images: ' . $e->getMessage());
    }
}

echo json_encode(['success' => true, 'urls' => $urls]);
