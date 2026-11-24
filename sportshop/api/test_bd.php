<?php
// Diagnóstico: http://localhost/sportshop/api/test_bd.php   (BORRAR antes de publicar)
require __DIR__ . '/config.php';                       // usa las mismas credenciales que la app
header('Content-Type: text/plain; charset=utf-8');
echo "DIAGNÓSTICO SPORTSHOP\n=====================\n";
echo 'PHP ' . PHP_VERSION . (PHP_VERSION_ID >= 80100 ? " OK\n" : " → NECESITA 8.1 O SUPERIOR\n");
echo 'Extensión pdo_mysql: ' . (extension_loaded('pdo_mysql') ? "OK\n" : "FALTA (activarla en php.ini)\n");
echo 'Sesiones PHP: ' . (session_status() === PHP_SESSION_ACTIVE ? "OK\n" : "FALLAN (revisa session.save_path en php.ini)\n");

try { $pdo = db(); echo "Conexión y base 'sportshop': OK\n"; }
catch (Throwable $e) { exit("Conexión/base: FALLA → " . $e->getMessage() . "\n→ Inicia MySQL en XAMPP, ejecuta sql/instalar_todo.sql o revisa la contraseña en config.php\n"); }

foreach (['usuarios', 'productos', 'pedidos', 'devoluciones'] as $t)
    echo "Tabla $t: " . ($pdo->query("SHOW TABLES LIKE '$t'")->fetch() ? "OK\n" : "FALTA → ejecuta sql/instalar_todo.sql\n");

$u = $pdo->query("SELECT password_hash, rol FROM usuarios WHERE email='admin@sportshop.com'")->fetch();
if (!$u) echo "Administrador: NO EXISTE → abre api/reset_admin.php\n";
else {
    echo "Administrador: existe (rol {$u['rol']})\n";
    echo "Contraseña Admin2026!: " . (password_verify('Admin2026!', $u['password_hash']) ? "OK\n" : "NO COINCIDE → abre api/reset_admin.php\n");
}
echo "\nSi todo dice OK, el ingreso y el registro deberían funcionar.\n";
