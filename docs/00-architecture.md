## Arquitetura do CMS Revita (PHP + MySQL, sem framework pesado)

### Objetivos
- Hospedagem compartilhada (PHP, Apache, `.htaccess`, `public_html`, MySQL).
- Estrutura simples, profissional e fácil de manter.
- MVC leve (sem Laravel/Symfony).
- Painel administrativo em HTML/CSS/JS.
- API REST em JSON para consumo no frontend (URLs completas das mídias).
- Campos dinâmicos com tipos: `texto`, `foto`, `galeria_fotos`, `vídeo`, `galeria_videos`, `repetidor`.

### Estratégia de rotas
- Um único *front controller* dentro de `admin/index.php`.
- O `Router` interpreta o path solicitado e despacha:
  - rotas *web* (painel: login, dashboard, CRUDs)
  - rotas *api* (prefixo `/admin/api/...`)

### Camadas (MVC leve)
- `admin/app/core`
  - bootstrap do app (carrega config, inicia sessão, cria dependências)
  - `Router` (mapeia rotas -> controllers)
  - `Request`/`Response` (helpers para entrada e saída)
  - `Database` (PDO com prepared statements)
  - `Auth` (sessão + usuário + validações)
  - `Csrf` (tokens para formulários)
  - middlewares (ex.: `RequireAuth`, `RequireLevel`)
- `admin/app/controllers`
  - `admin/`: controllers do painel
  - `api/`: controllers da API JSON
- `admin/app/models`
  - objetos de acesso a dados (DAO simples)
  - consultas via PDO e regras de mapeamento
- `admin/app/views`
  - templates do painel
  - layout base, forms e tabelas
- `admin/app/helpers`
  - `Slugger`, `HtmlEscape`, `FileUpload`, `MediaUrl`, `Json`

### Organização de mídia
- Mídias ficam em `admin/uploads/`:
  - `admin/uploads/images`
  - `admin/uploads/videos`
  - `admin/uploads/files`
- A tabela `media` armazena `relative_path` (ex.: `uploads/images/nome-final.jpg`).
- A API devolve URLs absolutas:
  - `https://dominio.com/admin/` + `relative_path`

### Campos dinâmicos (modelo de dados)
- O usuário cria *definições de campos* para páginas/postagens (nome interno, identificador/slug do campo, tipo, ordem).
- Os *valores* dessas definições são persistidos separadamente.
- Para `repetidor`, existem:
  - definição do repetidor (ligada a um campo)
  - definição dos subcampos
  - itens do repetidor (ordem)
  - valores por item/ subcampo

### Vídeo: apenas upload ou YouTube (`value_mixed_json`)
Para os tipos `video` e `galeria_videos`, as origens são **somente**:
- arquivo enviado pelo usuário (registrado em `media`, tipo `video`), ou
- **YouTube** (link ou ID do vídeo informado no painel).

O valor é armazenado em `value_mixed_json` (ou `value_mixed_json` dentro de `revita_crm_repeater_item_values` para subcampos de vídeo). Contrato sugerido:

- Um único vídeo (`field_type = video`):
  - upload: `{ "source": "upload", "media_id": <int> }`
  - YouTube: `{ "source": "youtube", "youtube_url": "<url completa ou youtu.be/...>" }` ou `{ "source": "youtube", "youtube_id": "<id>" }` (o backend valida e pode normalizar para ID na API).

- Galeria de vídeos (`field_type = galeria_videos`):
  - array do mesmo formato: `[ { "source": "upload", "media_id": 1 }, { "source": "youtube", "youtube_id": "..." }, ... ]`

Na resposta da API, o *serializer* converte `media_id` em URL absoluta do arquivo e YouTube em dados úteis ao frontend (ex.: `embed_url`, `watch_url`, `youtube_id`), mantendo o JSON previsível para o site.

### Segurança mínima prevista
- Prepared statements em toda persistência (PDO).
- Proteção de sessão (cookie flags) + logout.
- Controle de acesso por nível:
  - nível `1`: tudo
  - nível `2`: cria/edita páginas e postagens (sem usuários/config/gestões sensíveis)
- CSRF token nos formulários do painel.
- Upload seguro:
  - validação de extensão
  - validação de MIME (quando possível)
  - renomeação segura
  - criação de subpastas por tipo

