## Modelagem do Banco de Dados (MySQL)

### Premissas
- Hospedagem compartilhada comum: PHP + Apache + MySQL.
- Banco é criado manualmente antes da instalação (tabelas serão criadas pelo CMS).
- Prefixo de tabelas: `revita_crm_` (evita conflitos e facilita manutenção).

### Usuários e permissões
- `users.level` define o nível:
  - `1` Administrador (acesso total)
  - `2` Editor (apenas páginas/postagens)
- Sessão controla acesso às rotas do painel/API.

### Conteúdo (páginas e postagens)
- `pages` e `posts` têm campos “fixos” (título/slug/status e, para posts, categoria/subcategoria/capa).
- Conteúdo “dinâmico” é modelado por:
  - `field_definitions` (estrutura do campo por página/post)
  - `field_values` (valor por campo)
- `repeater_*` implementa listas repetíveis (arrays de objetos) com ordenação.

### Mídias
- `media` armazena metadados e `relative_path`.
- A API retorna URLs absolutas usando `relative_path`.

### `value_mixed_json` para vídeo (upload / YouTube)
- Campos `video` e `galeria_videos` **não** usam URL genérica de provedor: apenas **upload** (via `media_id`) ou **YouTube**.
- O JSON fica em `revita_crm_field_values.value_mixed_json` (páginas/posts) e, em repetidores, em `revita_crm_repeater_item_values.value_mixed_json`.
- Detalhe do contrato e exemplos: ver `docs/00-architecture.md` → seção *Vídeo*.

---

### Tabelas
1. `revita_crm_users`
2. `revita_crm_password_resets`
3. `revita_crm_settings`
4. `revita_crm_categories`
5. `revita_crm_subcategories`
6. `revita_crm_media`
7. `revita_crm_pages`
8. `revita_crm_posts`
9. `revita_crm_field_definitions`
10. `revita_crm_field_values`
11. `revita_crm_repeater_definitions`
12. `revita_crm_repeater_subfield_definitions`
13. `revita_crm_repeater_items`
14. `revita_crm_repeater_item_values`

---

### Observações de escalabilidade/performance
- Índices em `slug`, `owner_type + owner_id` e chaves estrangeiras mais comuns.
- `field_values` usa `field_definition_id` como PK/UNIQUE (1 valor por definição).
- Ordenação é preservada com campos `order_index`/`item_order`.

