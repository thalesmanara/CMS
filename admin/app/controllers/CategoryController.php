<?php

declare(strict_types=1);

namespace Revita\Crm\Controllers;

use Revita\Crm\Core\Auth;
use Revita\Crm\Core\Csrf;
use Revita\Crm\Core\Request;
use Revita\Crm\Core\Response;
use Revita\Crm\Core\Session;
use Revita\Crm\Core\View;
use Revita\Crm\Helpers\Slugger;
use Revita\Crm\Helpers\Url;
use Revita\Crm\Models\Category;
use Revita\Crm\Models\Subcategory;

final class CategoryController
{
    public function index(Request $request): void
    {
        Auth::requireEditor();
        $cat = new Category();
        $sub = new Subcategory();

        $html = View::layout('admin', 'categories/index', [
            'title' => 'Categorias — Revita CMS',
            'nav' => 'categories',
            'user' => Auth::user(),
            'categories' => $cat->all(),
            'subcategories' => $sub->allWithCategory(),
            'flashOk' => Session::flash('ok'),
            'flashErr' => Session::flash('error'),
            'csrfToken' => Csrf::token(),
        ]);
        Response::html($html);
    }

    // ---- categorias

    public function createCategoryForm(Request $request): void
    {
        Auth::requireEditor();
        $html = View::layout('admin', 'categories/category-form', [
            'title' => 'Nova categoria — Revita CMS',
            'nav' => 'categories',
            'user' => Auth::user(),
            'editCategory' => null,
            'error' => Session::flash('category_form_error'),
            'csrfToken' => Csrf::token(),
        ]);
        Response::html($html);
    }

    public function storeCategory(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('category_form_error', 'Sessão expirada. Tente novamente.');
            Url::redirect('/categories/create');
        }

        $name = trim((string) $request->post('name', ''));
        $slug = trim((string) $request->post('slug', ''));
        if ($slug === '') {
            $slug = Slugger::slugify($name);
        } else {
            $slug = Slugger::slugify($slug);
        }

        $err = $this->validateCategory($name, $slug);
        if ($err !== null) {
            Session::flash('category_form_error', $err);
            Url::redirect('/categories/create');
        }

        $model = new Category();
        if ($model->slugExists($slug)) {
            Session::flash('category_form_error', 'Slug já cadastrado.');
            Url::redirect('/categories/create');
        }

