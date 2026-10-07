-- Cuenta local del anfitrión. No se crea desde el registro público.
--
-- Correo: anfitrion@example.com
-- Contraseña inicial: Anfitrion.local
--
-- Cámbiala después del primer ingreso.
-- Importa este archivo en phpMyAdmin con la base de la agenda ya seleccionada.

INSERT INTO usuarios (nombre, email, password_hash, telefono, rol)
SELECT
  'Servicios Contables A&Z',
  'anfitrion@example.com',
  '$2y$10$/mcUTXXEd2R4roWJB2dy4.80syAlPj7/Oev0RvFvdmg77BoOrYdmi',
  '',
  'anfitrion'
WHERE NOT EXISTS (
  SELECT 1 FROM usuarios WHERE email = 'anfitrion@example.com'
);
