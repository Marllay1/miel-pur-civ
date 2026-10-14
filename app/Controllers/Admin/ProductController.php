<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\Catalog;
use App\Services\I18n;
use App\Services\ImageService;

final class ProductController extends Base
{
    private const FIELDS = ['category_id', 'name', 'slug', 'short_description', 'description', 'origin', 'usage_tips', 'storage_tips', 'meta_title', 'meta_description'];

    public function index(Request $r): Response
    {
        $where = ['p.deleted_at IS NULL'];
        $params = [];
        if (($q = $r->str('q')) !== '') {
            $where[] = '(p.name LIKE ? OR EXISTS (SELECT 1 FROM product_variants s WHERE s.product_id = p.id AND s.sku LIKE ?))';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($r->str('statut') === 'actif') {
            $where[] = 'p.is_active = 1';
        } elseif ($r->str('statut') === 'inactif') {
            $where[] = 'p.is_active = 0';
        }
        $products = DB::all(
            'SELECT p.*, c.name AS category_name,
               (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id AND v.deleted_at IS NULL) AS variant_count,
               (SELECT COALESCE(SUM(v.stock), 0) FROM product_variants v WHERE v.product_id = p.id AND v.deleted_at IS NULL) AS stock,
               (SELECT MIN(v.price) FROM product_variants v WHERE v.product_id = p.id AND v.deleted_at IS NULL) AS price_from
             FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.name',
            $params
        );
        return admin_view('products/index', ['products' => $products, 'q' => $q, 'statut' => $r->str('statut'), 'pageTitle' => 'Produits']);
    }

    private function categories(): array
    {
        return DB::all('SELECT id, name FROM categories ORDER BY sort_order, name');
    }

    public function create(Request $r, array $errors = [], array $old = [], int $status = 200): Response
    {
        return admin_view('products/form', [
            'product' => null, 'old' => $old + ['is_active' => '1'], 'errors' => $errors, 'categories' => $this->categories(),
            'variants' => [], 'images' => [], 'translation' => is_array($old['en'] ?? null) ? $old['en'] : [], 'variantTranslations' => [],
            'pageTitle' => 'Nouveau produit',
        ], $status);
    }

    private function validate(Request $r, ?int $id): array
    {
        $v = Validator::make($r->post, [
            'name' => 'required|max:160', 'slug' => 'slug|max:180', 'category_id' => 'int', 'short_description' => 'max:300',
            'description' => 'max:20000', 'origin' => 'max:5000', 'usage_tips' => 'max:5000', 'storage_tips' => 'max:5000',
            'meta_title' => 'max:160', 'meta_description' => 'max:300',
        ], ['name' => 'Le nom', 'slug' => 'L\'adresse (slug)', 'short_description' => 'Le résumé', 'meta_title' => 'Le titre SEO', 'meta_description' => 'La description SEO']);
        $data = [];
        foreach (self::FIELDS as $f) {
            $data[$f] = $v->data[$f] ?? null;
            if ($data[$f] === '') {
                $data[$f] = null;
            }
        }
        $data['slug'] = $data['slug'] ?: slugify((string) $data['name']);
        if ($data['category_id'] !== null && DB::value('SELECT id FROM categories WHERE id = ?', [$data['category_id']]) === null) {
            $v->errors['category_id'] = 'Catégorie inconnue.';
        }
        if (DB::value('SELECT id FROM products WHERE slug = ? AND id <> ?', [$data['slug'], $id ?? 0]) !== null) {
            $v->errors['slug'] = 'Cette adresse est déjà utilisée par un autre produit.';
        }
        $data['is_active'] = $r->str('is_active') === '1' ? 1 : 0;
        $data['is_featured'] = $r->str('is_featured') === '1' ? 1 : 0;
        $data['is_demo'] = $r->str('is_demo') === '1' ? 1 : 0;
        return [$data, $v->errors];
    }

    public function store(Request $r): Response
    {
        [$data, $errors] = $this->validate($r, null);
        if ($errors) {
            return $this->create($r, $errors, $r->post, 422);
        }
        $now = gmdate('Y-m-d H:i:s');
        $id = DB::insert('products', $data + ['created_at' => $now, 'updated_at' => $now, 'deleted_at' => null]);
        Audit::log('product.create', 'product', $id, ['name' => $data['name']]);
        $this->saveTranslation($r, 'product', $id);
        Session::flash('success', 'Produit créé. Ajoutez maintenant ses variantes (formats, prix, stock) et ses images.');
        return redirect('/admin/produits/' . $id);
    }

    private function find(int $id): array
    {
        $p = DB::one('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($p === null) {
            throw new HttpException(404, 'Produit introuvable.');
        }
        return $p;
    }

    public function edit(Request $r, array $errors = [], ?array $old = null, int $status = 200): Response
    {
        $p = $this->find((int) $r->params['id']);
        $variants = Catalog::variants((int) $p['id'], false);
        $variantTranslations = [];
        foreach ($variants as $variant) {
            $variantTranslations[(int) $variant['id']] = I18n::get('variant', (int) $variant['id'])['label'] ?? '';
        }
        return admin_view('products/form', [
            'product' => $p, 'old' => $old ?? $p, 'errors' => $errors, 'categories' => $this->categories(),
            'variants' => $variants, 'images' => Catalog::images((int) $p['id']),
            'translation' => is_array($old['en'] ?? null) ? $old['en'] : I18n::get('product', (int) $p['id']), 'variantTranslations' => $variantTranslations,
            'pageTitle' => $p['name'],
        ], $status);
    }

    public function update(Request $r): Response
    {
        $p = $this->find((int) $r->params['id']);
        [$data, $errors] = $this->validate($r, (int) $p['id']);
        if ($errors) {
            return $this->edit($r, $errors, $r->post + $p, 422);
        }
        $changed = array_keys(array_diff_assoc(array_map('strval', $data), array_map('strval', array_intersect_key($p, $data))));
        DB::update('products', $data + ['updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $p['id']]);
        Audit::log('product.update', 'product', (int) $p['id'], ['fields' => $changed]);
        $this->saveTranslation($r, 'product', (int) $p['id']);
        Session::flash('success', 'Produit enregistré.');
        return redirect('/admin/produits/' . $p['id']);
    }

    public function delete(Request $r): Response
    {
        $p = $this->find((int) $r->params['id']);
        $now = gmdate('Y-m-d H:i:s');
        DB::transaction(static function () use ($p, $now): void {
            DB::update('products', ['deleted_at' => $now, 'is_active' => 0, 'slug' => mb_substr($p['slug'], 0, 150) . '-supprime-' . $p['id'], 'updated_at' => $now], 'id = ?', [(int) $p['id']]);
            DB::run('UPDATE product_variants SET is_active = 0, deleted_at = ?, updated_at = ? WHERE product_id = ? AND deleted_at IS NULL', [$now, $now, (int) $p['id']]);
        });
        Audit::log('product.delete', 'product', (int) $p['id'], ['name' => $p['name']]);
        Session::flash('success', 'Produit « ' . $p['name'] . ' » supprimé (archivé : les commandes passées restent intactes).');
        return redirect('/admin/produits');
    }

    private function validateVariant(Request $r, ?int $variantId, bool $withStock): array
    {
        $rules = [
            'label' => 'required|max:100', 'format' => 'required|in:' . implode(',', array_keys(Catalog::FORMATS)),
            'weight_grams' => 'int|min:0|max:1000000', 'sku' => 'required|max:64', 'price' => 'required|int|min:0|max:100000000',
            'compare_at_price' => 'int|min:0|max:100000000', 'sort_order' => 'int|min:0|max:1000',
        ];
        if ($withStock) {
            $rules['stock'] = 'required|int|min:0|max:1000000';
        }
        $v = Validator::make($r->post, $rules, ['label' => 'Le libellé', 'format' => 'Le format', 'weight_grams' => 'Le poids', 'sku' => 'La référence (SKU)', 'price' => 'Le prix', 'compare_at_price' => 'L\'ancien prix', 'stock' => 'Le stock', 'sort_order' => 'L\'ordre']);
        $sku = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($v->data['sku'] ?? '')) ?? '');
        if ($sku === '' && !isset($v->errors['sku'])) {
            $v->errors['sku'] = 'Référence invalide (lettres, chiffres, tirets).';
        }
        if ($sku !== '' && DB::value('SELECT id FROM product_variants WHERE sku = ? AND id <> ?', [$sku, $variantId ?? 0]) !== null) {
            $v->errors['sku'] = 'Cette référence existe déjà.';
        }
        if (isset($v->data['compare_at_price'], $v->data['price']) && $v->data['compare_at_price'] !== null && $v->data['compare_at_price'] <= $v->data['price']) {
            $v->errors['compare_at_price'] = 'L\'ancien prix doit être supérieur au prix.';
        }
        $data = [
            'label' => $v->data['label'] ?? '', 'format' => $v->data['format'] ?? '', 'weight_grams' => $v->data['weight_grams'] ?? null,
            'sku' => $sku, 'price' => $v->data['price'] ?? 0, 'compare_at_price' => $v->data['compare_at_price'] ?? null,
            'sort_order' => $v->data['sort_order'] ?? 0, 'is_active' => $r->str('is_active') === '1' ? 1 : 0,
        ];
        if ($withStock) {
            $data['stock'] = $v->data['stock'] ?? 0;
        }
        return [$data, $v->errors];
    }

    public function variantStore(Request $r): Response
    {
        $p = $this->find((int) $r->params['id']);
        [$data, $errors] = $this->validateVariant($r, null, true);
        if ($errors) {
            Session::flash('error', 'Variante non créée : ' . implode(' ', $errors));
            return redirect('/admin/produits/' . $p['id'] . '#variantes');
        }
        $now = gmdate('Y-m-d H:i:s');
        $vid = DB::insert('product_variants', $data + ['product_id' => (int) $p['id'], 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null]);
        if ($data['stock'] > 0) {
            DB::insert('stock_movements', ['variant_id' => $vid, 'quantity' => $data['stock'], 'reason' => 'initial', 'reference' => null, 'user_id' => $this->uid(), 'note' => 'Stock initial', 'created_at' => $now]);
        }
        Audit::log('variant.create', 'variant', $vid, ['product' => (int) $p['id'], 'sku' => $data['sku'], 'price' => $data['price'], 'stock' => $data['stock']]);
        $this->saveTranslation($r, 'variant', $vid);
        Session::flash('success', 'Variante « ' . $data['label'] . ' » ajoutée.');
        return redirect('/admin/produits/' . $p['id'] . '#variantes');
    }

    private function findVariant(int $id): array
    {
        $v = DB::one('SELECT * FROM product_variants WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($v === null) {
            throw new HttpException(404, 'Variante introuvable.');
        }
        return $v;
    }

    public function variantUpdate(Request $r): Response
    {
        $variant = $this->findVariant((int) $r->params['id']);
        [$data, $errors] = $this->validateVariant($r, (int) $variant['id'], false);
        if ($errors) {
            Session::flash('error', 'Variante non modifiée : ' . implode(' ', $errors));
            return redirect('/admin/produits/' . $variant['product_id'] . '#variantes');
        }
        DB::update('product_variants', $data + ['updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $variant['id']]);
        $details = ['sku' => $data['sku']];
        if ((int) $variant['price'] !== (int) $data['price']) {
            $details['price'] = [(int) $variant['price'], (int) $data['price']];
        }
        Audit::log('variant.update', 'variant', (int) $variant['id'], $details);
        $this->saveTranslation($r, 'variant', (int) $variant['id']);
        Session::flash('success', 'Variante enregistrée.');
        return redirect('/admin/produits/' . $variant['product_id'] . '#variantes');
    }

    public function variantDelete(Request $r): Response
    {
        $variant = $this->findVariant((int) $r->params['id']);
        $now = gmdate('Y-m-d H:i:s');
        DB::update('product_variants', ['deleted_at' => $now, 'is_active' => 0, 'sku' => mb_substr($variant['sku'], 0, 50) . '-DEL' . $variant['id'], 'updated_at' => $now], 'id = ?', [(int) $variant['id']]);
        Audit::log('variant.delete', 'variant', (int) $variant['id'], ['sku' => $variant['sku']]);
        Session::flash('success', 'Variante supprimée.');
        return redirect('/admin/produits/' . $variant['product_id'] . '#variantes');
    }

    public function imageStore(Request $r): Response
    {
        $p = $this->find((int) $r->params['id']);
        $alt = mb_substr($r->str('alt'), 0, 200);
        if ($alt === '') {
            Session::flash('error', 'Le texte alternatif est obligatoire (accessibilité et référencement).');
            return redirect('/admin/produits/' . $p['id'] . '#images');
        }
        $result = ImageService::store($r->files['image'] ?? []);
        if (!$result['ok']) {
            Session::flash('error', 'Image refusée : ' . $result['error']);
            return redirect('/admin/produits/' . $p['id'] . '#images');
        }
        $order = (int) DB::value('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = ?', [(int) $p['id']]);
        $id = DB::insert('product_images', ['product_id' => (int) $p['id'], 'path' => $result['path'], 'alt' => $alt, 'sort_order' => $r->str('first') === '1' ? -1 : $order, 'is_demo' => 0, 'created_at' => gmdate('Y-m-d H:i:s')]);
        Audit::log('image.create', 'product', (int) $p['id'], ['image' => $id]);
        Session::flash('success', 'Image ajoutée.');
        return redirect('/admin/produits/' . $p['id'] . '#images');
    }

    public function imageDelete(Request $r): Response
    {
        $img = DB::one('SELECT * FROM product_images WHERE id = ?', [(int) $r->params['id']]);
        if ($img === null) {
            throw new HttpException(404);
        }
        DB::run('DELETE FROM product_images WHERE id = ?', [(int) $img['id']]);
        ImageService::delete($img['path']);
        Audit::log('image.delete', 'product', (int) $img['product_id'], ['path' => $img['path']]);
        Session::flash('success', 'Image supprimée.');
        return redirect('/admin/produits/' . $img['product_id'] . '#images');
    }
}
