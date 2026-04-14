<?php

declare(strict_types=1);

namespace Revita\Crm\Controllers\Concerns;

use Revita\Crm\Core\Request;
use Revita\Crm\Helpers\MediaUpload;
use Revita\Crm\Helpers\Youtube;
use Revita\Crm\Models\FieldDefinition;
use Revita\Crm\Models\FieldValue;
use Revita\Crm\Models\Media;
use Revita\Crm\Models\Repeater;

/**
 * @phpstan-require-extends object
 */
trait ManagesDynamicFields
{
    abstract protected function fieldOwnerType(): string;

    /** @return list<array<string, mixed>> */
    protected function listFieldDefs(FieldDefinition $fd, int $ownerId): array
    {
        return $fd->listByOwner($this->fieldOwnerType(), $ownerId);
    }

    /** @return list<array<string,mixed>> */
    protected function buildEditBlocks(int $ownerId): array
    {
        $fd = new FieldDefinition();
        $fv = new FieldValue();
        $rep = new Repeater();
        $blocks = [];
        foreach ($this->listFieldDefs($fd, $ownerId) as $f) {
            if ((string) $f['field_type'] === 'repetidor') {
                $rd = $rep->findDefinitionByFieldDefId((int) $f['id']);
                if ($rd === null) {
                    $blocks[] = ['kind' => 'repetidor', 'field' => $f, 'rep' => null];
                    continue;
                }
                $rid = (int) $rd['id'];
                $subs = $rep->listSubfields($rid);
                $items = $rep->listItems($rid);
                $itemData = [];
                foreach ($items as $it) {
                    $itemData[] = [
                        'item' => $it,
                        'values' => $rep->valuesMapForItem((int) $it['id']),
                    ];
                }
                $blocks[] = [
                    'kind' => 'repetidor',
                    'field' => $f,
                    'rep_id' => $rid,
                    'subfields' => $subs,
                    'items' => $itemData,
                ];
                continue;
            }
            $blocks[] = [
                'kind' => 'scalar',
                'field' => $f,
                'value' => $fv->get((int) $f['id']),
            ];
        }
        return $blocks;
    }

    protected function deleteFieldCascade(int $fieldDefinitionId): void
    {
        $fd = new FieldDefinition();
        $def = $fd->findById($fieldDefinitionId);
        if ($def === null) {
            return;
        }
        if ((string) $def['field_type'] === 'repetidor') {
            (new Repeater())->deleteCascadeByFieldDefinitionId($fieldDefinitionId);
        }
        (new FieldValue())->deleteForField($fieldDefinitionId);
        $fd->deleteRow($fieldDefinitionId);
    }

    /** @param array<string,mixed> $def */
    protected function saveScalarField(FieldValue $fv, array $def, Request $request, ?int $userId): void
    {
        $id = (int) $def['id'];
        $type = (string) $def['field_type'];
        switch ($type) {
            case 'texto':
                $text = trim((string) $request->post('fv_text_' . $id, ''));
                $fv->upsert($id, $text, null, null, null);
                return;
            case 'botao':
                $text = trim((string) $request->post('btn_text_' . $id, ''));
                $url = trim((string) $request->post('btn_url_' . $id, ''));
                $fv->upsert($id, $text, $url, null, null);
                return;
            case 'icone':
                $src = (string) $request->post('icon_src_' . $id, 'registry');
                if ($src === 'upload') {
                    $fileKey = 'icon_svg_' . $id;
                    if (isset($_FILES[$fileKey]) && (int) ($_FILES[$fileKey]['error'] ?? 0) === UPLOAD_ERR_OK) {
                        $mid = MediaUpload::handle($_FILES[$fileKey], 'image', $userId);
                        if ($mid !== null) {
                            $m = (new Media())->findById($mid);
                            if ($m !== null && strtolower((string) ($m['stored_name'] ?? '')) !== '' && !str_ends_with(strtolower((string) ($m['stored_name'] ?? '')), '.svg')) {
                                // Not an SVG; ignore.
                                $mid = null;
                            }
                        }
                        if ($mid !== null) {
                            $mixed = ['source' => 'upload', 'media_id' => $mid];
                            $fv->upsert($id, null, null, null, json_encode($mixed, JSON_UNESCAPED_UNICODE));
                            return;
                        }
                    }
                    $row = $fv->get($id);
                    if ($row && !empty($row['value_mixed_json'])) {
                        $prev = json_decode((string) $row['value_mixed_json'], true);
                        if (is_array($prev) && ($prev['source'] ?? '') === 'upload') {
                            $fv->upsert($id, null, null, null, json_encode($prev, JSON_UNESCAPED_UNICODE));
                            return;
                        }
                    }
                    $fv->upsert($id, null, null, null, null);
                    return;
                }
                $key = trim((string) $request->post('icon_key_' . $id, ''));
                $set = trim((string) $request->post('icon_set_' . $id, ''));
                $style = trim((string) $request->post('icon_style_' . $id, ''));
                $mixed = ['source' => 'registry', 'iconKey' => $key];
                if ($set !== '') {
                    $mixed['iconSet'] = $set;
                }
                if ($style !== '') {
                    $mixed['iconStyle'] = $style;
                }
                $fv->upsert($id, null, null, null, json_encode($mixed, JSON_UNESCAPED_UNICODE));
                return;
            case 'foto':
                $this->saveFotoField($fv, $id, $request, $userId);
                return;
            case 'galeria_fotos':
                $this->saveGaleriaFotos($fv, $id, $request, $userId);
                return;
            case 'video':
                $this->saveVideoField($fv, $id, $request, $userId);
                return;
            case 'galeria_videos':
                $this->saveGaleriaVideos($fv, $id, $request, $userId);
                return;
        }
    }

