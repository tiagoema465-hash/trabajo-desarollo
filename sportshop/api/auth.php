<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

// Endpoint de autenticación: ?accion=registro | login | salir | yo
$accion = $_GET['accion'] ?? '';
$post   = $_SERVER['REQUEST_METHOD'] === 'POST';

/** Guarda al usuario en sesión (sin la contraseña) y renueva el ID de sesión. */
function iniciarSesion(array $u): void {
    session_regenerate_id(true);   // evita fijación de sesión
    $_SESSION['user'] = ['id' => (int)$u['id'], 'nombre' => $u['nombre'], 'rol' => $u['rol']];
}

try {
    switch ($accion) {

        case 'registro': // Los clientes se registran aquí; el rol SIEMPRE es 'cliente'
            if (!$post) respond(['error' => 'Método no permitido'], 405);
            $d = input();
            $nombre = trim((string)($d['nombre'] ?? ''));
            $email  = strtolower(trim((string)($d['email'] ?? '')));
            $clave  = (string)($d['clave'] ?? '');

            if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 80)      respond(['error' => 'Nombre inválido'], 422);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))             respond(['error' => 'Correo no válido'], 422);
            if (strlen($clave) < 8 || !preg_match('/[A-Za-z]/', $clave) || !preg_match('/\d/', $clave))
                respond(['error' => 'La contraseña necesita 8+ caracteres, con letras y números'], 422);

            $st = db()->prepare('SELECT id FROM usuarios WHERE email = :e');
            $st->execute(['e' => $email]);
            if ($st->fetch()) respond(['error' => 'Ese correo ya tiene una cuenta'], 409);

            $st = db()->prepare("INSERT INTO usuarios (nombre,email,password_hash,rol) VALUES (:n,:e,:h,'cliente')");
            $st->execute(['n' => $nombre, 'e' => $email, 'h' => password_hash($clave, PASSWORD_DEFAULT)]);
            iniciarSesion(['id' => db()->lastInsertId(), 'nombre' => $nombre, 'rol' => 'cliente']);
            respond(['ok' => true, 'rol' => 'cliente'], 201);

        case 'login':
            if (!$post) respond(['error' => 'Método no permitido'], 405);
            // Freno básico a fuerza bruta: 5 fallos = 60 s de bloqueo
            if (($_SESSION['bloqueo'] ?? 0) > time()) respond(['error' => 'Demasiados intentos. Espera un minuto.'], 429);

            $d  = input();
            $st = db()->prepare('SELECT id,nombre,password_hash,rol FROM usuarios WHERE email = :e');
            $st->execute(['e' => strtolower(trim((string)($d['email'] ?? '')))]);
            $u = $st->fetch();

            if (!$u || !password_verify((string)($d['clave'] ?? ''), $u['password_hash'])) {
                $_SESSION['fallos'] = ($_SESSION['fallos'] ?? 0) + 1;
                if ($_SESSION['fallos'] >= 5) { $_SESSION['bloqueo'] = time() + 60; $_SESSION['fallos'] = 0; }
                respond(['error' => 'Correo o contraseña incorrectos'], 401); // mensaje genérico a propósito
            }
            $_SESSION['fallos'] = 0;
            iniciarSesion($u);
            respond(['ok' => true, 'rol' => $u['rol']]);

        case 'salir':
            $_SESSION = [];
            session_destroy();
            respond(['ok' => true]);

        case 'yo': // ¿quién está logueado?
            respond(['user' => usuarioActual()]);

        default: respond(['error' => 'Acción desconocida'], 404);
    }
} catch (Throwable $e) {
    respond(['error' => 'Error del servidor'], 500);
}
