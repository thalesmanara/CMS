<?php

declare(strict_types=1);

namespace Revita\Crm\Controllers;

use Revita\Crm\Core\Auth;
use Revita\Crm\Core\Csrf;
use Revita\Crm\Core\Request;
use Revita\Crm\Core\Response;
use Revita\Crm\Core\Session;
use Revita\Crm\Core\View;
use Revita\Crm\Helpers\Mail;
use Revita\Crm\Helpers\Url;
use Revita\Crm\Models\PasswordReset;
use Revita\Crm\Models\User;

final class AuthController
{
    private const MAX_ATTEMPTS = 8;

    private const LOCK_SECONDS = 120;

    public function root(Request $request): void
    {
        if (Auth::check()) {
            Url::redirect('/dashboard');
        }
        Url::redirect('/login');
    }

    public function showLogin(Request $request): void
    {
        if (Auth::check()) {
            Url::redirect('/dashboard');
        }
        $error = Session::flash('auth_error');
        $notice = Session::flash('auth_notice');
        $success = Session::flash('auth_success');
        $html = View::layout('guest', 'auth/login', [
            'title' => 'Entrar — Revita CMS',
            'csrfToken' => Csrf::token(),
            'error' => $error,
            'notice' => $notice,
            'success' => $success,
        ]);
        Response::html($html);
    }

    public function login(Request $request): void
    {
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('auth_error', 'Sessão expirada. Tente novamente.');
            Url::redirect('/login');
        }

        if ($this->isLocked()) {
            Session::flash('auth_error', 'Muitas tentativas. Aguarde alguns minutos e tente de novo.');
            Url::redirect('/login');
        }

        $login = trim((string) $request->post('login', ''));
        $password = (string) $request->post('password', '');

        if ($login === '' || $password === '') {
            Session::flash('auth_error', 'Informe login e senha.');
            Url::redirect('/login');
        }

        $model = new User();
        $row = $model->findByLogin($login);
        if ($row === null || !(int) ($row['is_active'] ?? 0)) {
            $this->registerFailure();
            Session::flash('auth_error', 'Login ou senha inválidos.');
            Url::redirect('/login');
        }

        if (!password_verify($password, (string) $row['password_hash'])) {
            $this->registerFailure();
            Session::flash('auth_error', 'Login ou senha inválidos.');
            Url::redirect('/login');
        }

        $this->clearFailures();
        Auth::login([
            'id' => (int) $row['id'],
            'login' => (string) $row['login'],
            'level' => (int) $row['level'],
            'email' => (string) $row['email'],
        ]);
        Url::redirect('/dashboard');
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        Url::redirect('/login');
    }

    public function showForgotPassword(Request $request): void
    {
        if (Auth::check()) {
            Url::redirect('/dashboard');
        }
        $info = Session::flash('forgot_info');
        $error = Session::flash('forgot_error');
        $html = View::layout('guest', 'auth/forgot-password', [
            'title' => 'Recuperar senha — Revita CMS',
            'csrfToken' => Csrf::token(),
            'info' => $info,
            'error' => $error,
        ]);
        Response::html($html);
    }

    public function sendResetLink(Request $request): void
    {
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('forgot_error', 'Sessão expirada. Tente novamente.');
            Url::redirect('/forgot-password');
        }

        $email = trim((string) $request->post('email', ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('forgot_error', 'Informe um e-mail válido.');
            Url::redirect('/forgot-password');
        }

        $model = new User();
        $row = $model->findByEmail($email);
        if ($row !== null && (int) ($row['is_active'] ?? 0) === 1) {
            $reset = new PasswordReset();
            $plain = $reset->createForUser((int) $row['id']);
            $link = Url::adminAbsolute('reset-password?token=' . rawurlencode($plain));
            $body = "Olá,\r\n\r\nPara redefinir sua senha no Revita CMS, acesse o link abaixo (válido por poucas horas):\r\n\r\n"
                . $link
                . "\r\n\r\nSe você não solicitou, ignore este e-mail.\r\n";
            Mail::sendPlain((string) $row['email'], 'Redefinição de senha — Revita CMS', $body);
        }

        Session::flash('auth_notice', 'Se o e-mail existir em nossa base, enviamos instruções para redefinição de senha.');
        Url::redirect('/login');
    }

    public function showResetPassword(Request $request): void
    {
        if (Auth::check()) {
            Url::redirect('/dashboard');
        }
        $token = trim((string) $request->query('token', ''));
        if ($token === '') {
            Session::flash('auth_error', 'Link inválido ou expirado.');
            Url::redirect('/login');
        }
        $reset = new PasswordReset();
        if ($reset->findValidByPlainToken($token) === null) {
            Session::flash('auth_error', 'Link inválido ou expirado.');
            Url::redirect('/login');
        }
        $error = Session::flash('reset_error');
        $html = View::layout('guest', 'auth/reset-password', [
            'title' => 'Nova senha — Revita CMS',
            'csrfToken' => Csrf::token(),
            'token' => $token,
            'error' => $error,
        ]);
        Response::html($html);
    }

    public function resetPassword(Request $request): void
    {
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('reset_error', 'Sessão expirada. Solicite um novo link.');
            Url::redirect('/forgot-password');
        }

        $token = trim((string) $request->post('token', ''));
        $pass = (string) $request->post('password', '');
        $pass2 = (string) $request->post('password_confirm', '');

        $reset = new PasswordReset();
        $row = $token !== '' ? $reset->findValidByPlainToken($token) : null;
        if ($row === null) {
            Session::flash('auth_error', 'Link inválido ou expirado.');
            Url::redirect('/login');
        }

        if (strlen($pass) < 8) {
            Session::flash('reset_error', 'A senha deve ter no mínimo 8 caracteres.');
            Url::redirect('/reset-password?token=' . rawurlencode($token));
        }
        if ($pass !== $pass2) {
            Session::flash('reset_error', 'As senhas não conferem.');
            Url::redirect('/reset-password?token=' . rawurlencode($token));
        }

        $userId = (int) $row['user_id'];
        $resetId = (int) $row['id'];
        $model = new User();
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $model->updatePassword($userId, $hash);
        $reset->markUsed($resetId);

        Session::flash('auth_success', 'Senha alterada. Faça login com a nova senha.');
        Url::redirect('/login');
    }

    private function isLocked(): bool
    {
        $until = (int) Session::get('auth_lock_until', 0);
        return $until > time();
    }

    private function registerFailure(): void
    {
        $n = (int) Session::get('auth_fail_count', 0) + 1;
        Session::set('auth_fail_count', $n);
        if ($n >= self::MAX_ATTEMPTS) {
            Session::set('auth_lock_until', time() + self::LOCK_SECONDS);
            Session::set('auth_fail_count', 0);
        }
    }

    private function clearFailures(): void
    {
        Session::remove('auth_fail_count');
        Session::remove('auth_lock_until');
    }
}
