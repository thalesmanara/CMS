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
use Revita\Crm\Helpers\MediaUpload;
use Revita\Crm\Helpers\Slugger;
use Revita\Crm\Helpers\Url;
use Revita\Crm\Models\Category;
use Revita\Crm\Models\FieldDefinition;
use Revita\Crm\Models\FieldValue;
use Revita\Crm\Models\Post;
use Revita\Crm\Models\Repeater;
use Revita\Crm\Models\Subcategory;
use Revita\Crm\Models\Section;
use Revita\Crm\Services\PageApiSerializer;

final class PostController
{
    use ManagesDynamicFields;

    private const FIELD_TYPES = [
        'texto', 'botao', 'foto', 'galeria_fotos', 'video', 'galeria_videos', 'repetidor',
    ];

    protected function fieldOwnerType(): string
    {
        return FieldDefinition::OWNER_POST;
    }

    public function index(Request $request): void
    {
        Auth::requireEditor();
        $post = new Post();
        $html = View::layout('admin', 'posts/index', [
            'title' => 'Postagens — Revita CMS',
            'nav' => 'posts',
            'user' => Auth::user(),
            'posts' => $post->all(),
            'flashOk' => Session::flash('ok'),
            'flashErr' => Session::flash('error'),
            'csrfToken' => Csrf::token(),
            'isAdmin' => Auth::isAdmin(),
        ]);
        Response::html($html);
    }

    public function createForm(Request $request): void
    {
        Auth::requireEditor();
        $subs = (new Subcategory())->allWithCategory();
        $cats = (new Category())->all();
        $html = View::layout('admin', 'posts/create', [
            'title' => 'Nova postagem — Revita CMS',
            'nav' => 'posts',
            'user' => Auth::user(),
            'error' => Session::flash('post_form_error'),
            'categories' => $cats,
            'subcategoriesRows' => $subs,
            'hasSubcategories' => $subs !== [],
            'hasCategories' => $cats !== [],
            'csrfToken' => Csrf::token(),
        ]);
        Response::html($html);
    }

