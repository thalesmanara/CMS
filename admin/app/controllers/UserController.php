<?php

declare(strict_types=1);

namespace Revita\Crm\Controllers;

use Revita\Crm\Core\Auth;
use Revita\Crm\Core\Csrf;
use Revita\Crm\Core\Request;
use Revita\Crm\Core\Response;
use Revita\Crm\Core\Session;
use Revita\Crm\Core\View;
use Revita\Crm\Helpers\Url;
use Revita\Crm\Models\User;

final class UserController
{
    public function index(Request $request): void
    {
        Auth::requireAdmin();
        $model = new User();
        $users = $model->allOrdered();
        $flashOk = Session::flash('ok');
        $flashErr = Session::flash('error');
        $html = View::layout('admin', 'users/index', [
            'title' => 'Usuários — Revita CMS',
            'nav' => 'users',
            'user' => Auth::user(),
            'users' => $users,
            'flashOk' => $flashOk,
            'flashErr' => $flashErr,
            'csrfToken' => Csrf::token(),
        ]);
        Response::html($html);
    }

    public function createForm(Request $request): void
    {
        Auth::requireAdmin();
        $html = View::layout('admin', 'users/form', [
            'title' => 'Novo usuário — Revita CMS',
            'nav' => 'users',
            'user' => Auth::user(),
            'editUser' => null,
            'error' => Session::flash('user_form_error'),
            'csrfToken' => Csrf::token(),
        ]);
        Response::html($html);
    }

    public function store(Request $request): void
    {
        Auth::requireAdmin();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('user_form_error', 'Sessão expirada. Tente novamente.');
            Url::redirect('/users/create');
        }

        $login = trim((string) $request->post('login', ''));
        $email = trim((string) $request->post('email', ''));
        $password = (string) $request->post('password', '');
        $level = (int) $request->post('level', 2);
        $active = $request->postFlag('is_active');

        $err = $this->validateUserInput($login, $email, $password, $level, true);
        if ($err !== null) {
            Session::flash('user_form_error', $err);
            Url::redirect('/users/create');
        }

        $model = new User();
        if ($model->loginExists($login) || $model->emailExists($email)) {
            Session::flash('user_form_error', 'Login ou e-mail já cadastrado.');
            Url::redirect('/users/create');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $model->insert($login, strtolower($email), $hash, $level, $active);
        Session::flash('ok', 'Usuário criado com sucesso.');
        Url::redirect('/users');
    }

    public function editForm(Request $request): void
    {
        Auth::requireAdmin();
        $id = (int) $request->query('id', 0);
        if ($id < 1) {
            Session::flash('error', 'Usuário inválido.');
            Url::redirect('/users');
        }
        $model = new User();
        $row = $model->findById($id);
        if ($row === null) {
            Session::flash('error', 'Usuário não encontrado.');
            Url::redirect('/users');
        }
        $html = View::layout('admin', 'users/form', [
            'title' => 'Editar usuário — Revita CMS',
            'nav' => 'users',
            'user' => Auth::user(),
            'editUser' => $row,
            'error' => Session::flash('user_form_error'),
            'csrfToken' => Csrf::token(),
        ]);
        Response::html($html);
    }

    public function update(Request $request): void
    {
        Auth::requireAdmin();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('user_form_error', 'Sessão expirada.');
            Url::redirect('/users');
        }

        $id = (int) $request->post('id', 0);
        if ($id < 1) {
            Session::flash('error', 'Usuário inválido.');
            Url::redirect('/users');
        }

        $model = new User();
        $existing = $model->findById($id);
        if ($existing === null) {
            Session::flash('error', 'Usuário não encontrado.');
            Url::redirect('/users');
        }

        $login = trim((string) $request->post('login', ''));
        $email = trim((string) $request->post('email', ''));
        $password = (string) $request->post('password', '');
        $level = (int) $request->post('level', 2);
        $active = $request->postFlag('is_active');

        $err = $this->validateUserInput($login, $email, $password, $level, false);
        if ($err !== null) {
            Session::flash('user_form_error', $err);
            Url::redirect('/users/edit?id=' . $id);
        }

        if ($model->loginExists($login, $id) || $model->emailExists($email, $id)) {
            Session::flash('user_form_error', 'Login ou e-mail já cadastrado.');
            Url::redirect('/users/edit?id=' . $id);
        }

        $me = Auth::user();
        $isSelf = $me !== null && (int) $me['id'] === $id;
        $wasAdmin = (int) $existing['level'] === 1 && (int) $existing['is_active'] === 1;

        if ($wasAdmin && ($level !== 1 || !$active)) {
            if ($model->countActiveAdmins() <= 1) {
                Session::flash('user_form_error', 'Não é possível remover o último administrador ativo do sistema.');
                Url::redirect('/users/edit?id=' . $id);
            }
        }

        if ($isSelf && (!$active || $level !== 1)) {
            if ($model->countActiveAdmins() <= 1) {
                Session::flash('user_form_error', 'Você não pode rebaixar ou desativar a si mesmo enquanto for o único administrador ativo.');
                Url::redirect('/users/edit?id=' . $id);
            }
        }

        $hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
        $model->update($id, $login, strtolower($email), $hash, $level, $active);
        Session::flash('ok', 'Usuário atualizado.');
        Url::redirect('/users');
    }

    public function delete(Request $request): void
    {
        Auth::requireAdmin();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/users');
        }

        $id = (int) $request->post('id', 0);
        if ($id < 1) {
            Session::flash('error', 'Usuário inválido.');
            Url::redirect('/users');
        }

        $me = Auth::user();
        if ($me !== null && (int) $me['id'] === $id) {
            Session::flash('error', 'Não é possível excluir o seu próprio usuário.');
            Url::redirect('/users');
        }

        $model = new User();
        $row = $model->findById($id);
        if ($row === null) {
            Session::flash('error', 'Usuário não encontrado.');
            Url::redirect('/users');
        }

        if ((int) $row['level'] === 1 && (int) $row['is_active'] === 1 && $model->countActiveAdmins() <= 1) {
            Session::flash('error', 'Não é possível excluir o último administrador ativo.');
            Url::redirect('/users');
        }

        $model->deleteById($id);
        Session::flash('ok', 'Usuário removido.');
        Url::redirect('/users');
    }

    private function validateUserInput(
        string $login,
        string $email,
        string $password,
        int $level,
        bool $passwordRequired
    ): ?string {
        if (!preg_match('/^[a-zA-Z0-9._-]{3,80}$/', $login)) {
            return 'Login: use de 3 a 80 caracteres (letras, números, ponto, _ e hífen).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'E-mail inválido.';
        }
        if ($level !== 1 && $level !== 2) {
            return 'Nível de acesso inválido.';
        }
        if ($passwordRequired && $password === '') {
            return 'Informe uma senha.';
        }
        if ($password !== '' && strlen($password) < 8) {
            return 'A senha deve ter no mínimo 8 caracteres.';
        }

        return null;
    }
}
