<?php
// Crea o restablece el administrador. Solo funciona desde localhost. BORRAR después de usarlo.
require __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit('Solo desde localhost'); }

$email = 'admin@sportshop.com';
$clave = 'Admin2026!';
try {
    $hash = password_hash($clave, PASSWORD_DEFAULT);       // hash generado por el propio PHP del servidor
    $st = db()->prepare('SELECT id FROM usuarios WHERE email = :e');
    $st->execute(['e' => $email]);
    if ($st->fetch()) {
        db()->prepare("UPDATE usuarios SET password_hash = :h, rol = 'admin' WHERE email = :e")->execute(['h' => $hash, 'e' => $email]);
        echo "Administrador RESTABLECIDO.\n";
    } else {
        db()->prepare("INSERT INTO usuarios (nombre,email,password_hash,rol) VALUES ('Administrador',:e,:h,'admin')")->execute(['e' => $email, 'h' => $hash]);
        echo "Administrador CREADO.\n";
    }
    echo "Correo: $email\nContraseña: $clave\n\nAhora ingresa en login.html y BORRA este archivo (reset_admin.php).\n";
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n→ Primero ejecuta sql/instalar_todo.sql en phpMyAdmin y enciende MySQL.\n";
}
