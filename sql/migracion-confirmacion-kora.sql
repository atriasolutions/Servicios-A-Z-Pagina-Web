-- Ejecuta este archivo una sola vez si la base ya fue creada.
-- Registra los webhooks de Kora y deja los correos para un proceso programado.

CREATE TABLE IF NOT EXISTS kora_eventos (
  id VARCHAR(100) PRIMARY KEY,
  tipo VARCHAR(80) NOT NULL,
  kora_reserva_id CHAR(36) NULL,
  estado VARCHAR(40) NULL,
  estado_anterior VARCHAR(40) NULL,
  recibido_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  procesado_en TIMESTAMP NULL DEFAULT NULL,
  KEY idx_kora_eventos_reserva (kora_reserva_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cola_notificaciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cita_id INT UNSIGNED NOT NULL,
  tipo ENUM('confirmacion', 'anulacion') NOT NULL,
  estado ENUM('pendiente', 'procesando', 'enviado') NOT NULL DEFAULT 'pendiente',
  intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  disponible_desde TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  bloqueo_token CHAR(36) NULL,
  bloqueada_en TIMESTAMP NULL DEFAULT NULL,
  ultimo_error VARCHAR(500) NULL,
  creada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  enviada_en TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uniq_notificacion_cita (cita_id, tipo),
  KEY idx_cola_pendiente (estado, disponible_desde),
  CONSTRAINT fk_cola_cita
    FOREIGN KEY (cita_id) REFERENCES citas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
