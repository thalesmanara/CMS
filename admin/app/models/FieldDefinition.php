<?php

declare(strict_types=1);

namespace Revita\Crm\Models;

use PDO;
use Revita\Crm\Core\Database;

final class FieldDefinition
{
    public const OWNER_PAGE = 'page';

    public const OWNER_POST = 'post';

    /** @return list<array<string, mixed>> */
    public function listByOwner(string $ownerType, int $ownerId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT f.id, f.owner_type, f.owner_id, f.section_id, f.field_key, f.label_name, f.field_type, f.order_index,
                    s.title AS section_title, s.order_index AS section_order
             FROM revita_crm_field_definitions f
             LEFT JOIN revita_crm_sections s ON s.id = f.section_id
             WHERE f.owner_type = :ot AND f.owner_id = :oid
             ORDER BY
               CASE WHEN f.section_id IS NULL THEN 0 ELSE 1 END ASC,
               COALESCE(s.order_index, 0) ASC,
               f.order_index ASC,
               f.id ASC'
        );
        $stmt->execute(['ot' => $ownerType, 'oid' => $ownerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function listByPageId(int $pageId): array
    {
        return $this->listByOwner(self::OWNER_PAGE, $pageId);
    }

    /** @return list<array<string, mixed>> */
    public function listByPostId(int $postId): array
    {
        return $this->listByOwner(self::OWNER_POST, $postId);
    }

    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, owner_type, owner_id, section_id, field_key, label_name, field_type, order_index
             FROM revita_crm_field_definitions WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function fieldKeyExists(string $ownerType, int $ownerId, string $key, ?int $excludeId = null): bool
    {
        $key = trim($key);
        if ($key === '') {
            return false;
        }
        $pdo = Database::pdo();
        $sql = 'SELECT 1 FROM revita_crm_field_definitions
                WHERE owner_type = :ot AND owner_id = :oid AND field_key = :fk';
        $p = ['ot' => $ownerType, 'oid' => $ownerId, 'fk' => $key];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :ex';
            $p['ex'] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($p);
        return (bool) $stmt->fetchColumn();
    }

    public function fieldKeyExistsOnPage(int $pageId, string $key, ?int $excludeId = null): bool
    {
        return $this->fieldKeyExists(self::OWNER_PAGE, $pageId, $key, $excludeId);
    }

    public function fieldKeyExistsOnPost(int $postId, string $key, ?int $excludeId = null): bool
    {
        return $this->fieldKeyExists(self::OWNER_POST, $postId, $key, $excludeId);
    }

    public function nextOrderIndexForOwner(string $ownerType, int $ownerId, ?int $sectionId = null): int
    {
        $pdo = Database::pdo();
        if ($sectionId === null) {
            $stmt = $pdo->prepare(
                'SELECT COALESCE(MAX(order_index), 0) + 1 AS n
                 FROM revita_crm_field_definitions
                 WHERE owner_type = :ot AND owner_id = :oid AND section_id IS NULL'
            );
            $stmt->execute(['ot' => $ownerType, 'oid' => $ownerId]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT COALESCE(MAX(order_index), 0) + 1 AS n
                 FROM revita_crm_field_definitions
                 WHERE owner_type = :ot AND owner_id = :oid AND section_id = :sid'
            );
            $stmt->execute(['ot' => $ownerType, 'oid' => $ownerId, 'sid' => $sectionId]);
        }
        return (int) $stmt->fetchColumn();
    }

    public function nextOrderIndex(int $pageId): int
    {
        return $this->nextOrderIndexForOwner(self::OWNER_PAGE, $pageId, null);
    }

    public function nextOrderIndexForPost(int $postId): int
    {
        return $this->nextOrderIndexForOwner(self::OWNER_POST, $postId, null);
    }

    public function insertForOwner(
        string $ownerType,
        int $ownerId,
        ?int $sectionId,
        string $fieldKey,
        string $label,
        string $fieldType,
        int $orderIndex
    ): int {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO revita_crm_field_definitions
             (owner_type, owner_id, section_id, field_key, label_name, field_type, order_index, updated_at)
             VALUES (:ot, :oid, :sid, :fk, :lb, :ft, :ord, NOW())'
        );
        $stmt->execute([
            'ot' => $ownerType,
            'oid' => $ownerId,
            'sid' => $sectionId,
            'fk' => $fieldKey,
            'lb' => $label,
            'ft' => $fieldType,
            'ord' => $orderIndex,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function insert(int $pageId, string $fieldKey, string $label, string $fieldType, int $orderIndex): int
    {
        return $this->insertForOwner(self::OWNER_PAGE, $pageId, null, $fieldKey, $label, $fieldType, $orderIndex);
    }

    public function insertForPost(int $postId, string $fieldKey, string $label, string $fieldType, int $orderIndex): int
    {
        return $this->insertForOwner(self::OWNER_POST, $postId, null, $fieldKey, $label, $fieldType, $orderIndex);
    }

    public function insertForOwnerInSection(
        string $ownerType,
        int $ownerId,
        ?int $sectionId,
        string $fieldKey,
        string $label,
        string $fieldType,
        int $orderIndex
    ): int {
        return $this->insertForOwner($ownerType, $ownerId, $sectionId, $fieldKey, $label, $fieldType, $orderIndex);
    }

    /** @param list<int> $orderedIds */
    public function reorderOnOwner(string $ownerType, int $ownerId, array $orderedIds): void
    {
        $pdo = Database::pdo();
        $ord = 0;
        foreach ($orderedIds as $fid) {
            $fid = (int) $fid;
            if ($fid < 1) {
                continue;
            }
            $stmt = $pdo->prepare(
                'UPDATE revita_crm_field_definitions SET order_index = :ord, updated_at = NOW()
                 WHERE id = :id AND owner_type = :ot AND owner_id = :pid'
            );
            $stmt->execute(['ord' => $ord++, 'id' => $fid, 'ot' => $ownerType, 'pid' => $ownerId]);
        }
    }

    /**
     * @param array<int, array{section_id:int|null, order:int}> $map
     */
    public function applySectionAndOrderMap(string $ownerType, int $ownerId, array $map): void
    {
        $pdo = Database::pdo();
        foreach ($map as $fieldId => $m) {
            $fid = (int) $fieldId;
            if ($fid < 1) {
                continue;
            }
            $sid = $m['section_id'];
            $ord = (int) $m['order'];
            $stmt = $pdo->prepare(
                'UPDATE revita_crm_field_definitions
                 SET section_id = :sid, order_index = :ord, updated_at = NOW()
                 WHERE id = :id AND owner_type = :ot AND owner_id = :oid'
            );
            $stmt->execute([
                'sid' => $sid,
                'ord' => $ord,
                'id' => $fid,
                'ot' => $ownerType,
                'oid' => $ownerId,
            ]);
        }
    }

    /** @param list<int> $orderedIds */
    public function reorderOnPage(int $pageId, array $orderedIds): void
    {
        $this->reorderOnOwner(self::OWNER_PAGE, $pageId, $orderedIds);
    }

    /** @param list<int> $orderedIds */
    public function reorderOnPost(int $postId, array $orderedIds): void
    {
        $this->reorderOnOwner(self::OWNER_POST, $postId, $orderedIds);
    }

    public function deleteRow(int $id): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM revita_crm_field_definitions WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
