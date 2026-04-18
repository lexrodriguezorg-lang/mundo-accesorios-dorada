<?php
require_once __DIR__ . '/../afiliados_lib.php';

session_name('afil_sess');
session_start();
if (empty($_SESSION['afil_ok']) || empty($_SESSION['afil_user'])) {
    header('Location: login.php'); exit;
}

$user = $_SESSION['afil_user'];
$afil = afil_find_user($user);
if (!$afil || empty($afil['activa'])) {
    session_destroy();
    header('Location: login.php'); exit;
}

// Cambiar clave
$msg = '';
if (($_POST['action'] ?? '') === 'cambiar_clave') {
    $actual = (string)($_POST['actual'] ?? '');
    $nueva  = (string)($_POST['nueva'] ?? '');
    if (strlen($nueva) < 6) {
        $msg = 'La clave nueva debe tener al menos 6 caracteres';
    } elseif (!password_verify($actual, $afil['clave_hash'] ?? '')) {
        $msg = 'Clave actual incorrecta';
    } else {
        $data = afil_read();
        $i = afil_idx_user($data, $user);
        if ($i >= 0) {
            $data['afiliadas'][$i]['clave_hash'] = password_hash($nueva, PASSWORD_DEFAULT);
            afil_write($data);
            $msg = 'Clave actualizada';
            $afil = afil_find_user($user);
        }
    }
}

// Datos resumidos
$codigo       = $afil['codigo']      ?? '';
$nombre       = $afil['nombre']      ?? $user;
$clics_total  = intval($afil['clics_total'] ?? 0);
$clics_rec    = array_reverse($afil['clics_recientes'] ?? []);
$ventas       = array_reverse($afil['ventas'] ?? []);
$ventas_tot   = array_sum(array_map(fn($v)=>intval($v['monto'] ?? 0), $afil['ventas'] ?? []));
$comision_tot = array_sum(array_map(fn($v)=>intval($v['comision'] ?? 0), $afil['ventas'] ?? []));
$comision_pag = array_sum(array_map(fn($v)=>!empty($v['pagada']) ? intval($v['comision'] ?? 0) : 0, $afil['ventas'] ?? []));
$comision_pen = $comision_tot - $comision_pag;

$link_base = 'https://mundoaccesoriosdorada.com/?ref=' . urlencode($codigo);

// Cargar productos para "Mi agente"
$cat_file = __DIR__ . '/../catalogo.json';
$productos = [];
if (file_exists($cat_file)) {
    $catd = json_decode(file_get_contents($cat_file), true);
    foreach ($catd['productos'] ?? [] as $p) {
        if (!empty($p['activo']) && ($p['precio'] ?? 0) > 0) {
            $productos[] = [
                'id'      => (int)$p['id'],
                'nombre'  => (string)$p['nombre'],
                'precio'  => (int)$p['precio'],
                'categoria'=> (string)($p['categoria'] ?? ''),
            ];
        }
    }
    usort($productos, fn($a,$b)=>strcmp($a['nombre'],$b['nombre']));
}

