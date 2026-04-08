<?php

declare(strict_types=1);

namespace Revita\Crm\Controllers;

use Revita\Crm\Controllers\Concerns\ManagesDynamicFields;
use Revita\Crm\Core\Auth;
use Revita\Crm\Core\Csrf;
use Revita\Crm\Core\Request;
use Revita\Crm\Core\Response;
use Revita\Crm\Core\Session;
use Revita\Crm\Core\View;
use Revita\Crm\Helpers\Slugger;
use Revita\Crm\Helpers\Url;
use Revita\Crm\Models\FieldDefinition;
use Revita\Crm\Models\FieldValue;
use Revita\Crm\Models\Page;
use Revita\Crm\Models\Repeater;

final class PageController
{
    use ManagesDynamicFields;

    private const FIELD_TYPES = [
        'texto', 'foto', 'galeria_fotos', 'video', 'galeria_videos', 'repetidor',
    ];

    protected function fieldOwnerType(): string
    {
        return FieldDefinition::OWNER_PAGE;
    }

    public function index(Request $request): void
    {
        Auth::requireEditor();
        $page = new Page();
        $html = View::layout('admin', 'pages/index', [
            'title' => 'Páginas — Revita CMS',
            'nav' => 'pages',
            'user' => Auth::user(),
            'pages' => $page->all(),
            'flashOk' => Session::flash('ok'),
            'flashErr' => Session::flash('error'),
            'csrfToken' => Csrf::token(),
            'isAdmin' => Auth::isAdmin(),
        ]);
        Response::html($html);
    }

    public function createForm(Request $request): void
    {
        Auth::requireAdmin();
        $html = View::layout('admin', 'pages/create', [
            'title' => 'Nova página — Revita CMS',
            'nav' => 'pages',
            'user' => Auth::user(),
            'error' => Session::flash('page_form_error'),
            'csrfToken' => Csrf::token(),
        ]);
        Response::html($html);
    }

