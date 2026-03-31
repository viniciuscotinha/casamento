ALTER TABLE familias
  ADD COLUMN public_slug VARCHAR(24) DEFAULT NULL AFTER nome_grupo,
  ADD UNIQUE KEY uq_familias_public_slug (public_slug);
