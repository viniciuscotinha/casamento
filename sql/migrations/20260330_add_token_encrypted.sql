ALTER TABLE familias
  ADD COLUMN token_encrypted VARCHAR(255) DEFAULT NULL AFTER token_hash;
