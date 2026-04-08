<?php

declare(strict_types=1);

namespace Revita\Crm\Controllers;

use Revita\Crm\Core\Auth;
use Revita\Crm\Core\Request;
use Revita\Crm\Core\Response;
use Revita\Crm\Core\Session;
use Revita\Crm\Core\View;

final class DashboardController
{
    public function index(Request $request): void
    {
        Auth::requireAuth();
        $user = Auth::user();
        $flashOk = Session::flash('ok');
        $flashErr = Session::flash('error');
        $html = View::layout('admin', 'dashboard/index', [
            'title' => 'Painel — Revita CMS',
            'nav' => 'dashboard',
            'user' => $user,
            'flashOk' => $flashOk,
            'flashErr' => $flashErr,
        ]);
        Response::html($html);
    }
}
