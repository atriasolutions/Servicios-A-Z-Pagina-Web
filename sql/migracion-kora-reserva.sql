-- Ejecuta este archivo una sola vez si la base ya fue creada.
-- No reimporta schema.sql. Guarda el identificador de la reserva de Kora
-- y deja registrada la prueba del 30 de octubre de 2026.

ALTER TABLE citas
  ADD COLUMN kora_reserva_id CHAR(36) NULL AFTER token_hash,
  ADD UNIQUE KEY uniq_kora_reserva (kora_reserva_id);

INSERT INTO citas (
  cliente_nombre,
  cliente_apellido,
  cliente_email,
  cliente_telefono,
  cliente_tipo_persona,
  fecha,
  hora_inicio,
  servicio,
  estado,
  kora_reserva_id
) VALUES (
  'Prueba',
  'Integracion',
  'prueba-integracion@example.com',
  '+56900000000',
  'persona_natural',
  '2026-10-30',
  '13:00:00',
  'contabilidad',
  'reservada',
  '2de18755-2575-491f-8693-4cea900a9c45'
);
