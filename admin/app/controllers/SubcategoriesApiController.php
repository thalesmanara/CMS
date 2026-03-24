<?php

declare(strict_types=1);

namespace Revita\Crm\Controllers;

use Revita\Crm\Core\Request;
use Revita\Crm\Core\Response;
use Revita\Crm\Models\Subcategory;

final class SubcategoriesApiController
{
    public function index(Request $request): void
    {
        $sub = new Subcategory();
        $data = [];
        foreach ($sub->allWithCategory() as $r) {
            $data[] = [
                'id' => (int) $r['id'],
                'nome' => (string) $r['name'],
                'slug' => (string) $r['slug'],
                'categoria_id' => (int) $r['category_id'],
                'categoria_nome' => (string) $r['category_name'],
                'categoria_slug' => (string) ($r['category_slug'] ?? ''),
            ];
        }
        Response::json([
            'success' => true,
            'data' => $data,
            'message' => 'Listagem de subcategorias',
        ]);
    }
}
