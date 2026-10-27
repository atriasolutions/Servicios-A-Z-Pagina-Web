-- Instalación inicial de Servicios Contables A&Z.
-- En phpMyAdmin selecciona la base csc119796_AYZdb e importa este archivo una sola vez.
-- No lo vuelvas a importar si la base ya tiene citas o artículos.
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
  usuario_id INT UNSIGNED NULL,
  cliente_nombre VARCHAR(120) NOT NULL,
  cliente_apellido VARCHAR(120) NOT NULL DEFAULT '',
  cliente_email VARCHAR(190) NOT NULL,
  cliente_telefono VARCHAR(40) NOT NULL,
  cliente_tipo_persona ENUM(
    'persona_natural',
    'empresa',
    'empresa_por_constituir'
  ) NULL,
  fecha DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NULL,
  servicio ENUM(
    'gestion_tributaria',
    'contabilidad_financiera',
    'gestion_remuneraciones',
    'constitucion_empresas',
    'contabilidad',
    'asesoria_laboral',
    'operacion_renta'
  ) NULL,
  estado ENUM('pendiente', 'reservada', 'cancelada') NOT NULL DEFAULT 'reservada',
  token_hash CHAR(64) NULL,
  kora_bloque_id CHAR(36) NULL,
  kora_reserva_id CHAR(36) NULL,
  creada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  activo TINYINT
    AS (IF(estado IN ('pendiente', 'reservada'), 1, NULL)) STORED,
  UNIQUE KEY uniq_cupo (fecha, hora_inicio, activo),
  UNIQUE KEY uniq_kora_reserva (kora_reserva_id),
  KEY idx_kora_bloque (kora_bloque_id),
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

CREATE TABLE IF NOT EXISTS articulos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(160) NOT NULL,
  cuerpo TEXT NOT NULL,
  estado ENUM('borrador', 'publicado') NOT NULL DEFAULT 'publicado',
  publicado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Cuenta local del anfitrión. No se crea desde el registro público.
--
-- Correo: contabilidad@scontablesayz.cl
-- La contraseña se guarda solo como hash.
-- Importa este archivo en phpMyAdmin con la base de la agenda ya seleccionada.

INSERT INTO usuarios (nombre, email, password_hash, telefono, rol)
SELECT
  'Servicios Contables A&Z',
  'contabilidad@scontablesayz.cl',
  '$2y$10$l9Ew59M80TLttgIFdWsCR.Bs8nyIyoJ.i09X3qL.4z0ubVhGNum9G',
  '',
  'anfitrion'
WHERE NOT EXISTS (
  SELECT 1 FROM usuarios WHERE email = 'contabilidad@scontablesayz.cl'
);

