<?php

declare(strict_types=1);

use Revita\Crm\Helpers\Escape;
use Revita\Crm\Helpers\Url;
use Revita\Crm\Services\PageApiSerializer;

/** @var array<string,mixed> $page */
/** @var list<array<string,mixed>> $blocks */
/** @var list<array<string,mixed>> $sections */
/** @var string $csrfToken */
/** @var bool $isAdmin */
/** @var string|null $flashOk */
/** @var string|null $flashErr */
/** @var string|null $metaError */
/** @var string|null $contentError */
/** @var string|null $fieldError */

$formContent = 'page-content-form';
$pageId = (int) $page['id'];
$sections = isset($sections) && is_array($sections) ? $sections : [];

$blocksBySection = [];
foreach ($blocks as $b) {
    $sid = isset($b['field']['section_id']) && $b['field']['section_id'] !== null ? (int) $b['field']['section_id'] : 0;
    if (!isset($blocksBySection[$sid])) {
        $blocksBySection[$sid] = [];
    }
    $blocksBySection[$sid][] = $b;
}

if (!function_exists('revita_crm_page_field_label')) {
    function revita_crm_page_field_label(string $t): string {
        return match ($t) {
            'texto' => 'Texto',
            'botao' => 'Botão',
            'icone' => 'Ícone',
            'foto' => 'Foto',
            'galeria_fotos' => 'Galeria de fotos',
            'video' => 'Vídeo',
            'galeria_videos' => 'Galeria de vídeos',
            'repetidor' => 'Repetidor',
            default => $t,
        };
    }
}
?>
<?php if (!empty($flashOk)): ?>
  <div class="alert alert-success"><?= Escape::html($flashOk) ?></div>
<?php endif; ?>
<?php if (!empty($flashErr)): ?>
  <div class="alert alert-danger"><?= Escape::html($flashErr) ?></div>
<?php endif; ?>

<div class="mb-3">
  <a href="<?= Escape::html(Url::to('/pages')) ?>" class="text-decoration-none small">← Lista de páginas</a>
</div>

<h2 class="h5 mb-3">Editar: <?= Escape::html((string) $page['title']) ?></h2>

