CREATE TABLE convites_padrinhos (
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

ALTER TABLE convidados
  ADD COLUMN manual_invite_id BIGINT UNSIGNED DEFAULT NULL AFTER familia_id,
  ADD KEY idx_convidados_manual_invite (manual_invite_id),
  ADD CONSTRAINT fk_convidados_manual_invite
    FOREIGN KEY (manual_invite_id)
    REFERENCES convites_padrinhos (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL;

ALTER TABLE auditoria
  MODIFY COLUMN entidade ENUM('familia', 'convidado', 'manual_invite', 'admin_user', 'login') NOT NULL;
