<?php

declare(strict_types=1);

use Revita\Crm\Core\Auth;
use Revita\Crm\Helpers\Escape;
use Revita\Crm\Helpers\Url;

/** @var string $title */
/** @var string $content */
/** @var array{id:int,login:string,level:int,email:string}|null $user */
/** @var string $nav */

$nav = $nav ?? 'dashboard';
$assetLogo = Url::to('/assets/img/logoRevita.png');
$isAdmin = Auth::isAdmin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= Escape::html($title) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <style>
    :root {
      --revita-orange: #FF912C;
      --revita-sidebar: #1e2228;
      --revita-sidebar-hover: #2a3038;
      --revita-bg: #f4f5f7;
    }
    body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--revita-bg); }
    .sidebar {
      min-height: 100vh;
      background: var(--revita-sidebar);
      color: #e8eaed;
    }
    .sidebar a {
      color: #c9ced6;
      text-decoration: none;
      display: block;
      padding: 0.65rem 1.25rem;
      border-radius: 8px;
      margin: 0.15rem 0.75rem;
    }
    .sidebar a:hover, .sidebar a.active {
      background: var(--revita-sidebar-hover);
      color: #fff;
    }
    .sidebar .brand {
      padding: 1.25rem 1rem;
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .topbar {
      background: #fff;
      border-bottom: 1px solid #e3e5e8;
    }
    .badge-level { background: var(--revita-orange); }
    .btn-revita {
      background-color: var(--revita-orange);
      border-color: var(--revita-orange);
      color: #fff;
      font-weight: 600;
      border-radius: 10px;
    }
    .btn-revita:hover {
      background-color: #e67e24;
      border-color: #e67e24;
      color: #fff;
    }
  </style>
</head>
<body>
<div class="container-fluid">
  <div class="row g-0">
    <nav class="col-12 col-md-3 col-lg-2 sidebar px-0 py-0">
      <div class="brand text-center">
        <a href="<?= Escape::html(Url::to('/dashboard')) ?>" class="d-inline-block p-0">
          <img src="<?= Escape::html($assetLogo) ?>" alt="Revita" class="img-fluid" style="max-width: 160px;">
        </a>
      </div>
      <div class="py-3">
        <a class="<?= $nav === 'dashboard' ? 'active' : '' ?>" href="<?= Escape::html(Url::to('/dashboard')) ?>">Dashboard</a>
        <?php if ($isAdmin): ?>
          <a class="<?= $nav === 'users' ? 'active' : '' ?>" href="<?= Escape::html(Url::to('/users')) ?>">Usuários</a>
          <a class="<?= $nav === 'categories' ? 'active' : '' ?>" href="<?= Escape::html(Url::to('/categories')) ?>">Categorias</a>
        <?php endif; ?>
        <a class="<?= $nav === 'pages' ? 'active' : '' ?>" href="<?= Escape::html(Url::to('/pages')) ?>">Páginas</a>
        <a class="<?= $nav === 'posts' ? 'active' : '' ?>" href="<?= Escape::html(Url::to('/posts')) ?>">Postagens</a>
        <?php if ($isAdmin): ?>
          <a class="<?= $nav === 'endpoints' ? 'active' : '' ?>" href="<?= Escape::html(Url::to('/endpoints')) ?>">Endpoints</a>
        <?php endif; ?>
      </div>
    </nav>
    <main class="col px-0">
      <header class="topbar py-3 px-4 d-flex justify-content-between align-items-center">
        <h1 class="h5 mb-0 text-secondary"><?= Escape::html($title) ?></h1>
        <div class="d-flex align-items-center gap-3">
          <?php if ($user): ?>
            <span class="small text-muted"><?= Escape::html($user['login']) ?></span>
            <span class="badge badge-level"><?= (int) $user['level'] === 1 ? 'Admin' : 'Editor' ?></span>
          <?php endif; ?>
          <?php if ($isAdmin): ?>
            <div class="dropdown">
              <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                Backup
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= Escape::html(Url::to('/backup/export')) ?>">Exportar (baixar)</a></li>
                <li><a class="dropdown-item" href="<?= Escape::html(Url::to('/backup')) ?>">Importar / Gerenciar</a></li>
              </ul>
            </div>
          <?php endif; ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?= Escape::html(Url::to('/logout')) ?>">Sair</a>
        </div>
      </header>
      <div class="p-4">
        <?= $content ?>
      </div>
    </main>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
</body>
</html>
