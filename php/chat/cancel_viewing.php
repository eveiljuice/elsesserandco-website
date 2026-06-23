<?php
/**
 * /php/chat/cancel_viewing.php
 *
 * Отмена просмотра клиентом или агентом. Меняет status в `viewings` на cancelled
 * и пишет системное сообщение в чат.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/check_auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
if (!validateCSRFToken($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'bad_csrf']);
    exit;
}

$userId = getCurrentUserId();
$viewingId = (int)($input['viewing_id'] ?? 0);

if ($viewingId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'missing_viewing']);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM viewings WHERE id = ?");
    $stmt->execute([$viewingId]);
    $v = $stmt->fetch();

    if (!$v) {
        echo json_encode(['ok' => false, 'error' => 'not_found']);
        exit;
    }

    // Доступ: либо клиент, либо агент, либо админ
    $isClient = ((int)$v['client_id'] === $userId);
    $isAgent  = ((int)$v['agent_id']  === $userId);
    $isAdmin  = (getCurrentUserRole() === 'admin');
    if (!$isClient && !$isAgent && !$isAdmin) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }
    if ($v['status'] !== 'scheduled') {
        echo json_encode(['ok' => false, 'error' => 'already_finalized', 'status' => $v['status']]);
        exit;
    }

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE viewings SET status = 'cancelled' WHERE id = ?")->execute([$viewingId]);

    // Системное сообщение в чат
    $systemText = sprintf(
        '❌ Просмотр отменён (%s, %s)',
        date('d.m.Y', strtotime($v['viewing_date'])),
        mb_substr($v['viewing_time'], 0, 5)
    );
    $metadata = json_encode([
        'kind'        => 'viewing_cancelled',
        'viewing_id'  => $viewingId,
        'property_id' => $v['property_id'],
    ], JSON_UNESCAPED_UNICODE);

    // Отмена — сообщение пишет тот, кто отменил. Адресат — другая сторона.
    $otherId = $isClient ? (int)$v['agent_id'] : (int)$v['client_id'];
    if ($otherId > 0) {
        $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, property_id, message, is_system, metadata, is_read, created_at)
            VALUES (?, ?, ?, ?, 1, ?, 0, NOW())
        ")->execute([$userId, $otherId, $v['property_id'], $systemText, $metadata]);
    }

    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('cancel_viewing error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}
