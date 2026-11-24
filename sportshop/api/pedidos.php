<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

// GET  → (solo admin) todos los pedidos, con el usuario que los hizo.
// POST → (cliente con sesión) convierte el carrito en pedidos.
$pdo = null;
try {
    $pdo = db();
    switch ($_SERVER['REQUEST_METHOD']) {

        case 'GET':
            requireAdmin();
            // JOIN: cada pedido trae los datos de su usuario (N:1) y de su producto (N:1)
            $st = $pdo->query(
                'SELECT p.id, p.talla, p.cantidad, p.total, p.metodo_pago, p.estado, p.creado_en,
                        u.id AS usuario_id, u.nombre AS usuario_nombre, u.email AS usuario_email,
                        pr.nombre AS producto, pr.marca
                   FROM pedidos p
                   JOIN usuarios u  ON u.id  = p.usuario_id
                   JOIN productos pr ON pr.id = p.producto_id
                  ORDER BY p.id DESC LIMIT 200');
            respond($st->fetchAll());

        case 'POST':
            $u = requireLogin();                       // el pedido queda a nombre del usuario logueado
            $d = input();
            $ids = array_map('intval', is_array($d['ids'] ?? null) ? $d['ids'] : []);

            if (!$ids || count($ids) > 50)                                    respond(['error' => 'Tu carrito está vacío'], 422);
            if (!in_array($d['talla'] ?? '', ['S', 'M', 'L', 'XL'], true))    respond(['error' => 'Elige una talla válida'], 422);
            if (!in_array($d['metodo'] ?? '', ['tarjeta', 'transferencia', 'billetera_digital'], true))
                respond(['error' => 'Método de pago inválido'], 422);

            $pdo->beginTransaction();                  // todo o nada
            $sel = $pdo->prepare('SELECT nombre, precio, stock FROM productos WHERE id = :id AND activo = 1 FOR UPDATE');
            $ins = $pdo->prepare('INSERT INTO pedidos (usuario_id, producto_id, talla, cantidad, total, metodo_pago)
                                  VALUES (:u, :p, :t, :c, :tot, :m)');
            $upd = $pdo->prepare('UPDATE productos SET stock = stock - :c WHERE id = :id');
            $total = 0.0; $creados = [];

            // ids repetidos = más unidades del mismo producto → un pedido por producto
            foreach (array_count_values($ids) as $pid => $cant) {
                $sel->execute(['id' => $pid]);
                $p = $sel->fetch();
                if (!$p) { $pdo->rollBack(); respond(['error' => 'Un producto del carrito ya no está disponible'], 409); }
                if ((int)$p['stock'] < $cant) {
                    $pdo->rollBack();
                    respond(['error' => "Stock insuficiente de «{$p['nombre']}» (quedan {$p['stock']})"], 409);
                }
                $sub = round((float)$p['precio'] * $cant, 2);   // el precio sale de la BD, nunca del navegador
                $ins->execute(['u' => $u['id'], 'p' => $pid, 't' => $d['talla'], 'c' => $cant, 'tot' => $sub, 'm' => $d['metodo']]);
                $creados[] = (int)$pdo->lastInsertId();
                $upd->execute(['c' => $cant, 'id' => $pid]);
                $total += $sub;
            }
            $pdo->commit();
            respond(['ok' => true, 'pedidos' => $creados, 'total' => round($total, 2)], 201);

        default: respond(['error' => 'Método no permitido'], 405);
    }
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    respond(['error' => 'Error del servidor'], 500);
}