    public function store(Request $request): void
    {
        Auth::requireAdmin();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('page_form_error', 'Sessão expirada.');
            Url::redirect('/pages/create');
        }
        $title = trim((string) $request->post('title', ''));
        $slug = trim((string) $request->post('slug', ''));
        $slug = $slug === '' ? Slugger::slugify($title) : Slugger::slugify($slug);
        $status = (string) $request->post('status', 'draft') === 'published' ? 'published' : 'draft';
        if ($title === '' || mb_strlen($title, 'UTF-8') < 2) {
            Session::flash('page_form_error', 'Título inválido.');
            Url::redirect('/pages/create');
        }
        if ($slug === '' || !preg_match('/^[a-z0-9-]{2,190}$/', $slug)) {
            Session::flash('page_form_error', 'Slug inválido.');
            Url::redirect('/pages/create');
        }
        $p = new Page();
        if ($p->slugExists($slug)) {
            Session::flash('page_form_error', 'Slug já em uso.');
            Url::redirect('/pages/create');
        }
        $id = $p->insert($title, $slug, $status);
        Session::flash('ok', 'Página criada. Adicione campos de conteúdo abaixo.');
        Url::redirect('/pages/edit?id=' . $id);
    }

    public function editForm(Request $request): void
    {
        Auth::requireEditor();
        $id = (int) $request->query('id', 0);
        $p = new Page();
        $row = $p->findById($id);
        if ($row === null) {
            Session::flash('error', 'Página não encontrada.');
            Url::redirect('/pages');
        }
        $blocks = $this->buildEditBlocks($id);
        $html = View::layout('admin', 'pages/edit', [
            'title' => 'Editar página — Revita CMS',
            'nav' => 'pages',
            'user' => Auth::user(),
            'page' => $row,
            'blocks' => $blocks,
            'flashOk' => Session::flash('ok'),
            'flashErr' => Session::flash('error'),
            'metaError' => Session::flash('page_meta_error'),
            'contentError' => Session::flash('page_content_error'),
            'fieldError' => Session::flash('page_field_error'),
            'csrfToken' => Csrf::token(),
            'isAdmin' => Auth::isAdmin(),
        ]);
        Response::html($html);
    }

    public function updateMeta(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('page_meta_error', 'Sessão expirada.');
            Url::redirect('/pages');
        }
        $id = (int) $request->post('id', 0);
        $p = new Page();
        $row = $p->findById($id);
        if ($row === null) {
            Session::flash('error', 'Página não encontrada.');
            Url::redirect('/pages');
        }
        $title = trim((string) $request->post('title', ''));
        $slug = trim((string) $request->post('slug', ''));
        $slug = $slug === '' ? Slugger::slugify($title) : Slugger::slugify($slug);
        $status = $request->postFlag('status_published') ? 'published' : 'draft';
        if ($title === '' || $slug === '' || !preg_match('/^[a-z0-9-]{2,190}$/', $slug)) {
            Session::flash('page_meta_error', 'Dados inválidos.');
            Url::redirect('/pages/edit?id=' . $id);
        }
        if ($p->slugExists($slug, $id)) {
            Session::flash('page_meta_error', 'Slug já em uso.');
            Url::redirect('/pages/edit?id=' . $id);
        }
        $p->update($id, $title, $slug, $status);
        Session::flash('ok', 'Dados da página atualizados.');
        Url::redirect('/pages/edit?id=' . $id);
    }

    public function updateContent(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('page_content_error', 'Sessão expirada.');
            Url::redirect('/pages');
        }
        $pageId = (int) $request->post('page_id', 0);
        $p = new Page();
        if ($p->findById($pageId) === null) {
            Session::flash('error', 'Página não encontrada.');
            Url::redirect('/pages');
        }
        $uid = Auth::user()['id'] ?? null;
        $userId = $uid !== null ? (int) $uid : null;

        $fd = new FieldDefinition();
        $fv = new FieldValue();
        $rep = new Repeater();
        foreach ($this->listFieldDefs($fd, $pageId) as $def) {
            $fid = (int) $def['id'];
            $type = (string) $def['field_type'];
            if ($type === 'repetidor') {
                $this->saveRepeaterFieldContent($rep, $def, $userId);
                continue;
            }
            $this->saveScalarField($fv, $def, $request, $userId);
        }
        Session::flash('ok', 'Conteúdo dos campos salvo.');
        Url::redirect('/pages/edit?id=' . $pageId);
    }

    public function addField(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('page_field_error', 'Sessão expirada.');
            Url::redirect('/pages');
        }
        $pageId = (int) $request->post('page_id', 0);
        $p = new Page();
        if ($p->findById($pageId) === null) {
            Session::flash('error', 'Página não encontrada.');
            Url::redirect('/pages');
        }
        $label = trim((string) $request->post('label_name', ''));
        $type = (string) $request->post('field_type', 'texto');
        if (!in_array($type, self::FIELD_TYPES, true)) {
            $type = 'texto';
        }
        $key = trim((string) $request->post('field_key', ''));
        $key = $key === '' ? Slugger::slugify($label) : Slugger::slugify($key);
        if ($label === '' || $key === '' || !preg_match('/^[a-z0-9-]{2,120}$/', $key)) {
            Session::flash('page_field_error', 'Nome ou identificador do campo inválido.');
            Url::redirect('/pages/edit?id=' . $pageId);
        }
        $fd = new FieldDefinition();
        if ($fd->fieldKeyExistsOnPage($pageId, $key)) {
            $base = $key;
            $n = 1;
            while ($fd->fieldKeyExistsOnPage($pageId, $key)) {
                $key = $base . '-' . ($n++);
            }
        }
        $ord = $fd->nextOrderIndex($pageId);
        $fid = $fd->insert($pageId, $key, $label, $type, $ord);
        $fv = new FieldValue();
        $fv->ensureRowExists($fid);
        if ($type === 'repetidor') {
            $rep = new Repeater();
            $rep->createDefinitionForField($fid);
        }
        Session::flash('ok', 'Campo adicionado.');
        Url::redirect('/pages/edit?id=' . $pageId);
    }

    public function deleteField(Request $request): void
    {
        Auth::requireAdmin();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/pages');
        }
        $fieldId = (int) $request->post('field_id', 0);
        $pageId = (int) $request->post('page_id', 0);
        if ($fieldId < 1 || $pageId < 1) {
            Session::flash('error', 'Dados inválidos.');
            Url::redirect('/pages');
        }
        $def = (new FieldDefinition())->findById($fieldId);
        if ($def === null || (int) $def['owner_id'] !== $pageId || (string) $def['owner_type'] !== FieldDefinition::OWNER_PAGE) {
            Session::flash('error', 'Campo não encontrado.');
            Url::redirect('/pages');
        }
        $this->deleteFieldCascade($fieldId);
        Session::flash('ok', 'Campo removido.');
        Url::redirect('/pages/edit?id=' . $pageId);
    }

    public function reorderFields(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/pages');
        }
        $pageId = (int) $request->post('page_id', 0);
        $order = $_POST['order'] ?? [];
        if (!is_array($order) || $pageId < 1) {
            Session::flash('error', 'Ordem inválida.');
            Url::redirect('/pages/edit?id=' . $pageId);
        }
        $ids = array_values(array_filter(array_map('intval', $order), static fn (int $x) => $x > 0));
        (new FieldDefinition())->reorderOnPage($pageId, $ids);
        Session::flash('ok', 'Ordem dos campos atualizada.');
        Url::redirect('/pages/edit?id=' . $pageId);
    }

    public function repeaterAddSubfield(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/pages');
        }
        $pageId = (int) $request->post('page_id', 0);
        $fieldDefId = (int) $request->post('field_definition_id', 0);
        $label = trim((string) $request->post('sub_label', ''));
        $type = (string) $request->post('sub_type', 'texto');
        $key = trim((string) $request->post('sub_key', ''));
        $key = $key === '' ? Slugger::slugify($label) : Slugger::slugify($key);
        if (!in_array($type, ['texto', 'foto', 'galeria_fotos', 'video', 'galeria_videos'], true)) {
            $type = 'texto';
        }
        $fd = (new FieldDefinition())->findById($fieldDefId);
        if ($fd === null || (string) $fd['owner_type'] !== FieldDefinition::OWNER_PAGE
            || (int) $fd['owner_id'] !== $pageId || (string) $fd['field_type'] !== 'repetidor') {
            Session::flash('error', 'Campo repetidor inválido.');
            Url::redirect('/pages/edit?id=' . $pageId);
        }
        if ($label === '' || !preg_match('/^[a-z0-9-]{2,120}$/', $key)) {
            Session::flash('error', 'Subcampo inválido.');
            Url::redirect('/pages/edit?id=' . $pageId);
        }
        $rep = new Repeater();
        $rdef = $rep->findDefinitionByFieldDefId($fieldDefId);
        if ($rdef === null) {
            Session::flash('error', 'Repetidor não inicializado.');
            Url::redirect('/pages/edit?id=' . $pageId);
        }
        $rid = (int) $rdef['id'];
        if ($rep->subfieldKeyExists($rid, $key)) {
            Session::flash('error', 'Identificador de subcampo já existe neste repetidor.');
            Url::redirect('/pages/edit?id=' . $pageId);
        }
        $ord = $rep->nextSubfieldOrder($rid);
        $rep->insertSubfield($rid, $key, $label, $type, $ord);
        Session::flash('ok', 'Subcampo adicionado.');
        Url::redirect('/pages/edit?id=' . $pageId);
    }

    public function repeaterDeleteSubfield(Request $request): void
    {
        Auth::requireAdmin();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/pages');
        }
        $pageId = (int) $request->post('page_id', 0);
        $subId = (int) $request->post('subfield_id', 0);
        $sf = (new Repeater())->findSubfieldById($subId);
        if ($sf === null) {
            Session::flash('error', 'Subcampo não encontrado.');
            Url::redirect('/pages/edit?id=' . $pageId);
        }
        (new Repeater())->deleteSubfield($subId);
        Session::flash('ok', 'Subcampo removido.');
        Url::redirect('/pages/edit?id=' . $pageId);
    }

    public function repeaterAddItem(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/pages');
        }
        $pageId = (int) $request->post('page_id', 0);
        $fieldDefId = (int) $request->post('field_definition_id', 0);
        $fd = (new FieldDefinition())->findById($fieldDefId);
        if ($fd === null || (string) $fd['owner_type'] !== FieldDefinition::OWNER_PAGE
            || (int) $fd['owner_id'] !== $pageId || (string) $fd['field_type'] !== 'repetidor') {
            Session::flash('error', 'Repetidor inválido.');
            Url::redirect('/pages/edit?id=' . $pageId);
        }
        $rep = new Repeater();
        $rdef = $rep->findDefinitionByFieldDefId($fieldDefId);
        if ($rdef === null) {
            Session::flash('error', 'Repetidor não encontrado.');
            Url::redirect('/pages/edit?id=' . $pageId);
        }
        $rep->addItem((int) $rdef['id']);
        Session::flash('ok', 'Item adicionado ao repetidor.');
        Url::redirect('/pages/edit?id=' . $pageId);
    }

    public function repeaterDeleteItem(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/pages');
        }
        $pageId = (int) $request->post('page_id', 0);
        $itemId = (int) $request->post('item_id', 0);
        $rep = new Repeater();
        $it = $rep->findItemById($itemId);
        if ($it === null) {
            Session::flash('error', 'Item não encontrado.');
            Url::redirect('/pages/edit?id=' . $pageId);
        }
        $rep->deleteItem($itemId);
        Session::flash('ok', 'Item removido.');
        Url::redirect('/pages/edit?id=' . $pageId);
    }

    public function repeaterReorderItems(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/pages');
        }
        $pageId = (int) $request->post('page_id', 0);
        $repDefId = (int) $request->post('repeater_definition_id', 0);
        $order = $_POST['item_order'] ?? [];
        if (!is_array($order) || $repDefId < 1) {
            Session::flash('error', 'Ordem inválida.');
            Url::redirect('/pages/edit?id=' . $pageId);
        }
        $ids = array_values(array_filter(array_map('intval', $order), static fn (int $x) => $x > 0));
        (new Repeater())->reorderItems($repDefId, $ids);
        Session::flash('ok', 'Ordem dos itens atualizada.');
        Url::redirect('/pages/edit?id=' . $pageId);
    }

    public function delete(Request $request): void
    {
        Auth::requireAdmin();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/pages');
        }
        $id = (int) $request->post('id', 0);
        if ($id < 1) {
            Session::flash('error', 'Página inválida.');
            Url::redirect('/pages');
        }
        $p = new Page();
        if ($p->findById($id) === null) {
            Session::flash('error', 'Página não encontrada.');
            Url::redirect('/pages');
        }
        foreach ((new FieldDefinition())->listByPageId($id) as $f) {
            $this->deleteFieldCascade((int) $f['id']);
        }
        $p->delete($id);
        Session::flash('ok', 'Página excluída.');
        Url::redirect('/pages');
    }

}
