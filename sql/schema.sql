-- Tablas de la agenda de citas.
--
-- En cPanel: crea la base en «Bases de datos MySQL», ábrela en phpMyAdmin
-- y después importa este archivo. No incluye CREATE DATABASE porque
-- el usuario de cPanel no puede crear bases desde aquí.
-- Luego importa anfitrion.sql con esa misma base seleccionada.

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  telefono VARCHAR(40) NOT NULL DEFAULT '',
  rol ENUM('anfitrion', 'cliente') NOT NULL DEFAULT 'cliente',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS citas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  fecha DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  estado ENUM('reservada', 'cancelada') NOT NULL DEFAULT 'reservada',
  creada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  activo TINYINT
    AS (IF(estado = 'reservada', 1, NULL)) STORED,
  UNIQUE KEY uniq_cupo (fecha, hora_inicio, activo),
  KEY idx_citas_usuario (usuario_id),
  KEY idx_citas_fecha (fecha, hora_inicio),
  CONSTRAINT fk_citas_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bloqueos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  UNIQUE KEY uniq_bloqueo (fecha, hora_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