function pf(int $n): string { return '$' . number_format($n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Panel afiliada · <?= htmlspecialchars($nombre) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;1,400;1,500&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
:root{--ink:#0A0410;--fu:#7209B7;--mag:#B5179E;--pink:#F72585;
  --grad:linear-gradient(135deg,#B5179E,#7209B7);--gs:linear-gradient(135deg,#F72585,#B5179E);
  --bg:#120818;--card:rgba(255,255,255,.04);--bd:rgba(255,255,255,.09);
  --txt:#fff;--mu:rgba(255,255,255,.5);--ok:#10B981;--warn:#F59E0B}
body{min-height:100dvh;background:var(--bg);font-family:'DM Sans',sans-serif;color:#fff;
  -webkit-font-smoothing:antialiased;padding-bottom:40px}
body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
  background:radial-gradient(ellipse 50% 50% at 15% 8%,rgba(247,37,133,.11),transparent),
             radial-gradient(ellipse 55% 55% at 85% 30%,rgba(114,9,183,.15),transparent)}
.wrap{position:relative;z-index:1;max-width:900px;margin:0 auto;padding:clamp(16px,4vw,30px)}

/* HEADER */
.hdr{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;
  padding-bottom:18px;border-bottom:1px solid var(--bd);margin-bottom:22px}
.hdr-l{display:flex;align-items:center;gap:12px;min-width:0}
.avatar{width:44px;height:44px;border-radius:50%;background:var(--grad);
  display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.95rem;flex-shrink:0}
.hdr-txt h1{font-family:'Cormorant Garamond',serif;font-size:1.5rem;font-weight:500;line-height:1}
.hdr-txt .sub{font-size:.7rem;color:var(--mu);margin-top:3px}
.hdr-btns{display:flex;gap:8px;flex-wrap:wrap}
.btn-h{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:50px;
  background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:#fff;
  font-size:.73rem;font-weight:500;text-decoration:none;cursor:pointer;font-family:inherit;transition:.15s}
.btn-h:hover{background:rgba(255,255,255,.11)}
.btn-h.out{color:#F8B4D4;border-color:rgba(247,37,133,.3)}
.msg{font-size:.75rem;background:rgba(16,185,129,.14);border:1px solid rgba(16,185,129,.32);
  color:#6EE7B7;border-radius:10px;padding:9px 12px;margin-bottom:16px}

/* STATS GRID */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:22px}
.stat{background:var(--card);border:1px solid var(--bd);border-radius:16px;padding:16px 18px}
.stat .lbl{font-size:.6rem;color:var(--mu);letter-spacing:.12em;text-transform:uppercase;font-weight:500;margin-bottom:8px}
.stat .val{font-family:'Cormorant Garamond',serif;font-size:2rem;font-weight:500;line-height:1}
.stat .val.gs{background:var(--gs);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.stat .sm{font-size:.68rem;color:var(--mu);margin-top:4px}
.stat.ok .val{color:var(--ok)}
.stat.warn .val{color:var(--warn)}

/* CARD */
.card{background:var(--card);border:1px solid var(--bd);border-radius:16px;
  padding:18px 20px;margin-bottom:16px}
.card h2{font-size:.68rem;font-weight:600;letter-spacing:.16em;text-transform:uppercase;
  color:var(--mu);margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:10px}
.card h2 .tag{background:var(--grad);color:#fff;font-size:.6rem;padding:2px 9px;border-radius:50px;letter-spacing:.1em}

/* LINK REFERIDO */
.ref-box{display:flex;align-items:center;gap:8px;background:rgba(0,0,0,.3);
  border:1px solid var(--bd);border-radius:12px;padding:10px 14px;flex-wrap:wrap}
.ref-code{font-family:'DM Sans';font-weight:600;font-size:.85rem;
  background:var(--gs);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.ref-url{flex:1;font-size:.74rem;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
  font-family:'DM Sans';font-weight:400;min-width:0}
.btn-sm{display:inline-flex;align-items:center;gap:5px;padding:7px 12px;border-radius:8px;
  background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);color:#fff;
  font-size:.7rem;font-weight:500;cursor:pointer;font-family:inherit;transition:.15s}
.btn-sm:hover{background:rgba(255,255,255,.15)}
.btn-sm.prim{background:var(--grad);border-color:transparent}
.btn-sm.wa{background:linear-gradient(135deg,#25D366,#1AAF55);border-color:transparent}

/* MI AGENTE */
.agente-fila{display:flex;gap:10px;margin-bottom:10px;flex-wrap:wrap}
.agente-fila select,.agente-fila .btn-sm{font-size:.76rem}
.sel{flex:1;min-width:200px;background:rgba(0,0,0,.3);border:1px solid var(--bd);border-radius:10px;
  padding:9px 12px;color:#fff;font-family:inherit;font-size:.78rem;outline:none;cursor:pointer}
.sel:focus{border-color:#F72585}
.sel option{background:#120818;color:#fff}
.agente-msg{background:rgba(0,0,0,.3);border:1px solid var(--bd);border-radius:10px;
  padding:12px 14px;font-size:.78rem;line-height:1.6;color:rgba(255,255,255,.88);
  white-space:pre-wrap;margin-bottom:10px;min-height:80px;font-family:inherit}
.agente-acc{display:flex;gap:8px;flex-wrap:wrap}
.agente-hint{font-size:.66rem;color:var(--mu);margin-top:10px;line-height:1.6}

/* TABLAS */
.tbl{width:100%;font-size:.76rem}
.tbl th,.tbl td{padding:8px 10px;text-align:left;border-bottom:1px solid var(--bd)}
.tbl th{font-size:.6rem;font-weight:600;color:var(--mu);letter-spacing:.1em;text-transform:uppercase}
.tbl tr:last-child td{border-bottom:0}
.tbl td.n{text-align:right;font-family:'DM Sans';font-weight:500}
.pill{display:inline-block;padding:2px 8px;border-radius:50px;font-size:.62rem;font-weight:600}
.pill.ok{background:rgba(16,185,129,.15);color:#6EE7B7}
.pill.pen{background:rgba(245,158,11,.15);color:#FCD34D}
.empty{text-align:center;padding:26px 16px;color:var(--mu);font-size:.8rem}

.cp{background:rgba(16,185,129,.16);border-color:rgba(16,185,129,.32);color:#6EE7B7}

/* CAMBIAR CLAVE */
.clave-form{display:flex;gap:8px;flex-wrap:wrap}
.clave-form input{flex:1;min-width:160px;background:rgba(0,0,0,.3);border:1px solid var(--bd);
  border-radius:10px;padding:9px 12px;color:#fff;font-family:inherit;font-size:.78rem;outline:none}
.clave-form input:focus{border-color:#F72585}

@media(max-width:520px){
  .hdr{flex-direction:column;align-items:flex-start}
  .hdr-btns{width:100%}
  .stats{grid-template-columns:1fr 1fr;gap:10px}
  .stat{padding:12px 14px}
  .stat .val{font-size:1.6rem}
  .tbl{font-size:.72rem}
  .tbl th:nth-child(3),.tbl td:nth-child(3){display:none}
}
</style>
</head>
<body>
<div class="wrap">

<div class="hdr">
  <div class="hdr-l">
    <div class="avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($nombre,0,2))) ?></div>
    <div class="hdr-txt">
      <h1><?= htmlspecialchars($nombre) ?></h1>
      <div class="sub">@<?= htmlspecialchars($user) ?> · código <strong style="color:#F8B4D4"><?= htmlspecialchars($codigo) ?></strong></div>
    </div>
  </div>
  <div class="hdr-btns">
    <a class="btn-h" href="#cardAgente" onclick="document.getElementById('cardAgente').scrollIntoView({behavior:'smooth'});return false;"
       style="background:linear-gradient(135deg,#B5179E,#7209B7);border-color:transparent;font-weight:600">
      Mi agente
    </a>
    <a class="btn-h" href="/" target="_blank">Ver sitio</a>
    <a class="btn-h out" href="login.php?salir=1">Salir</a>
  </div>
</div>

<?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif ?>

<div class="stats">
  <div class="stat">
    <div class="lbl">Clics</div>
    <div class="val gs"><?= $clics_total ?></div>
    <div class="sm">visitas con tu código</div>
  </div>
  <div class="stat">
    <div class="lbl">Ventas</div>
    <div class="val gs"><?= count($afil['ventas'] ?? []) ?></div>
    <div class="sm"><?= pf($ventas_tot) ?> atribuidas</div>
  </div>
  <div class="stat ok">
    <div class="lbl">Comisión pagada</div>
    <div class="val"><?= pf($comision_pag) ?></div>
    <div class="sm">ya recibida</div>
  </div>
  <div class="stat warn">
    <div class="lbl">Comisión pendiente</div>
    <div class="val"><?= pf($comision_pen) ?></div>
    <div class="sm">por cobrar</div>
  </div>
</div>

<!-- LINK REFERIDO -->
<div class="card">
  <h2>Tu link de referido <span class="tag">comparte y cobra</span></h2>
  <div class="ref-box">
    <span class="ref-code"><?= htmlspecialchars($codigo) ?></span>
    <span class="ref-url" id="refUrl"><?= htmlspecialchars($link_base) ?></span>
    <button class="btn-sm prim" onclick="copiarLink()">Copiar link</button>
  </div>
  <div style="font-size:.68rem;color:var(--mu);margin-top:10px;line-height:1.6">
    Cuando alguien entra con tu link, queda asociado a ti por 30 días. Todas las ventas que se concreten en ese tiempo se atribuyen a tu código.
  </div>
</div>

<!-- MI AGENTE -->
<?php
$plantillas_list = afil_plantillas($afil);
$plant_labels = ['Principal','Variante A','Variante B'];
?>
<div class="card" id="cardAgente">
  <h2>Mi agente <span class="tag">mensaje + WhatsApp</span></h2>

  <?php if (count($plantillas_list) > 1): ?>
  <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px" id="plantTabs">
    <?php foreach ($plantillas_list as $pi => $_): ?>
    <button type="button" class="btn-sm <?= $pi===0?'prim':'' ?>" data-pi="<?= $pi ?>" onclick="elegirPlantilla(<?= $pi ?>)"><?= $plant_labels[$pi] ?? ('Var '.$pi) ?></button>
    <?php endforeach ?>
  </div>
  <?php endif ?>

  <div class="agente-fila">
    <select class="sel" id="agProd" onchange="actualizarAgente()">
      <option value="">— Mensaje general (sin producto específico) —</option>
      <?php foreach ($productos as $p): ?>
      <option value="<?= $p['id'] ?>"
              data-nombre="<?= htmlspecialchars($p['nombre']) ?>"
              data-precio="<?= pf($p['precio']) ?>"><?= htmlspecialchars($p['nombre']) ?> — <?= pf($p['precio']) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="agente-msg" id="agMsg"><?= htmlspecialchars(afil_render_agente($afil)) ?></div>
  <div class="agente-acc">
    <button class="btn-sm prim" onclick="copiarMensaje()">📋 Copiar mensaje</button>
    <a class="btn-sm wa" id="agWa" href="#" target="_blank">Enviar por WhatsApp</a>
    <?php if (!empty($afil['wa_telefono'])): ?>
    <a class="btn-sm wa" id="agWaMio" href="#" target="_blank" title="Abrir tu WhatsApp con el mensaje listo para enviar a una clienta">Abrir mi WA</a>
    <?php endif ?>
  </div>
  <div class="agente-hint">
    El mensaje se arma con tu código y el producto elegido. El link lleva al producto ya marcado con tu referido (30 días).
    <?php if (count($plantillas_list) > 1): ?>Cambiá entre plantillas con las pestañas de arriba.<?php endif ?>
  </div>
</div>

<!-- VENTAS -->
<div class="card">
  <h2>Ventas atribuidas</h2>
  <?php if (empty($afil['ventas'])): ?>
    <div class="empty">Todavía no hay ventas registradas. Comparte tu link para empezar a acumular.</div>
  <?php else: ?>
  <table class="tbl">
    <thead><tr><th>Fecha</th><th>Producto</th><th>Nota</th><th class="n">Monto</th><th class="n">Comisión</th><th>Estado</th></tr></thead>
    <tbody>
    <?php foreach ($ventas as $v):
      $estado = !empty($v['pagada']) ? 'ok' : 'pen';
      $lblEst = !empty($v['pagada']) ? 'Pagada' : 'Pendiente';
    ?>
    <tr>
      <td><?= htmlspecialchars(date('d/m/Y', strtotime($v['t'] ?? 'now'))) ?></td>
      <td>#<?= intval($v['prod_id'] ?? 0) ?></td>
      <td style="color:var(--mu);font-size:.72rem"><?= htmlspecialchars($v['nota'] ?? '') ?></td>
      <td class="n"><?= pf(intval($v['monto'] ?? 0)) ?></td>
      <td class="n"><?= pf(intval($v['comision'] ?? 0)) ?></td>
      <td><span class="pill <?= $estado ?>"><?= $lblEst ?></span></td>
    </tr>
    <?php endforeach ?>
    </tbody>
  </table>
  <?php endif ?>
</div>

<!-- CLICS RECIENTES -->
<div class="card">
  <h2>Clics recientes <span class="tag">últimos <?= count($clics_rec) ?></span></h2>
  <?php if (empty($clics_rec)): ?>
    <div class="empty">Todavía no hay clics. Comparte tu link para empezar.</div>
  <?php else: ?>
  <table class="tbl">
    <thead><tr><th>Fecha y hora</th><th>Entró a</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($clics_rec, 0, 30) as $c): ?>
    <tr>
      <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($c['t'] ?? 'now'))) ?></td>
      <td style="color:var(--mu);font-size:.72rem;font-family:'DM Sans';word-break:break-all"><?= htmlspecialchars($c['url'] ?? '/') ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
  </table>
  <?php endif ?>
</div>

<!-- CAMBIAR CLAVE -->
<div class="card">
  <h2>Cambiar clave</h2>
  <form method="POST" class="clave-form" autocomplete="off">
    <input type="hidden" name="action" value="cambiar_clave">
    <input type="password" name="actual" placeholder="Clave actual" required>
    <input type="password" name="nueva"  placeholder="Nueva clave (mín 6)" required minlength="6">
    <button class="btn-sm prim" type="submit">Guardar</button>
  </form>
</div>

</div>

<script>
var CODIGO = <?= json_encode($codigo) ?>;
var LINK_BASE = <?= json_encode($link_base) ?>;
var SITE = 'https://mundoaccesoriosdorada.com';
var WA_NUM = '573233453004';
var AFIL = <?= json_encode(['nombre'=>$nombre,'user'=>$user,'codigo'=>$codigo,'wa'=>$afil['wa_telefono']??'']) ?>;
var PLANTILLAS = <?= json_encode($plantillas_list) ?>;
var plantActual = 0;

function copiarLink(){
  navigator.clipboard?.writeText(LINK_BASE).then(()=>{
    toast('Link copiado');
  }).catch(()=>fallback(LINK_BASE));
}
function copiarMensaje(){
  var t = document.getElementById('agMsg').textContent;
  navigator.clipboard?.writeText(t).then(()=>toast('Mensaje copiado')).catch(()=>fallback(t));
}
function fallback(t){
  var ta=document.createElement('textarea');ta.value=t;document.body.appendChild(ta);ta.select();
  document.execCommand('copy');document.body.removeChild(ta);toast('Copiado');
}
function toast(t){
  var d=document.createElement('div');d.textContent=t;
  d.style.cssText='position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#10B981;color:#fff;padding:10px 20px;border-radius:50px;font-size:.8rem;font-weight:500;z-index:9999;box-shadow:0 8px 28px rgba(0,0,0,.4);font-family:DM Sans';
  document.body.appendChild(d);setTimeout(()=>d.remove(),1800);
}

function elegirPlantilla(i){
  plantActual = i;
  document.querySelectorAll('#plantTabs button').forEach(b => {
    b.classList.toggle('prim', parseInt(b.dataset.pi) === i);
  });
  actualizarAgente();
}
function actualizarAgente(){
  var sel  = document.getElementById('agProd');
  var opt  = sel.options[sel.selectedIndex];
  var prodId = sel.value;
  var ctx = {
    '{afil_nombre}':     AFIL.nombre,
    '{afil_user}':       AFIL.user,
    '{afil_codigo}':     AFIL.codigo,
    '{link_home}':       LINK_BASE,
    '{link_catalogo}':   SITE + '/catalogo.php?ref=' + encodeURIComponent(AFIL.codigo),
    '{producto_nombre}': prodId ? opt.dataset.nombre : '[elige un producto]',
    '{producto_precio}': prodId ? opt.dataset.precio : '[precio]',
    '{producto_link}':   prodId
      ? (SITE + '/producto.php?id=' + prodId + '&ref=' + encodeURIComponent(AFIL.codigo))
      : LINK_BASE,
  };
  var t = PLANTILLAS[plantActual] || PLANTILLAS[0] || '';
  Object.keys(ctx).forEach(function(k){ t = t.split(k).join(ctx[k]); });
  document.getElementById('agMsg').textContent = t;
  var enc = encodeURIComponent(t);
  document.getElementById('agWa').href = 'https://wa.me/?text=' + enc;
  var mio = document.getElementById('agWaMio');
  if (mio && AFIL.wa) mio.href = 'https://wa.me/' + encodeURIComponent(AFIL.wa) + '?text=' + enc;
}
// Inicializar WA links
actualizarAgente();
</script>
</body>
</html>
