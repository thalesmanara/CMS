<?php

declare(strict_types=1);

use Revita\Crm\Helpers\Escape;

/** @var array<string, array<int, array{method:string,url:string,note:string}>> $endpoints */
?>
<div class="card border-0 shadow-sm" style="border-radius:12px;">
  <div class="card-body p-4">
    <h2 class="h5 mb-3">Endpoints do projeto</h2>
    <p class="text-secondary mb-4">
      Lista completa das rotas do painel <code>/admin</code> e da API JSON <code>/admin/api</code> (para facilitar o consumo no site).
    </p>

    <?php foreach ($endpoints as $section => $items): ?>
      <h3 class="h6 mt-4 mb-3"><?= Escape::html((string) $section) ?></h3>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle" style="border-radius:12px;">
          <thead class="table-light">
            <tr>
              <th style="width:90px;">Método</th>
              <th>Endpoint</th>
              <th>Observação</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $e): ?>
              <tr>
                <td><code class="small"><?= Escape::html((string) $e['method']) ?></code></td>
                <td>
                  <code class="small"><?= Escape::html((string) $e['url']) ?></code>
                </td>
                <td class="small text-muted"><?= Escape::html((string) $e['note']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>
  </div>
</div>

