<?php
// Guardia: solo administradores. Si no hay sesión de admin, redirige al login.
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
if (($_SESSION['user']['rol'] ?? '') !== 'admin') { header('Location: login.html'); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel de administración · SportShop</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
  <script src="js/app.js?v=<?= time() ?>" defer></script>
</head>
<body data-page="admin">
  <header class="top"><a href="admin.php">Panel admin</a>
    <nav><a href="index.html">Ver tienda</a><button class="btn sec" id="btnSalir" style="min-height:36px">Salir</button></nav></header>

  <main class="split">
    <section aria-label="Resumen" style="grid-column:1/-1">
      <div class="kpis">
        <div><b id="kTotal">0</b>Productos</div>
        <div><b id="kStock">0</b>Unidades</div>
        <div><b id="kBajo">0</b>Stock bajo</div>
      </div>
    </section>

    <!-- Registro de producto -->
    <section aria-labelledby="t-new">
      <h2 id="t-new">Nuevo producto</h2>
      <form id="formProd" novalidate>
        <label>Nombre<input name="nombre" required maxlength="120"><span class="error"></span></label>
        <label>Deporte
          <select name="deporte" required><option value="">Elige</option><option>Fútbol</option><option>Running</option><option>Tenis</option><option>Fitness</option></select><span class="error"></span>
        </label>
        <label>Marca
          <select name="marca" required><option value="">Elige</option><option>Nike</option><option>Adidas</option><option>Puma</option></select><span class="error"></span>
        </label>
        <div class="row cols" style="grid-template-columns:1fr 1fr">
          <label>Precio<input name="precio" type="number" min="0" step="0.01" inputmode="decimal" required><span class="error"></span></label>
          <label>Stock<input name="stock" type="number" min="0" step="1" inputmode="numeric" required><span class="error"></span></label>
        </div>
        <label>Imagen (ruta o URL)<input name="imagen_url" type="text" placeholder="img/productos/camiseta.webp" required><span class="error"></span></label>
        <button class="btn" type="submit">Guardar producto</button>
        <p class="msg" id="resProd" hidden></p>
      </form>
    </section>

    <!-- Inventario -->
    <section aria-labelledby="t-inv">
      <h2 id="t-inv">Inventario</h2>
      <ul class="items" id="inventario"></ul>
    </section>
  
    <!-- Pedidos recibidos: qué se pidió y qué usuario lo hizo -->
    <section aria-labelledby="t-ped" style="grid-column:1/-1">
      <h2 id="t-ped">Pedidos recibidos (<span id="cantPedidos">0</span>)</h2>
      <div style="display:flex;gap:8px;margin-bottom:12px">
        <input id="buscarPedido" type="search" placeholder="Buscar por nombre o correo del cliente" aria-label="Buscar pedidos por cliente">
        <button class="btn sec" id="btnActualizar" type="button">Actualizar</button>
      </div>
      <ul class="ped" id="pedidos"></ul>
    </section>

    <!-- Devoluciones y Reembolsos -->
    <section aria-labelledby="t-dev" style="grid-column:1/-1">
      <h2 id="t-dev">Devoluciones y Reembolsos (<span id="cantDevoluciones">0</span>)</h2>
      <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
        <select id="filtroEstadoDev" aria-label="Filtrar por estado" style="max-width:240px">
          <option value="">Todas las solicitudes</option>
          <option value="solicitada">Pendientes</option>
          <option value="aprobada">Aprobadas</option>
          <option value="rechazada">Rechazadas</option>
        </select>
        <button class="btn sec" id="btnActualizarDev" type="button">Actualizar</button>
      </div>
      <p class="msg" id="resDevAdmin" hidden style="margin-bottom:12px"></p>
      <ul class="ped" id="devoluciones"></ul>
    </section>
  </main>
</body>
</html>
