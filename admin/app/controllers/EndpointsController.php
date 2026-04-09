<?php

declare(strict_types=1);

namespace Revita\Crm\Controllers;

use Revita\Crm\Core\Auth;
use Revita\Crm\Core\Request;
use Revita\Crm\Core\Response;
use Revita\Crm\Core\View;
use Revita\Crm\Helpers\Escape;
use Revita\Crm\Helpers\Url;

final class EndpointsController
{
    public function index(Request $request): void
    {
        Auth::requireAdmin();

        $endpoints = [
            'API JSON (public/ leitura)' => [
                ['GET', '/api/pages', 'Listar páginas publicadas (?slug para buscar)'],
                ['GET', '/api/pages/{slug}', 'Detalhe por slug'],
                ['GET', '/api/posts', 'Listar posts publicados (?categoria={slug}&subcategoria={slug} opcional; ?slug para detalhe)'],
                ['GET', '/api/posts/{slug}', 'Detalhe por slug'],
                ['GET', '/api/categories', 'Listar categorias com subcategorias'],
                ['GET', '/api/subcategories', 'Listar subcategorias (plano)'],
            ],
            'Painel (Web)' => [
                ['GET', '/', 'Raiz (redireciona para /login ou /dashboard)'],
                ['GET', '/login', 'Tela de login'],
                ['POST', '/login', 'Autenticar'],
                ['GET', '/logout', 'Encerrar sessão'],
                ['GET', '/forgot-password', 'Tela de recuperação de senha'],
                ['POST', '/forgot-password', 'Enviar link de recuperação'],
                ['GET', '/reset-password', 'Tela de redefinição (token)'],
                ['POST', '/reset-password', 'Confirmar nova senha'],
                ['GET', '/dashboard', 'Dashboard'],
                ['GET', '/users', 'Listar usuários'],
                ['GET', '/users/create', 'Form criar usuário'],
                ['POST', '/users/store', 'Salvar usuário'],
                ['GET', '/users/edit', 'Form editar usuário'],
                ['POST', '/users/update', 'Atualizar usuário'],
                ['POST', '/users/delete', 'Excluir usuário'],
                ['GET', '/categories', 'Listar categorias'],
                ['GET', '/categories/create', 'Form criar categoria'],
                ['POST', '/categories/store', 'Salvar categoria'],
                ['GET', '/categories/edit', 'Form editar categoria'],
                ['POST', '/categories/update', 'Atualizar categoria'],
                ['POST', '/categories/delete', 'Excluir categoria (admin-only)'],
                ['GET', '/subcategories/create', 'Form criar subcategoria'],
                ['POST', '/subcategories/store', 'Salvar subcategoria'],
                ['GET', '/subcategories/edit', 'Form editar subcategoria'],
                ['POST', '/subcategories/update', 'Atualizar subcategoria'],
                ['POST', '/subcategories/delete', 'Excluir subcategoria (admin-only)'],
                ['GET', '/pages', 'Listar páginas'],
                ['GET', '/pages/create', 'Form criar página (admin-only)'],
                ['POST', '/pages/store', 'Salvar página (admin-only)'],
                ['GET', '/pages/edit', 'Form editar página'],
                ['POST', '/pages/update-meta', 'Salvar meta (título/slug/status)'],
                ['POST', '/pages/update-content', 'Salvar campos dinâmicos'],
                ['POST', '/pages/add-field', 'Adicionar campo dinâmico'],
                ['POST', '/pages/delete-field', 'Excluir campo dinâmico'],
                ['POST', '/pages/reorder-fields', 'Reordenar campos'],
                ['POST', '/pages/delete', 'Excluir página (admin-only)'],
                ['POST', '/pages/rep-add-sub', 'Repetidor: adicionar subcampo'],
                ['POST', '/pages/rep-del-sub', 'Repetidor: excluir subcampo'],
                ['POST', '/pages/rep-add-item', 'Repetidor: adicionar item'],
                ['POST', '/pages/rep-del-item', 'Repetidor: excluir item'],
                ['POST', '/pages/rep-reorder-items', 'Repetidor: reordenar itens'],
                ['GET', '/posts', 'Listar postagens'],
                ['GET', '/posts/create', 'Form criar post'],
                ['POST', '/posts/store', 'Salvar post'],
                ['GET', '/posts/edit', 'Form editar post'],
                ['POST', '/posts/update-meta', 'Salvar meta (título/slug/status/categoria/subcategoria/capa)'],
                ['POST', '/posts/update-content', 'Salvar campos dinâmicos'],
                ['POST', '/posts/add-field', 'Adicionar campo dinâmico'],
                ['POST', '/posts/delete-field', 'Excluir campo dinâmico'],
                ['POST', '/posts/reorder-fields', 'Reordenar campos'],
                ['POST', '/posts/delete', 'Excluir post (admin-only)'],
                ['POST', '/posts/rep-add-sub', 'Repetidor: adicionar subcampo'],
                ['POST', '/posts/rep-del-sub', 'Repetidor: excluir subcampo'],
                ['POST', '/posts/rep-add-item', 'Repetidor: adicionar item'],
                ['POST', '/posts/rep-del-item', 'Repetidor: excluir item'],
                ['POST', '/posts/rep-reorder-items', 'Repetidor: reordenar itens'],
                ['GET', '/backup', 'Backup/migração (admin-only)'],
                ['GET', '/backup/export', 'Exportar backup (admin-only)'],
                ['POST', '/backup/import', 'Importar backup (admin-only)'],
            ],
            'Instalador' => [
                ['GET', '/install', 'Tela de instalação (antes de finalizar)'],
                ['POST', '/install', 'Criar schema + seed do usuário mestre'],
            ],
        ];

        // Normaliza URLs para exibir caminho completo dentro do admin.
        $out = [];
        foreach ($endpoints as $section => $items) {
            $out[$section] = [];
            foreach ($items as $e) {
                [$m, $path, $note] = $e;
                $out[$section][] = [
                    'method' => (string) $m,
                    'url' => Url::to((string) $path),
                    'note' => (string) $note,
                ];
            }
        }

        $html = View::layout('admin', 'endpoints/index', [
            'title' => 'Endpoints — Revita CMS',
            'nav' => 'endpoints',
            'user' => Auth::user(),
            'csrfToken' => '', // apenas para compatibilidade visual se a view usar
            'endpoints' => $out,
        ]);

        Response::html($html);
    }
}

