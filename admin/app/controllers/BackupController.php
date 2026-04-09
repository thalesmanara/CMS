<?php

declare(strict_types=1);

namespace Revita\Crm\Controllers;

use PDO;
use Revita\Crm\Core\Auth;
use Revita\Crm\Core\Csrf;
use Revita\Crm\Core\Database;
use Revita\Crm\Core\Request;
use Revita\Crm\Core\Response;
use Revita\Crm\Core\Session;
use Revita\Crm\Core\View;
use Revita\Crm\Helpers\Url;

final class BackupController
{
    /** Ordem importa: filhos primeiro ao truncar. */
    private const TABLES = [
        'revita_crm_repeater_item_values',
        'revita_crm_repeater_items',
        'revita_crm_repeater_subfield_definitions',
        'revita_crm_repeater_definitions',
        'revita_crm_field_values',
        'revita_crm_field_definitions',
        'revita_crm_posts',
        'revita_crm_pages',
        'revita_crm_media',
        'revita_crm_subcategories',
        'revita_crm_categories',
        'revita_crm_settings',
        'revita_crm_password_resets',
        'revita_crm_users',
    ];

    public function index(Request $request): void
    {
        Auth::requireAdmin();
        $html = View::layout('admin', 'backup/index', [
            'title' => 'Backup / Migração — Revita CMS',
            'nav' => 'backup',
            'user' => Auth::user(),
            'csrfToken' => Csrf::token(),
            'flashOk' => Session::flash('ok'),
            'flashErr' => Session::flash('error'),
        ]);
        Response::html($html);
    }

