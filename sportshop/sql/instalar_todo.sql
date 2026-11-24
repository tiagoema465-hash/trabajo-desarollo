-- INSTALADOR COMPLETO (se puede ejecutar varias veces sin romper nada)
-- phpMyAdmin → pestaña SQL → pegar todo → Continuar
-- SportShop · MySQL 8 · 4 entidades
-- Relación principal: usuarios 1 ── N pedidos 1 ── N devoluciones ; productos 1 ── N pedidos
CREATE DATABASE IF NOT EXISTS sportshop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sportshop;

CREATE TABLE IF NOT EXISTS usuarios (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(80)  NOT NULL,
  email         VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol           ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
  creado_en     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS productos (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre     VARCHAR(120) NOT NULL,
  deporte    VARCHAR(40)  NOT NULL,           -- fútbol, running, tenis...
  marca      VARCHAR(40)  NOT NULL,           -- Nike, Adidas...
  precio     DECIMAL(10,2) NOT NULL CHECK (precio >= 0),
  stock      INT UNSIGNED NOT NULL DEFAULT 0,
  imagen_url VARCHAR(255) NOT NULL,           -- WebP/AVIF ya optimizado
  activo     TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_filtro (deporte, marca)           -- acelera el filtrado
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedidos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NOT NULL,                      -- FK
  producto_id INT UNSIGNED NOT NULL,                      -- FK
  talla       VARCHAR(5)   NOT NULL,
  cantidad    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  total       DECIMAL(10,2) NOT NULL,
  metodo_pago ENUM('tarjeta','transferencia','billetera_digital') NOT NULL,
  estado      ENUM('pendiente','pagado','enviado','entregado') NOT NULL DEFAULT 'pendiente',
  creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS devoluciones (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pedido_id  INT UNSIGNED NOT NULL,                       -- FK
  tipo       ENUM('cambio_prenda','cambio_talla','reembolso') NOT NULL,
  motivo     VARCHAR(500) NOT NULL,
  talla_nueva VARCHAR(5) NULL,
  estado     ENUM('solicitada','aprobada','rechazada') NOT NULL DEFAULT 'solicitada',
  creado_en  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
-- Wishlist: en el MVP vive en localStorage del navegador (sin tabla extra).
-- Tablas de tallas por marca: viven en api/config.php (límite de 4 entidades).

-- Ejecutar UNA vez en phpMyAdmin (base sportshop, pestaña SQL).
-- Crea el administrador inicial:  admin@sportshop.com  /  Admin2026!   (cámbiala después)
INSERT IGNORE INTO usuarios (nombre, email, password_hash, rol)
VALUES ('Administrador', 'admin@sportshop.com', '$6$mqQgx9VAeEJnfa2D$EmEkwT3fHtkb8bc2XdqdSu7alAaMgZz5KQt/QEWfOso4kV5ftuH6e8vPiSg2HrTVRS.JW9PQHC6lHRjuxoJZ7.', 'admin');
