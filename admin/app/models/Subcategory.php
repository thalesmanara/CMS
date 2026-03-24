<?php

declare(strict_types=1);

namespace Revita\Crm\Models;

use PDO;
use Revita\Crm\Core\Database;

final class Subcategory
{
    /** @return list<array<string, mixed>> */
    public function allByCategory(int $categoryId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, category_id, name, slug, created_at, updated_at
             FROM revita_crm_subcategories
             WHERE category_id = :cid
             ORDER BY name ASC, id ASC'
        );
        $stmt->execute(['cid' => $categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function allWithCategory(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            'SELECT s.id, s.category_id, s.name, s.slug, s.created_at, s.updated_at, c.name AS category_name
             FROM revita_crm_subcategories s
             INNER JOIN revita_crm_categories c ON c.id = s.category_id
             ORDER BY c.name ASC, s.name ASC, s.id ASC'
        );
        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, category_id, name, slug, created_at, updated_at
             FROM revita_crm_subcategories WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }
        $pdo = Database::pdo();
        $sql = 'SELECT 1 FROM revita_crm_subcategories WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude';
            $params['exclude'] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function insert(int $categoryId, string $name, string $slug): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO revita_crm_subcategories (category_id, name, slug, updated_at)
             VALUES (:cid, :name, :slug, NOW())'
        );
        $stmt->execute(['cid' => $categoryId, 'name' => $name, 'slug' => $slug]);
        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, int $categoryId, string $name, string $slug): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE revita_crm_subcategories
             SET category_id = :cid, name = :name, slug = :slug, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'cid' => $categoryId, 'name' => $name, 'slug' => $slug]);
    }

    public function delete(int $id): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM revita_crm_subcategories WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}

