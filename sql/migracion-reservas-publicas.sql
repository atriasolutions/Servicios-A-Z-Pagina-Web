-- Ejecuta este archivo una sola vez si la base ya fue creada con una
-- versión anterior del proyecto.

ALTER TABLE citas
  ADD COLUMN cliente_nombre VARCHAR(120) NULL AFTER usuario_id,
  ADD COLUMN cliente_email VARCHAR(190) NULL AFTER cliente_nombre,
  ADD COLUMN cliente_telefono VARCHAR(40) NULL AFTER cliente_email;

UPDATE citas AS c
INNER JOIN usuarios AS u ON u.id = c.usuario_id
SET
  c.cliente_nombre = u.nombre,
  c.cliente_email = u.email,
  c.cliente_telefono = u.telefono
WHERE c.cliente_nombre IS NULL;

ALTER TABLE citas
  MODIFY usuario_id INT UNSIGNED NULL,
  MODIFY cliente_nombre VARCHAR(120) NOT NULL,
  MODIFY cliente_email VARCHAR(190) NOT NULL,
  MODIFY cliente_telefono VARCHAR(40) NOT NULL;

UPDATE usuarios
SET email = 'contabilidad@scontablesayz.cl'
WHERE email = 'anfitrion@example.com'
  AND rol = 'anfitrion'
  AND NOT EXISTS (
    SELECT 1
    FROM (SELECT email FROM usuarios) AS existentes
    WHERE existentes.email = 'contabilidad@scontablesayz.cl'
  );

UPDATE usuarios
SET
  nombre = 'Servicios Contables A&Z',
  password_hash = '$2y$10$/mcUTXXEd2R4roWJB2dy4.80syAlPj7/Oev0RvFvdmg77BoOrYdmi',
  rol = 'anfitrion'
WHERE email = 'contabilidad@scontablesayz.cl';

UPDATE usuarios
SET rol = 'cliente'
WHERE email = 'anfitrion@example.com' AND rol = 'anfitrion';
