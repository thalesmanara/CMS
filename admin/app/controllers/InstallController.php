<?php

declare(strict_types=1);

namespace Revita\Crm\Controllers;

use PDO;
use PDOException;
use Revita\Crm\Core\Config;
use Revita\Crm\Core\Csrf;
use Revita\Crm\Core\Database;
use Revita\Crm\Core\Request;
use Revita\Crm\Core\Response;
use Revita\Crm\Core\Session;
use Revita\Crm\Core\View;

final class InstallController
{
    private const MASTER_LOGIN = 'revitacomunicacao';

    private const MASTER_PASSWORD = 'RevitaCRM@#';

    private const MASTER_EMAIL = 'admin@revitacomunicacao.local';

    public function showForm(Request $request): void
    {
        $error = Session::flash('install_error');
        $html = View::layout('guest', 'install/index', [
            'title' => 'Instalação — Revita CRM',
            'csrfToken' => Csrf::token(),
            'error' => $error,
        ]);
        Response::html($html);
    }

    public function submit(Request $request): void
    {
        if (!Csrf::validate((string) $request->post('_csrf'))) {
            Session::flash('install_error', 'Sessão inválida. Atualize a página e tente novamente.');
            \Revita\Crm\Helpers\Url::redirect('/install');
        }

        $host = trim((string) $request->post('db_host', ''));
        $name = trim((string) $request->post('db_name', ''));
        $user = trim((string) $request->post('db_user', ''));
        $pass = (string) $request->post('db_password', '');

        if ($host === '' || $name === '' || $user === '') {
            Session::flash('install_error', 'Preencha host, nome do banco e usuário.');
            \Revita\Crm\Helpers\Url::redirect('/install');
        }

        try {
            $pdo = Database::fromConfig([
                'host' => $host,
                'name' => $name,
                'user' => $user,
                'password' => $pass,
                'charset' => 'utf8mb4',
            ], true);
        } catch (PDOException $e) {
            Session::flash('install_error', self::connectionErrorMessage($e));
            \Revita\Crm\Helpers\Url::redirect('/install');
        }

        $schemaPath = REVITA_CRM_ROOT . '/database/schema.sql';
        if (!is_file($schemaPath)) {
            Session::flash('install_error', 'Arquivo de schema não encontrado (database/schema.sql).');
            \Revita\Crm\Helpers\Url::redirect('/install');
        }
        $sql = (string) file_get_contents($schemaPath);
        $sql = preg_replace('/--.*$/m', '', $sql) ?? $sql;

        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $hint = 'Confirme se o usuário tem permissão CREATE/ALTER ou importe manualmente o arquivo database/schema.sql.';
            Session::flash('install_error', 'Erro ao executar o schema SQL.' . "\n\n" . $hint . "\n\n" . 'Detalhe: ' . $e->getMessage());
            \Revita\Crm\Helpers\Url::redirect('/install');
        }

        $this->seedMasterUser($pdo);

        $config = [
            'installed' => true,
            'installed_at' => date('c'),
            'db' => [
                'host' => $host,
                'name' => $name,
                'user' => $user,
                'password' => $pass,
                'charset' => 'utf8mb4',
            ],
        ];

        $exported = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
        if (file_put_contents(Config::path(), $exported) === false) {
            Session::flash('install_error', 'Não foi possível gravar admin/config/app.php (permissões).');
            \Revita\Crm\Helpers\Url::redirect('/install');
        }

        Session::regenerate();
        \Revita\Crm\Helpers\Url::redirect('/login');
    }

    private function seedMasterUser(PDO $pdo): void
    {
        $stmt = $pdo->prepare('SELECT id FROM revita_crm_users WHERE login = :login LIMIT 1');
        $stmt->execute(['login' => self::MASTER_LOGIN]);
        if ($stmt->fetch() !== false) {
            return;
        }
        $hash = password_hash(self::MASTER_PASSWORD, PASSWORD_DEFAULT);
        $ins = $pdo->prepare(
            'INSERT INTO revita_crm_users (login, email, password_hash, level, is_active)
             VALUES (:login, :email, :hash, :level, 1)'
        );
        $ins->execute([
            'login' => self::MASTER_LOGIN,
            'email' => self::MASTER_EMAIL,
            'hash' => $hash,
            'level' => 1,
        ]);
    }

    private static function connectionErrorMessage(PDOException $e): string
    {
        $msg = $e->getMessage();
        $hints = [];
        if (str_contains($msg, '1044')) {
            $hints[] = 'O usuário MySQL não tem permissão sobre o banco informado — associe o usuário ao banco com privilégios adequados no painel da hospedagem.';
        } elseif (str_contains($msg, '1045')) {
            $hints[] = 'Usuário ou senha incorretos, ou o usuário MySQL não está autorizado a conectar deste host.';
        } elseif (stripos($msg, 'Access denied') !== false) {
            $hints[] = 'Acesso negado ao MySQL — verifique usuário, senha, host permitido e permissões no banco.';
        }
        if (str_contains($msg, '1049') || stripos($msg, 'Unknown database') !== false) {
            $hints[] = 'O banco informado não existe — crie-o no painel da hospedagem antes de instalar.';
        }
        if (str_contains($msg, '2002')
            || stripos($msg, 'Connection refused') !== false
            || stripos($msg, 'timed out') !== false
            || stripos($msg, 'No such file or directory') !== false) {
            $hints[] = 'Host incorreto ou MySQL inacessível (em muitas hospedagens o host não é "localhost" — use o valor indicado pelo provedor).';
        }
        if (stripos($msg, 'could not find driver') !== false || stripos($msg, 'pdo_mysql') !== false) {
            $hints[] = 'A extensão PHP pdo_mysql pode estar desabilitada — ative-a no painel ou peça ao provedor.';
        }

        $parts = ['Não foi possível conectar ao banco. Verifique os dados.'];
        if ($hints !== []) {
            $parts[] = implode(' ', array_unique($hints));
        }
        $parts[] = 'Detalhe técnico: ' . $msg;

        return implode("\n\n", $parts);
    }
}