<?php if (!empty($metaError)): ?>
  <div class="alert alert-warning"><?= Escape::html($metaError) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
  <div class="card-body p-4">
    <h3 class="h6 mb-3">Dados da página</h3>
    <form method="post" action="<?= Escape::html(Url::to('/pages/update-meta')) ?>">
      <input type="hidden" name="_csrf" value="<?= Escape::html($csrfToken) ?>">
      <input type="hidden" name="id" value="<?= $pageId ?>">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label" for="title">Título</label>
          <input class="form-control" id="title" name="title" required value="<?= Escape::html((string) $page['title']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label" for="slug">Slug</label>
          <input class="form-control font-monospace" id="slug" name="slug" required pattern="[a-z0-9-]+" value="<?= Escape::html((string) $page['slug']) ?>">
        </div>
        <div class="col-md-3">
          <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" id="status_published" name="status_published" value="1" <?= (string) $page['status'] === 'published' ? 'checked' : '' ?>>
            <label class="form-check-label" for="status_published">Publicada</label>
          </div>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-outline-secondary w-100">Salvar</button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
  <div class="card-body p-4">
    <h3 class="h6 mb-3">Seções e Campos</h3>

    <form method="post" action="<?= Escape::html(Url::to('/pages/add-section')) ?>" class="mb-4">
      <input type="hidden" name="_csrf" value="<?= Escape::html($csrfToken) ?>">
      <input type="hidden" name="page_id" value="<?= $pageId ?>">
      <h4 class="small text-secondary mb-2">Adicionar seção</h4>
      <div class="row g-2 align-items-end">
        <div class="col-md-10">
          <label class="form-label" for="section_title">Nome da seção</label>
          <input class="form-control" id="section_title" name="section_title" required placeholder="Ex.: Hero / Sobre / Rodapé">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-outline-secondary w-100">Adicionar</button>
        </div>
      </div>
    </form>

    <form method="post" action="<?= Escape::html(Url::to('/pages/add-field')) ?>" class="mb-4">
      <input type="hidden" name="_csrf" value="<?= Escape::html($csrfToken) ?>">
      <input type="hidden" name="page_id" value="<?= $pageId ?>">
      <h4 class="small text-secondary mb-2">Adicionar campo</h4>
      <?php if (!empty($fieldError)): ?>
        <div class="alert alert-danger py-2"><?= Escape::html($fieldError) ?></div>
      <?php endif; ?>
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label" for="section_id">Seção</label>
          <select class="form-select" id="section_id" name="section_id">
            <option value="0">(sem seção)</option>
            <?php foreach ($sections as $s): ?>
              <option value="<?= (int) $s['id'] ?>"><?= Escape::html((string) $s['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="label_name">Nome (label)</label>
          <input class="form-control" id="label_name" name="label_name" required placeholder="Ex.: Texto principal">
        </div>
        <div class="col-md-3">
          <label class="form-label" for="field_key">Identificador <span class="text-muted small">(opcional)</span></label>
          <input class="form-control" id="field_key" name="field_key" pattern="[a-z0-9-]*" placeholder="auto">
        </div>
        <div class="col-md-2">
          <label class="form-label" for="field_type">Tipo</label>
          <select class="form-select" id="field_type" name="field_type">
            <?php foreach (['texto', 'botao', 'icone', 'foto', 'galeria_fotos', 'video', 'galeria_videos', 'repetidor'] as $ft): ?>
              <option value="<?= Escape::html($ft) ?>"><?= Escape::html(revita_crm_page_field_label($ft)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-revita w-100">Adicionar</button>
        </div>
      </div>
    </form>

    <form method="post" action="<?= Escape::html(Url::to('/pages/reorder-fields')) ?>" id="form-reorder">
      <input type="hidden" name="_csrf" value="<?= Escape::html($csrfToken) ?>">
      <input type="hidden" name="page_id" value="<?= $pageId ?>">
      <h4 class="small text-secondary mb-2">Ordem das seções e campos</h4>
      <div id="sort-sections" style="max-width:720px;">
        <div class="border rounded p-3 mb-3 bg-light" data-section-id="0">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>(sem seção)</strong>
            <span class="text-muted small">Campos soltos</span>
          </div>
          <ul class="list-group" data-fields-list="1" data-section-id="0">
            <?php foreach ($blocksBySection[0] ?? [] as $b): ?>
              <?php $fid = (int) $b['field']['id']; ?>
              <li class="list-group-item d-flex justify-content-between align-items-center" data-field-id="<?= $fid ?>">
                <span><span class="text-muted me-2">↕</span><?= Escape::html((string) $b['field']['label_name']) ?> <small class="text-muted">(<?= Escape::html(revita_crm_page_field_label((string) $b['field']['field_type'])) ?>)</small></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <?php foreach ($sections as $s): ?>
          <?php $sid = (int) $s['id']; ?>
          <div class="border rounded p-3 mb-3" data-section-id="<?= $sid ?>">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <strong><span class="text-muted me-2">↕</span><?= Escape::html((string) $s['title']) ?></strong>
              <span class="text-muted small">Seção</span>
            </div>
            <ul class="list-group" data-fields-list="1" data-section-id="<?= $sid ?>">
              <?php foreach ($blocksBySection[$sid] ?? [] as $b): ?>
                <?php $fid = (int) $b['field']['id']; ?>
                <li class="list-group-item d-flex justify-content-between align-items-center" data-field-id="<?= $fid ?>">
                  <span><span class="text-muted me-2">↕</span><?= Escape::html((string) $b['field']['label_name']) ?> <small class="text-muted">(<?= Escape::html(revita_crm_page_field_label((string) $b['field']['field_type'])) ?>)</small></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </div>
      <div id="reorder-hidden"></div>
      <button type="button" class="btn btn-sm btn-outline-primary" id="btn-save-order">Aplicar ordem</button>
    </form>
  </div>
</div>

<?php if (!empty($contentError)): ?>
  <div class="alert alert-danger"><?= Escape::html($contentError) ?></div>
<?php endif; ?>

<form id="<?= Escape::html($formContent) ?>" method="post" action="<?= Escape::html(Url::to('/pages/update-content')) ?>" enctype="multipart/form-data" class="d-none" aria-hidden="true">
  <input type="hidden" name="_csrf" value="<?= Escape::html($csrfToken) ?>">
  <input type="hidden" name="page_id" value="<?= $pageId ?>">
</form>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
  <div class="card-body p-4">
    <h3 class="h6 mb-3">Conteúdo</h3>
    <?php
      $orderedSectionIds = [0];
      foreach ($sections as $s) {
        $orderedSectionIds[] = (int) $s['id'];
      }
      $firstSectionRendered = true;
    ?>
    <?php foreach ($orderedSectionIds as $secId): ?>
      <?php
        $secTitle = $secId === 0 ? '(sem seção)' : '';
        if ($secId !== 0) {
          foreach ($sections as $s) {
            if ((int) $s['id'] === $secId) {
              $secTitle = (string) $s['title'];
              break;
            }
          }
        }
        $secBlocks = $blocksBySection[$secId] ?? [];
      ?>
      <?php if ($secBlocks === []): ?>
        <?php continue; ?>
      <?php endif; ?>
      <div class="mb-3" style="<?= $firstSectionRendered ? '' : 'margin-top:80px;' ?>">
        <h3 class="mb-0"><?= Escape::html($secTitle) ?></h3>
        <div class="text-muted small">Campos desta seção</div>
      </div>
      <?php $firstSectionRendered = false; ?>
      <?php foreach ($secBlocks as $b): ?>
      <?php
        $f = $b['field'];
        $fid = (int) $f['id'];
        $ftype = (string) $f['field_type'];
      ?>
      <div class="card border-0 shadow-sm mb-4 field-block" style="border-radius:12px;" data-field-id="<?= $fid ?>">
        <div class="card-header d-flex justify-content-between align-items-center bg-white py-3">
          <div>
            <strong><?= Escape::html((string) $f['label_name']) ?></strong>
            <span class="badge bg-secondary ms-1"><?= Escape::html(revita_crm_page_field_label($ftype)) ?></span>
            <code class="small ms-1"><?= Escape::html((string) $f['field_key']) ?></code>
          </div>
          <?php if ($isAdmin): ?>
            <form method="post" action="<?= Escape::html(Url::to('/pages/delete-field')) ?>" class="m-0" onsubmit="return confirm('Remover este campo e seus dados?');">
              <input type="hidden" name="_csrf" value="<?= Escape::html($csrfToken) ?>">
              <input type="hidden" name="page_id" value="<?= $pageId ?>">
              <input type="hidden" name="field_id" value="<?= $fid ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger">Excluir campo</button>
            </form>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <?php if ($b['kind'] === 'scalar'): ?>
        <?php
          $v = $b['value'];
          $mid = null;
          $galIds = [];
          $vidJson = '';
          if ($ftype === 'foto' && $v && !empty($v['value_media_ids_json'])) {
            $tmp = json_decode((string) $v['value_media_ids_json'], true);
            if (is_array($tmp) && isset($tmp[0])) {
              $mid = (int) $tmp[0];
            }
          }
          if ($ftype === 'galeria_fotos' && $v && !empty($v['value_media_ids_json'])) {
            $tmp = json_decode((string) $v['value_media_ids_json'], true);
            if (is_array($tmp)) {
              $galIds = array_values(array_filter(array_map('intval', $tmp), static fn (int $x) => $x > 0));
            }
          }
          if (($ftype === 'video' || $ftype === 'galeria_videos') && $v && !empty($v['value_mixed_json'])) {
            $vidJson = (string) $v['value_mixed_json'];
          }
        ?>
        <?php if ($ftype === 'texto'): ?>
          <textarea class="form-control" name="fv_text_<?= $fid ?>" form="<?= Escape::html($formContent) ?>" rows="6"><?= $v ? Escape::html((string) ($v['value_text'] ?? '')) : '' ?></textarea>
        <?php elseif ($ftype === 'botao'): ?>
          <div class="row g-2">
            <div class="col-md-5">
              <label class="form-label small mb-1">Texto do botão</label>
              <input class="form-control" name="btn_text_<?= $fid ?>" form="<?= Escape::html($formContent) ?>" value="<?= $v ? Escape::html((string) ($v['value_text'] ?? '')) : '' ?>" placeholder="Ex.: Saiba mais">
            </div>
            <div class="col-md-7">
              <label class="form-label small mb-1">Link</label>
              <input class="form-control font-monospace" type="url" name="btn_url_<?= $fid ?>" form="<?= Escape::html($formContent) ?>" value="<?= $v ? Escape::html((string) ($v['value_url'] ?? '')) : '' ?>" placeholder="https://...">
            </div>
          </div>
        <?php elseif ($ftype === 'icone'): ?>
          <?php
            $icon = [];
            if ($v && !empty($v['value_mixed_json'])) {
              $dj = json_decode((string) $v['value_mixed_json'], true);
              if (is_array($dj)) {
                $icon = $dj;
              }
            }
            $iconSrc = (string) ($icon['source'] ?? 'registry');
            $iconKey = (string) ($icon['iconKey'] ?? '');
            $iconSet = (string) ($icon['iconSet'] ?? '');
            $iconStyle = (string) ($icon['iconStyle'] ?? '');
            $iconUrl = null;
            if ($iconSrc === 'upload' && !empty($icon['media_id'])) {
              $mrow = (new \Revita\Crm\Models\Media())->findById((int) $icon['media_id']);
              if ($mrow) {
                $iconUrl = PageApiSerializer::mediaPublicUrl((string) $mrow['relative_path']);
              }
            }
          ?>
          <div class="row g-2">
            <div class="col-md-3">
              <label class="form-label small mb-1">Origem</label>
              <select class="form-select" name="icon_src_<?= $fid ?>" form="<?= Escape::html($formContent) ?>">
                <option value="registry" <?= $iconSrc === 'registry' ? 'selected' : '' ?>>iconKey (frontend)</option>
                <option value="upload" <?= $iconSrc === 'upload' ? 'selected' : '' ?>>Upload SVG</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1">iconSet <span class="text-muted">(opcional)</span></label>
              <input class="form-control font-monospace" name="icon_set_<?= $fid ?>" form="<?= Escape::html($formContent) ?>" value="<?= Escape::html($iconSet) ?>" placeholder="lucide / brand">
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1">iconStyle <span class="text-muted">(opcional)</span></label>
              <input class="form-control font-monospace" name="icon_style_<?= $fid ?>" form="<?= Escape::html($formContent) ?>" value="<?= Escape::html($iconStyle) ?>" placeholder="outline / solid">
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1">iconKey</label>
              <input class="form-control font-monospace" name="icon_key_<?= $fid ?>" form="<?= Escape::html($formContent) ?>" value="<?= Escape::html($iconKey) ?>" placeholder="whatsapp / user / check-circle">
            </div>
            <div class="col-md-8">
              <label class="form-label small mb-1">SVG <span class="text-muted">(opcional)</span></label>
              <input type="file" class="form-control" name="icon_svg_<?= $fid ?>" form="<?= Escape::html($formContent) ?>" accept=".svg,image/svg+xml">
              <?php if (!empty($iconUrl)): ?>
                <div class="small mt-1">Atual: <a href="<?= Escape::html($iconUrl) ?>" target="_blank" rel="noreferrer">ver SVG</a></div>
              <?php endif; ?>
            </div>
          </div>
        <?php elseif ($ftype === 'foto'): ?>
          <?php if ($mid): ?>
            <?php $mrow = (new \Revita\Crm\Models\Media())->findById($mid); ?>
            <?php if ($mrow): ?>
              <p class="small"><img src="<?= Escape::html(PageApiSerializer::mediaPublicUrl((string) $mrow['relative_path'])) ?>" alt="" style="max-height:120px;"></p>
            <?php endif; ?>
          <?php endif; ?>
          <input type="file" class="form-control" name="file_field_<?= $fid ?>" form="<?= Escape::html($formContent) ?>" accept="image/*">
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="clear_foto_<?= $fid ?>" value="1" id="cl_<?= $fid ?>" form="<?= Escape::html($formContent) ?>">
            <label class="form-check-label small" for="cl_<?= $fid ?>">Remover imagem</label>
          </div>
        <?php elseif ($ftype === 'galeria_fotos'): ?>
          <input type="hidden" name="existing_gal_<?= $fid ?>" form="<?= Escape::html($formContent) ?>" value="<?= Escape::html(json_encode($galIds, JSON_UNESCAPED_UNICODE)) ?>">
          <p class="small text-muted">Novas imagens serão acrescentadas às existentes.</p>
          <input type="file" class="form-control" name="gal_<?= $fid ?>[]" form="<?= Escape::html($formContent) ?>" accept="image/*" multiple>
        <?php elseif ($ftype === 'video'): ?>
          <?php
            $vsrc = 'upload';
            $vyt = '';
            if ($vidJson !== '') {
              $dj = json_decode($vidJson, true);
              if (is_array($dj) && ($dj['source'] ?? '') === 'youtube') {
                $vsrc = 'youtube';
                $vyt = (string) ($dj['youtube_url'] ?? '');
              }
            }
          ?>
          <select class="form-select mb-2" name="vid_src_<?= $fid ?>" form="<?= Escape::html($formContent) ?>" style="max-width:220px;">
            <option value="upload" <?= $vsrc === 'upload' ? 'selected' : '' ?>>Arquivo (upload)</option>
            <option value="youtube" <?= $vsrc === 'youtube' ? 'selected' : '' ?>>YouTube</option>
          </select>
          <input type="url" class="form-control mb-2" name="vid_yt_<?= $fid ?>" form="<?= Escape::html($formContent) ?>" placeholder="URL do YouTube" value="<?= Escape::html($vyt) ?>" style="max-width:480px;">
          <input type="file" class="form-control" name="vid_file_<?= $fid ?>" form="<?= Escape::html($formContent) ?>" accept="video/*">
        <?php elseif ($ftype === 'galeria_videos'): ?>
          <?php
            $gvItems = [];
            if ($vidJson !== '') {
              $arr = json_decode($vidJson, true);
              if (is_array($arr)) {
                $gvItems = $arr;
              }
            }
            $nRows = max(1, count($gvItems) + 1);
          ?>
          <?php for ($gi = 0; $gi < $nRows; $gi++): ?>
            <?php
              $gvr = $gvItems[$gi] ?? [];
              $gsrc = (is_array($gvr) && ($gvr['source'] ?? '') === 'youtube') ? 'youtube' : 'upload';
              $gyt = is_array($gvr) ? (string) ($gvr['youtube_url'] ?? '') : '';
            ?>
            <div class="row g-2 mb-2 align-items-center border rounded p-2">
              <div class="col-auto">
                <select class="form-select form-select-sm" name="gv_src[<?= $fid ?>][]" form="<?= Escape::html($formContent) ?>">
                  <option value="upload" <?= $gsrc === 'upload' ? 'selected' : '' ?>>Upload</option>
                  <option value="youtube" <?= $gsrc === 'youtube' ? 'selected' : '' ?>>YouTube</option>
                </select>
              </div>
              <div class="col">
                <input class="form-control form-control-sm" type="url" name="gv_yt[<?= $fid ?>][]" form="<?= Escape::html($formContent) ?>" placeholder="URL YouTube" value="<?= Escape::html($gyt) ?>">
              </div>
              <div class="col">
                <input type="file" class="form-control form-control-sm" name="gv_file_<?= $fid ?>[]" form="<?= Escape::html($formContent) ?>" accept="video/*">
              </div>
            </div>
          <?php endfor; ?>
        <?php endif; ?>

          <?php elseif ($b['kind'] === 'repetidor'): ?>
        <?php if (empty($b['rep_id'])): ?>
          <p class="text-warning small">Repetidor inconsistente. Remova e crie novamente.</p>
        <?php else: ?>
          <?php $rid = (int) $b['rep_id']; ?>
          <div class="mb-3">
            <h4 class="h6">Subcampos</h4>
            <ul class="list-unstyled small mb-2">
              <?php foreach ($b['subfields'] as $sf): ?>
                <li class="d-flex justify-content-between align-items-center border-bottom py-1">
                  <span><?= Escape::html((string) $sf['label_name']) ?> <code><?= Escape::html((string) $sf['field_key']) ?></code> (<?= Escape::html(revita_crm_page_field_label((string) $sf['field_type'])) ?>)</span>
                  <?php if ($isAdmin): ?>
                    <form method="post" action="<?= Escape::html(Url::to('/pages/rep-del-sub')) ?>" class="m-0" onsubmit="return confirm('Remover subcampo?');">
                      <input type="hidden" name="_csrf" value="<?= Escape::html($csrfToken) ?>">
                      <input type="hidden" name="page_id" value="<?= $pageId ?>">
                      <input type="hidden" name="subfield_id" value="<?= (int) $sf['id'] ?>">
                      <button type="submit" class="btn btn-link btn-sm text-danger p-0">remover</button>
                    </form>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
            <form method="post" action="<?= Escape::html(Url::to('/pages/rep-add-sub')) ?>" class="row g-2 align-items-end">
              <input type="hidden" name="_csrf" value="<?= Escape::html($csrfToken) ?>">
              <input type="hidden" name="page_id" value="<?= $pageId ?>">
              <input type="hidden" name="field_definition_id" value="<?= $fid ?>">
              <div class="col-md-3">
                <input class="form-control form-control-sm" name="sub_label" placeholder="Nome do subcampo" required>
              </div>
              <div class="col-md-2">
                <input class="form-control form-control-sm" name="sub_key" placeholder="id (opcional)" pattern="[a-z0-9-]*">
              </div>
              <div class="col-md-3">
                <select class="form-select form-select-sm" name="sub_type">
                  <?php foreach (['texto', 'botao', 'icone', 'foto', 'galeria_fotos', 'video', 'galeria_videos'] as $ft): ?>
                    <option value="<?= $ft ?>"><?= Escape::html(revita_crm_page_field_label($ft)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary">+ Subcampo</button>
              </div>
            </form>
          </div>

          <form method="post" action="<?= Escape::html(Url::to('/pages/rep-add-item')) ?>" class="mb-3">
            <input type="hidden" name="_csrf" value="<?= Escape::html($csrfToken) ?>">
            <input type="hidden" name="page_id" value="<?= $pageId ?>">
            <input type="hidden" name="field_definition_id" value="<?= $fid ?>">
            <button type="submit" class="btn btn-sm btn-revita">+ Item no repetidor</button>
          </form>

          <?php foreach ($b['items'] as $itemBundle): ?>
            <?php
              $it = $itemBundle['item'];
              $iid = (int) $it['id'];
              $vmap = $itemBundle['values'];
            ?>
            <div class="border rounded p-3 mb-3 bg-light">
              <div class="d-flex justify-content-between mb-2">
                <strong class="small">Item #<?= $iid ?></strong>
                <form method="post" action="<?= Escape::html(Url::to('/pages/rep-del-item')) ?>" class="m-0" onsubmit="return confirm('Remover este item?');">
                  <input type="hidden" name="_csrf" value="<?= Escape::html($csrfToken) ?>">
                  <input type="hidden" name="page_id" value="<?= $pageId ?>">
                  <input type="hidden" name="item_id" value="<?= $iid ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Excluir item</button>
                </form>
              </div>
              <?php foreach ($b['subfields'] as $sf): ?>
                <?php
                  $sid = (int) $sf['id'];
                  $st = (string) $sf['field_type'];
                  $vr = $vmap[$sid] ?? null;
                ?>
                <div class="mb-3 pb-2 border-bottom">
                  <label class="form-label small mb-1"><?= Escape::html((string) $sf['label_name']) ?></label>
                  <?php if ($st === 'texto'): ?>
                    <input class="form-control form-control-sm" name="rp_<?= $iid ?>_<?= $sid ?>" form="<?= Escape::html($formContent) ?>"
                           value="<?= $vr ? Escape::html((string) ($vr['value_text'] ?? '')) : '' ?>">
                  <?php elseif ($st === 'botao'): ?>
                    <div class="row g-1">
                      <div class="col">
                        <input class="form-control form-control-sm" name="rp_btn_text_<?= $iid ?>_<?= $sid ?>" form="<?= Escape::html($formContent) ?>"
                               value="<?= $vr ? Escape::html((string) ($vr['value_text'] ?? '')) : '' ?>" placeholder="Texto do botão">
                      </div>
                      <div class="col">
                        <input class="form-control form-control-sm font-monospace" type="url" name="rp_btn_url_<?= $iid ?>_<?= $sid ?>" form="<?= Escape::html($formContent) ?>"
                               value="<?= $vr ? Escape::html((string) ($vr['value_url'] ?? '')) : '' ?>" placeholder="https://...">
                      </div>
                    </div>
                  <?php elseif ($st === 'icone'): ?>
                    <?php
                      $ic = [];
                      if ($vr && !empty($vr['value_mixed_json'])) {
                        $dj = json_decode((string) $vr['value_mixed_json'], true);
                        if (is_array($dj)) {
                          $ic = $dj;
                        }
                      }
                      $ics = (string) ($ic['source'] ?? 'registry');
                      $ick = (string) ($ic['iconKey'] ?? '');
                      $icset = (string) ($ic['iconSet'] ?? '');
                      $icsty = (string) ($ic['iconStyle'] ?? '');
                      $icUrl = null;
                      if ($ics === 'upload' && !empty($ic['media_id'])) {
                        $mrow = (new \Revita\Crm\Models\Media())->findById((int) $ic['media_id']);
                        if ($mrow) {
                          $icUrl = PageApiSerializer::mediaPublicUrl((string) $mrow['relative_path']);
                        }
                      }
                    ?>
                    <div class="row g-1">
                      <div class="col-auto">
                        <select class="form-select form-select-sm" name="rp_icon_src_<?= $iid ?>_<?= $sid ?>" form="<?= Escape::html($formContent) ?>">
                          <option value="registry" <?= $ics === 'registry' ? 'selected' : '' ?>>iconKey</option>
                          <option value="upload" <?= $ics === 'upload' ? 'selected' : '' ?>>SVG</option>
                        </select>
                      </div>
                      <div class="col">
                        <input class="form-control form-control-sm font-monospace" name="rp_icon_set_<?= $iid ?>_<?= $sid ?>" form="<?= Escape::html($formContent) ?>" value="<?= Escape::html($icset) ?>" placeholder="iconSet (opcional)">
                      </div>
                      <div class="col">
                        <input class="form-control form-control-sm font-monospace" name="rp_icon_style_<?= $iid ?>_<?= $sid ?>" form="<?= Escape::html($formContent) ?>" value="<?= Escape::html($icsty) ?>" placeholder="iconStyle (opcional)">
                      </div>
                      <div class="col">
                        <input class="form-control form-control-sm font-monospace" name="rp_icon_key_<?= $iid ?>_<?= $sid ?>" form="<?= Escape::html($formContent) ?>" value="<?= Escape::html($ick) ?>" placeholder="iconKey">
                      </div>
                      <div class="col-12 mt-1">
                        <input type="file" class="form-control form-control-sm" name="rp_icon_svg_<?= $iid ?>_<?= $sid ?>" form="<?= Escape::html($formContent) ?>" accept=".svg,image/svg+xml">
                        <?php if (!empty($icUrl)): ?>
                          <div class="small mt-1">Atual: <a href="<?= Escape::html($icUrl) ?>" target="_blank" rel="noreferrer">ver SVG</a></div>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php elseif ($st === 'foto'): ?>
                    <?php
                      $pm = [];
                      if ($vr && !empty($vr['value_media_ids_json'])) {
                        $j = json_decode((string) $vr['value_media_ids_json'], true);
                        if (is_array($j) && isset($j[0])) {
                          $pm = (new \Revita\Crm\Models\Media())->findById((int) $j[0]);
                        }
                      }
                    ?>
                    <?php if ($pm): ?>
                      <p class="mb-1"><img src="<?= Escape::html(PageApiSerializer::mediaPublicUrl((string) $pm['relative_path'])) ?>" alt="" style="max-height:72px;"></p>
                    <?php endif; ?>
                    <input type="file" class="form-control form-control-sm" name="rpfile_<?= $iid ?>_<?= $sid ?>" form="<?= Escape::html($formContent) ?>" accept="image/*">
                    <div class="form-check mt-1">
                      <input class="form-check-input" type="checkbox" name="rp_clear_foto_<?= $iid ?>_<?= $sid ?>" value="1" id="rc_<?= $iid ?>_<?= $sid ?>" form="<?= Escape::html($formContent) ?>">
                      <label class="form-check-label small" for="rc_<?= $iid ?>_<?= $sid ?>">Remover</label>
                    </div>
                  <?php elseif ($st === 'galeria_fotos'): ?>
                    <?php
                      $ig = [];
                      if ($vr && !empty($vr['value_media_ids_json'])) {
                        $xj = json_decode((string) $vr['value_media_ids_json'], true);
                        if (is_array($xj)) {
                          $ig = array_values(array_filter(array_map('intval', $xj), static fn (int $x) => $x > 0));
                        }
                      }
                    ?>
                    <input type="hidden" name="rp_gal_existing_<?= $iid ?>_<?= $sid ?>" form="<?= Escape::html($formContent) ?>" value="<?= Escape::html(json_encode($ig, JSON_UNESCAPED_UNICODE)) ?>">
                    <input type="file" class="form-control form-control-sm" name="rp_gal_<?= $iid ?>_<?= $sid ?>[]" form="<?= Escape::html($formContent) ?>" accept="image/*" multiple>
                  <?php elseif ($st === 'video'): ?>
                    <?php
                      $rs = 'upload';
                      $ryt = '';
                      if ($vr && !empty($vr['value_mixed_json'])) {
                        $dj = json_decode((string) $vr['value_mixed_json'], true);
                        if (is_array($dj) && ($dj['source'] ?? '') === 'youtube') {
                          $rs = 'youtube';
                          $ryt = (string) ($dj['youtube_url'] ?? '');
                        }
                      }
                    ?>
                    <select class="form-select form-select-sm mb-1" name="rp_vid_src_<?= $iid ?>_<?= $sid ?>" form="<?= Escape::html($formContent) ?>" style="max-width:180px;">
                      <option value="upload" <?= $rs === 'upload' ? 'selected' : '' ?>>Upload</option>
                      <option value="youtube" <?= $rs === 'youtube' ? 'selected' : '' ?>>YouTube</option>
                    </select>
                    <input type="url" class="form-control form-control-sm mb-1" name="rp_vid_yt_<?= $iid ?>_<?= $sid ?>" form="<?= Escape::html($formContent) ?>" value="<?= Escape::html($ryt) ?>" placeholder="URL YouTube">
                    <input type="file" class="form-control form-control-sm" name="rp_vid_file_<?= $iid ?>_<?= $sid ?>" form="<?= Escape::html($formContent) ?>" accept="video/*">
                  <?php elseif ($st === 'galeria_videos'): ?>
                    <?php
                      $rex = [];
                      if ($vr && !empty($vr['value_mixed_json'])) {
                        $ja = json_decode((string) $vr['value_mixed_json'], true);
                        if (is_array($ja)) {
                          $rex = $ja;
                        }
                      }
                      $nr = max(1, count($rex) + 1);
                      $px = $iid . '_' . $sid;
                    ?>
                    <input type="hidden" name="rp_gv_existing_<?= $iid ?>_<?= $sid ?>" form="<?= Escape::html($formContent) ?>" value="<?= Escape::html(json_encode($rex, JSON_UNESCAPED_UNICODE)) ?>">
                    <?php for ($ri = 0; $ri < $nr; $ri++): ?>
                      <?php
                        $rit = $rex[$ri] ?? [];
                        $rsrc = (is_array($rit) && ($rit['source'] ?? '') === 'youtube') ? 'youtube' : 'upload';
                        $ryu = is_array($rit) ? (string) ($rit['youtube_url'] ?? '') : '';
                      ?>
                      <div class="row g-1 mb-1">
                        <div class="col-auto">
                          <select class="form-select form-select-sm" name="rp_gv_src_<?= $px ?>[]" form="<?= Escape::html($formContent) ?>">
                            <option value="upload" <?= $rsrc === 'upload' ? 'selected' : '' ?>>Upload</option>
                            <option value="youtube" <?= $rsrc === 'youtube' ? 'selected' : '' ?>>YouTube</option>
                          </select>
                        </div>
                        <div class="col">
                          <input class="form-control form-control-sm" type="url" name="rp_gv_yt_<?= $px ?>[]" form="<?= Escape::html($formContent) ?>" value="<?= Escape::html($ryu) ?>" placeholder="YouTube URL">
                        </div>
                        <div class="col">
                          <input type="file" class="form-control form-control-sm" name="rp_gv_file_<?= $iid ?>_<?= $sid ?>[]" form="<?= Escape::html($formContent) ?>" accept="video/*">
                        </div>
                      </div>
                    <?php endfor; ?>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>

          <?php if ($b['items'] === []): ?>
            <p class="text-muted small">Nenhum item ainda. Use &quot;+ Item no repetidor&quot;.</p>
          <?php endif; ?>

        <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
</div>

<div class="sticky-bottom bg-white py-3 border-top mt-4" style="z-index:100;">
  <button type="submit" form="<?= Escape::html($formContent) ?>" class="btn btn-revita btn-lg">Salvar conteúdo dos campos</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
  var sectionsRoot = document.getElementById('sort-sections');
  if (!sectionsRoot || typeof Sortable === 'undefined') return;

  // Sort sections (excludes "(sem seção)" by not attaching sortable to it)
  new Sortable(sectionsRoot, {
    animation: 150,
    handle: 'strong',
    draggable: '[data-section-id]:not([data-section-id="0"])'
  });

  // Sort fields within each section
  sectionsRoot.querySelectorAll('ul[data-fields-list="1"]').forEach(function (ul) {
    new Sortable(ul, { animation: 150 });
  });

  var btn = document.getElementById('btn-save-order');
  if (btn) btn.addEventListener('click', function () {
    var wrap = document.getElementById('reorder-hidden');
    wrap.innerHTML = '';

    // section_order[]
    sectionsRoot.querySelectorAll('[data-section-id]').forEach(function (secEl) {
      var sid = secEl.getAttribute('data-section-id');
      if (!sid || sid === '0') return;
      var inpS = document.createElement('input');
      inpS.type = 'hidden';
      inpS.name = 'section_order[]';
      inpS.value = sid;
      wrap.appendChild(inpS);
    });

    // field_section[fieldId] + field_order[fieldId]
    sectionsRoot.querySelectorAll('ul[data-fields-list="1"]').forEach(function (ul) {
      var sid = ul.getAttribute('data-section-id') || '0';
      ul.querySelectorAll('li[data-field-id]').forEach(function (li, idx) {
        var fid = li.getAttribute('data-field-id');
        if (!fid) return;
        var inpSec = document.createElement('input');
        inpSec.type = 'hidden';
        inpSec.name = 'field_section[' + fid + ']';
        inpSec.value = sid;
        wrap.appendChild(inpSec);

        var inpOrd = document.createElement('input');
        inpOrd.type = 'hidden';
        inpOrd.name = 'field_order[' + fid + ']';
        inpOrd.value = String(idx);
        wrap.appendChild(inpOrd);
      });
    });

    document.getElementById('form-reorder').submit();
  });
})();
</script>
