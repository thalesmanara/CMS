<?php

declare(strict_types=1);

namespace Revita\Crm\Models;

use PDO;
use Revita\Crm\Core\Database;

final class Category
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            'SELECT id, name, slug, created_at, updated_at
             FROM revita_crm_categories
             ORDER BY name ASC, id ASC'
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
            'SELECT id, name, slug, created_at, updated_at
             FROM revita_crm_categories WHERE id = :id LIMIT 1'
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
        $sql = 'SELECT 1 FROM revita_crm_categories WHERE slug = :slug';
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

    public function insert(string $name, string $slug): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO revita_crm_categories (name, slug, updated_at)
             VALUES (:name, :slug, NOW())'
        );
        $stmt->execute(['name' => $name, 'slug' => $slug]);
        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, string $name, string $slug): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE revita_crm_categories SET name = :name, slug = :slug, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'name' => $name, 'slug' => $slug]);
    }

    public function delete(int $id): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM revita_crm_categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}

