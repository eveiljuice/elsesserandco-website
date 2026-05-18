<?php
/**
 * Notifier — высокоуровневая отправка push-уведомлений пользователю.
 * Чистит протухшие подписки (410 Gone, 404 Not Found).
 *
 * Использование (в send_message.php, inquiries/submit.php и т.д.):
 *   Notifier::push($userId, ['title' => 'Новое сообщение', 'body' => '…', 'url' => '/chat.php?user=42']);
 */

require_once __DIR__ . '/WebPush.php';
require_once __DIR__ . '/../config/database.php';

final class Notifier
{
    public static function push(int $userId, array $payload): void
    {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, endpoint, p256dh_key, auth_key FROM push_subscriptions WHERE user_id = ?");
        $stmt->execute([$userId]);

        $stale = [];
        while ($row = $stmt->fetch()) {
            try {
                $resp = WebPush::send([
                    'endpoint' => $row['endpoint'],
                    'keys' => ['p256dh' => $row['p256dh_key'], 'auth' => $row['auth_key']],
                ], $payload);
                if (in_array($resp['status'], [404, 410], true)) {
                    $stale[] = (int)$row['id'];
                }
            } catch (Throwable $e) {
                error_log('Notifier push: ' . $e->getMessage());
            }
        }

        if ($stale) {
            $in = implode(',', array_fill(0, count($stale), '?'));
            $pdo->prepare("DELETE FROM push_subscriptions WHERE id IN ($in)")->execute($stale);
        }
    }
}
