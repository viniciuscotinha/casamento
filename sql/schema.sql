-- =========================================================
-- RSVP Casamento
-- Schema inicial para HostGator + MySQL/MariaDB
-- Importar este arquivo no banco criado via cPanel/phpMyAdmin
-- =========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS familias (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome_grupo VARCHAR(150) NOT NULL,
  public_slug VARCHAR(24) DEFAULT NULL,
  token_hash CHAR(64) NOT NULL,
  token_encrypted VARCHAR(255) DEFAULT NULL,
  token_preview VARCHAR(12) DEFAULT NULL,
  token_ativo TINYINT(1) NOT NULL DEFAULT 1,
  observacoes TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_familias_public_slug (public_slug),
  UNIQUE KEY uq_familias_token_hash (token_hash),
  KEY idx_familias_nome_grupo (nome_grupo),
  KEY idx_familias_token_ativo (token_ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(120) DEFAULT NULL,
  login VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME DEFAULT NULL,
  last_login_ip VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_users_login (login),
  KEY idx_admin_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS convites_padrinhos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome_grupo VARCHAR(150) NOT NULL,
  public_slug VARCHAR(24) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  manual_question_text VARCHAR(190) DEFAULT NULL,
  manual_intro_title VARCHAR(120) DEFAULT NULL,
  manual_intro_line_1 TEXT DEFAULT NULL,
  manual_intro_line_2 TEXT DEFAULT NULL,
  manual_calendar_title VARCHAR(120) DEFAULT NULL,
  manual_calendar_month_label VARCHAR(60) DEFAULT NULL,
  manual_day_title VARCHAR(120) DEFAULT NULL,
  manual_day_line_1 TEXT DEFAULT NULL,
  manual_day_line_2 TEXT DEFAULT NULL,
  manual_day_line_3 TEXT DEFAULT NULL,
  manual_thanks_text VARCHAR(255) DEFAULT NULL,
  observacoes TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_convites_padrinhos_public_slug (public_slug),
  KEY idx_convites_padrinhos_nome_grupo (nome_grupo),
  KEY idx_convites_padrinhos_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS convidados (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  familia_id BIGINT UNSIGNED NOT NULL,
  manual_invite_id BIGINT UNSIGNED DEFAULT NULL,
  responsavel_id BIGINT UNSIGNED DEFAULT NULL,
  nome VARCHAR(80) NOT NULL,
  sobrenome VARCHAR(120) DEFAULT NULL,
  telefone VARCHAR(25) DEFAULT NULL,
  email VARCHAR(190) DEFAULT NULL,
  manual_role_title VARCHAR(80) DEFAULT NULL,
  manual_role_text TEXT DEFAULT NULL,
  manual_sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  is_responsavel TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('pendente', 'confirmado', 'nao_ira') NOT NULL DEFAULT 'pendente',
  responded_at DATETIME DEFAULT NULL,
  updated_by_origin ENUM('admin', 'public', 'system') NOT NULL DEFAULT 'admin',
  observacoes TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_convidados_id_familia (id, familia_id),
  KEY idx_convidados_familia (familia_id),
  KEY idx_convidados_manual_invite (manual_invite_id),
  KEY idx_convidados_responsavel (responsavel_id),
  KEY idx_convidados_status (status),
  KEY idx_convidados_familia_status (familia_id, status),
  KEY idx_convidados_deleted_at (deleted_at),
  CONSTRAINT fk_convidados_familia
    FOREIGN KEY (familia_id)
    REFERENCES familias (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_convidados_manual_invite
    FOREIGN KEY (manual_invite_id)
    REFERENCES convites_padrinhos (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_convidados_responsavel_mesma_familia
    FOREIGN KEY (responsavel_id, familia_id)
    REFERENCES convidados (id, familia_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_user_id BIGINT UNSIGNED DEFAULT NULL,
  origem ENUM('admin', 'public', 'system') NOT NULL,
  acao VARCHAR(60) NOT NULL,
  entidade ENUM('familia', 'convidado', 'manual_invite', 'admin_user', 'login') NOT NULL,
  entidade_id BIGINT UNSIGNED DEFAULT NULL,
  familia_id BIGINT UNSIGNED DEFAULT NULL,
  convidado_id BIGINT UNSIGNED DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  payload_text TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_auditoria_admin_user (admin_user_id),
  KEY idx_auditoria_origem (origem),
  KEY idx_auditoria_entidade (entidade, entidade_id),
  KEY idx_auditoria_familia (familia_id),
  KEY idx_auditoria_convidado (convidado_id),
  KEY idx_auditoria_created_at (created_at),
  CONSTRAINT fk_auditoria_admin_user
    FOREIGN KEY (admin_user_id)
    REFERENCES admin_users (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_auditoria_familia
    FOREIGN KEY (familia_id)
    REFERENCES familias (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_auditoria_convidado
    FOREIGN KEY (convidado_id)
    REFERENCES convidados (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  login VARCHAR(100) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  was_success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_login_attempts_login_ip_time (login, ip_address, attempted_at),
  KEY idx_admin_login_attempts_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- Regras praticas para a aplicacao
-- =========================================================
-- 1. O QR Code pode apontar para /c/<slug-curto> ou, em legado, /c/<token>.
-- 2. O slug publico das familias deve ser curto, unico e editavel pelo admin.
-- 3. O banco deve guardar o hash do token legado para compatibilidade e,
--    opcionalmente, a versao criptografada para o admin conseguir copiar
--    ou exportar o link antigo quando precisar.
--    Nunca guarde o token puro em texto aberto.
-- 4. Recomenda-se token aleatorio com pelo menos 16 caracteres.
-- 5. O convidado responsavel deve ter:
--      is_responsavel = 1
--      responsavel_id = NULL
-- 6. Os demais convidados da familia devem apontar para o representante em responsavel_id.
-- 7. Exclusao de convidado deve ser logica, preenchendo deleted_at.
-- 8. Ao regenerar o token legado, gere novo token, atualize token_hash e invalide o anterior.
-- 9. O convite de padrinhos usa a tabela convites_padrinhos como base.
-- 10. Cada convite de padrinhos pode reunir 1 pessoa sozinha ou 2 pessoas como casal,
--     mesmo que os convidados pertençam a familias diferentes no RSVP.
-- 11. Os textos compartilhados do manual ficam em convites_padrinhos e
--     os textos individuais de card ficam em convidados.
-- =========================================================
