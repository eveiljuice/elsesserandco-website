<?php
/**
 * /php/chat/schedule_viewing.php
 *
 * Создать viewing (показ объекта) из чата. Клиент в чате с агентом
 * нажимает «Хочу посмотреть», выбирает дату/время — здесь создаётся:
 *  1) запись в `viewings` (с client_id/sender_id из сессии)
 *  2) системное сообщение в `messages` (is_system=1, metadata JSON)
 *
 * Агент увидит viewing в /agent/calendar.php + сообщение в чате.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/check_auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$userId   = getCurrentUserId();
$agentId  = (int)($input['agent_id'] ?? 0);
$propertyId = (int)($input['property_id'] ?? 0);
$date     = trim($input['date'] ?? '');
$time     = trim($input['time'] ?? '');
$note     = trim($input['note'] ?? '');

if ($agentId <= 0 || $propertyId <= 0 || $date === '' || $time === '') {
    echo json_encode(['ok' => false, 'error' => 'missing_fields']);
    exit;
}

if ($agentId === $userId) {
    echo json_encode(['ok' => false, 'error' => 'cannot_schedule_with_self']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
    echo json_encode(['ok' => false, 'error' => 'bad_date_format']);
    exit;
}

if (strtotime("$date $time") < time() - 3600) {
    echo json_encode(['ok' => false, 'error' => 'date_in_past']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Проверяем, что property существует и агент действительно его владелец
    $stmt = $pdo->prepare("SELECT id, title, agent_id FROM properties WHERE id = ?");
    $stmt->execute([$propertyId]);
    $property = $stmt->fetch();

    if (!$property) {
        echo json_encode(['ok' => false, 'error' => 'property_not_found']);
        exit;
    }
    if ((int)$property['agent_id'] !== $agentId) {
        echo json_encode(['ok' => false, 'error' => 'agent_mismatch']);
        exit;
    }

    // Проверяем что агент — реально агент
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, phone FROM users WHERE id = ?");
    $stmt->execute([$agentId]);
    $agent = $stmt->fetch();
    if (!$agent) {
        echo json_encode(['ok' => false, 'error' => 'agent_not_found']);
        exit;
    }

    // Данные клиента
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $client = $stmt->fetch();
    if (!$client) {
        echo json_encode(['ok' => false, 'error' => 'client_not_found']);
        exit;
    }

    $pdo->beginTransaction();

    // 1) Создаём viewing
    $stmt = $pdo->prepare("
        INSERT INTO viewings
            (property_id, agent_id, client_id, client_name, client_phone, client_email,
             viewing_date, viewing_time, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
    ");
    $clientName = trim(($client['last_name'] ?? '') . ' ' . ($client['first_name'] ?? ''));
    $stmt->execute([
        $propertyId,
        $agentId,
        $userId,
        $clientName,
        $client['phone'] ?: null,
        $client['email'] ?: null,
        $date,
        $time,
        $note ?: null,
    ]);
    $viewingId = (int)$pdo->lastInsertId();

    // 2) Системное сообщение в чат
    $humanDate = date('d.m.Y', strtotime($date));
    $humanTime = mb_substr($time, 0, 5);
    $systemText = sprintf(
        '📅 Запись на просмотр\n%s, %s\nАгент: %s\nОбъект: %s',
        $humanDate,
        $humanTime,
        trim($agent['first_name'] . ' ' . ($agent['last_name'] ?? '')),
        $property['title']
    );

    $metadata = json_encode([
        'kind'        => 'viewing_scheduled',
        'viewing_id'  => $viewingId,
        'property_id' => $propertyId,
        'agent_id'    => $agentId,
        'date'        => $date,
        'time'        => $time,
    ], JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare("
        INSERT INTO messages
            (sender_id, receiver_id, property_id, message, is_system, metadata, is_read, created_at)
        VALUES (?, ?, ?, ?, 1, ?, 0, NOW())
    ");
    // Сообщение идёт от клиента (sender) к агенту (receiver)
    $stmt->execute([$userId, $agentId, $propertyId, $systemText, $metadata]);

    $pdo->commit();

    echo json_encode([
        'ok'         => true,
        'viewing_id' => $viewingId,
        'date'       => $humanDate,
        'time'       => $humanTime,
        'message'    => $systemText,
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('schedule_viewing error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}
