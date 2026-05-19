<?php
declare(strict_types=1);

require_once __DIR__ . '/../properties/catalog_query.php';

final class SavedSearchService
{
    public static function save(PDO $pdo, int $userId, string $name, array $filters, bool $notifyEmail = true): int
    {
        $json = json_encode($filters, JSON_UNESCAPED_UNICODE);
        $stmt = $pdo->prepare("
            INSERT INTO saved_searches (user_id, name, filters_json, notify_email)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $name, $json, $notifyEmail ? 1 : 0]);
        return (int)$pdo->lastInsertId();
    }

    public static function listForUser(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare("SELECT * FROM saved_searches WHERE user_id = ? ORDER BY updated_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function delete(PDO $pdo, int $userId, int $id): bool
    {
        $stmt = $pdo->prepare("DELETE FROM saved_searches WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    /** @return array<int, array> property rows */
    public static function findNewMatches(PDO $pdo, array $savedRow, int $limit = 20): array
    {
        $filters = json_decode((string)$savedRow['filters_json'], true);
        if (!is_array($filters)) {
            return [];
        }
        $parsed = catalogParseFilters($filters);
        $parsed['page'] = 1;
        $parsed['perPage'] = $limit;
        $parsed['offset'] = 0;

        $whereSql = implode(' AND ', $parsed['where']);
        $params = $parsed['params'];

        $stmt = $pdo->prepare("
            SELECT p.id, p.title_ru, p.price, p.category, p.slug
            FROM properties p
            WHERE {$whereSql}
              AND p.created_at > COALESCE(?, '1970-01-01')
            ORDER BY p.created_at DESC
            LIMIT ?
        ");
        $since = $savedRow['last_notified_at'] ?? $savedRow['created_at'];
        $exec = array_merge($params, [$since, $limit]);
        $stmt->execute($exec);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
