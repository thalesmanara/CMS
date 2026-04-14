## Instalação — Revita CMS (`/admin`)

### Requisitos
- PHP **8.1 ou superior** (PHP 7.4 pode causar erro **500**), extensão **pdo_mysql**
- MySQL 5.7+ ou 8.x
- Apache com **mod_rewrite** (para o arquivo `admin/.htaccess`)
- Banco de dados **criado manualmente** na hospedagem (nome, usuário e senha)

### Estrutura no servidor (padrão do projeto)
1. Envie a pasta **`admin/` completa** para **`public_html/admin/`** no domínio.
2. URL do painel: **`https://seusite.com.br/admin`**
3. Confirme que o logo existe em `admin/assets/img/logoRevita.png` (ou substitua pelo arquivo oficial).
4. Garanta permissões de escrita em:
   - `admin/config/` (para gerar `app.php`)
   - `admin/uploads/` e subpastas (`images`, `videos`, `files`)

### Primeiro acesso
1. Acesse `https://seusite.com.br/admin`.
2. Se ainda **não** existir `admin/config/app.php`, o sistema redireciona para o **instalador** (`/admin/install`).
3. Preencha host, nome do banco, usuário e senha do MySQL e envie o formulário.
4. O instalador:
   - testa a conexão
   - executa o DDL em `admin/database/schema.sql`
   - cria o **usuário mestre** (somente se ainda não existir com o mesmo login)
   - grava `admin/config/app.php` com `installed => true`
5. Após concluir, o instalador **não fica mais acessível** (retorno HTTP **403** em `/install`).

### Usuário mestre (padrão)
| Campo   | Valor              |
|---------|--------------------|
| Login   | `revitacomunicacao` |
| Senha   | `RevitaCRM@#`       |
| Nível   | 1 (administrador)   |

Troque a senha após o primeiro login quando possível.

### Alteração de banco após instalação
Conforme especificação do projeto, **não** há tela no painel para alterar credenciais do MySQL. Qualquer mudança deve ser feita **editando manualmente** `admin/config/app.php` no servidor (ou removendo o arquivo **apenas** em ambiente controlado e reexecutando a instalação — em produção, prefira editar o arquivo).

### `.htaccess` e `/install` (404)
- O arquivo `admin/.htaccess` usa **`RewriteBase /admin/`**, alinhado ao deploy em **`https://dominio/admin`**
- O roteamento amigável depende de **`mod_rewrite`** e de **`AllowOverride`** permitir `.htaccess` em `public_html`.
- Se `/admin/install` retornar **404**:
  1. Confirme `public_html/admin/index.php` e `public_html/admin/.htaccess`.
  2. Teste `https://dominio/admin/index.php` — se também der 404, o upload/caminho está incorreto.

### Fora do padrão `/admin` (opcional)
Se o painel for colocado em outra URL (ex. subpasta extra no domínio), será necessário ajustar **`RewriteBase`** no `admin/.htaccess` para coincidir com o caminho público da pasta (este repositório assume **`/admin`**).

### Desenvolvimento local sem Apache
O roteamento depende do `.htaccess`. Em ambiente local, use **Apache** com rewrites ou o equivalente. O servidor embutido do PHP (`php -S`) não aplica `.htaccess` automaticamente.

### Recuperação de senha
- O fluxo **Esqueci minha senha** grava token em `revita_crm_password_resets` e envia link por **`mail()`** do PHP.
- O recebimento depende da hospedagem (SPF, remetente, função `mail` habilitada). Se o e-mail não chegar, verifique logs do servidor ou substitua `Revita\Crm\Helpers\Mail` por SMTP nas próximas evoluções.

### Segurança
- `admin/config/` e `admin/storage/` possuem `.htaccess` com `Require all denied`.
- Não versionar `admin/config/app.php` (já listado no `.gitignore` na raiz do projeto).
