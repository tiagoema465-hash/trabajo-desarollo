<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

// CRUD de productos. GET admite filtros ?deporte=&marca=
try {
    $pdo = db();
    switch ($_SERVER['REQUEST_METHOD']) {

        case 'GET': // READ + filtrado
            $sql = 'SELECT id,nombre,deporte,marca,precio,stock,imagen_url FROM productos WHERE activo=1';
            $params = [];
            foreach (['deporte', 'marca'] as $f) {
                if (!empty($_GET[$f])) { $sql .= " AND $f = :$f"; $params[$f] = $_GET[$f]; }
            }
            $st = $pdo->prepare($sql . ' ORDER BY id DESC LIMIT 50');
            $st->execute($params);
            respond($st->fetchAll());

        case 'POST': // CREATE (solo admin)
            requireAdmin();
            $d = input();
            $err = validarProducto($d);
            if ($err) respond(['error' => $err], 422);
            $st = $pdo->prepare('INSERT INTO productos (nombre,deporte,marca,precio,stock,imagen_url)
                                 VALUES (:nombre,:deporte,:marca,:precio,:stock,:imagen_url)');
            $st->execute(array_intersect_key($d, array_flip(['nombre','deporte','marca','precio','stock','imagen_url'])));
            respond(['id' => (int)$pdo->lastInsertId()], 201);

        case 'PUT': // UPDATE (solo admin)
            requireAdmin();
            $d = input();
            if (!is_numeric($d['precio'] ?? null) || $d['precio'] < 0 || !ctype_digit((string)($d['stock'] ?? '')))
                respond(['error' => 'Precio o stock inválido: no pueden ser negativos'], 422);
            $st = $pdo->prepare('UPDATE productos SET precio=:precio, stock=:stock WHERE id=:id');
            $st->execute(['precio' => $d['precio'] ?? 0, 'stock' => $d['stock'] ?? 0, 'id' => $d['id'] ?? 0]);
            respond(['actualizado' => $st->rowCount()]);

        case 'DELETE': // baja lógica (solo admin)
            requireAdmin();
            $st = $pdo->prepare('UPDATE productos SET activo=0 WHERE id=:id');
            $st->execute(['id' => (int)($_GET['id'] ?? 0)]);
            respond(['eliminado' => $st->rowCount()]);

        default: respond(['error' => 'Método no permitido'], 405);
    }
} catch (Throwable $e) {
    respond(['error' => 'Error del servidor'], 500); // nunca exponer detalles al cliente
}

/** Validación del lado servidor (nunca confiar solo en el JS). */
function validarProducto(array $d): ?string {
    foreach (['nombre','deporte','marca','imagen_url'] as $c)
        if (empty(trim((string)($d[$c] ?? '')))) return "El campo $c es obligatorio";
    $img = trim((string)$d['imagen_url']);
    if (str_contains($img, '..') || !preg_match('#^(https?://\S+|img/[\w./-]+\.(webp|jpe?g|png|avif))$#i', $img))
        return 'Imagen inválida: usa img/productos/archivo.webp o una URL https';
    if (!is_numeric($d['precio'] ?? null) || $d['precio'] < 0) return 'El precio no puede ser menor a 0';
    if (!ctype_digit((string)($d['stock'] ?? ''))) return 'El stock debe ser un entero no menor a 0';
    return null;
}
