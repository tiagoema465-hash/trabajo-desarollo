<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

// Recomienda la talla más pequeña que cubra TODAS las medidas del usuario.
$d = input();
$marca = $d['marca'] ?? '';
$m = [(float)($d['pecho'] ?? 0), (float)($d['cintura'] ?? 0), (float)($d['cadera'] ?? 0)];

if (!isset(TABLAS_TALLAS[$marca]) || min($m) < 50 || max($m) > 200)
    respond(['error' => 'Marca o medidas inválidas (50–200 cm)'], 422);

foreach (TABLAS_TALLAS[$marca] as $talla => $limites) {
    if ($m[0] <= $limites[0] && $m[1] <= $limites[1] && $m[2] <= $limites[2])
        respond(['marca' => $marca, 'talla' => $talla]);
}
respond(['marca' => $marca, 'talla' => null, 'aviso' => 'Fuera de tabla: consulta con soporte']);