    private function saveFotoField(FieldValue $fv, int $id, Request $request, ?int $userId): void
    {
        $row = $fv->get($id);
        $ids = [];
        if ($row && !empty($row['value_media_ids_json'])) {
            $d = json_decode((string) $row['value_media_ids_json'], true);
            if (is_array($d)) {
                $ids = array_values(array_filter(array_map('intval', $d), static fn (int $x) => $x > 0));
            }
        }
        $fileKey = 'file_field_' . $id;
        if (isset($_FILES[$fileKey]) && (int) ($_FILES[$fileKey]['error'] ?? 0) === UPLOAD_ERR_OK) {
            $mid = MediaUpload::handle($_FILES[$fileKey], 'image', $userId);
            if ($mid !== null) {
                $ids = [$mid];
            }
        }
        if ($request->postFlag('clear_foto_' . $id)) {
            $ids = [];
        }
        $fv->upsert($id, null, null, json_encode($ids, JSON_UNESCAPED_UNICODE), null);
    }

    private function saveGaleriaFotos(FieldValue $fv, int $id, Request $request, ?int $userId): void
    {
        $raw = (string) $request->post('existing_gal_' . $id, '[]');
        $ids = [];
        $d = json_decode($raw, true);
        if (is_array($d)) {
            $ids = array_values(array_filter(array_map('intval', $d), static fn (int $x) => $x > 0));
        }
        $fk = 'gal_' . $id;
        if (isset($_FILES[$fk]) && is_array($_FILES[$fk]['name'] ?? null)) {
            $files = $this->normalizeFilesArray($_FILES[$fk]);
            foreach ($files as $f) {
                $mid = MediaUpload::handle($f, 'image', $userId);
                if ($mid !== null) {
                    $ids[] = $mid;
                }
            }
        }
        $fv->upsert($id, null, null, json_encode($ids, JSON_UNESCAPED_UNICODE), null);
    }

