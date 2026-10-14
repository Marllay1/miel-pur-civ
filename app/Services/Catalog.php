<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Lecture du catalogue public (produits actifs, non supprimés, avec au moins une variante active).
 */
final class Catalog
{
    public const BASE_SELECT = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.is_active AS category_active,
        (SELECT MIN(v.price) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1 AND v.deleted_at IS NULL) AS price_from,
        (SELECT MAX(v.price) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1 AND v.deleted_at IS NULL) AS price_to,
        (SELECT COALESCE(SUM(v.stock), 0) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1 AND v.deleted_at IS NULL) AS total_stock,
        (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1 AND v.deleted_at IS NULL) AS variant_count,
        (SELECT i.path FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS image_path,
        (SELECT i.alt FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS image_alt
        FROM products p LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.deleted_at IS NULL";

    public const SORTS = [
        'pertinence' => 'Pertinence',
        'prix_asc' => 'Prix croissant',
        'prix_desc' => 'Prix décroissant',
        'nouveautes' => 'Nouveautés',
        'nom' => 'Nom (A → Z)',
    ];

    public const FORMATS = [
        'pot-verre' => 'Pot en verre',
        'pot-plastique' => 'Pot plastique',
        'bidon' => 'Bidon / seau',
        'flacon' => 'Flacon souple',
        'pain-cire' => 'Pain de cire',
        'coffret' => 'Coffret',
    ];

    /** Traductions des produits et de leur catégorie dans la langue courante. */
    public static function localizeProducts(array $rows): array
    {
        $rows = I18n::localize('product', $rows);
        return I18n::localize('category', $rows, 'category_id', ['name' => 'category_name', 'description' => '_cat_description']);
    }

    private static function publicWhere(): string
    {
        return 't.is_active = 1 AND (t.category_id IS NULL OR t.category_active = 1) AND t.variant_count > 0';
    }

    /**
     * @param array{q?:string, categorie?:string, format?:string, prix_min?:?int, prix_max?:?int, tri?:string, en_stock?:bool} $f
     * @return array{items:array, total:int, page:int, pages:int, per_page:int}
     */
    public static function search(array $f, int $page = 1, int $perPage = 12): array
    {
        $where = [self::publicWhere()];
        $params = [];
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr($q, 0, 80)) . '%';
            $where[] = "(t.name LIKE ? ESCAPE '!' OR t.short_description LIKE ? ESCAPE '!' OR t.category_name LIKE ? ESCAPE '!' OR EXISTS (SELECT 1 FROM translations tr WHERE tr.entity_type = 'product' AND tr.entity_id = t.id AND tr.field IN ('name', 'short_description') AND tr.value LIKE ? ESCAPE '!'))";
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($f['categorie'])) {
            $where[] = 't.category_slug = ?';
            $params[] = (string) $f['categorie'];
        }
        if (!empty($f['format'])) {
            $where[] = 'EXISTS (SELECT 1 FROM product_variants fv WHERE fv.product_id = t.id AND fv.format = ? AND fv.is_active = 1 AND fv.deleted_at IS NULL)';
            $params[] = (string) $f['format'];
        }
        if (isset($f['prix_min']) && $f['prix_min'] !== null) {
            $where[] = 't.price_to >= ?';
            $params[] = (int) $f['prix_min'];
        }
        if (isset($f['prix_max']) && $f['prix_max'] !== null) {
            $where[] = 't.price_from <= ?';
            $params[] = (int) $f['prix_max'];
        }
        if (!empty($f['en_stock'])) {
            $where[] = 't.total_stock > 0';
        }
        $order = match ($f['tri'] ?? 'pertinence') {
            'prix_asc' => 't.price_from ASC, t.name ASC',
            'prix_desc' => 't.price_from DESC, t.name ASC',
            'nouveautes' => 't.created_at DESC, t.id DESC',
            'nom' => 't.name ASC',
            default => 't.is_featured DESC, (t.total_stock > 0) DESC, t.name ASC',
        };
        $sqlWhere = implode(' AND ', $where);
        $total = (int) DB::value('SELECT COUNT(*) FROM (' . self::BASE_SELECT . ") t WHERE $sqlWhere", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;
        $items = self::localizeProducts(DB::all('SELECT * FROM (' . self::BASE_SELECT . ") t WHERE $sqlWhere ORDER BY $order LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset, $params));
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    public static function findBySlug(string $slug): ?array
    {
        $row = DB::one('SELECT * FROM (' . self::BASE_SELECT . ') t WHERE t.slug = ? AND ' . self::publicWhere(), [$slug]);
        return $row === null ? null : self::localizeProducts([$row])[0];
    }

    public static function featured(int $limit = 4): array
    {
        return self::localizeProducts(DB::all('SELECT * FROM (' . self::BASE_SELECT . ') t WHERE ' . self::publicWhere() . ' ORDER BY t.is_featured DESC, t.created_at DESC LIMIT ' . (int) $limit));
    }

    public static function related(array $product, int $limit = 4): array
    {
        return self::localizeProducts(DB::all(
            'SELECT * FROM (' . self::BASE_SELECT . ') t WHERE ' . self::publicWhere() . ' AND t.id <> ? ORDER BY (t.category_id = ?) DESC, t.is_featured DESC, t.name LIMIT ' . (int) $limit,
            [(int) $product['id'], (int) ($product['category_id'] ?? 0)]
        ));
    }

    public static function variants(int $productId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM product_variants WHERE product_id = ? AND deleted_at IS NULL' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, price, id';
        return I18n::localize('variant', DB::all($sql, [$productId]));
    }

    public static function images(int $productId): array
    {
        return DB::all('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id', [$productId]);
    }

    public static function categories(): array
    {
        return I18n::localize('category', DB::all(
            'SELECT c.*, (SELECT COUNT(*) FROM (' . self::BASE_SELECT . ') t WHERE t.category_id = c.id AND ' . self::publicWhere() . ') AS product_count
             FROM categories c WHERE c.is_active = 1 ORDER BY c.sort_order, c.name'
        ));
    }

    /** Formats effectivement présents dans le catalogue public. */
    public static function availableFormats(): array
    {
        $rows = DB::all('SELECT DISTINCT v.format FROM product_variants v JOIN products p ON p.id = v.product_id
            WHERE v.is_active = 1 AND v.deleted_at IS NULL AND p.is_active = 1 AND p.deleted_at IS NULL ORDER BY v.format');
        $out = [];
        foreach ($rows as $r) {
            $out[$r['format']] = self::FORMATS[$r['format']] ?? $r['format'];
        }
        return $out;
    }

    /** Variante achetable (variante et produit actifs, non supprimés, catégorie active). */
    public static function purchasableVariant(int $variantId): ?array
    {
        $v = DB::one(
            'SELECT v.*, p.name AS product_name, p.slug AS product_slug FROM product_variants v
             JOIN products p ON p.id = v.product_id LEFT JOIN categories c ON c.id = p.category_id
             WHERE v.id = ? AND v.is_active = 1 AND v.deleted_at IS NULL AND p.is_active = 1 AND p.deleted_at IS NULL
               AND (c.id IS NULL OR c.is_active = 1)',
            [$variantId]
        );
        if ($v === null) {
            return null;
        }
        $v = I18n::localizeOne('variant', $v);
        return I18n::localizeOne('product', $v, 'product_id', ['name' => 'product_name']);
    }

    public static function approvedReviews(int $productId): array
    {
        return DB::all("SELECT * FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 50", [$productId]);
    }
}
