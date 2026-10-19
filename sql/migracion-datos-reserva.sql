-- Ejecuta este archivo una sola vez si la base ya fue creada con una
-- versión anterior del proyecto. Las citas históricas quedan sin tipo
-- de persona porque ese dato no se solicitaba.

ALTER TABLE citas
  ADD COLUMN cliente_apellido VARCHAR(120) NOT NULL DEFAULT '' AFTER cliente_nombre,
  ADD COLUMN cliente_tipo_persona ENUM(
    'persona_natural',
    'empresa',
    'empresa_por_constituir'
  ) NULL AFTER cliente_telefono;
