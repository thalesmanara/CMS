<?php

declare(strict_types=1);

namespace Revita\Crm\Models;

use PDO;
use Revita\Crm\Core\Database;

final class Post
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            'SELECT p.id, p.title, p.slug, p.status, p.published_at, p.created_at, p.updated_at,
                    p.category_id, p.subcategory_id, p.featured_media_id, p.author_user_id,
                    c.name AS category_name, c.slug AS category_slug,
                    s.name AS subcategory_name, s.slug AS subcategory_slug,
                    u.login AS author_login
             FROM revita_crm_posts p
             INNER JOIN revita_crm_categories c ON c.id = p.category_id
             INNER JOIN revita_crm_subcategories s ON s.id = p.subcategory_id
             INNER JOIN revita_crm_users u ON u.id = p.author_user_id
             ORDER BY p.updated_at DESC, p.id DESC'
        );
        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /** @return list<array<string, mixed>> */
    public function allPublished(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            "SELECT p.id, p.title, p.slug, p.status, p.published_at, p.created_at, p.updated_at,
                    p.category_id, p.subcategory_id, p.featured_media_id, p.author_user_id,
                    c.name AS category_name, c.slug AS category_slug,
                    s.name AS subcategory_name, s.slug AS subcategory_slug,
                    u.login AS author_login
             FROM revita_crm_posts p
             INNER JOIN revita_crm_categories c ON c.id = p.category_id
             INNER JOIN revita_crm_subcategories s ON s.id = p.subcategory_id
             INNER JOIN revita_crm_users u ON u.id = p.author_user_id
             WHERE p.status = 'published'
             ORDER BY p.published_at DESC, p.id DESC"
        );
        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @return list<array<string, mixed>>
     * @param array{category_slug?:string,subcategory_slug?:string} $filters
     */
    public function allPublishedFiltered(array $filters): array
    {
        $catSlug = trim((string) ($filters['category_slug'] ?? ''));
        $subSlug = trim((string) ($filters['subcategory_slug'] ?? ''));
        $sql = "SELECT p.id, p.title, p.slug, p.status, p.published_at, p.created_at, p.updated_at,
                    p.category_id, p.subcategory_id, p.featured_media_id, p.author_user_id,
                    c.name AS category_name, c.slug AS category_slug,
                    s.name AS subcategory_name, s.slug AS subcategory_slug,
                    u.login AS author_login
             FROM revita_crm_posts p
             INNER JOIN revita_crm_categories c ON c.id = p.category_id
             INNER JOIN revita_crm_subcategories s ON s.id = p.subcategory_id
             INNER JOIN revita_crm_users u ON u.id = p.author_user_id
             WHERE p.status = 'published'";
        $params = [];
        if ($catSlug !== '') {
            $sql .= ' AND c.slug = :cs';
            $params['cs'] = $catSlug;
        }
        if ($subSlug !== '') {
            $sql .= ' AND s.slug = :ss';
            $params['ss'] = $subSlug;
        }
        $sql .= ' ORDER BY p.published_at DESC, p.id DESC';
        $pdo = Database::pdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT p.id, p.title, p.slug, p.status, p.published_at, p.created_at, p.updated_at,
                    p.category_id, p.subcategory_id, p.featured_media_id, p.author_user_id,
                    c.name AS category_name, c.slug AS category_slug,
                    s.name AS subcategory_name, s.slug AS subcategory_slug,
                    u.login AS author_login
             FROM revita_crm_posts p
             INNER JOIN revita_crm_categories c ON c.id = p.category_id
             INNER JOIN revita_crm_subcategories s ON s.id = p.subcategory_id
             INNER JOIN revita_crm_users u ON u.id = p.author_user_id
             WHERE p.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT p.id, p.title, p.slug, p.status, p.published_at, p.created_at, p.updated_at,
                    p.category_id, p.subcategory_id, p.featured_media_id, p.author_user_id,
                    c.name AS category_name, c.slug AS category_slug,
                    s.name AS subcategory_name, s.slug AS subcategory_slug,
                    u.login AS author_login
             FROM revita_crm_posts p
             INNER JOIN revita_crm_categories c ON c.id = p.category_id
             INNER JOIN revita_crm_subcategories s ON s.id = p.subcategory_id
             INNER JOIN revita_crm_users u ON u.id = p.author_user_id
             WHERE p.slug = :s LIMIT 1'
        );
        $stmt->execute(['s' => $slug]);
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
        $sql = 'SELECT 1 FROM revita_crm_posts WHERE slug = :s';
        $p = ['s' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :ex';
            $p['ex'] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($p);
        return (bool) $stmt->fetchColumn();
    }

    public function subcategoryBelongsToCategory(int $subcategoryId, int $categoryId): bool
    {
        if ($subcategoryId < 1 || $categoryId < 1) {
            return false;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM revita_crm_subcategories WHERE id = :sid AND category_id = :cid LIMIT 1'
        );
        $stmt->execute(['sid' => $subcategoryId, 'cid' => $categoryId]);
        return (bool) $stmt->fetchColumn();
    }

    public function insert(
        string $title,
        string $slug,
        int $categoryId,
        int $subcategoryId,
        ?int $featuredMediaId,
        string $status,
        ?string $publishedAt,
        int $authorUserId
    ): int {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO revita_crm_posts
             (title, slug, category_id, subcategory_id, featured_media_id, status, published_at, author_user_id, updated_at)
             VALUES (:t, :s, :cid, :sid, :fm, :st, :pub, :aid, NOW())'
        );
        $stmt->execute([
            't' => $title,
            's' => $slug,
            'cid' => $categoryId,
            'sid' => $subcategoryId,
            'fm' => $featuredMediaId,
            'st' => $status,
            'pub' => $publishedAt,
            'aid' => $authorUserId,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function update(
        int $id,
        string $title,
        string $slug,
        int $categoryId,
        int $subcategoryId,
        ?int $featuredMediaId,
        string $status,
        ?string $publishedAt
    ): void {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE revita_crm_posts SET
              title = :t, slug = :s, category_id = :cid, subcategory_id = :sid,
              featured_media_id = :fm, status = :st, published_at = :pub, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            't' => $title,
            's' => $slug,
            'cid' => $categoryId,
            'sid' => $subcategoryId,
            'fm' => $featuredMediaId,
            'st' => $status,
            'pub' => $publishedAt,
        ]);
    }

    public function delete(int $id): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM revita_crm_posts WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