    public function store(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('post_form_error', 'Sessão expirada.');
            Url::redirect('/posts/create');
        }
        if ((new Category())->all() === []) {
            Session::flash('post_form_error', 'Crie ao menos uma categoria.');
            Url::redirect('/posts/create');
        }
        $subs = (new Subcategory())->allWithCategory();
        if ($subs === []) {
            Session::flash('post_form_error', 'Crie ao menos uma subcategoria antes de publicar posts.');
            Url::redirect('/posts/create');
        }
        $title = trim((string) $request->post('title', ''));
        $slug = trim((string) $request->post('slug', ''));
        $slug = $slug === '' ? Slugger::slugify($title) : Slugger::slugify($slug);
        $status = (string) $request->post('status', 'draft') === 'published' ? 'published' : 'draft';
        $categoryId = (int) $request->post('category_id', 0);
        $subcategoryId = (int) $request->post('subcategory_id', 0);
        $uid = Auth::user()['id'] ?? null;
        $authorId = $uid !== null ? (int) $uid : 0;
        if ($authorId < 1) {
            Session::flash('post_form_error', 'Sessão inválida.');
            Url::redirect('/posts/create');
        }
        if ($title === '' || mb_strlen($title, 'UTF-8') < 2) {
            Session::flash('post_form_error', 'Título inválido.');
            Url::redirect('/posts/create');
        }
        if ($slug === '' || !preg_match('/^[a-z0-9-]{2,190}$/', $slug)) {
            Session::flash('post_form_error', 'Slug inválido.');
            Url::redirect('/posts/create');
        }
        if ($categoryId < 1 || $subcategoryId < 1) {
            Session::flash('post_form_error', 'Selecione categoria e subcategoria.');
            Url::redirect('/posts/create');
        }
        $p = new Post();
        if (!$p->subcategoryBelongsToCategory($subcategoryId, $categoryId)) {
            Session::flash('post_form_error', 'Subcategoria não pertence à categoria escolhida.');
            Url::redirect('/posts/create');
        }
        if ($p->slugExists($slug)) {
            Session::flash('post_form_error', 'Slug já em uso.');
            Url::redirect('/posts/create');
        }
        $publishedAt = null;
        if ($status === 'published') {
            $publishedAt = date('Y-m-d H:i:s');
        }
        $id = $p->insert($title, $slug, $categoryId, $subcategoryId, null, $status, $publishedAt, $authorId);
        Session::flash('ok', 'Post criado. Ajuste a imagem destacada e os campos abaixo.');
        Url::redirect('/posts/edit?id=' . $id);
    }

    public function editForm(Request $request): void
    {
        Auth::requireEditor();
        $id = (int) $request->query('id', 0);
        $p = new Post();
        $row = $p->findById($id);
        if ($row === null) {
            Session::flash('error', 'Post não encontrado.');
            Url::redirect('/posts');
        }
        $blocks = $this->buildEditBlocks($id);
        $sections = (new Section())->listByOwner(FieldDefinition::OWNER_POST, $id);
        $featuredPreview = PageApiSerializer::featuredImagePublicUrl(
            isset($row['featured_media_id']) && $row['featured_media_id'] !== null
                ? (int) $row['featured_media_id']
                : null
        );
        $html = View::layout('admin', 'posts/edit', [
            'title' => 'Editar post — Revita CMS',
            'nav' => 'posts',
            'user' => Auth::user(),
            'post' => $row,
            'categories' => (new Category())->all(),
            'subcategoriesRows' => (new Subcategory())->allWithCategory(),
            'blocks' => $blocks,
            'sections' => $sections,
            'featuredPreview' => $featuredPreview,
            'flashOk' => Session::flash('ok'),
            'flashErr' => Session::flash('error'),
            'metaError' => Session::flash('post_meta_error'),
            'contentError' => Session::flash('post_content_error'),
            'fieldError' => Session::flash('post_field_error'),
            'csrfToken' => Csrf::token(),
            'isAdmin' => Auth::isAdmin(),
        ]);
        Response::html($html);
    }

    public function updateMeta(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('post_meta_error', 'Sessão expirada.');
            Url::redirect('/posts');
        }
        $id = (int) $request->post('id', 0);
        $p = new Post();
        $row = $p->findById($id);
        if ($row === null) {
            Session::flash('error', 'Post não encontrado.');
            Url::redirect('/posts');
        }
        $title = trim((string) $request->post('title', ''));
        $slug = trim((string) $request->post('slug', ''));
        $slug = $slug === '' ? Slugger::slugify($title) : Slugger::slugify($slug);
        $status = $request->postFlag('status_published') ? 'published' : 'draft';
        $categoryId = (int) $request->post('category_id', 0);
        $subcategoryId = (int) $request->post('subcategory_id', 0);
        $publishedAtInput = trim((string) $request->post('published_at', ''));
        $featuredId = isset($row['featured_media_id']) && $row['featured_media_id'] !== null
            ? (int) $row['featured_media_id']
            : null;

        $parsedPublished = null;
        if ($publishedAtInput !== '') {
            $parsedPublished = str_replace('T', ' ', $publishedAtInput);
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $parsedPublished)) {
                $parsedPublished .= ':00';
            }
        }

        if ($title === '' || $slug === '' || !preg_match('/^[a-z0-9-]{2,190}$/', $slug)) {
            Session::flash('post_meta_error', 'Dados inválidos.');
            Url::redirect('/posts/edit?id=' . $id);
        }
        if ($categoryId < 1 || $subcategoryId < 1 || !$p->subcategoryBelongsToCategory($subcategoryId, $categoryId)) {
            Session::flash('post_meta_error', 'Categoria / subcategoria inválidas.');
            Url::redirect('/posts/edit?id=' . $id);
        }
        if ($p->slugExists($slug, $id)) {
            Session::flash('post_meta_error', 'Slug já em uso.');
            Url::redirect('/posts/edit?id=' . $id);
        }

        $uid = Auth::user()['id'] ?? null;
        $userId = $uid !== null ? (int) $uid : null;
        if (isset($_FILES['featured_image']) && (int) ($_FILES['featured_image']['error'] ?? 0) === UPLOAD_ERR_OK) {
            $mid = MediaUpload::handle($_FILES['featured_image'], 'image', $userId);
            if ($mid !== null) {
                $featuredId = $mid;
            }
        }
        if ($request->postFlag('clear_featured')) {
            $featuredId = null;
        }

        if ($status === 'published') {
            $publishedAt = $parsedPublished;
            if ($publishedAt === null) {
                $publishedAt = !empty($row['published_at'])
                    ? (string) $row['published_at']
                    : date('Y-m-d H:i:s');
            }
        } else {
            $publishedAt = $row['published_at'] !== null ? (string) $row['published_at'] : null;
        }

        $p->update($id, $title, $slug, $categoryId, $subcategoryId, $featuredId, $status, $publishedAt);
        Session::flash('ok', 'Dados do post atualizados.');
        Url::redirect('/posts/edit?id=' . $id);
    }

    public function updateContent(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('post_content_error', 'Sessão expirada.');
            Url::redirect('/posts');
        }
        $postId = (int) $request->post('post_id', 0);
        $p = new Post();
        if ($p->findById($postId) === null) {
            Session::flash('error', 'Post não encontrado.');
            Url::redirect('/posts');
        }
        $uid = Auth::user()['id'] ?? null;
        $userId = $uid !== null ? (int) $uid : null;

        $fd = new FieldDefinition();
        $fv = new FieldValue();
        $rep = new Repeater();
        foreach ($this->listFieldDefs($fd, $postId) as $def) {
            $type = (string) $def['field_type'];
            if ($type === 'repetidor') {
                $this->saveRepeaterFieldContent($rep, $def, $userId);
                continue;
            }
            $this->saveScalarField($fv, $def, $request, $userId);
        }
        Session::flash('ok', 'Conteúdo dos campos salvo.');
        Url::redirect('/posts/edit?id=' . $postId);
    }

    public function addField(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('post_field_error', 'Sessão expirada.');
            Url::redirect('/posts');
        }
        $postId = (int) $request->post('post_id', 0);
        $p = new Post();
        if ($p->findById($postId) === null) {
            Session::flash('error', 'Post não encontrado.');
            Url::redirect('/posts');
        }
        $label = trim((string) $request->post('label_name', ''));
        $type = (string) $request->post('field_type', 'texto');
        if (!in_array($type, self::FIELD_TYPES, true)) {
            $type = 'texto';
        }
        $key = trim((string) $request->post('field_key', ''));
        $key = $key === '' ? Slugger::slugify($label) : Slugger::slugify($key);
        $sectionIdRaw = (int) $request->post('section_id', 0);
        $sectionId = $sectionIdRaw > 0 ? $sectionIdRaw : null;
        if ($label === '' || $key === '' || !preg_match('/^[a-z0-9-]{2,120}$/', $key)) {
            Session::flash('post_field_error', 'Nome ou identificador do campo inválido.');
            Url::redirect('/posts/edit?id=' . $postId);
        }
        $fd = new FieldDefinition();
        if ($fd->fieldKeyExistsOnPost($postId, $key)) {
            $base = $key;
            $n = 1;
            while ($fd->fieldKeyExistsOnPost($postId, $key)) {
                $key = $base . '-' . ($n++);
            }
        }
        $ord = $fd->nextOrderIndexForOwner(FieldDefinition::OWNER_POST, $postId, $sectionId);
        $fid = $fd->insertForOwnerInSection(FieldDefinition::OWNER_POST, $postId, $sectionId, $key, $label, $type, $ord);
        $fv = new FieldValue();
        $fv->ensureRowExists($fid);
        if ($type === 'repetidor') {
            $rep = new Repeater();
            $rep->createDefinitionForField($fid);
        }
        Session::flash('ok', 'Campo adicionado.');
        Url::redirect('/posts/edit?id=' . $postId);
    }

    public function addSection(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/posts');
        }
        $postId = (int) $request->post('post_id', 0);
        $p = new Post();
        if ($p->findById($postId) === null) {
            Session::flash('error', 'Post não encontrado.');
            Url::redirect('/posts');
        }
        $title = trim((string) $request->post('section_title', ''));
        if ($title === '' || mb_strlen($title, 'UTF-8') < 2) {
            Session::flash('error', 'Nome da seção inválido.');
            Url::redirect('/posts/edit?id=' . $postId);
        }
        $sec = new Section();
        $ord = $sec->nextOrderIndex(FieldDefinition::OWNER_POST, $postId);
        $sec->insert(FieldDefinition::OWNER_POST, $postId, $title, $ord);
        Session::flash('ok', 'Seção adicionada.');
        Url::redirect('/posts/edit?id=' . $postId);
    }

    public function deleteField(Request $request): void
    {
        Auth::requireAdmin();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/posts');
        }
        $fieldId = (int) $request->post('field_id', 0);
        $postId = (int) $request->post('post_id', 0);
        if ($fieldId < 1 || $postId < 1) {
            Session::flash('error', 'Dados inválidos.');
            Url::redirect('/posts');
        }
        $def = (new FieldDefinition())->findById($fieldId);
        if ($def === null || (int) $def['owner_id'] !== $postId || (string) $def['owner_type'] !== FieldDefinition::OWNER_POST) {
            Session::flash('error', 'Campo não encontrado.');
            Url::redirect('/posts');
        }
        $this->deleteFieldCascade($fieldId);
        Session::flash('ok', 'Campo removido.');
        Url::redirect('/posts/edit?id=' . $postId);
    }

    public function reorderFields(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/posts');
        }
        $postId = (int) $request->post('post_id', 0);
        if ($postId < 1) {
            Session::flash('error', 'Ordem inválida.');
            Url::redirect('/posts');
        }

        // Novo formato (com seções)
        $sectionOrder = $_POST['section_order'] ?? null;
        $fieldSection = $_POST['field_section'] ?? null;
        $fieldOrder = $_POST['field_order'] ?? null;
        if (is_array($sectionOrder) && is_array($fieldSection) && is_array($fieldOrder)) {
            $secIds = array_values(array_filter(array_map('intval', $sectionOrder), static fn (int $x) => $x > 0));
            (new Section())->reorder(FieldDefinition::OWNER_POST, $postId, $secIds);
            $map = [];
            foreach ($fieldOrder as $fid => $ord) {
                $fid = (int) $fid;
                if ($fid < 1) {
                    continue;
                }
                $sidRaw = isset($fieldSection[$fid]) ? (int) $fieldSection[$fid] : 0;
                $sid = $sidRaw > 0 ? $sidRaw : null;
                $map[$fid] = ['section_id' => $sid, 'order' => (int) $ord];
            }
            (new FieldDefinition())->applySectionAndOrderMap(FieldDefinition::OWNER_POST, $postId, $map);
            Session::flash('ok', 'Ordem das seções e campos atualizada.');
            Url::redirect('/posts/edit?id=' . $postId);
        }

        // Formato antigo (fallback)
        $order = $_POST['order'] ?? [];
        if (!is_array($order)) {
            Session::flash('error', 'Ordem inválida.');
            Url::redirect('/posts/edit?id=' . $postId);
        }
        $ids = array_values(array_filter(array_map('intval', $order), static fn (int $x) => $x > 0));
        (new FieldDefinition())->reorderOnPost($postId, $ids);
        Session::flash('ok', 'Ordem dos campos atualizada.');
        Url::redirect('/posts/edit?id=' . $postId);
    }

    public function repeaterAddSubfield(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/posts');
        }
        $postId = (int) $request->post('post_id', 0);
        $fieldDefId = (int) $request->post('field_definition_id', 0);
        $label = trim((string) $request->post('sub_label', ''));
        $type = (string) $request->post('sub_type', 'texto');
        $key = trim((string) $request->post('sub_key', ''));
        $key = $key === '' ? Slugger::slugify($label) : Slugger::slugify($key);
        if (!in_array($type, ['texto', 'foto', 'galeria_fotos', 'video', 'galeria_videos'], true)) {
            $type = 'texto';
        }
        $fd = (new FieldDefinition())->findById($fieldDefId);
        if ($fd === null || (string) $fd['owner_type'] !== FieldDefinition::OWNER_POST
            || (int) $fd['owner_id'] !== $postId || (string) $fd['field_type'] !== 'repetidor') {
            Session::flash('error', 'Campo repetidor inválido.');
            Url::redirect('/posts/edit?id=' . $postId);
        }
        if ($label === '' || !preg_match('/^[a-z0-9-]{2,120}$/', $key)) {
            Session::flash('error', 'Subcampo inválido.');
            Url::redirect('/posts/edit?id=' . $postId);
        }
        $rep = new Repeater();
        $rdef = $rep->findDefinitionByFieldDefId($fieldDefId);
        if ($rdef === null) {
            Session::flash('error', 'Repetidor não inicializado.');
            Url::redirect('/posts/edit?id=' . $postId);
        }
        $rid = (int) $rdef['id'];
        if ($rep->subfieldKeyExists($rid, $key)) {
            Session::flash('error', 'Identificador de subcampo já existe neste repetidor.');
            Url::redirect('/posts/edit?id=' . $postId);
        }
        $ord = $rep->nextSubfieldOrder($rid);
        $rep->insertSubfield($rid, $key, $label, $type, $ord);
        Session::flash('ok', 'Subcampo adicionado.');
        Url::redirect('/posts/edit?id=' . $postId);
    }

    public function repeaterDeleteSubfield(Request $request): void
    {
        Auth::requireAdmin();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/posts');
        }
        $postId = (int) $request->post('post_id', 0);
        $subId = (int) $request->post('subfield_id', 0);
        $sf = (new Repeater())->findSubfieldById($subId);
        if ($sf === null) {
            Session::flash('error', 'Subcampo não encontrado.');
            Url::redirect('/posts/edit?id=' . $postId);
        }
        (new Repeater())->deleteSubfield($subId);
        Session::flash('ok', 'Subcampo removido.');
        Url::redirect('/posts/edit?id=' . $postId);
    }

    public function repeaterAddItem(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/posts');
        }
        $postId = (int) $request->post('post_id', 0);
        $fieldDefId = (int) $request->post('field_definition_id', 0);
        $fd = (new FieldDefinition())->findById($fieldDefId);
        if ($fd === null || (string) $fd['owner_type'] !== FieldDefinition::OWNER_POST
            || (int) $fd['owner_id'] !== $postId || (string) $fd['field_type'] !== 'repetidor') {
            Session::flash('error', 'Repetidor inválido.');
            Url::redirect('/posts/edit?id=' . $postId);
        }
        $rep = new Repeater();
        $rdef = $rep->findDefinitionByFieldDefId($fieldDefId);
        if ($rdef === null) {
            Session::flash('error', 'Repetidor não encontrado.');
            Url::redirect('/posts/edit?id=' . $postId);
        }
        $rep->addItem((int) $rdef['id']);
        Session::flash('ok', 'Item adicionado ao repetidor.');
        Url::redirect('/posts/edit?id=' . $postId);
    }

    public function repeaterDeleteItem(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/posts');
        }
        $postId = (int) $request->post('post_id', 0);
        $itemId = (int) $request->post('item_id', 0);
        $rep = new Repeater();
        $it = $rep->findItemById($itemId);
        if ($it === null) {
            Session::flash('error', 'Item não encontrado.');
            Url::redirect('/posts/edit?id=' . $postId);
        }
        $rep->deleteItem($itemId);
        Session::flash('ok', 'Item removido.');
        Url::redirect('/posts/edit?id=' . $postId);
    }

    public function repeaterReorderItems(Request $request): void
    {
        Auth::requireEditor();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/posts');
        }
        $postId = (int) $request->post('post_id', 0);
        $repDefId = (int) $request->post('repeater_definition_id', 0);
        $order = $_POST['item_order'] ?? [];
        if (!is_array($order) || $repDefId < 1) {
            Session::flash('error', 'Ordem inválida.');
            Url::redirect('/posts/edit?id=' . $postId);
        }
        $ids = array_values(array_filter(array_map('intval', $order), static fn (int $x) => $x > 0));
        (new Repeater())->reorderItems($repDefId, $ids);
        Session::flash('ok', 'Ordem dos itens atualizada.');
        Url::redirect('/posts/edit?id=' . $postId);
    }

    public function delete(Request $request): void
    {
        Auth::requireAdmin();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada.');
            Url::redirect('/posts');
        }
        $id = (int) $request->post('id', 0);
        if ($id < 1) {
            Session::flash('error', 'Post inválido.');
            Url::redirect('/posts');
        }
        $p = new Post();
        if ($p->findById($id) === null) {
            Session::flash('error', 'Post não encontrado.');
            Url::redirect('/posts');
        }
        foreach ((new FieldDefinition())->listByPostId($id) as $f) {
            $this->deleteFieldCascade((int) $f['id']);
        }
        $p->delete($id);
        Session::flash('ok', 'Post excluído.');
        Url::redirect('/posts');
    }
}
