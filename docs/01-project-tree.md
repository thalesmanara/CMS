## Árvore completa de pastas (instalação em `/admin`)

Estrutura sugerida no workspace (o instalador será criado em `admin/`):

```text
/admin
  /app
    /controllers
      /admin        (painel)
      /api          (endpoints JSON)
    /models
    /views
      /layouts
      /admin
    /core
    /helpers

  /config          (configuração gerada pelo instalador; arquivos protegidos)
  /routes
  /uploads
    /images
    /videos
    /files
  /storage         (logs temporários, cache simples etc.)
  /database
    /migrations
  /public          (assets estáticos do admin, se necessário)
  /assets          (CSS/JS do admin)

  index.php
  .htaccess

/.htaccess         (opcional, se você preferir configurar algo no domínio inteiro)

/docs
  00-architecture.md
  01-project-tree.md
  02-db-schema.md

  (outros documentos futuros)
```

### Regras importantes para compatibilidade
- O CMS é acessado em `https://exemplo.com.br/admin`.
- Os endpoints JSON ficam em `https://exemplo.com.br/admin/api/...`.
- Mídias enviadas ficam em `https://exemplo.com.br/admin/uploads/...`.
- Nenhuma dependência pesada (framework) é exigida.