    /** @param array<string,mixed> $files $_FILES single slot */
    /** @return list<array<string,mixed>> */
    private function normalizeFilesArray(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return [(array) $files];
        }
        $out = [];
        foreach ($files['name'] as $i => $name) {
            if ((int) ($files['error'][$i] ?? 0) !== UPLOAD_ERR_OK) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => (int) ($files['error'][$i] ?? 0),
                'size' => (int) ($files['size'][$i] ?? 0),
            ];
        }
        return $out;
    }

    private function saveVideoField(FieldValue $fv, int $id, Request $request, ?int $userId): void
    {
        $src = (string) $request->post('vid_src_' . $id, 'upload');
        if ($src === 'youtube') {
            $url = trim((string) $request->post('vid_yt_' . $id, ''));
            $yid = Youtube::extractId($url);
            $mixed = [
                'source' => 'youtube',
                'youtube_url' => $url,
                'youtube_id' => $yid,
            ];
            $fv->upsert($id, null, null, null, json_encode($mixed, JSON_UNESCAPED_UNICODE));
            return;
        }
        $fileKey = 'vid_file_' . $id;
        if (isset($_FILES[$fileKey]) && (int) ($_FILES[$fileKey]['error'] ?? 0) === UPLOAD_ERR_OK) {
            $mid = MediaUpload::handle($_FILES[$fileKey], 'video', $userId);
            if ($mid !== null) {
                $mixed = ['source' => 'upload', 'media_id' => $mid];
                $fv->upsert($id, null, null, null, json_encode($mixed, JSON_UNESCAPED_UNICODE));
                return;
            }
        }
        $row = $fv->get($id);
        if ($row && !empty($row['value_mixed_json'])) {
            $prev = json_decode((string) $row['value_mixed_json'], true);
            if (is_array($prev) && ($prev['source'] ?? '') === 'upload') {
                $fv->upsert($id, null, null, null, json_encode($prev, JSON_UNESCAPED_UNICODE));
            }
        }
    }

    private function saveGaleriaVideos(FieldValue $fv, int $id, Request $request, ?int $userId): void
    {
        $row = $fv->get($id);
        $existing = [];
        if ($row && !empty($row['value_mixed_json'])) {
            $e = json_decode((string) $row['value_mixed_json'], true);
            if (is_array($e)) {
                $existing = $e;
            }
        }
        $srcs = $_POST['gv_src'][$id] ?? [];
        $yts = $_POST['gv_yt'][$id] ?? [];
        if (!is_array($srcs)) {
            $srcs = [];
        }
        if (!is_array($yts)) {
            $yts = [];
        }
        $out = [];
        $count = max(count($srcs), count($yts));
        for ($i = 0; $i < $count; $i++) {
            $s = (string) ($srcs[$i] ?? 'upload');
            if ($s === 'youtube') {
                $yt = trim((string) ($yts[$i] ?? ''));
                $yid = Youtube::extractId($yt);
                $out[] = ['source' => 'youtube', 'youtube_url' => $yt, 'youtube_id' => $yid];
                continue;
            }
            $fileArr = null;
            if (isset($_FILES['gv_file_' . $id]) && is_array($_FILES['gv_file_' . $id]['name'] ?? null)) {
                $gf = $_FILES['gv_file_' . $id];
                if (isset($gf['name'][$i]) && (int) ($gf['error'][$i] ?? 0) === UPLOAD_ERR_OK) {
                    $fileArr = [
                        'name' => $gf['name'][$i],
                        'type' => $gf['type'][$i] ?? '',
                        'tmp_name' => $gf['tmp_name'][$i] ?? '',
                        'error' => (int) $gf['error'][$i],
                        'size' => (int) ($gf['size'][$i] ?? 0),
                    ];
                }
            }
            if ($fileArr !== null) {
                $mid = MediaUpload::handle($fileArr, 'video', $userId);
                if ($mid !== null) {
                    $out[] = ['source' => 'upload', 'media_id' => $mid];
                }
            } elseif (isset($existing[$i]) && is_array($existing[$i])) {
                $out[] = $existing[$i];
            }
        }
        $fv->upsert($id, null, null, null, json_encode($out, JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string,mixed> $def */
    protected function saveRepeaterFieldContent(Repeater $rep, array $def, ?int $userId): void
    {
        $fieldDefId = (int) $def['id'];
        $rdef = $rep->findDefinitionByFieldDefId($fieldDefId);
        if ($rdef === null) {
            return;
        }
        $rid = (int) $rdef['id'];
        $subfields = $rep->listSubfields($rid);
        foreach ($rep->listItems($rid) as $it) {
            $itemId = (int) $it['id'];
            foreach ($subfields as $sf) {
                $sid = (int) $sf['id'];
                $stype = (string) $sf['field_type'];
                $this->saveRepeaterSubfieldValue($rep, $itemId, $sid, $stype, $userId);
            }
        }
    }

    private function saveRepeaterSubfieldValue(
        Repeater $rep,
        int $itemId,
        int $subfieldDefId,
        string $type,
        ?int $userId
    ): void {
        if ($type === 'texto') {
            $text = trim((string) ($_POST['rp_' . $itemId . '_' . $subfieldDefId] ?? ''));
            $rep->upsertItemValue($itemId, $subfieldDefId, $text, null, null, null);
            return;
        }
        if ($type === 'botao') {
            $text = trim((string) ($_POST['rp_btn_text_' . $itemId . '_' . $subfieldDefId] ?? ''));
            $url = trim((string) ($_POST['rp_btn_url_' . $itemId . '_' . $subfieldDefId] ?? ''));
            $rep->upsertItemValue($itemId, $subfieldDefId, $text, $url, null, null);
            return;
        }
        if ($type === 'icone') {
            $src = (string) ($_POST['rp_icon_src_' . $itemId . '_' . $subfieldDefId] ?? 'registry');
            if ($src === 'upload') {
                $fk = 'rp_icon_svg_' . $itemId . '_' . $subfieldDefId;
                if (isset($_FILES[$fk]) && (int) ($_FILES[$fk]['error'] ?? 0) === UPLOAD_ERR_OK) {
                    $mid = MediaUpload::handle($_FILES[$fk], 'image', $userId);
                    if ($mid !== null) {
                        $m = (new Media())->findById($mid);
                        if ($m !== null && strtolower((string) ($m['stored_name'] ?? '')) !== '' && !str_ends_with(strtolower((string) ($m['stored_name'] ?? '')), '.svg')) {
                            $mid = null;
                        }
                    }
                    if ($mid !== null) {
                        $mixed = ['source' => 'upload', 'media_id' => $mid];
                        $rep->upsertItemValue($itemId, $subfieldDefId, null, null, null, json_encode($mixed, JSON_UNESCAPED_UNICODE));
                        return;
                    }
                }
                $row = $rep->valuesMapForItem($itemId)[$subfieldDefId] ?? null;
                if ($row && !empty($row['value_mixed_json'])) {
                    $prev = json_decode((string) $row['value_mixed_json'], true);
                    if (is_array($prev) && ($prev['source'] ?? '') === 'upload') {
                        $rep->upsertItemValue($itemId, $subfieldDefId, null, null, null, json_encode($prev, JSON_UNESCAPED_UNICODE));
                        return;
                    }
                }
                $rep->upsertItemValue($itemId, $subfieldDefId, null, null, null, null);
                return;
            }

            $key = trim((string) ($_POST['rp_icon_key_' . $itemId . '_' . $subfieldDefId] ?? ''));
            $set = trim((string) ($_POST['rp_icon_set_' . $itemId . '_' . $subfieldDefId] ?? ''));
            $style = trim((string) ($_POST['rp_icon_style_' . $itemId . '_' . $subfieldDefId] ?? ''));
            $mixed = ['source' => 'registry', 'iconKey' => $key];
            if ($set !== '') {
                $mixed['iconSet'] = $set;
            }
            if ($style !== '') {
                $mixed['iconStyle'] = $style;
            }
            $rep->upsertItemValue($itemId, $subfieldDefId, null, null, null, json_encode($mixed, JSON_UNESCAPED_UNICODE));
            return;
        }
        if ($type === 'foto') {
            $row = $rep->valuesMapForItem($itemId)[$subfieldDefId] ?? null;
            $ids = [];
            if ($row && !empty($row['value_media_ids_json'])) {
                $d = json_decode((string) $row['value_media_ids_json'], true);
                if (is_array($d)) {
                    $ids = array_values(array_filter(array_map('intval', $d), static fn (int $x) => $x > 0));
                }
            }
            $fk = 'rpfile_' . $itemId . '_' . $subfieldDefId;
            if (isset($_FILES[$fk]) && (int) ($_FILES[$fk]['error'] ?? 0) === UPLOAD_ERR_OK) {
                $mid = MediaUpload::handle($_FILES[$fk], 'image', $userId);
                if ($mid !== null) {
                    $ids = [$mid];
                }
            }
            if (!empty($_POST['rp_clear_foto_' . $itemId . '_' . $subfieldDefId])) {
                $ids = [];
            }
            $rep->upsertItemValue($itemId, $subfieldDefId, null, null, json_encode($ids, JSON_UNESCAPED_UNICODE), null);
            return;
        }
        if ($type === 'galeria_fotos') {
            $raw = (string) ($_POST['rp_gal_existing_' . $itemId . '_' . $subfieldDefId] ?? '[]');
            $ids = [];
            $d = json_decode($raw, true);
            if (is_array($d)) {
                $ids = array_values(array_filter(array_map('intval', $d), static fn (int $x) => $x > 0));
            }
            $fk = 'rp_gal_' . $itemId . '_' . $subfieldDefId;
            if (isset($_FILES[$fk]) && is_array($_FILES[$fk]['name'] ?? null)) {
                foreach ($this->normalizeFilesArray($_FILES[$fk]) as $f) {
                    $mid = MediaUpload::handle($f, 'image', $userId);
                    if ($mid !== null) {
                        $ids[] = $mid;
                    }
                }
            }
            $rep->upsertItemValue($itemId, $subfieldDefId, null, null, json_encode($ids, JSON_UNESCAPED_UNICODE), null);
            return;
        }
        if ($type === 'video') {
            $src = (string) ($_POST['rp_vid_src_' . $itemId . '_' . $subfieldDefId] ?? 'upload');
            if ($src === 'youtube') {
                $url = trim((string) ($_POST['rp_vid_yt_' . $itemId . '_' . $subfieldDefId] ?? ''));
                $yid = Youtube::extractId($url);
                $mixed = ['source' => 'youtube', 'youtube_url' => $url, 'youtube_id' => $yid];
                $rep->upsertItemValue($itemId, $subfieldDefId, null, null, null, json_encode($mixed, JSON_UNESCAPED_UNICODE));
                return;
            }
            $fk = 'rp_vid_file_' . $itemId . '_' . $subfieldDefId;
            if (isset($_FILES[$fk]) && (int) ($_FILES[$fk]['error'] ?? 0) === UPLOAD_ERR_OK) {
                $mid = MediaUpload::handle($_FILES[$fk], 'video', $userId);
                if ($mid !== null) {
                    $mixed = ['source' => 'upload', 'media_id' => $mid];
                    $rep->upsertItemValue($itemId, $subfieldDefId, null, null, null, json_encode($mixed, JSON_UNESCAPED_UNICODE));
                    return;
                }
            }
            $map = $rep->valuesMapForItem($itemId);
            $row = $map[$subfieldDefId] ?? null;
            if ($row && !empty($row['value_mixed_json'])) {
                $prev = json_decode((string) $row['value_mixed_json'], true);
                if (is_array($prev) && ($prev['source'] ?? '') === 'upload') {
                    $rep->upsertItemValue($itemId, $subfieldDefId, null, null, null, json_encode($prev, JSON_UNESCAPED_UNICODE));
                }
            }
            return;
        }
        if ($type === 'galeria_videos') {
            $raw = (string) ($_POST['rp_gv_existing_' . $itemId . '_' . $subfieldDefId] ?? '[]');
            $out = [];
            $existing = json_decode($raw, true);
            if (!is_array($existing)) {
                $existing = [];
            }
            $px = $itemId . '_' . $subfieldDefId;
            $srcs = $_POST['rp_gv_src_' . $px] ?? [];
            $yts = $_POST['rp_gv_yt_' . $px] ?? [];
            if (!is_array($srcs)) {
                $srcs = [];
            }
            if (!is_array($yts)) {
                $yts = [];
            }
            $n = max(count($srcs), count($yts));
            for ($i = 0; $i < $n; $i++) {
                $s = (string) ($srcs[$i] ?? 'upload');
                if ($s === 'youtube') {
                    $yt = trim((string) ($yts[$i] ?? ''));
                    $out[] = [
                        'source' => 'youtube',
                        'youtube_url' => $yt,
                        'youtube_id' => Youtube::extractId($yt),
                    ];
                    continue;
                }
                $fileArr = null;
                $gf = $_FILES['rp_gv_file_' . $itemId . '_' . $subfieldDefId] ?? null;
                if (is_array($gf) && isset($gf['name'][$i]) && (int) ($gf['error'][$i] ?? 0) === UPLOAD_ERR_OK) {
                    $fileArr = [
                        'name' => $gf['name'][$i],
                        'type' => $gf['type'][$i] ?? '',
                        'tmp_name' => $gf['tmp_name'][$i] ?? '',
                        'error' => (int) $gf['error'][$i],
                        'size' => (int) ($gf['size'][$i] ?? 0),
                    ];
                }
                if ($fileArr !== null) {
                    $mid = MediaUpload::handle($fileArr, 'video', $userId);
                    if ($mid !== null) {
                        $out[] = ['source' => 'upload', 'media_id' => $mid];
                    }
                } elseif (isset($existing[$i]) && is_array($existing[$i])) {
                    $out[] = $existing[$i];
                }
            }
            $rep->upsertItemValue($itemId, $subfieldDefId, null, null, null, json_encode($out, JSON_UNESCAPED_UNICODE));
        }
    }
}
