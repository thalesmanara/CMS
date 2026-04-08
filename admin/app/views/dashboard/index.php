<?php

declare(strict_types=1);

use Revita\Crm\Helpers\Escape;

/** @var array{id:int,login:string,level:int,email:string}|null $user */
/** @var string|null $flashOk */
/** @var string|null $flashErr */
?>
<?php if (!empty($flashOk)): ?>
  <div class="alert alert-success"><?= Escape::html($flashOk) ?></div>
<?php endif; ?>
<?php if (!empty($flashErr)): ?>
  <div class="alert alert-danger"><?= Escape::html($flashErr) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="border-radius: 12px;">
  <div class="card-body p-4">
    <h2 class="h5 mb-3">Bem-vindo ao CMS</h2>
    <p class="text-secondary mb-0">
      Olá, <strong><?= $user ? Escape::html($user['login']) : '' ?></strong>.
      O instalador e o login estão ativos. Nas próximas etapas entrarão usuários, categorias, páginas, postagens, mídias e API JSON.
    </p>
  </div>
</div>