        $model->insert($name, $slug);
        Session::flash('ok', 'Categoria criada.');
        Url::redirect('/categories');
    }

    public function editCategoryForm(Request $request): void
    {
        Auth::requireEditor();
        $id = (int) $request->query('id', 0);
        $model = new Category();
        $row = $model->findById($id);
        if ($row === null) {
            Session::flash('error', 'Categoria não encontrada.');
            Url::redirect('/categories');
        }
        $html = View::layout('admin', 'categories/category-form', [
            'title' => 'Editar categoria — Revita CMS',
            'nav' => 'categories',
            'user' => Auth::user(),
            'editCategory' => $row,
            'error' => Session::flash('category_form_error'),
            'csrfToken' => Csrf::token(),
        ]);
        Response::html($html);
    }

    public function updateCategory(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/categories');
        }
        $id = (int) $request->post('id', 0);
        $model = new Category();
        $existing = $model->findById($id);
        if ($existing === null) {
            Session::flash('error', 'Categoria não encontrada.');
            Url::redirect('/categories');
        }

        $name = trim((string) $request->post('name', ''));
        $slug = trim((string) $request->post('slug', ''));
        $slug = $slug === '' ? Slugger::slugify($name) : Slugger::slugify($slug);

        $err = $this->validateCategory($name, $slug);
        if ($err !== null) {
            Session::flash('category_form_error', $err);
            Url::redirect('/categories/edit?id=' . $id);
        }
        if ($model->slugExists($slug, $id)) {
            Session::flash('category_form_error', 'Slug já cadastrado.');
            Url::redirect('/categories/edit?id=' . $id);
        }

        $model->update($id, $name, $slug);
        Session::flash('ok', 'Categoria atualizada.');
        Url::redirect('/categories');
    }

    public function deleteCategory(Request $request): void
    {
        Auth::requireAdmin();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/categories');
        }
        $id = (int) $request->post('id', 0);
        if ($id < 1) {
            Session::flash('error', 'Categoria inválida.');
            Url::redirect('/categories');
        }
        // Quando postagens existirem, validar vínculos antes de excluir.
        $model = new Category();
        $sub = new Subcategory();
        $sub->deleteAllByCategoryId($id);
        $model->delete($id);
        Session::flash('ok', 'Categoria removida (subcategorias vinculadas também foram removidas).');
        Url::redirect('/categories');
    }

    // ---- subcategorias

    public function createSubcategoryForm(Request $request): void
    {
        Auth::requireEditor();
        $cat = new Category();
        $html = View::layout('admin', 'categories/subcategory-form', [
            'title' => 'Nova subcategoria — Revita CMS',
            'nav' => 'categories',
            'user' => Auth::user(),
            'editSubcategory' => null,
            'categories' => $cat->all(),
            'error' => Session::flash('subcategory_form_error'),
            'csrfToken' => Csrf::token(),
        ]);
        Response::html($html);
    }

    public function storeSubcategory(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('subcategory_form_error', 'Sessão expirada. Tente novamente.');
            Url::redirect('/subcategories/create');
        }

        $categoryId = (int) $request->post('category_id', 0);
        $name = trim((string) $request->post('name', ''));
        $slug = trim((string) $request->post('slug', ''));
        $slug = $slug === '' ? Slugger::slugify($name) : Slugger::slugify($slug);

        $err = $this->validateSubcategory($categoryId, $name, $slug);
        if ($err !== null) {
            Session::flash('subcategory_form_error', $err);
            Url::redirect('/subcategories/create');
        }

        $cat = new Category();
        if ($cat->findById($categoryId) === null) {
            Session::flash('subcategory_form_error', 'Categoria inválida.');
            Url::redirect('/subcategories/create');
        }

        $model = new Subcategory();
        if ($model->slugExists($slug)) {
            Session::flash('subcategory_form_error', 'Slug já cadastrado.');
            Url::redirect('/subcategories/create');
        }

        $model->insert($categoryId, $name, $slug);
        Session::flash('ok', 'Subcategoria criada.');
        Url::redirect('/categories');
    }

    public function editSubcategoryForm(Request $request): void
    {
        Auth::requireEditor();
        $id = (int) $request->query('id', 0);
        $model = new Subcategory();
        $row = $model->findById($id);
        if ($row === null) {
            Session::flash('error', 'Subcategoria não encontrada.');
            Url::redirect('/categories');
        }
        $cat = new Category();
        $html = View::layout('admin', 'categories/subcategory-form', [
            'title' => 'Editar subcategoria — Revita CMS',
            'nav' => 'categories',
            'user' => Auth::user(),
            'editSubcategory' => $row,
            'categories' => $cat->all(),
            'error' => Session::flash('subcategory_form_error'),
            'csrfToken' => Csrf::token(),
        ]);
        Response::html($html);
    }

    public function updateSubcategory(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/categories');
        }
        $id = (int) $request->post('id', 0);
        $model = new Subcategory();
        $existing = $model->findById($id);
        if ($existing === null) {
            Session::flash('error', 'Subcategoria não encontrada.');
            Url::redirect('/categories');
        }

        $categoryId = (int) $request->post('category_id', 0);
        $name = trim((string) $request->post('name', ''));
        $slug = trim((string) $request->post('slug', ''));
        $slug = $slug === '' ? Slugger::slugify($name) : Slugger::slugify($slug);

        $err = $this->validateSubcategory($categoryId, $name, $slug);
        if ($err !== null) {
            Session::flash('subcategory_form_error', $err);
            Url::redirect('/subcategories/edit?id=' . $id);
        }

        $cat = new Category();
        if ($cat->findById($categoryId) === null) {
            Session::flash('subcategory_form_error', 'Categoria inválida.');
            Url::redirect('/subcategories/edit?id=' . $id);
        }

        if ($model->slugExists($slug, $id)) {
            Session::flash('subcategory_form_error', 'Slug já cadastrado.');
            Url::redirect('/subcategories/edit?id=' . $id);
        }

        $model->update($id, $categoryId, $name, $slug);
        Session::flash('ok', 'Subcategoria atualizada.');
        Url::redirect('/categories');
    }

    public function deleteSubcategory(Request $request): void
    {
        Auth::requireAdmin();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/categories');
        }
        $id = (int) $request->post('id', 0);
        if ($id < 1) {
            Session::flash('error', 'Subcategoria inválida.');
            Url::redirect('/categories');
        }
        $model = new Subcategory();
        $model->delete($id);
        Session::flash('ok', 'Subcategoria removida.');
        Url::redirect('/categories');
    }

    private function validateCategory(string $name, string $slug): ?string
    {
        if ($name === '' || mb_strlen($name, 'UTF-8') < 2) {
            return 'Informe um nome de categoria válido.';
        }
        if ($slug === '' || !preg_match('/^[a-z0-9-]{2,190}$/', $slug)) {
            return 'Slug inválido. Use letras minúsculas, números e hífen.';
        }
        return null;
    }

    private function validateSubcategory(int $categoryId, string $name, string $slug): ?string
    {
        if ($categoryId < 1) {
            return 'Selecione uma categoria.';
        }
        if ($name === '' || mb_strlen($name, 'UTF-8') < 2) {
            return 'Informe um nome de subcategoria válido.';
        }
        if ($slug === '' || !preg_match('/^[a-z0-9-]{2,190}$/', $slug)) {
            return 'Slug inválido. Use letras minúsculas, números e hífen.';
        }
        return null;
    }
}

