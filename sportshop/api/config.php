<?php
declare(strict_types=1);
// Conexión PDO + utilidades compartidas por todos los endpoints.
header('Content-Type: application/json; charset=utf-8');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=localhost;dbname=sportshop;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // consultas preparadas reales
        ]);
    }
    return $pdo;
}

/** Lee el cuerpo JSON de la petición. */
function input(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

/** Responde JSON y termina. */
function respond(array $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Tallas por marca: límites superiores de pecho/cintura/cadera en cm. */
const TABLAS_TALLAS = [
    'Nike'   => ['S' => [92, 78, 98],  'M' => [100, 86, 104], 'L' => [108, 94, 110], 'XL' => [116, 102, 116]],
    'Adidas' => ['S' => [94, 80, 100], 'M' => [102, 88, 106], 'L' => [110, 96, 112], 'XL' => [118, 104, 118]],
    'Puma'   => ['S' => [90, 76, 96],  'M' => [98, 84, 102],  'L' => [106, 92, 108], 'XL' => [114, 100, 114]],
];

// ---------- Sesiones y control de acceso ----------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']); // cookie no legible por JS
    session_start();
}

function usuarioActual(): ?array { return $_SESSION['user'] ?? null; }

/** Exige haber iniciado sesión (401 si no). */
function requireLogin(): array {
    $u = usuarioActual();
    if (!$u) respond(['error' => 'Inicia sesión para continuar'], 401);
    return $u;
}
/** Exige rol administrador (403 si no lo es). */
function requireAdmin(): array {
    $u = requireLogin();
    if ($u['rol'] !== 'admin') respond(['error' => 'Acceso solo para administradores'], 403);
    return $u;
}
