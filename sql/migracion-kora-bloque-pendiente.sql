-- Ejecuta este archivo una sola vez si la base ya fue creada.
-- Conserva el bloque elegido mientras la solicitud espera aprobación.

ALTER TABLE citas
  ADD COLUMN kora_bloque_id CHAR(36) NULL AFTER token_hash,
  ADD COLUMN hora_fin TIME NULL AFTER hora_inicio,
  ADD KEY idx_kora_bloque (kora_bloque_id);
