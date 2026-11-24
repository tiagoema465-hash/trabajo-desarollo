/* SportShop · JS modular. Un único archivo, secciones independientes. */
'use strict';
const API = '../api/';
const $  = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];

/* ---------- 1. VALIDACIÓN DE CAMPOS (reutilizable) ---------- */
// Regla por campo: función que devuelve un mensaje de error o '' si es válido.
const reglas = {
  required: v => (v.trim() ? '' : 'Este campo es obligatorio'),
  numero:   v => (v !== '' && !isNaN(v) && Number(v) >= 0 ? '' : 'Debe ser un número no menor a 0'),
  medida:   v => (Number(v) >= 50 && Number(v) <= 200 ? '' : 'Entre 50 y 200 cm'),
  entero:   v => (/^\d+$/.test(v) ? '' : 'Solo enteros, no menores a 0'),
  // Imagen: ruta local (img/productos/foto.webp) o URL https
  imagen:   v => (/^(https?:\/\/\S+|img\/[\w.\/-]+\.(webp|jpe?g|png|avif))$/i.test(v.trim()) && !v.includes('..') ? '' : 'Usa una ruta como img/productos/foto.webp o una URL https'),
  motivo:   v => (v.trim().length >= 10 ? '' : 'Describe el motivo (mín. 10 caracteres)'),
  email:    v => (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? '' : 'Correo no válido'),
  clave:    v => (v.length >= 8 && /[A-Za-z]/.test(v) && /\d/.test(v) ? '' : 'Mínimo 8 caracteres, con letras y números')
};

/**
 * Valida un formulario. `mapa` = { nombreCampo: [reglas...] }.
 * Marca aria-invalid, muestra el error junto al campo y devuelve true si todo es válido.
 */
function validarFormulario(form, mapa) {
  let ok = true;
  for (const [campo, lista] of Object.entries(mapa)) {
    const el = form.elements[campo];
    if (!el || el.closest('[hidden]')) continue;       // ignora campos ocultos
    const error = lista.map(r => reglas[r](el.value)).find(Boolean) || '';
    el.setAttribute('aria-invalid', error ? 'true' : 'false');
    const out = el.closest('label')?.querySelector('.error');
    if (out) out.textContent = error;
    if (error) ok = false;
  }
  return ok;
}

/* ---------- 2. UTILIDADES ---------- */
async function api(ruta, opciones = {}) {
  const r = await fetch(API + ruta, { headers: { 'Content-Type': 'application/json' }, ...opciones });
  const data = await r.json();
  // Sesión vencida o sin permiso en el panel admin → al login
  if ((r.status === 401 || r.status === 403) && document.body.dataset.page === 'admin') location.href = 'login.html';
  if (!r.ok) throw new Error(data.error || 'Error de red');
  return data;
}
function aviso(el, texto, ok = true) {
  el.hidden = false; el.textContent = texto; el.className = 'msg ' + (ok ? 'ok' : 'bad');
}
const money = n => '$' + Number(n).toFixed(2);
const store = {
  get: k => JSON.parse(localStorage.getItem(k) || '[]'),
  set: (k, v) => localStorage.setItem(k, JSON.stringify(v))
};
// Escapa HTML para evitar XSS al pintar datos de la BD
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

// Datos de ejemplo si el backend aún no está levantado
const DEMO = [
  { id: 1, nombre: 'Camiseta Dri-FIT', deporte: 'Running', marca: 'Nike',   precio: 29.9, stock: 12, imagen_url: '' },
  { id: 2, nombre: 'Short Tiro 23',    deporte: 'Fútbol',  marca: 'Adidas', precio: 24.5, stock: 3,  imagen_url: '' },
  { id: 3, nombre: 'Licra Training',   deporte: 'Fitness', marca: 'Puma',   precio: 34,   stock: 20, imagen_url: '' }
];
async function cargarProductos(qs = '') {
  try { return await api('productos.php' + qs); } catch { return DEMO; }
}
// Imagen optimizada: lazy, dimensiones fijas (evita saltos de layout), decode asíncrono
const imgTag = p => p.imagen_url
  ? `<img src="${esc(p.imagen_url)}" alt="${esc(p.nombre)}" width="400" height="500" loading="lazy" decoding="async">`
  : '<div class="ph" role="img" aria-label="Sin imagen"></div>';

