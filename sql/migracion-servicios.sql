-- Ejecuta este archivo una sola vez si la base ya fue creada
-- con los tres servicios anteriores.

ALTER TABLE citas
  MODIFY servicio ENUM(
    'gestion_tributaria',
    'contabilidad_financiera',
    'gestion_remuneraciones',
    'constitucion_empresas',
    'contabilidad',
    'asesoria_laboral',
    'operacion_renta'
  ) NULL;
