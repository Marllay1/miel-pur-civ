<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\I18n;

final class CategoryController extends Base
{
    public function index(Request $r, array $errors = [], ?array $old = null, int $status = 200): Response
    {
        $edit = $r->int('id') ? DB::one('SELECT * FROM categories WHERE id = ?', [(int) $r->int('id')]) : null;
        return admin_view('categories', [
            'categories' => DB::all('SELECT c.*, pc.name AS parent_name, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.deleted_at IS NULL) AS product_count
                FROM categories c LEFT JOIN categories pc ON pc.id = c.parent_id ORDER BY COALESCE(pc.sort_order, c.sort_order), COALESCE(pc.name, c.name), c.parent_id IS NOT NULL, c.sort_order, c.name'),
            'edit' => $edit, 'old' => $old ?? ($edit ?? ['is_active' => 1]), 'errors' => $errors, 'pageTitle' => 'Catégories',
            'translation' => is_array($old['en'] ?? null) ? $old['en'] : ($edit ? I18n::get('category', (int) $edit['id']) : []),
        ], $status);
    }

    public function save(Request $r): Response
    {
        $id = (int) $r->int('id', 0);
        $v = Validator::make($r->post, ['name' => 'required|max:120', 'slug' => 'slug|max:140', 'description' => 'max:2000', 'sort_order' => 'int|min:0|max:1000'],
            ['name' => 'Le nom', 'slug' => 'L\'adresse', 'description' => 'La description', 'sort_order' => 'L\'ordre']);
        $slug = $v->data['slug'] ?: slugify((string) $v->data['name']);
        if (DB::value('SELECT id FROM categories WHERE slug = ? AND id <> ?', [$slug, $id]) !== null) {
            $v->errors['slug'] = 'Adresse déjà utilisée.';
        }
        // Un seul niveau de sous-catégorie : le parent doit être une catégorie principale, différente de celle-ci, et sans enfant si elle-même devient enfant.
        $parentId = $r->int('parent_id', 0) ?: null;
        if ($parentId !== null) {
            $parent = DB::one('SELECT id, parent_id FROM categories WHERE id = ?', [$parentId]);
            if ($parent === null || $parent['parent_id'] !== null || $parentId === $id) {
                $v->errors['parent_id'] = 'Catégorie parente invalide (une catégorie principale, différente de celle-ci).';
            } elseif ($id > 0 && DB::value('SELECT id FROM categories WHERE parent_id = ? LIMIT 1', [$id]) !== null) {
                $v->errors['parent_id'] = 'Cette catégorie a des sous-catégories : elle ne peut pas devenir elle-même une sous-catégorie.';
            }
        }
        if ($v->fails()) {
            return $this->index($r, $v->errors, $r->post, 422);
        }
        $now = gmdate('Y-m-d H:i:s');
        $data = ['name' => $v->data['name'], 'slug' => $slug, 'description' => $v->data['description'] ?: null, 'sort_order' => $v->data['sort_order'] ?? 0,
            'parent_id' => $parentId, 'is_active' => $r->str('is_active') === '1' ? 1 : 0, 'updated_at' => $now];
        if ($id > 0 && DB::value('SELECT id FROM categories WHERE id = ?', [$id]) !== null) {
            DB::update('categories', $data, 'id = ?', [$id]);
            Audit::log('category.update', 'category', $id, ['name' => $data['name'], 'active' => $data['is_active']]);
        } else {
            $id = DB::insert('categories', $data + ['created_at' => $now]);
            Audit::log('category.create', 'category', $id, ['name' => $data['name']]);
        }
        $this->saveTranslation($r, 'category', $id);
        Session::flash('success', 'Catégorie enregistrée.');
        return redirect('/admin/categories');
    }
}