/* ---------- 3. VISTA CLIENTE ---------- */
function initCliente() {
  const grid = $('#productos');
  let catalogo = [];
  const cache = {};   // todos los productos vistos (el carrito no depende del filtro activo)

  const actualizarContadores = () => {
    $('#wishCount').textContent = store.get('wish').length;
    $('#cartCount').textContent = store.get('cart').length;
  };

  async function pintar(qs = '') {
    catalogo = await cargarProductos(qs);
    catalogo.forEach(p => { cache[p.id] = p; });
    grid.innerHTML = catalogo.length ? catalogo.map(p => `
      <article class="prod">${imgTag(p)}
        <div><small>${esc(p.marca)} · ${esc(p.deporte)}</small><b>${esc(p.nombre)}</b>
          <span class="price">${money(p.precio)}</span>
          <span class="acts">
            <button class="btn sec" data-wish="${p.id}" aria-label="Guardar en deseos">♡</button>
            <button class="btn" data-cart="${p.id}">Añadir</button>
          </span></div></article>`).join('') : '<p>No hay prendas con esos filtros.</p>';
  }

  // Delegación de eventos: un solo listener para toda la grilla
  grid.addEventListener('click', e => {
    const w = e.target.closest('[data-wish]'), c = e.target.closest('[data-cart]');
    if (w) toggle('wish', +w.dataset.wish);
    if (c) { const lista = store.get('cart'); lista.push(+c.dataset.cart); store.set('cart', lista); pintarCarrito(); }
    actualizarContadores();
  });
  function toggle(key, id) {
    const l = store.get(key); store.set(key, l.includes(id) ? l.filter(x => x !== id) : [...l, id]);
  }
  function pintarCarrito() {
    let ids = store.get('cart');
    // Si el catálogo ya cargó productos, purga del carrito los que ya no existen en la base de datos
    if (Object.keys(cache).length > 0) {
      const validos = ids.filter(id => cache[id]);
      if (validos.length !== ids.length) {
        ids = validos;
        store.set('cart', ids);
      }
    }
    const prods = ids.map((id, i) => ({ p: cache[id], i })).filter(x => x.p);
    $('#listaCarrito').innerHTML = prods.length
      ? prods.map(({ p, i }) => `<li>${imgTag(p)}<span>${esc(p.nombre)}</span><b>${money(p.precio)}</b>
          <button class="btn danger" type="button" data-quitar="${i}" aria-label="Quitar ${esc(p.nombre)} del carrito">✕</button></li>`).join('')
      : '<li class="vacio">Tu carrito está vacío. Elige algo del catálogo.</li>';
    $('#totalCarrito').textContent = prods.length ? 'Total: ' + money(prods.reduce((t, x) => t + Number(x.p.precio), 0)) : '';
    $('#btnVaciar').hidden = !prods.length;
    actualizarContadores();
  }

  $('#filtros').addEventListener('submit', e => {
    e.preventDefault();
    const qs = new URLSearchParams(new FormData(e.target));
    [...qs.keys()].forEach(k => !qs.get(k) && qs.delete(k));
    pintar(qs.toString() ? '?' + qs : '');
  });

  // Guía de tallas
  $('#formTalla').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    if (!validarFormulario(f, { marca: ['required'], pecho: ['medida'], cintura: ['medida'], cadera: ['medida'] })) return;
    try {
      const r = await api('talla.php', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(f))) });
      aviso($('#resTalla'), r.talla ? `Tu talla en ${r.marca}: ${r.talla}` : r.aviso);
    } catch (err) { aviso($('#resTalla'), err.message, false); }
  });

  // Checkout: crea los pedidos en la base de datos (requiere sesión iniciada)
  $('#formPago').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    if (!store.get('cart').length) return aviso($('#resPago'), 'Tu carrito está vacío.', false);
    if (!validarFormulario(f, { talla: ['required'], metodo: ['required'] })) return;
    try {
      const r = await api('pedidos.php', { method: 'POST', body: JSON.stringify({ ids: store.get('cart'), talla: f.talla.value, metodo: f.metodo.value }) });
      store.set('cart', []); f.reset();
      aviso($('#resPago'), `Pedido registrado. Total: ${money(r.total)}. ¡Gracias por tu compra!`);
      pintar().then(pintarCarrito);   // refresca stock y carrito
    } catch (err) {
      aviso($('#resPago'), err.message, false);
      pintar().then(pintarCarrito);
    }
  });

  // Devoluciones: la talla nueva solo aplica a "cambio de talla"
  const formDev = $('#formDev');
  formDev.tipo.addEventListener('change', () => { $('#lblNueva').hidden = formDev.tipo.value !== 'cambio_talla'; });
  formDev.addEventListener('submit', async e => {
    e.preventDefault();
    const mapa = { pedido_id: ['entero'], tipo: ['required'], motivo: ['motivo'] };
    if (formDev.tipo.value === 'cambio_talla') mapa.talla_nueva = ['required'];
    if (!validarFormulario(formDev, mapa)) return;
    try {
      await api('devoluciones.php', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(formDev))) });
      aviso($('#resDev'), 'Solicitud enviada. Te avisaremos por correo.'); formDev.reset();
    } catch (err) { aviso($('#resDev'), err.message, false); }
  });

  // Muestra "Ingresar" o "Salir" según la sesión
  async function pintarSesion() {
    try {
      const { user } = await api('auth.php?accion=yo');
      if (!user) return;
      const l = $('#lnkSesion');
      l.textContent = 'Salir (' + user.nombre.split(' ')[0] + ')'; l.href = '#';
      l.addEventListener('click', async e => {
        e.preventDefault(); await api('auth.php?accion=salir', { method: 'POST' }); location.reload();
      });
      $('#lnkAdmin').hidden = user.rol !== 'admin';
    } catch { /* sin backend: queda "Ingresar" */ }
  }
  // Quitar un ítem o vaciar todo el carrito
  $('#listaCarrito').addEventListener('click', e => {
    const b = e.target.closest('[data-quitar]');
    if (!b) return;
    const lista = store.get('cart'); lista.splice(+b.dataset.quitar, 1); store.set('cart', lista); pintarCarrito();
  });
  $('#btnVaciar').addEventListener('click', () => { store.set('cart', []); pintarCarrito(); });

  pintarSesion();
  pintar().then(pintarCarrito);
}

