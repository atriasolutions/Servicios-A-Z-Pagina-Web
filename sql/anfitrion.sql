-- Cuenta local del anfitrión. No se crea desde el registro público.
--
-- Correo: mmb21894@gmail.com
-- La contraseña se guarda solo como hash.
-- Importa este archivo en phpMyAdmin con la base de la agenda ya seleccionada.

INSERT INTO usuarios (nombre, email, password_hash, telefono, rol)
SELECT
  'Servicios Contables A&Z',
  'mmb21894@gmail.com',
  '$2y$10$l9Ew59M80TLttgIFdWsCR.Bs8nyIyoJ.i09X3qL.4z0ubVhGNum9G',
  '',
  'anfitrion'
WHERE NOT EXISTS (
  SELECT 1 FROM usuarios WHERE email = 'mmb21894@gmail.com'
);
