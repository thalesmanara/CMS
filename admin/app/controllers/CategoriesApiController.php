<?php

declare(strict_types=1);

namespace Revita\Crm\Controllers;

use Revita\Crm\Core\Request;
use Revita\Crm\Core\Response;
use Revita\Crm\Models\Category;
use Revita\Crm\Models\Subcategory;

final class CategoriesApiController
{
    public function index(Request $request): void
    {
        $cat = new Category();
        $sub = new Subcategory();
        $data = [];
        foreach ($cat->all() as $c) {
            $subs = [];
            foreach ($sub->allByCategory((int) $c['id']) as $s) {
                $subs[] = [
                    'id' => (int) $s['id'],
                    'nome' => (string) $s['name'],
                    'slug' => (string) $s['slug'],
                ];
            }
            $data[] = [
                'id' => (int) $c['id'],
                'nome' => (string) $c['name'],
                'slug' => (string) $c['slug'],
                'subcategorias' => $subs,
            ];
        }
        Response::json([
            'success' => true,
            'data' => $data,
            'message' => 'Categorias e subcategorias',
        ]);
    }
}
