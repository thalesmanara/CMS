<?php

declare(strict_types=1);

namespace Revita\Crm\Controllers;

use Revita\Crm\Core\Request;
use Revita\Crm\Core\Response;
use Revita\Crm\Models\Post;
use Revita\Crm\Services\PageApiSerializer;

final class PostsApiController
{
    public function index(Request $request): void
    {
        $slug = trim((string) $request->query('slug', ''));
        if ($slug !== '') {
            $payload = PageApiSerializer::postPayloadBySlug($slug, true);
            if ($payload === null) {
                Response::json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Post não encontrado ou não publicado.',
                ], 404);
            }
            Response::json([
                'success' => true,
                'data' => $payload,
                'message' => 'Post carregado',
            ]);
        }

        $filters = [
            'category_slug' => trim((string) $request->query('categoria', '')),
            'subcategory_slug' => trim((string) $request->query('subcategoria', '')),
        ];
        $post = new Post();
        $rows = $post->allPublishedFiltered($filters);
        $data = [];
        foreach ($rows as $r) {
            $data[] = PageApiSerializer::postPayloadFromRow($r, false);
        }
        Response::json([
            'success' => true,
            'data' => $data,
            'message' => 'Listagem de posts publicados',
        ]);
    }

    public function show(Request $request): void
    {
        $slug = trim($request->routeParam('slug'));
        if ($slug === '') {
            Response::json(['success' => false, 'data' => null, 'message' => 'Slug inválido.'], 404);
        }
        $payload = PageApiSerializer::postPayloadBySlug($slug, true);
        if ($payload === null) {
            Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Post não encontrado ou não publicado.',
            ], 404);
        }
        Response::json([
            'success' => true,
            'data' => $payload,
            'message' => 'Post carregado',
        ]);
    }
}
