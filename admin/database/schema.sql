-- Revita CMS — DDL (MySQL). Executado pelo instalador PHP.
-- Gerado a partir do arquivo raiz do projeto; manter em sync com admin_database_init.sql quando alterar.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS revita_crm_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  login VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  level TINYINT UNSIGNED NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_login (login),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_level (level),
  KEY idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS revita_crm_password_resets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  token_expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_password_resets_token_hash (token_hash),
  KEY idx_password_resets_user_id (user_id),
  KEY idx_password_resets_expires_at (token_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS revita_crm_settings (
  settings_key VARCHAR(120) NOT NULL,
  settings_value LONGTEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (settings_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS revita_crm_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS revita_crm_subcategories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subcategories_slug (slug),
  KEY idx_subcategories_category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS revita_crm_media (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  media_type ENUM('image','video','file') NOT NULL,
  relative_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NULL,
  size_bytes BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  uploaded_by INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_media_relative_path (relative_path),
  KEY idx_media_type (media_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS revita_crm_pages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pages_slug (slug),
  KEY idx_pages_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS revita_crm_posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  subcategory_id INT UNSIGNED NOT NULL,
  featured_media_id INT UNSIGNED NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  author_user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_posts_slug (slug),
  KEY idx_posts_status (status),
  KEY idx_posts_category (category_id),
  KEY idx_posts_subcategory (subcategory_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS revita_crm_sections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_type ENUM('page','post') NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  order_index INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_sections_owner (owner_type, owner_id),
  KEY idx_sections_order (owner_type, owner_id, order_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS revita_crm_field_definitions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_type ENUM('page','post') NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  section_id INT UNSIGNED NULL,
  field_key VARCHAR(120) NOT NULL,
  label_name VARCHAR(190) NOT NULL,
  field_type ENUM(
    'texto',
    'botao',
    'icone',
    'foto',
    'galeria_fotos',
    'video',
    'galeria_videos',
    'repetidor'
  ) NOT NULL,
  order_index INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_field_def_owner (owner_type, owner_id, field_key),
  KEY idx_field_def_owner (owner_type, owner_id),
  KEY idx_field_def_section (section_id),
  KEY idx_field_def_type (field_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS revita_crm_repeater_definitions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  field_definition_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_repeater_field_def (field_definition_id),
  KEY idx_repeater_field_def (field_definition_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS revita_crm_repeater_subfield_definitions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  repeater_definition_id INT UNSIGNED NOT NULL,
  field_key VARCHAR(120) NOT NULL,
  label_name VARCHAR(190) NOT NULL,
  field_type ENUM(
    'texto',
    'botao',
    'icone',
    'foto',
    'galeria_fotos',
    'video',
    'galeria_videos'
  ) NOT NULL,
  order_index INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_repeater_subfield (repeater_definition_id, field_key),
  KEY idx_repeater_subfield_rep (repeater_definition_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS revita_crm_repeater_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  repeater_definition_id INT UNSIGNED NOT NULL,
  item_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_repeater_items_rep (repeater_definition_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS revita_crm_field_values (
  field_definition_id INT UNSIGNED NOT NULL,
  value_text LONGTEXT NULL,
  value_url LONGTEXT NULL,
  value_media_ids_json LONGTEXT NULL,
  value_mixed_json LONGTEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (field_definition_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS revita_crm_repeater_item_values (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  repeater_item_id INT UNSIGNED NOT NULL,
  repeater_subfield_definition_id INT UNSIGNED NOT NULL,
  value_text LONGTEXT NULL,
  value_url LONGTEXT NULL,
  value_media_ids_json LONGTEXT NULL,
  value_mixed_json LONGTEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_repeater_item_subfield (repeater_item_id, repeater_subfield_definition_id),
  KEY idx_repeater_item_values_item (repeater_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
