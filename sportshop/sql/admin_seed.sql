-- Ejecutar UNA vez en phpMyAdmin (base sportshop, pestaña SQL).
-- Crea el administrador inicial:  admin@sportshop.com  /  Admin2026!   (cámbiala después)
USE sportshop;
INSERT INTO usuarios (nombre, email, password_hash, rol)
VALUES ('Administrador', 'admin@sportshop.com', '$6$mqQgx9VAeEJnfa2D$EmEkwT3fHtkb8bc2XdqdSu7alAaMgZz5KQt/QEWfOso4kV5ftuH6e8vPiSg2HrTVRS.JW9PQHC6lHRjuxoJZ7.', 'admin');
