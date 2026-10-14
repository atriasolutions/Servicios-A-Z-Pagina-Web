-- Ejecuta este archivo una sola vez si la base ya fue creada
-- antes de existir la página de artículos.

CREATE TABLE IF NOT EXISTS articulos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(160) NOT NULL,
  cuerpo TEXT NOT NULL,
  publicado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
