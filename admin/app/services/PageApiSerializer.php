<?php

declare(strict_types=1);

namespace Revita\Crm\Services;

use Revita\Crm\Helpers\Url;
use Revita\Crm\Helpers\Youtube;
use Revita\Crm\Models\FieldDefinition;
use Revita\Crm\Models\FieldValue;
use Revita\Crm\Models\Media;
use Revita\Crm\Models\Page;
use Revita\Crm\Models\Post;
use Revita\Crm\Models\Repeater;
use Revita\Crm\Models\Section;

final class PageApiSerializer
{
    public static function mediaPublicUrl(string $relativePath): string
    {
        return Url::adminRootAbsolute() . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    /** @return list<int> */
    private static function decodeIdList(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $a = json_decode($json, true);
        if (!is_array($a)) {
            return [];
        }
        return array_values(array_filter(array_map('intval', $a), static fn (int $x) => $x > 0));
    }

    /** @param list<int> $ids */
    private static function urlsForMediaIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $media = new Media();
        $rows = $media->findByIdsOrdered($ids);
        $urls = [];
        foreach ($rows as $r) {
            $urls[] = self::mediaPublicUrl((string) $r['relative_path']);
        }
        return $urls;
    }

    /** @return array<string, mixed>|list<mixed> */
    private static function expandVideoMixed(?string $json): mixed
    {
        if ($json === null || $json === '') {
            return [];
        }
        $d = json_decode($json, true);
        if (!is_array($d)) {
            return [];
        }
        if (isset($d['source']) && $d['source'] === 'upload' && !empty($d['media_id'])) {
            $m = (new Media())->findById((int) $d['media_id']);
            if ($m !== null) {
                return [
                    'source' => 'upload',
                    'media_id' => (int) $m['id'],
                    'url' => self::mediaPublicUrl((string) $m['relative_path']),
                ];
            }
            return ['source' => 'upload', 'media_id' => (int) $d['media_id'], 'url' => null];
        }
        if (isset($d['source']) && $d['source'] === 'youtube') {
            $id = $d['youtube_id'] ?? null;
            if (!$id && !empty($d['youtube_url'])) {
                $id = Youtube::extractId((string) $d['youtube_url']);
            }
            $id = $id ? (string) $id : null;
            return [
                'source' => 'youtube',
                'youtube_id' => $id,
                'watch_url' => $id ? 'https://www.youtube.com/watch?v=' . rawurlencode($id) : null,
                'embed_url' => $id ? 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id) : null,
            ];
        }
        return $d;
    }

    /** @return list<mixed> */
    private static function expandGalleryVideos(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $arr = json_decode($json, true);
        if (!is_array($arr)) {
            return [];
        }
        $out = [];
        foreach ($arr as $item) {
            if (!is_array($item)) {
                continue;
            }
            $out[] = self::expandVideoMixed(json_encode($item, JSON_UNESCAPED_UNICODE));
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $def
     * @return array{nome:string,identificador:string,tipo:string,valor:mixed}
     */
    private static function mapScalarField(array $def, ?array $valRow): array
    {
        $tipo = (string) $def['field_type'];
        $nome = (string) $def['label_name'];
        $ident = (string) $def['field_key'];
        $valor = null;

        if ($valRow === null) {
            return [
                'nome' => $nome,
                'identificador' => $ident,
                'tipo' => $tipo,
                'valor' => null,
            ];
        }

        switch ($tipo) {
            case 'texto':
                $valor = (string) ($valRow['value_text'] ?? '');
                break;
            case 'botao':
                $valor = [
                    'texto' => (string) ($valRow['value_text'] ?? ''),
                    'link' => (string) ($valRow['value_url'] ?? ''),
                ];
                break;
            case 'foto':
                $ids = self::decodeIdList($valRow['value_media_ids_json'] ?? null);
                $urls = self::urlsForMediaIds($ids);
                $valor = $urls[0] ?? null;
                break;
            case 'galeria_fotos':
                $ids = self::decodeIdList($valRow['value_media_ids_json'] ?? null);
                $valor = self::urlsForMediaIds($ids);
                break;
            case 'video':
                $valor = self::expandVideoMixed($valRow['value_mixed_json'] ?? null);
                break;
            case 'galeria_videos':
                $valor = self::expandGalleryVideos($valRow['value_mixed_json'] ?? null);
                break;
            default:
                $valor = null;
        }

        return [
            'nome' => $nome,
            'identificador' => $ident,
            'tipo' => $tipo,
            'valor' => $valor,
        ];
    }

    /**
     * @param array<string,mixed> $def
     * @return array{nome:string,identificador:string,tipo:string,valor:list<array<string,mixed>>}
     */
    private static function mapRepeaterField(array $def): array
    {
        $rep = new Repeater();
        $fd = (int) $def['id'];
        $rdef = $rep->findDefinitionByFieldDefId($fd);
        $itemsOut = [];
        if ($rdef === null) {
            return [
                'nome' => (string) $def['label_name'],
                'identificador' => (string) $def['field_key'],
                'tipo' => 'repetidor',
                'valor' => [],
            ];
        }
        $repId = (int) $rdef['id'];
        $subfields = $rep->listSubfields($repId);
        $items = $rep->listItems($repId);
        foreach ($items as $it) {
            $itemId = (int) $it['id'];
            $rowMap = $rep->valuesMapForItem($itemId);
            $obj = [];
            foreach ($subfields as $sf) {
                $sid = (int) $sf['id'];
                $skey = (string) $sf['field_key'];
                $st = (string) $sf['field_type'];
                $vr = $rowMap[$sid] ?? null;
                if ($st === 'texto') {
                    $obj[$skey] = $vr ? (string) ($vr['value_text'] ?? '') : '';
                    continue;
                }
                if ($st === 'foto') {
                    $ids = $vr ? self::decodeIdList($vr['value_media_ids_json'] ?? null) : [];
                    $u = self::urlsForMediaIds($ids);
                    $obj[$skey] = $u[0] ?? null;
                    continue;
                }
                if ($st === 'galeria_fotos') {
                    $ids = $vr ? self::decodeIdList($vr['value_media_ids_json'] ?? null) : [];
                    $obj[$skey] = self::urlsForMediaIds($ids);
                    continue;
                }
                if ($st === 'video') {
                    $obj[$skey] = $vr ? self::expandVideoMixed($vr['value_mixed_json'] ?? null) : [];
                    continue;
                }
                if ($st === 'galeria_videos') {
                    $obj[$skey] = $vr ? self::expandGalleryVideos($vr['value_mixed_json'] ?? null) : [];
                    continue;
                }
            }
            $itemsOut[] = $obj;
        }

        return [
            'nome' => (string) $def['label_name'],
            'identificador' => (string) $def['field_key'],
            'tipo' => 'repetidor',
            'valor' => $itemsOut,
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function buildCampos(int $pageId): array
    {
        $fd = new FieldDefinition();
        return self::buildCamposFromDefinitions($fd->listByPageId($pageId));
    }

    /** @return list<array<string,mixed>> */
    public static function buildCamposForPost(int $postId): array
    {
        $fd = new FieldDefinition();
        return self::buildCamposFromDefinitions($fd->listByPostId($postId));
    }

    /** @return list<array<string,mixed>> */
    public static function buildSecoes(int $pageId): array
    {
        $defs = (new FieldDefinition())->listByPageId($pageId);
        $sections = (new Section())->listByOwner(FieldDefinition::OWNER_PAGE, $pageId);
        return self::buildSecoesFromDefinitions($defs, $sections);
    }

    /** @return list<array<string,mixed>> */
    public static function buildSecoesForPost(int $postId): array
    {
        $defs = (new FieldDefinition())->listByPostId($postId);
        $sections = (new Section())->listByOwner(FieldDefinition::OWNER_POST, $postId);
        return self::buildSecoesFromDefinitions($defs, $sections);
    }

    /**
     * @param list<array<string,mixed>> $defs
     * @return list<array<string,mixed>>
     */
    private static function buildCamposFromDefinitions(array $defs): array
    {
        $fv = new FieldValue();
        $campos = [];
        foreach ($defs as $def) {
            if ((string) $def['field_type'] === 'repetidor') {
                $campos[] = self::mapRepeaterField($def);
                continue;
            }
            $val = $fv->get((int) $def['id']);
            $campos[] = self::mapScalarField($def, $val);
        }
        return $campos;
    }

    /**
     * @param list<array<string,mixed>> $defs
     * @param list<array<string,mixed>> $sectionsRows
     * @return list<array<string,mixed>>
     */
    private static function buildSecoesFromDefinitions(array $defs, array $sectionsRows): array
    {
        $fv = new FieldValue();

        $sectionsById = [];
        foreach ($sectionsRows as $s) {
            $sid = (int) ($s['id'] ?? 0);
            if ($sid < 1) {
                continue;
            }
            $sectionsById[$sid] = [
                'id' => $sid,
                'nome' => (string) ($s['title'] ?? ''),
                'ordem' => (int) ($s['order_index'] ?? 0),
                'campos' => [],
            ];
        }

        $loose = [
            'id' => null,
            'nome' => '(sem seção)',
            'ordem' => -1,
            'campos' => [],
        ];

        foreach ($defs as $def) {
            $campo = null;
            if ((string) $def['field_type'] === 'repetidor') {
                $campo = self::mapRepeaterField($def);
            } else {
                $val = $fv->get((int) $def['id']);
                $campo = self::mapScalarField($def, $val);
            }

            $sid = isset($def['section_id']) && $def['section_id'] !== null ? (int) $def['section_id'] : 0;
            if ($sid > 0 && isset($sectionsById[$sid])) {
                $sectionsById[$sid]['campos'][] = $campo;
            } else {
                $loose['campos'][] = $campo;
            }
        }

        $out = [];
        if ($loose['campos'] !== []) {
            $out[] = $loose;
        }

        // keep section order as defined in DB
        $ordered = array_values($sectionsById);
        usort($ordered, static fn (array $a, array $b) => ($a['ordem'] <=> $b['ordem']) ?: (($a['id'] ?? 0) <=> ($b['id'] ?? 0)));
        foreach ($ordered as $s) {
            $out[] = $s;
        }
        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function pagePayloadBySlug(string $slug, bool $publishedOnly): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }
        $page = new Page();
        $row = $page->findBySlug($slug);
        if ($row === null) {
            return null;
        }
        if ($publishedOnly && (string) $row['status'] !== 'published') {
            return null;
        }
        $pid = (int) $row['id'];
        return [
            'id' => $pid,
            'titulo' => (string) $row['title'],
            'slug' => (string) $row['slug'],
            'status' => (string) $row['status'],
            'campos' => self::buildCampos($pid),
            'secoes' => self::buildSecoes($pid),
        ];
    }

    public static function featuredImagePublicUrl(?int $mediaId): ?string
    {
        if ($mediaId === null || $mediaId < 1) {
            return null;
        }
        $m = (new Media())->findById($mediaId);
        if ($m === null) {
            return null;
        }
        return self::mediaPublicUrl((string) $m['relative_path']);
    }

    /** @param array<string,mixed> $row from Post::findBySlug / findById */
    /** @return array<string, mixed> */
    public static function postPayloadFromRow(array $row, bool $withCampos): array
    {
        $pid = (int) $row['id'];
        $out = [
            'id' => $pid,
            'titulo' => (string) $row['title'],
            'slug' => (string) $row['slug'],
            'status' => (string) $row['status'],
            'publicado_em' => $row['published_at'] !== null ? (string) $row['published_at'] : null,
            'categoria' => [
                'id' => (int) $row['category_id'],
                'nome' => (string) $row['category_name'],
                'slug' => (string) $row['category_slug'],
            ],
            'subcategoria' => [
                'id' => (int) $row['subcategory_id'],
                'nome' => (string) $row['subcategory_name'],
                'slug' => (string) $row['subcategory_slug'],
            ],
            'imagem_destaque' => self::featuredImagePublicUrl(
                isset($row['featured_media_id']) && $row['featured_media_id'] !== null
                    ? (int) $row['featured_media_id']
                    : null
            ),
            'autor' => [
                'login' => (string) ($row['author_login'] ?? ''),
            ],
        ];
        if ($withCampos) {
            $out['campos'] = self::buildCamposForPost($pid);
            $out['secoes'] = self::buildSecoesForPost($pid);
        }
        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function postPayloadBySlug(string $slug, bool $publishedOnly): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }
        $post = new Post();
        $row = $post->findBySlug($slug);
        if ($row === null) {
            return null;
        }
        if ($publishedOnly && (string) $row['status'] !== 'published') {
            return null;
        }
        return self::postPayloadFromRow($row, true);
    }
}