/* ---------- 4. VISTA ADMIN (MVP) ---------- */
function initAdmin() {
  const lista = $('#inventario');

  async function pintar() {
    const data = await cargarProductos();
    $('#kTotal').textContent = data.length;
    $('#kStock').textContent = data.reduce((s, p) => s + Number(p.stock), 0);
    $('#kBajo').textContent  = data.filter(p => p.stock < 5).length;
    lista.innerHTML = data.map(p => `
      <li>${imgTag(p)}
        <div><b>${esc(p.nombre)}</b><br><small>${esc(p.marca)} · ${esc(p.deporte)} · ${money(p.precio)}</small><br>
          <span class="${p.stock < 5 ? 'low' : ''}">Stock: ${p.stock}</span></div>
        <button class="btn danger" data-del="${p.id}" aria-label="Eliminar ${esc(p.nombre)}">✕</button>
      </li>`).join('') || '<li class="vacio">Aún no hay productos.</li>';
  }

  lista.addEventListener('click', async e => {
    const b = e.target.closest('[data-del]');
    if (!b || !confirm('¿Eliminar este producto?')) return;
    try { await api('productos.php?id=' + b.dataset.del, { method: 'DELETE' }); pintar(); } catch (err) { alert(err.message); }
  });

  $('#formProd').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const ok = validarFormulario(f, {
      nombre: ['required'], deporte: ['required'], marca: ['required'],
      precio: ['numero'], stock: ['entero'], imagen_url: ['imagen']
    });
    if (!ok) return;
    try {
      await api('productos.php', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(f))) });
      aviso($('#resProd'), 'Producto guardado.'); f.reset(); pintar();
    } catch (err) { aviso($('#resProd'), err.message, false); }
  });

  // ----- Pedidos recibidos (con el usuario que los hizo) -----
  const pago = { tarjeta: 'Tarjeta', transferencia: 'Transferencia', billetera_digital: 'Billetera digital' };
  let pedidos = [];
  async function cargarPedidos() {
    try { pedidos = await api('pedidos.php'); pintarPedidos(); }
    catch { $('#pedidos').innerHTML = '<li>No se pudieron cargar los pedidos.</li>'; }
  }
  function pintarPedidos() {
    const q = $('#buscarPedido').value.trim().toLowerCase();
    const vis = pedidos.filter(p => !q || (p.usuario_nombre + ' ' + p.usuario_email).toLowerCase().includes(q));
    $('#cantPedidos').textContent = vis.length;
    $('#pedidos').innerHTML = vis.map(p => `
      <li>
        <div><b>#${p.id}</b><br><small>${esc(p.creado_en)}</small></div>
        <div><b>${esc(p.usuario_nombre)}</b><br><small>${esc(p.usuario_email)} · usuario n.º ${p.usuario_id}</small></div>
        <div>${esc(p.producto)}<br><small>${esc(p.marca)} · talla ${esc(p.talla)} · ${p.cantidad} ud.</small></div>
        <div><b>${money(p.total)}</b><br><small>${esc(pago[p.metodo_pago] || p.metodo_pago)}</small></div>
        <div><span class="tag">${esc(p.estado)}</span></div>
      </li>`).join('') || '<li>Aún no hay pedidos.</li>';
  }
  $('#buscarPedido').addEventListener('input', pintarPedidos);
  $('#btnActualizar').addEventListener('click', cargarPedidos);
  cargarPedidos();

  // ----- Devoluciones y Reembolsos -----
  const tipoTexto = {
    reembolso: 'Reembolso',
    cambio_prenda: 'Cambio de prenda',
    cambio_talla: 'Cambio de talla'
  };
  let devoluciones = [];

  async function cargarDevoluciones() {
    if (!$('#devoluciones')) return;
    try {
      devoluciones = await api('devoluciones.php');
      pintarDevoluciones();
    } catch (err) {
      console.error('Error cargando devoluciones:', err);
      $('#devoluciones').innerHTML = '<li class="vacio">No se pudieron cargar las solicitudes de devolución.</li>';
    }
  }

  function pintarDevoluciones() {
    if (!$('#devoluciones')) return;
    const fEstado = $('#filtroEstadoDev')?.value || '';
    const vis = devoluciones.filter(d => !fEstado || d.estado === fEstado);
    if ($('#cantDevoluciones')) $('#cantDevoluciones').textContent = vis.length;

    $('#devoluciones').innerHTML = vis.map(d => `
      <li>
        <div>
          <b>#${d.id}</b> <span class="tag tag-${esc(d.tipo)}">${esc(tipoTexto[d.tipo] || d.tipo)}</span><br>
          <small>${esc(d.creado_en)}</small>
        </div>
        <div>
          <b>${esc(d.usuario_nombre)}</b><br>
          <small>${esc(d.usuario_email)} · Pedido #${d.pedido_id}</small><br>
          <small>${esc(d.producto_nombre)} · ${money(d.total)}</small>
          ${d.talla_nueva ? `<br><small>Talla solicitada: <b>${esc(d.talla_nueva)}</b></small>` : ''}
        </div>
        <div>
          <b>Motivo:</b><br>
          <small style="display:block;white-space:pre-wrap;max-width:300px">${esc(d.motivo)}</small>
        </div>
        <div>
          <span class="tag tag-${esc(d.estado)}">${esc(d.estado)}</span>
        </div>
        <div class="acts">
          ${d.estado === 'solicitada' ? `
            <button class="btn ok" data-dev-id="${d.id}" data-accion="aprobada" type="button" aria-label="Aprobar solicitud #${d.id}">Aprobar</button>
            <button class="btn danger" data-dev-id="${d.id}" data-accion="rechazada" type="button" aria-label="Rechazar solicitud #${d.id}">Rechazar</button>
          ` : `
            <button class="btn sec" data-dev-id="${d.id}" data-accion="${d.estado === 'aprobada' ? 'rechazada' : 'aprobada'}" type="button">
              ${d.estado === 'aprobada' ? 'Cambiar a rechazar' : 'Cambiar a aprobar'}
            </button>
          `}
        </div>
      </li>`).join('') || '<li class="vacio">No hay solicitudes de devolución o reembolso.</li>';
  }

  $('#devoluciones')?.addEventListener('click', async e => {
    const btn = e.target.closest('[data-dev-id]');
    if (!btn) return;
    const id = btn.dataset.devId;
    const nuevoEstado = btn.dataset.accion;
    const accionTxt = nuevoEstado === 'aprobada' ? 'aprobar' : 'rechazar';
    if (!confirm(`¿Confirmas que deseas ${accionTxt} la solicitud #${id}?`)) return;

    try {
      const res = await api('devoluciones.php', {
        method: 'PUT',
        body: JSON.stringify({ id: +id, estado: nuevoEstado })
      });
      if ($('#resDevAdmin')) aviso($('#resDevAdmin'), res.mensaje || `Solicitud #${id} ${nuevoEstado}.`);
      cargarDevoluciones();
      cargarPedidos();
      pintar(); // Refresca inventario y KPIs si se repuso stock
    } catch (err) {
      if ($('#resDevAdmin')) aviso($('#resDevAdmin'), err.message, false);
      else alert(err.message);
    }
  });

  $('#filtroEstadoDev')?.addEventListener('change', pintarDevoluciones);
  $('#btnActualizarDev')?.addEventListener('click', cargarDevoluciones);
  cargarDevoluciones();

  $('#btnSalir').addEventListener('click', async () => {
    await api('auth.php?accion=salir', { method: 'POST' }); location.href = 'login.html';
  });
  pintar();
}

