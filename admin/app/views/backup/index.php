<?php

declare(strict_types=1);

use Revita\Crm\Helpers\Escape;
use Revita\Crm\Helpers\Url;

/** @var string $csrfToken */
/** @var string|null $flashOk */
/** @var string|null $flashErr */
?>

<?php if (!empty($flashOk)): ?>
  <div class="alert alert-success"><?= Escape::html($flashOk) ?></div>
<?php endif; ?>
<?php if (!empty($flashErr)): ?>
  <div class="alert alert-danger"><?= Escape::html($flashErr) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="border-radius:12px;">
  <div class="card-body p-4">
    <h2 class="h5 mb-2">Backup / Migração</h2>
    <p class="text-secondary mb-4">
      Exporta e importa todos os dados do painel (tabelas do banco) e, quando disponível, também a pasta <code>uploads/</code>.
      Use para backups e migrações completas entre projetos.
    </p>

    <div class="row g-4">
      <div class="col-12 col-lg-6">
        <div class="border rounded p-3 bg-white">
          <h3 class="h6 mb-2">Exportar</h3>
          <p class="text-secondary small mb-3">Gera um arquivo <code>.zip</code> com <code>db.json</code> + <code>uploads/</code>.</p>
          <a class="btn btn-revita" href="<?= Escape::html(Url::to('/backup/export')) ?>">Baixar backup</a>
        </div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="border rounded p-3 bg-white">
          <h3 class="h6 mb-2">Importar</h3>
          <p class="text-secondary small mb-3">
            Importar substitui os dados atuais do banco. Recomenda-se fazer export antes.
          </p>
          <form method="post" action="<?= Escape::html(Url::to('/backup/import')) ?>" enctype="multipart/form-data"
                onsubmit="return confirm('Importar irá substituir os dados atuais do painel. Deseja continuar?');">
            <input type="hidden" name="_csrf" value="<?= Escape::html($csrfToken) ?>">
            <input class="form-control mb-3" type="file" name="backup_file" accept=".zip,.json" required>
            <button type="submit" class="btn btn-outline-secondary">Importar backup</button>
          </form>
        </div>
      </div>
    </div>

  </div>
</div>

