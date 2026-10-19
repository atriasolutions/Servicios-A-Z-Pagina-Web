-- Ejecuta este archivo una sola vez si la base ya fue creada
-- antes de existir la ventanilla de chat.

CREATE TABLE IF NOT EXISTS conversaciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  tema VARCHAR(40) NOT NULL,
  creada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_conversacion_token (token_hash),
  KEY idx_conversaciones_actualizada (actualizada_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensajes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversacion_id INT UNSIGNED NOT NULL,
  autor ENUM('visita', 'anfitrion') NOT NULL,
  texto VARCHAR(1000) NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  leido_en TIMESTAMP NULL DEFAULT NULL,
  KEY idx_mensajes_conversacion (conversacion_id, id),
  CONSTRAINT fk_mensajes_conversacion
    FOREIGN KEY (conversacion_id) REFERENCES conversaciones (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
