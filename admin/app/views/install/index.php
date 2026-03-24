<?php

declare(strict_types=1);

use Revita\Crm\Helpers\Escape;

/** @var string $csrfToken */
/** @var string|null $error */
?>
<h2 class="h4 mb-3">Configuração do banco de dados</h2>
<p class="text-secondary small mb-4">Informe os dados do MySQL criados manualmente na hospedagem. Após concluir, esta tela deixará de ficar disponível.</p>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?= nl2br(Escape::html($error)) ?></div>
<?php endif; ?>

<form method="post" action="">
  <input type="hidden" name="_csrf" value="<?= Escape::html($csrfToken) ?>">

  <div class="mb-3">
    <label class="form-label" for="db_host">Host</label>
    <input class="form-control" id="db_host" name="db_host" required placeholder="localhost" autocomplete="off">
  </div>
  <div class="mb-3">
    <label class="form-label" for="db_name">Nome do banco</label>
    <input class="form-control" id="db_name" name="db_name" required autocomplete="off">
  </div>
  <div class="mb-3">
    <label class="form-label" for="db_user">Usuário</label>
    <input class="form-control" id="db_user" name="db_user" required autocomplete="off">
  </div>
  <div class="mb-4">
    <label class="form-label" for="db_password">Senha</label>
    <input class="form-control" type="password" id="db_password" name="db_password" autocomplete="off">
  </div>

  <button type="submit" class="btn btn-revita w-100">Instalar e criar tabelas</button>
</form>
