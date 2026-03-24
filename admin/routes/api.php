<?php

declare(strict_types=1);

use Revita\Crm\Controllers\CategoriesApiController;
use Revita\Crm\Controllers\PagesApiController;
use Revita\Crm\Controllers\PostsApiController;
use Revita\Crm\Controllers\SubcategoriesApiController;

return [
    'GET' => [
        '/api/pages' => [PagesApiController::class, 'index'],
        '/api/posts' => [PostsApiController::class, 'index'],
        '/api/categories' => [CategoriesApiController::class, 'index'],
        '/api/subcategories' => [SubcategoriesApiController::class, 'index'],
    ],
    '_patterns' => [
        'GET' => [
            [
                'pattern' => '#^/api/pages/([^/]+)$#',
                'handler' => [PagesApiController::class, 'show'],
                'params' => ['slug'],
            ],
            [
                'pattern' => '#^/api/posts/([^/]+)$#',
                'handler' => [PostsApiController::class, 'show'],
                'params' => ['slug'],
            ],
        ],
    ],
];
