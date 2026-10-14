-- Ejecuta este archivo una sola vez si la base ya fue creada
-- antes de confirmar las reservas desde el correo.

ALTER TABLE citas
  MODIFY estado ENUM('pendiente', 'reservada', 'cancelada') NOT NULL DEFAULT 'reservada';

ALTER TABLE citas
  ADD COLUMN token_hash CHAR(64) NULL AFTER estado;

ALTER TABLE citas DROP INDEX uniq_cupo;
ALTER TABLE citas DROP COLUMN activo;

ALTER TABLE citas
  ADD COLUMN activo TINYINT
    AS (IF(estado IN ('pendiente', 'reservada'), 1, NULL)) STORED,
  ADD UNIQUE KEY uniq_cupo (fecha, hora_inicio, activo);
