<?php

declare(strict_types=1);

use Revita\Crm\Helpers\Escape;
use Revita\Crm\Helpers\Url;

/** @var string|null $error */
/** @var string $csrfToken */
/** @var list<array<string,mixed>> $categories */
/** @var list<array<string,mixed>> $subcategoriesRows */
/** @var bool $hasSubcategories */
/** @var bool $hasCategories */
?>
<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?= Escape::html($error) ?></div>
<?php endif; ?>

<div class="mb-4">
  <a href="<?= Escape::html(Url::to('/posts')) ?>" class="text-decoration-none small">← Voltar</a>
</div>

<h2 class="h5 mb-4">Nova postagem</h2>

<?php if (!$hasCategories): ?>
  <div class="alert alert-warning">Cadastre ao menos uma <a href="<?= Escape::html(Url::to('/categories')) ?>">categoria e subcategoria</a> antes de criar posts.</div>
<?php elseif (!$hasSubcategories): ?>
  <div class="alert alert-warning">Cadastre ao menos uma <a href="<?= Escape::html(Url::to('/categories')) ?>">subcategoria</a> antes de criar posts.</div>
<?php endif; ?>

<?php $canCreate = $hasCategories && $hasSubcategories; ?>

<form method="post" action="<?= Escape::html(Url::to('/posts/store')) ?>" class="card border-0 shadow-sm p-4" style="border-radius:12px;max-width:560px;">
  <input type="hidden" name="_csrf" value="<?= Escape::html($csrfToken) ?>">
  <div class="mb-3">
    <label class="form-label" for="title">Título</label>
    <input class="form-control" id="title" name="title" required minlength="2" maxlength="190" <?= !$canCreate ? 'disabled' : '' ?>>
  </div>
  <div class="mb-3">
    <label class="form-label" for="slug">Slug <span class="text-muted small">(opcional)</span></label>
    <input class="form-control" id="slug" name="slug" maxlength="190" pattern="[a-z0-9-]*" placeholder="gerado a partir do título" <?= !$canCreate ? 'disabled' : '' ?>>
  </div>
  <div class="mb-3">
    <label class="form-label" for="category_id">Categoria</label>
    <select class="form-select" id="category_id" name="category_id" required <?= !$canCreate ? 'disabled' : '' ?>>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int) $c['id'] ?>"><?= Escape::html((string) $c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label" for="subcategory_id">Subcategoria</label>
    <select class="form-select" id="subcategory_id" name="subcategory_id" required <?= !$canCreate ? 'disabled' : '' ?>>
      <?php foreach ($subcategoriesRows as $s): ?>
        <option value="<?= (int) $s['id'] ?>" data-category-id="<?= (int) $s['category_id'] ?>">
          <?= Escape::html((string) $s['category_name']) ?> — <?= Escape::html((string) $s['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="mb-4 form-check">
    <input class="form-check-input" type="checkbox" id="status_pub" name="status" value="published" <?= !$canCreate ? 'disabled' : '' ?>>
    <label class="form-check-label" for="status_pub">Publicada</label>
  </div>
  <button type="submit" class="btn btn-revita" <?= !$canCreate ? 'disabled' : '' ?>>Criar</button>
</form>
<script>
(function () {
  var cat = document.getElementById('category_id');
  var sub = document.getElementById('subcategory_id');
  if (!cat || !sub) return;
  function filterSubs() {
    var cid = cat.value;
    sub.querySelectorAll('option').forEach(function (o) {
      var ok = o.getAttribute('data-category-id') === cid;
      o.hidden = !ok;
      o.style.display = ok ? '' : 'none';
    });
    var vis = Array.prototype.filter.call(sub.options, function (o) { return !o.hidden; });
    if (vis.length && sub.selectedOptions.length && sub.selectedOptions[0].hidden) {
      sub.value = vis[0].value;
    }
  }
  cat.addEventListener('change', filterSubs);
  filterSubs();
})();
</script>