    public function export(Request $request): void
    {
        Auth::requireAdmin();

        $pdo = Database::pdo();
        $dump = [
            'meta' => [
                'app' => 'Revita CMS',
                'exported_at' => date('c'),
                'tables' => self::TABLES,
            ],
            'data' => [],
        ];
        foreach (self::TABLES as $t) {
            $stmt = $pdo->query('SELECT * FROM ' . $t);
            $dump['data'][$t] = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        }

        $json = json_encode($dump, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            Session::flash('error', 'Não foi possível gerar o backup (JSON inválido).');
            Url::redirect('/backup');
        }

        $baseName = 'revita-cms-backup_' . date('Ymd_His');

        // Preferir ZIP (inclui uploads). Se ZipArchive não existir, retorna JSON.
        if (!class_exists(\ZipArchive::class)) {
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $baseName . '.json"');
            echo $json;
            exit;
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'revita_zip_');
        if ($tmpZip === false) {
            Session::flash('error', 'Não foi possível criar arquivo temporário de backup.');
            Url::redirect('/backup');
        }
        @unlink($tmpZip);
        $tmpZip .= '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($tmpZip, \ZipArchive::CREATE) !== true) {
            Session::flash('error', 'Não foi possível gerar o arquivo ZIP de backup.');
            Url::redirect('/backup');
        }
        $zip->addFromString('db.json', $json);

        $uploadsDir = REVITA_CRM_ROOT . '/uploads';
        if (is_dir($uploadsDir)) {
            $this->zipAddDir($zip, $uploadsDir, 'uploads');
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $baseName . '.zip"');
        header('Content-Length: ' . (string) filesize($tmpZip));
        readfile($tmpZip);
        @unlink($tmpZip);
        exit;
    }

    public function import(Request $request): void
    {
        Auth::requireAdmin();
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('error', 'Sessão expirada. Atualize e tente novamente.');
            Url::redirect('/backup');
        }

        $file = $_FILES['backup_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Envie um arquivo de backup válido.');
            Url::redirect('/backup');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            Session::flash('error', 'Upload inválido.');
            Url::redirect('/backup');
        }

        $name = (string) ($file['name'] ?? 'backup');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        $workDir = sys_get_temp_dir() . '/revita_import_' . bin2hex(random_bytes(8));
        @mkdir($workDir, 0755, true);

        $json = null;
        $extractedUploads = null;

        if ($ext === 'zip') {
            if (!class_exists(\ZipArchive::class)) {
                Session::flash('error', 'Importação de ZIP indisponível (ZipArchive não instalado no PHP).');
                Url::redirect('/backup');
            }
            $zip = new \ZipArchive();
            if ($zip->open($tmp) !== true) {
                Session::flash('error', 'ZIP inválido ou corrompido.');
                Url::redirect('/backup');
            }
            $zip->extractTo($workDir);
            $zip->close();

            $dbPath = $workDir . '/db.json';
            if (!is_file($dbPath)) {
                Session::flash('error', 'Backup ZIP inválido: db.json não encontrado.');
                Url::redirect('/backup');
            }
            $json = (string) file_get_contents($dbPath);
            $uploadsPath = $workDir . '/uploads';
            $extractedUploads = is_dir($uploadsPath) ? $uploadsPath : null;
        } else {
            $json = (string) file_get_contents($tmp);
        }

        $payload = json_decode((string) $json, true);
        if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
            Session::flash('error', 'Arquivo de backup inválido (JSON).');
            Url::redirect('/backup');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach (self::TABLES as $t) {
                $pdo->exec('TRUNCATE TABLE ' . $t);
            }
            foreach (self::TABLES as $t) {
                $rows = $payload['data'][$t] ?? [];
                if (!is_array($rows) || $rows === []) {
                    continue;
                }
                foreach ($rows as $row) {
                    if (!is_array($row) || $row === []) {
                        continue;
                    }
                    $this->insertRow($pdo, $t, $row);
                }
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Session::flash('error', 'Falha ao importar backup: ' . $e->getMessage());
            Url::redirect('/backup');
        }

        // Restaurar uploads (se vierem no ZIP).
        if ($extractedUploads !== null) {
            $dest = REVITA_CRM_ROOT . '/uploads';
            $this->deleteDir($dest);
            @mkdir($dest, 0755, true);
            $this->copyDir($extractedUploads, $dest);
        }

        $this->deleteDir($workDir);
        Session::flash('ok', 'Backup importado com sucesso.');
        Url::redirect('/dashboard');
    }

    private function insertRow(PDO $pdo, string $table, array $row): void
    {
        $cols = array_keys($row);
        $place = implode(',', array_fill(0, count($cols), '?'));
        $colSql = implode(',', array_map(static fn (string $c) => '`' . str_replace('`', '', $c) . '`', $cols));
        $stmt = $pdo->prepare("INSERT INTO {$table} ({$colSql}) VALUES ({$place})");
        $vals = [];
        foreach ($cols as $c) {
            $vals[] = $row[$c];
        }
        $stmt->execute($vals);
    }

    private function zipAddDir(\ZipArchive $zip, string $dir, string $zipPath): void
    {
        $dir = rtrim($dir, '/\\');
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            $real = $file->getPathname();
            $rel = substr($real, strlen($dir) + 1);
            $rel = str_replace('\\', '/', $rel);
            $target = rtrim($zipPath, '/') . '/' . $rel;
            if ($file->isDir()) {
                $zip->addEmptyDir($target);
            } else {
                $zip->addFile($real, $target);
            }
        }
    }

    private function deleteDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($path);
    }

    private function copyDir(string $src, string $dest): void
    {
        $src = rtrim($src, '/\\');
        $dest = rtrim($dest, '/\\');
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            $from = $file->getPathname();
            $rel = substr($from, strlen($src) + 1);
            $rel = str_replace('\\', '/', $rel);
            $to = $dest . '/' . $rel;
            $toDir = dirname($to);
            if (!is_dir($toDir)) {
                @mkdir($toDir, 0755, true);
            }
            if ($file->isDir()) {
                if (!is_dir($to)) {
                    @mkdir($to, 0755, true);
                }
            } else {
                @copy($from, $to);
            }
        }
    }
}

