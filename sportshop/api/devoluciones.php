<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

// Gestión de devoluciones y reembolsos (Cliente crea solicitud, Admin consulta y aprueba/rechaza)
$pdo = null;
try {
    $pdo = db();
    switch ($_SERVER['REQUEST_METHOD']) {

        case 'GET': // Admin: lista todas las solicitudes de devolución / reembolso
            requireAdmin();
            $st = $pdo->query('
                SELECT d.id, d.pedido_id, d.tipo, d.motivo, d.talla_nueva, d.estado, d.creado_en,
                       u.id AS usuario_id, u.nombre AS usuario_nombre, u.email AS usuario_email,
                       p.total, p.cantidad, p.metodo_pago, p.talla AS pedido_talla,
                       COALESCE(pr.nombre, "Producto eliminado") AS producto_nombre,
                       COALESCE(pr.marca, "") AS producto_marca,
                       pr.id AS producto_id
                  FROM devoluciones d
                  JOIN pedidos p     ON p.id = d.pedido_id
                  JOIN usuarios u    ON u.id = p.usuario_id
             LEFT JOIN productos pr  ON pr.id = p.producto_id
                 ORDER BY d.id DESC LIMIT 200
            ');
            respond($st->fetchAll());

        case 'POST': // Cliente: crea una solicitud desde la tienda
            $u = requireLogin();
            $d = input();
            $tipos = ['cambio_prenda', 'cambio_talla', 'reembolso'];
            $motivo = trim((string)($d['motivo'] ?? ''));

            if (!ctype_digit((string)($d['pedido_id'] ?? ''))) respond(['error' => 'Pedido inválido'], 422);
            if (!in_array($d['tipo'] ?? '', $tipos, true))     respond(['error' => 'Tipo inválido'], 422);
            if (mb_strlen($motivo) < 10 || mb_strlen($motivo) > 500) respond(['error' => 'El motivo debe tener 10–500 caracteres'], 422);
            if ($d['tipo'] === 'cambio_talla' && empty($d['talla_nueva'])) respond(['error' => 'Indica la nueva talla'], 422);

            $own = $pdo->prepare('SELECT id FROM pedidos WHERE id=:p AND usuario_id=:u');
            $own->execute(['p' => $d['pedido_id'], 'u' => $u['id']]);
            if (!$own->fetch()) respond(['error' => 'Ese pedido no existe en tu cuenta'], 404);

            $st = $pdo->prepare('INSERT INTO devoluciones (pedido_id,tipo,motivo,talla_nueva)
                                 VALUES (:p,:t,:m,:n)');
            $st->execute(['p' => $d['pedido_id'], 't' => $d['tipo'], 'm' => $motivo, 'n' => $d['talla_nueva'] ?? null]);
            respond(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);

        case 'PUT': // Admin: aprobar o rechazar devolución / reembolso
            requireAdmin();
            $d = input();
            $id = (int)($d['id'] ?? 0);
            $estado = (string)($d['estado'] ?? '');

            if ($id <= 0 || !in_array($estado, ['aprobada', 'rechazada'], true)) {
                respond(['error' => 'Datos inválidos para actualizar la solicitud'], 422);
            }

            $pdo->beginTransaction();
            $sel = $pdo->prepare('
                SELECT d.id, d.pedido_id, d.tipo, d.estado, p.producto_id, p.cantidad
                  FROM devoluciones d
                  JOIN pedidos p ON p.id = d.pedido_id
                 WHERE d.id = :id FOR UPDATE
            ');
            $sel->execute(['id' => $id]);
            $dev = $sel->fetch();

            if (!$dev) {
                $pdo->rollBack();
                respond(['error' => 'Solicitud no encontrada'], 404);
            }

            // Actualiza el estado de la devolución
            $upd = $pdo->prepare('UPDATE devoluciones SET estado = :estado WHERE id = :id');
            $upd->execute(['estado' => $estado, 'id' => $id]);

            // Si se aprueba y antes no estaba aprobada: cancelamos el pedido y reponemos stock
            if ($estado === 'aprobada' && $dev['estado'] !== 'aprobada') {
                $updPed = $pdo->prepare("UPDATE pedidos SET estado = 'cancelado' WHERE id = :pid");
                $updPed->execute(['pid' => $dev['pedido_id']]);

                if ($dev['producto_id']) {
                    $updStock = $pdo->prepare('UPDATE productos SET stock = stock + :cant WHERE id = :pr_id');
                    $updStock->execute(['cant' => (int)$dev['cantidad'], 'pr_id' => (int)$dev['producto_id']]);
                }
            } elseif ($estado === 'rechazada' && $dev['estado'] === 'aprobada') {
                // Si se revierte una aprobación previa a rechazada: restauramos el pedido y descontamos stock
                $updPed = $pdo->prepare("UPDATE pedidos SET estado = 'pagado' WHERE id = :pid");
                $updPed->execute(['pid' => $dev['pedido_id']]);

                if ($dev['producto_id']) {
                    $updStock = $pdo->prepare('UPDATE productos SET stock = GREATEST(0, stock - :cant) WHERE id = :pr_id');
                    $updStock->execute(['cant' => (int)$dev['cantidad'], 'pr_id' => (int)$dev['producto_id']]);
                }
            }

            $pdo->commit();
            respond(['ok' => true, 'mensaje' => "Solicitud #$id marcada como $estado con éxito."]);

        default:
            respond(['error' => 'Método no permitido'], 405);
    }
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(['error' => 'Error en el servidor: ' . $e->getMessage()], 500);
}