/* ---------- 4b. LOGIN / REGISTRO ---------- */
function initLogin() {
  // Admin → panel; cliente → tienda
  const ir = rol => { location.href = rol === 'admin' ? 'admin.php' : 'index.html'; };
  const enviar = (form, accion, mapa, salida) => form.addEventListener('submit', async e => {
    e.preventDefault();
    if (!validarFormulario(form, mapa)) return;
    try {
      const r = await api('auth.php?accion=' + accion, { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(form))) });
      ir(r.rol);
    } catch (err) { aviso(salida, err.message, false); }
  });
  enviar($('#formLogin'), 'login', { email: ['email'], clave: ['required'] }, $('#resLogin'));
  enviar($('#formRegistro'), 'registro', { nombre: ['required'], email: ['email'], clave: ['clave'] }, $('#resReg'));
}

/* ---------- 5. ARRANQUE ---------- */
document.addEventListener('DOMContentLoaded', () => {
  // Campos numéricos con min="0": impide teclear "-", "+" y "e"
  document.addEventListener('keydown', e => {
    if (e.target.matches('input[type=number][min="0"]') && ['-', '+', 'e', 'E'].includes(e.key)) e.preventDefault();
  });
  ({ cliente: initCliente, admin: initAdmin, login: initLogin })[document.body.dataset.page]?.();
});
