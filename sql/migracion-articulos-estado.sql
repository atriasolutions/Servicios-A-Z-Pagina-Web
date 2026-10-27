-- Ejecuta este archivo una sola vez si la base ya fue creada
-- antes de distinguir borradores de artículos publicados.
-- Los artículos que ya existen quedan publicados.

ALTER TABLE articulos
  ADD COLUMN estado ENUM('borrador', 'publicado') NOT NULL DEFAULT 'publicado' AFTER cuerpo;
