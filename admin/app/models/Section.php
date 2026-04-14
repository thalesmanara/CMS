<?php

declare(strict_types=1);

namespace Revita\Crm\Models;

use PDO;
use Revita\Crm\Core\Database;

final class Section
{
    /** @return list<array<string,mixed>> */
    public function listByOwner(string $ownerType, int $ownerId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, owner_type, owner_id, title, order_index
             FROM revita_crm_sections
             WHERE owner_type = :ot AND owner_id = :oid
             ORDER BY order_index ASC, id ASC'
        );
        $stmt->execute(['ot' => $ownerType, 'oid' => $ownerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function nextOrderIndex(string $ownerType, int $ownerId): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT COALESCE(MAX(order_index), 0) + 1 AS n
             FROM revita_crm_sections
             WHERE owner_type = :ot AND owner_id = :oid'
        );
        $stmt->execute(['ot' => $ownerType, 'oid' => $ownerId]);
        return (int) $stmt->fetchColumn();
    }

    public function insert(string $ownerType, int $ownerId, string $title, int $orderIndex): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO revita_crm_sections (owner_type, owner_id, title, order_index, updated_at)
             VALUES (:ot, :oid, :t, :ord, NOW())'
        );
        $stmt->execute(['ot' => $ownerType, 'oid' => $ownerId, 't' => $title, 'ord' => $orderIndex]);
        return (int) $pdo->lastInsertId();
    }

    /** @param list<int> $orderedIds */
    public function reorder(string $ownerType, int $ownerId, array $orderedIds): void
    {
        $pdo = Database::pdo();
        $ord = 0;
        foreach ($orderedIds as $sid) {
            $sid = (int) $sid;
            if ($sid < 1) {
                continue;
            }
            $stmt = $pdo->prepare(
                'UPDATE revita_crm_sections SET order_index = :ord, updated_at = NOW()
                 WHERE id = :id AND owner_type = :ot AND owner_id = :oid'
            );
            $stmt->execute(['ord' => $ord++, 'id' => $sid, 'ot' => $ownerType, 'oid' => $ownerId]);
        }
    }

    public function delete(int $id): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM revita_crm_sections WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}

