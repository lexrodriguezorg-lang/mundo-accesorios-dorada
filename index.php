<?php
require_once __DIR__ . '/afiliados_lib.php';
afil_handle_incoming_ref(); // setea cookie y trackea si viene ?ref=CODIGO

$WA = 'https://wa.me/573233453004?text=Hola%2C+me+interesa+un+producto';

$data    = file_exists(__DIR__.'/catalogo.json') ? json_decode(file_get_contents(__DIR__.'/catalogo.json'),true) : ['v'=>1,'productos'=>[]];
$activos = array_values(array_filter($data['productos']??[], fn($p)=>$p['activo']??false));

function foto(int $id, int $slot=1):?string {
    $base = $slot===1 ? $id : "{$id}_{$slot}";
    foreach(['jpg','jpeg','png','webp'] as $e)
        if(file_exists(__DIR__."/uploads/fotos-productos/{$base}.{$e}"))
            return "/uploads/fotos-productos/{$base}.{$e}";
    return null;
}
function foto_home(array $p):?string {
    $slot = intval($p['foto_home'] ?? 1);
    $f = foto($p['id'], $slot);
    return $f ?? foto($p['id'], 1);
}
function pf(int $n):string { return '$'.number_format($n,0,',','.'); }

$CATS_CFG = [
  ['label'=>'Termos',                'cat'=>'Termos',                'h2'=>'Ese que preguntan <em>dónde lo compraste.</em>',         'desc'=>'Stanley en todos los colores. Siempre en bodega.'],
  ['label'=>'Audífonos y Diademas',  'cat'=>'Audífonos y Diademas',  'h2'=>'Ponlo. El mundo <em>puede esperar.</em>',                'desc'=>'Balacas, TWS, sport y cable. Para cada estilo y presupuesto.'],
  ['label'=>'Relojes',               'cat'=>'Relojes',               'h2'=>'La tecnología <em>que llevas puesta.</em>',              'desc'=>'Smartwatches que funcionan de verdad. Al precio que se merece.'],
  ['label'=>'Combos',                'cat'=>'Combos',                'h2'=>'El regalo <em>que siempre acierta.</em>',                'desc'=>'Los kits más pedidos reunidos en un solo lugar.'],
  ['label'=>'Parlantes',             'cat'=>'Parlantes',             'h2'=>'Sube el volumen. <em>Ya.</em>',                          'desc'=>'Mini, mediano o de fiesta. Marcas reales, sonido real.'],
  ['label'=>'Cargadores y Cables',   'cat'=>'Cargadores y Cables',   'h2'=>'Batería llena. <em>Siempre.</em>',                      'desc'=>'Carga rápida, cables que duran. Para todos los dispositivos.'],
  ['label'=>'Aros de Luz y Tripodes','cat'=>'Aros de Luz y Tripodes','h2'=>'Para el que <em>crea en serio.</em>',                   'desc'=>'Iluminación LED y trípodes al alcance de todos.'],
  ['label'=>'Micrófonos',            'cat'=>'Micrófonos',            'h2'=>'Graba <em>como los grandes.</em>',                      'desc'=>'Calidad de estudio en tu bolsillo.'],
  ['label'=>'Power Bank',            'cat'=>'Power Bank',            'h2'=>'Nunca más <em>sin batería.</em>',                       'desc'=>'Power banks compactos con capacidad real.'],
  ['label'=>'Humidificadores',       'cat'=>'Humidificadores',       'h2'=>'El ambiente que nadie <em>sabe de dónde vino.</em>',    'desc'=>'Diseños únicos que transforman cualquier cuarto.'],
  ['label'=>'Soporte Celular',       'cat'=>'Soporte Celular',       'h2'=>'Libera <em>las manos.</em>',                            'desc'=>'Popsockets, soportes de carro y escritorio.'],
  ['label'=>'Cases y Estuches',      'cat'=>'Cases y Estuches',      'h2'=>'Protege. <em>Con estilo.</em>',                         'desc'=>'Fundas y estuches para todos los modelos.'],
  ['label'=>'Vidrios Templados',     'cat'=>'Vidrios Templados',     'h2'=>'La pantalla <em>protegida.</em>',                       'desc'=>'Vidrios templados para todos los dispositivos.'],
  ['label'=>'Tecnología',           'cat'=>'Tecnología',           'h2'=>'Gadgets que <em>hacen la diferencia.</em>',              'desc'=>'Mouse, memorias, impresoras, hubs y más tech para tu vida.'],
  ['label'=>'Otros',                 'cat'=>'Otros',                 'h2'=>'Lo que también <em>necesitas.</em>',                    'desc'=>'Pulseras y más accesorios disponibles.'],
];

$by_cat = [];
foreach($activos as $p){ $c=$p['categoria']??'Otros'; $by_cat[$c][]=$p; }
$home_cfg = $data['home_secciones'] ?? null;
$cats_map = [];
foreach($CATS_CFG as $cfg) $cats_map[$cfg['cat']] = $cfg;

$secciones = [];
$source = ($home_cfg && count($home_cfg)>0) ? $home_cfg : array_map(fn($c)=>['cat'=>$c['cat'],'max'=>8], $CATS_CFG);
foreach($source as $hc){
    $cat = $hc['cat'] ?? ''; $max = intval($hc['max'] ?? 8);
    if(!$cat || empty($by_cat[$cat])) continue;
    $cfg = $cats_map[$cat] ?? ['label'=>$cat,'cat'=>$cat,'h2'=>$cat,'desc'=>''];
    $prods = $by_cat[$cat];
    $con_foto = array_values(array_filter($prods, fn($p) =>
        foto_home($p) !== null && ($p['en_home'] ?? true) !== false
    ));
    usort($con_foto, function($a, $b) {
        $pa = intval($a['pos_home'] ?? 0);
        $pb = intval($b['pos_home'] ?? 0);
        if ($pa === 0 && $pb === 0) return 0;
        if ($pa === 0) return 1;
        if ($pb === 0) return -1;
        return $pa - $pb;
    });
    $prices = array_filter(array_column($prods,'precio'),fn($v)=>$v>0);
    if(count($con_foto)===0) continue;
    $secciones[]=[...$cfg,'items'=>array_slice($con_foto,0,$max),'total'=>count($prods),'pmin'=>$prices?min($prices):0];
}

$hero_fotos=[];
foreach($secciones as $s)
    foreach($s['items'] as $p){
        $f=foto_home($p); if($f && !in_array($f,$hero_fotos)){ $hero_fotos[]=$f; if(count($hero_fotos)>=6) break 2; }
    }
// Necesitamos exactamente 3 fotos para el grid (hg0 + 2 derecha)
// Si tenemos menos, rellenar con la 2301 o repetir
$fallback='/uploads/fotos-productos/2301.jpg';
$fallback_exists=file_exists(__DIR__.'/uploads/fotos-productos/2301.jpg');
while(count($hero_fotos)<3){
    if(count($hero_fotos)>0) $hero_fotos[]=$hero_fotos[0];
    elseif($fallback_exists) $hero_fotos[]=$fallback;
    else break;
}
// Cap en 3
$hero_fotos=array_slice($hero_fotos,0,3);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mundo Accesorios · Accesorios para tu celular</title>
<meta name="description" content="Termos, audífonos, relojes, cargadores y más. Stock permanente, precio directo.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --fu:#B5179E;--vi:#7209B7;--ro:#F72585;
  --grad:linear-gradient(135deg,#F72585 0%,#7209B7 55%,#B5179E 100%);
  --gs:linear-gradient(135deg,#F72585,#7209B7);
  --ink:#1a0a1e;--mid:#5a4060;--muted:#9a7aaa;
  --pale:#FFFAFF;--soft:#F8F0FF;--border:#EDD9F5;--white:#fff;
}
html{scroll-behavior:smooth;scroll-padding-top:90px}
body{background:var(--white);color:var(--ink);font-family:'DM Sans',sans-serif;font-weight:300;overflow-x:hidden;-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}

/* PROGRESS */
#pg{position:fixed;top:0;left:0;right:0;z-index:9999;height:2px;background:var(--grad);width:0;transition:width .08s linear;pointer-events:none}

/* TICKER */
.ticker{background:var(--grad);height:28px;overflow:hidden;position:relative;z-index:250}
.ticker-track{display:flex;align-items:center;height:100%;width:max-content;
  animation:tickerMove 22s linear infinite}
.ticker-track:hover{animation-play-state:paused}
@keyframes tickerMove{from{transform:translateX(0)}to{transform:translateX(-50%)}}
.ticker-item{display:flex;align-items:center;gap:14px;padding:0 28px;
  font-size:.58rem;font-weight:500;letter-spacing:.18em;text-transform:uppercase;color:#fff;white-space:nowrap}
.ticker-sep{opacity:.5;font-size:.7rem}

/* NAV */
nav{position:fixed;top:0;left:0;right:0;z-index:300;height:72px;
  display:flex;align-items:center;justify-content:space-between;padding:0 48px;
  background:rgba(255,250,255,.96);backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border)}
.nav-logo{height:72px;overflow:hidden;display:flex;align-items:center}
.nav-logo img{height:72px;width:auto;max-width:200px;object-fit:contain;display:block}
.nav-wa{display:flex;align-items:center;gap:7px;padding:10px 22px;border-radius:50px;
  background:var(--grad);color:#fff;font-size:.75rem;font-weight:500;letter-spacing:.05em;
  box-shadow:0 4px 20px rgba(183,17,158,.25);transition:.2s}
.nav-wa:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(183,17,158,.35)}
.nav-wa svg{width:15px;height:15px;fill:#fff;flex-shrink:0}
@media(max-width:640px){nav{padding:0 20px;height:62px}.nav-logo{height:44px}.nav-logo img{height:44px}}

/* CAT TABS */
.cat-tabs{position:fixed;top:calc(72px + 28px);left:0;right:0;z-index:200;
  background:rgba(255,250,255,0.78);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
  border-bottom:1px solid rgba(183,17,158,.08);
  overflow-x:auto;scrollbar-width:none;padding:0 48px;
  transition:transform .35s cubic-bezier(.4,0,.2,1),opacity .35s ease}
.cat-tabs.hidden{transform:translateY(-110%);opacity:0}
.cat-tabs::-webkit-scrollbar{display:none}
.tabs-inner{display:flex;gap:4px;min-width:max-content;padding:8px 0}
.ct{padding:7px 16px;border-radius:100px;border:1px solid var(--border);background:transparent;
  font-family:'DM Sans';font-size:.72rem;font-weight:400;color:var(--muted);
  cursor:pointer;white-space:nowrap;transition:.2s;text-decoration:none}
.ct:hover{border-color:var(--fu);color:var(--fu)}
.ct.act{background:var(--grad);border-color:transparent;color:#fff}
@media(max-width:640px){
  .cat-tabs{padding:0 16px;top:calc(62px + 28px)}
  .hero-tag::before{display:none}
}

/* HERO */
.hero{padding-top:calc(72px + 28px + 40px + 8px);min-height:92vh;
  display:grid;grid-template-columns:1fr 1fr;position:relative;overflow:hidden}
@keyframes hfloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:none}}
@keyframes fadeLeft{from{opacity:0;transform:translateX(-20px)}to{opacity:1;transform:none}}
.hero-left{display:flex;flex-direction:column;justify-content:center;padding:56px 56px 56px 64px;position:relative;z-index:1}
.hero-tag{font-size:.58rem;font-weight:500;letter-spacing:.26em;text-transform:uppercase;
  color:var(--fu);display:flex;align-items:center;gap:10px;margin-bottom:24px;
  animation:fadeLeft .5s .1s ease both}
.hero-tag::before{content:'';width:24px;height:1.5px;background:var(--grad);flex-shrink:0}
.hero-h1{font-family:'Cormorant Garamond',serif;font-size:clamp(4.2rem,7vw,8.5rem);
  font-weight:500;line-height:.9;letter-spacing:-.02em;color:var(--ink);margin-bottom:28px;
  animation:fadeUp .6s .2s cubic-bezier(.16,1,.3,1) both}
.hero-h1 em{font-style:italic;font-weight:400;background:var(--gs);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.hero-sub{font-size:.95rem;font-weight:300;color:var(--mid);line-height:1.72;
  max-width:380px;margin-bottom:44px;animation:fadeUp .5s .34s ease both}
.hero-btns{display:flex;flex-direction:column;gap:10px;align-items:flex-start;animation:fadeUp .5s .46s ease both}
.hero-cta{display:inline-flex;align-items:center;gap:10px;padding:14px 36px;border-radius:50px;
  background:var(--grad);color:#fff;font-size:.82rem;font-weight:500;letter-spacing:.06em;text-transform:uppercase;
  box-shadow:0 6px 28px rgba(183,17,158,.3);transition:.2s}
.hero-cta:hover{transform:translateY(-3px);box-shadow:0 12px 40px rgba(183,17,158,.4)}
.hero-explore-link{
  display:inline-flex;align-items:center;gap:6px;
  margin-top:20px;
  font-family:'Cormorant Garamond',serif;
  font-size:.9rem;font-style:italic;font-weight:400;
  color:var(--fu);text-decoration:underline;
  text-underline-offset:4px;text-decoration-color:rgba(183,17,158,.3);
  letter-spacing:.03em;transition:.2s;opacity:.75
}
.hero-explore-link:hover{opacity:1;text-decoration-color:var(--fu)}
.hero-proof{display:flex;align-items:center;gap:8px;font-size:.75rem;font-weight:400;color:var(--muted);
  animation:fadeUp .5s .58s ease both}
.hero-proof-dot{width:6px;height:6px;border-radius:50%;background:var(--fu);flex-shrink:0;
  box-shadow:0 0 0 3px rgba(183,17,158,.15);animation:pulse 2s infinite}
@keyframes pulse{0%,100%{box-shadow:0 0 0 3px rgba(183,17,158,.15)}50%{box-shadow:0 0 0 6px rgba(183,17,158,.08)}}

/* Hero right — foto collage */
.hero-right{position:relative;overflow:hidden;background:#1a0a1e}
.hero-right::before{content:'';position:absolute;inset:0;z-index:1;
  background:radial-gradient(ellipse at 30% 60%,rgba(247,37,133,.18),transparent 55%),
             radial-gradient(ellipse at 75% 20%,rgba(114,9,183,.22),transparent 50%)}
.hero-grid{position:absolute;inset:0;z-index:2;display:grid;grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr;gap:2px}
.hero-grid img{width:100%;height:100%;object-fit:cover;transition:transform 10s ease}
.hero-grid .hg0{grid-row:1/3}
.hero:hover .hero-grid img{transform:scale(1.04)}
.hero-solo{position:absolute;inset:0;z-index:2}
.hero-solo img{width:100%;height:100%;object-fit:cover}
.hero-ph{position:absolute;inset:0;z-index:2;background:linear-gradient(135deg,#2d0a3a,#7209B7 60%,#B5179E)}
.hero-badge{position:absolute;bottom:32px;left:28px;z-index:10;
  padding:8px 18px;border-radius:50px;background:rgba(255,255,255,.1);backdrop-filter:blur(12px);
  border:1px solid rgba(255,255,255,.2);font-size:.6rem;font-weight:500;color:#fff;letter-spacing:.1em;text-transform:uppercase}
@media(max-width:900px){
  .hero{padding-top:0;min-height:100svh;grid-template-columns:1fr;position:relative}
  .hero-right{
    position:absolute;inset:0;display:block;z-index:0;
    background:var(--soft)
  }
  .hero-right::after{
    content:'';position:absolute;inset:0;z-index:1;
    background:linear-gradient(
      to bottom,
      rgba(255,250,255,0) 0%,
      rgba(255,250,255,.4) 30%,
      rgba(255,250,255,.88) 55%,
      #fffaff 72%
    )
  }
  .hero-right .hero-grid{display:none}
  .hero-right .hero-solo{z-index:0}
  .hero-right .hero-ph{z-index:0}
  .hero-right .hero-badge{display:none}
  .hero-left{
    position:relative;z-index:2;
    padding:calc(62px + 28px + 38px) 28px 48px;
    min-height:100svh;
    display:flex;flex-direction:column;
    justify-content:center
  }
}

/* SECCIONES */
.sec{position:relative;padding:0}
@keyframes bounceIn{
  0%  {opacity:0;transform:translateY(50px) scale(.9) rotate(-1deg)}
  60% {opacity:1;transform:translateY(-8px) scale(1.03) rotate(.3deg)}
  80% {transform:translateY(3px) scale(.99)}
  100%{opacity:1;transform:none}
}
@keyframes slideInLeft{from{opacity:0;transform:translateX(-40px)}to{opacity:1;transform:none}}
@keyframes slideInRight{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:none}}
@keyframes zoomFade{from{opacity:0;transform:scale(1.08)}to{opacity:.18;transform:scale(1)}}

/* Fondo por sección */
.sec{position:relative}
.sec-bg{
  position:absolute;left:0;right:0;top:0;
  height:360px;overflow:hidden;
  pointer-events:none;z-index:0
}
.sec-bg img{
  position:absolute;right:0;top:0;
  width:55%;height:130%;
  object-fit:cover;
  opacity:0;
  filter:blur(6px) saturate(1.1);
  transition:opacity 1.1s .1s ease;
  will-change:transform
}
.sec-bg::after{
  content:'';position:absolute;inset:0;z-index:1;
  background:
    linear-gradient(90deg,var(--pale) 20%,rgba(255,250,255,.82) 50%,rgba(255,250,255,.2) 100%),
    linear-gradient(180deg,transparent 55%,var(--pale) 100%)
}
.sec.on .sec-bg img{
  opacity:.5;
  animation:bgZoom 9s ease forwards
}
@keyframes bgZoom{from{transform:scale(1.08)}to{transform:scale(1)}}
.sec-head{position:relative;z-index:2}
.sec-track-wrap{
  position:relative;z-index:2;
  background:linear-gradient(to bottom,rgba(255,250,255,.0) 0%,var(--pale) 24px)
}
.sec-foot{position:relative;z-index:2}

/* Section head — clean editorial */
.sec-head{padding:64px 64px 0;display:flex;align-items:flex-end;justify-content:space-between;gap:24px;flex-wrap:wrap}
.sec-head-text{}
.sec-pill{display:inline-flex;align-items:center;gap:6px;margin-bottom:14px;
  font-size:.55rem;font-weight:500;letter-spacing:.22em;text-transform:uppercase;color:var(--fu)}
.sec-pill::before{content:'';width:18px;height:1.5px;background:var(--fu);flex-shrink:0}
.sec-h2{font-family:'Cormorant Garamond',serif;font-size:clamp(2.4rem,4.5vw,4.4rem);
  font-weight:500;line-height:.95;letter-spacing:-.01em;color:var(--ink);
  opacity:0;transform:translateY(28px);
  transition:opacity .45s .05s cubic-bezier(.16,1,.3,1),transform .45s .05s cubic-bezier(.16,1,.3,1)}
.sec-h2 em{font-style:italic;font-weight:400;background:var(--gs);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.sec.on .sec-h2{opacity:1;transform:none}
.sec-pill{opacity:0;transform:translateX(-14px);transition:opacity .3s,transform .3s}
.sec.on .sec-pill{opacity:1;transform:none}
.sec-desc{font-size:.85rem;font-weight:300;color:var(--muted);margin-top:10px;max-width:420px;
  opacity:0;transition:opacity .4s .12s}
.sec.on .sec-desc{opacity:1}
.sec-link{display:inline-flex;align-items:center;gap:6px;flex-shrink:0;margin-bottom:6px;
  font-size:.72rem;font-weight:500;color:var(--fu);letter-spacing:.06em;text-transform:uppercase;
  border-bottom:1.5px solid rgba(183,17,158,.2);padding-bottom:3px;transition:.2s;text-decoration:none}
.sec-link:hover{border-color:var(--fu)}
.sec-explore-btn{
  width:26px;height:26px;border-radius:50%;flex-shrink:0;
  background:rgba(183,17,158,.07);border:1px solid rgba(183,17,158,.18);
  display:flex;align-items:center;justify-content:center;
  color:var(--fu);text-decoration:none;transition:.2s;margin-bottom:6px;
  font-size:.7rem;font-style:italic;font-family:'Cormorant Garamond',serif;font-weight:500
}
.sec-explore-btn:hover{background:var(--grad);border-color:transparent;color:#fff}

/* Flecha cute siguiente sección */
.sec-flower{display:none}

/* CTA Explorar */
.explore-cta{display:flex;align-items:center;justify-content:space-between;margin:0 24px 8px;padding:16px 20px;background:linear-gradient(135deg,rgba(114,9,183,.07),rgba(247,37,133,.07));border:1px solid rgba(183,17,158,.18);border-radius:18px;text-decoration:none;transition:.2s}
.explore-cta:hover{border-color:rgba(183,17,158,.35);transform:translateY(-1px)}
.explore-cta-left{display:flex;flex-direction:column;gap:2px}
.explore-cta-tag{font-size:.52rem;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:var(--fu);opacity:.8}
.explore-cta-title{font-family:'Cormorant Garamond',serif;font-size:1.25rem;font-weight:500;color:var(--ink);line-height:1}
.explore-cta-sub{font-size:.72rem;color:var(--muted);font-weight:300}
.explore-cta-arr{width:38px;height:38px;border-radius:50%;flex-shrink:0;background:var(--grad);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1rem;box-shadow:0 4px 14px rgba(183,17,158,.25)}

/* TRACK */
.sec-track-wrap{padding:28px 64px 0;position:relative;
  opacity:0;transform:translateY(18px);
  transition:opacity .4s .18s,transform .4s .18s}
.sec.on .sec-track-wrap{opacity:1;transform:none}
.track{display:flex;gap:12px;overflow-x:auto;overflow-y:visible;
  scroll-snap-type:x mandatory;scrollbar-width:none;
  cursor:grab;-webkit-overflow-scrolling:touch;padding-bottom:4px}
.track::-webkit-scrollbar{display:none}
.track.dragging{cursor:grabbing;user-select:none}

/* PRODUCT CARD — clean white */
.pcard{flex-shrink:0;width:240px;border-radius:20px;overflow:hidden;
  scroll-snap-align:start;background:#fff;
  border:1px solid var(--border);
  box-shadow:0 2px 16px rgba(183,17,158,.06);
  transition:box-shadow .3s,transform .3s;
  opacity:0}
.sec.on .pcard:nth-child(1){animation:bounceIn .5s .02s cubic-bezier(.34,1.3,.64,1) forwards}
.sec.on .pcard:nth-child(2){animation:bounceIn .5s .1s cubic-bezier(.34,1.3,.64,1) forwards}
.sec.on .pcard:nth-child(3){animation:bounceIn .5s .17s cubic-bezier(.34,1.3,.64,1) forwards}
.sec.on .pcard:nth-child(4){animation:bounceIn .5s .24s cubic-bezier(.34,1.3,.64,1) forwards}
.sec.on .pcard:nth-child(5){animation:bounceIn .5s .30s cubic-bezier(.34,1.3,.64,1) forwards}
.sec.on .pcard:nth-child(6){animation:bounceIn .5s .36s cubic-bezier(.34,1.3,.64,1) forwards}
.sec.on .pcard:nth-child(7){animation:bounceIn .5s .42s cubic-bezier(.34,1.3,.64,1) forwards}
.sec.on .pcard:nth-child(8){animation:bounceIn .5s .48s cubic-bezier(.34,1.3,.64,1) forwards}
.pcard:hover{transform:translateY(-8px) scale(1.02);box-shadow:0 24px 56px rgba(183,17,158,.18)}
.pcard:active{transform:scale(.97);box-shadow:0 4px 16px rgba(183,17,158,.12);transition:.1s}
.pcard-img{aspect-ratio:3/4;overflow:hidden;background:var(--soft);position:relative}
.pcard-img img{width:100%;height:100%;object-fit:cover;transition:transform .55s cubic-bezier(.25,.46,.45,.94);display:block}
.pcard:hover .pcard-img img{transform:scale(1.06)}
.pcard-body{padding:14px 16px 18px}
.pcard-name{font-family:'Cormorant Garamond',serif;font-size:1rem;font-weight:500;
  line-height:1.25;color:var(--ink);margin-bottom:10px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.pcard-price{font-size:.88rem;font-weight:500;color:var(--fu);margin-bottom:12px}
.pcard-price.nopr{display:none}
.pcard-btns{display:flex;gap:6px}
.pcard-btn-ver{flex:1;padding:8px 12px;border-radius:50px;border:1px solid var(--border);
  background:#fff;color:var(--mid);font-family:'DM Sans';font-size:.68rem;font-weight:500;
  text-align:center;text-decoration:none;transition:.2s}
.pcard-btn-ver:hover{border-color:var(--fu);color:var(--fu)}
.pcard-btn-wa{flex:1;padding:8px 12px;border-radius:50px;
  background:linear-gradient(135deg,#25D366,#1AAF55);color:#fff;
  font-family:'DM Sans';font-size:.68rem;font-weight:500;text-align:center;
  text-decoration:none;display:flex;align-items:center;justify-content:center;gap:4px;transition:.2s}
.pcard-btn-wa:hover{opacity:.9}
.pcard-btn-wa svg{width:11px;height:11px;fill:#fff;flex-shrink:0}

/* CARD VER TODO */
.card-all{background:var(--ink);border-color:transparent;display:flex;align-items:center;justify-content:center}
.card-all-in{padding:24px 20px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:16px}
.card-all-ring{width:56px;height:56px;border-radius:50%;background:var(--grad);
  display:flex;align-items:center;justify-content:center}
.card-all-ring svg{width:20px;height:20px;stroke:#fff;fill:none;stroke-width:1.5}
.card-all-name{font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-style:italic;color:#fff;line-height:1.2}
.card-all-n{font-size:.65rem;color:rgba(255,255,255,.4);letter-spacing:.06em;text-transform:uppercase}
.card-all-btn{padding:8px 22px;border-radius:50px;border:1px solid rgba(255,255,255,.2);
  color:rgba(255,255,255,.65);font-size:.7rem;text-decoration:none;transition:.2s;display:inline-block}
.card-all-btn:hover{border-color:#fff;color:#fff}

/* SEC FOOTER */
.sec-foot{padding:20px 64px 56px;display:flex;align-items:center;gap:0;opacity:0;transition:opacity .35s .3s}
.sec.on .sec-foot{opacity:1}
.sec-foot-line{flex:1;height:1px;background:var(--border)}
.sec-foot-ver{display:inline-flex;align-items:center;gap:8px;margin:0 20px;
  font-size:.7rem;font-weight:500;color:var(--fu);letter-spacing:.08em;text-transform:uppercase;
  text-decoration:none;white-space:nowrap;transition:.2s}
.sec-foot-ver:hover{color:var(--vi)}
.sec-foot-ver svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2}

/* PREFOOTER */
.prefooter{background:var(--ink);padding:clamp(56px,9vw,100px) clamp(20px,5vw,48px);text-align:center;position:relative;overflow:hidden}
.prefooter::before{content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse 60% 70% at 15% 50%,rgba(247,37,133,.12),transparent),
             radial-gradient(ellipse 55% 60% at 85% 30%,rgba(114,9,183,.15),transparent)}
.prefooter::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--grad)}
.pf-in{position:relative;z-index:1;max-width:520px;margin:0 auto;display:flex;flex-direction:column;align-items:center;width:100%}
.pf-badge{padding:5px 16px;border-radius:100px;border:1px solid rgba(255,255,255,.12);
  font-size:.55rem;font-weight:500;letter-spacing:.2em;text-transform:uppercase;
  color:rgba(255,255,255,.4);margin-bottom:24px;display:inline-block}
.prefooter h2{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,7vw,3.4rem);
  font-weight:500;line-height:1;letter-spacing:-.01em;color:#fff;margin-bottom:14px}
.prefooter h2 em{font-style:italic;background:var(--gs);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.prefooter p{font-size:clamp(.78rem,2.2vw,.88rem);font-weight:300;color:rgba(255,255,255,.42);line-height:1.7;margin-bottom:30px;max-width:380px}
.pf-actions{display:grid;grid-template-columns:1fr;gap:12px;width:100%;max-width:360px;margin-bottom:22px}
.pf-btn{display:flex;align-items:center;justify-content:center;gap:9px;
  padding:14px 20px;border-radius:14px;font-size:.82rem;font-weight:500;letter-spacing:.03em;
  text-decoration:none;transition:.2s;position:relative;overflow:hidden;white-space:nowrap}
.pf-btn svg{width:17px;height:17px;flex-shrink:0}
.pf-btn.wa{background:linear-gradient(135deg,#25D366,#1AAF55);color:#fff;
  box-shadow:0 6px 24px rgba(37,211,102,.28)}
.pf-btn.wa:hover{transform:translateY(-2px);box-shadow:0 12px 36px rgba(37,211,102,.38)}
.pf-btn.wa svg{fill:#fff}
.pf-btn.cat{background:rgba(255,255,255,.06);color:#fff;border:1px solid rgba(255,255,255,.14)}
.pf-btn.cat:hover{background:rgba(255,255,255,.11);border-color:rgba(255,255,255,.26);transform:translateY(-2px)}
.pf-btn.cat svg{stroke:#fff;fill:none;stroke-width:1.8}
.pf-btn.asesor{background:linear-gradient(135deg,#B5179E,#7209B7);color:#fff;
  box-shadow:0 6px 24px rgba(181,23,158,.3)}
.pf-btn.asesor:hover{transform:translateY(-2px);box-shadow:0 12px 36px rgba(181,23,158,.42)}
.pf-btn.asesor svg{stroke:#fff;fill:none;stroke-width:1.8}
.pf-btn.asesor .pf-new{position:absolute;top:-7px;right:10px;
  background:linear-gradient(135deg,#F72585,#B5179E);color:#fff;
  font-size:.55rem;font-weight:600;letter-spacing:.1em;padding:2px 8px;border-radius:50px;
  text-transform:uppercase;box-shadow:0 2px 8px rgba(247,37,133,.4)}
.pf-note{font-size:.68rem;color:rgba(255,255,255,.3);letter-spacing:.04em;
  line-height:1.6;margin-top:4px;max-width:320px}
.pf-note strong{color:rgba(255,255,255,.55);font-weight:500}
@media(min-width:560px){
  .pf-actions{grid-template-columns:1fr 1fr;max-width:460px}
  .pf-btn.wa{grid-column:1/-1}
}

/* FOOTER */
footer{background:#0f0416;padding:56px 64px 40px}
.ft-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:56px;margin-bottom:44px;
  padding-bottom:36px;border-bottom:1px solid rgba(255,255,255,.06)}
.ft-brand img{height:52px;filter:brightness(0) invert(1);opacity:.5;display:block;margin-bottom:16px}
.ft-brand p{font-size:.78rem;font-weight:300;color:rgba(255,255,255,.28);line-height:1.8}
.ft-col h4{font-size:.55rem;font-weight:500;letter-spacing:.2em;text-transform:uppercase;
  color:rgba(255,255,255,.2);margin-bottom:16px}
.ft-col a{display:block;font-size:.78rem;font-weight:300;color:rgba(255,255,255,.38);
  margin-bottom:10px;transition:.2s}
.ft-col a:hover{color:#fff}
.ft-bottom p{font-size:.65rem;color:rgba(255,255,255,.15)}

/* FLOATS */
.floats{position:fixed;bottom:24px;right:24px;z-index:400;display:flex;flex-direction:column;gap:10px;align-items:flex-end}
.wa-float{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#25D366,#1AAF55);
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 6px 24px rgba(37,211,102,.4);text-decoration:none;transition:.25s}
.wa-float:hover{transform:scale(1.1)}
.wa-float svg{width:26px;height:26px;fill:#fff}

/* Humidificadores: sin blur, que se vean las plantas */
[data-cat="Humidificadores"] .sec-bg{height:420px}
[data-cat="Humidificadores"] .sec-bg img{
  filter:none;
  width:62%;
  opacity:0
}
[data-cat="Humidificadores"].on .sec-bg img{opacity:.42}
/* Flecha hero scroll */
.hero-scroll{
  position:absolute;bottom:28px;left:50%;transform:translateX(-50%);
  background:none;border:none;cursor:pointer;z-index:5;
  display:flex;flex-direction:column;align-items:center;gap:4px;
  animation:heroBounce 2.5s ease-in-out infinite
}
.hero-scroll span{font-family:'Cormorant Garamond',serif;font-size:.65rem;font-style:italic;
  color:var(--fu);letter-spacing:.08em;opacity:.7}
.hero-scroll svg{width:22px;height:22px;stroke:var(--fu);fill:none;stroke-width:1.5;opacity:.6}
@keyframes heroBounce{0%,100%{transform:translateX(-50%) translateY(0)}55%{transform:translateX(-50%) translateY(7px)}}

/* Transiciones suaves entre secciones */
.sec{border-top:none}
.sec + .sec{
  background:linear-gradient(to bottom,rgba(255,250,255,0) 0px,var(--pale) 32px)
}
.sec-divider{height:1px;background:linear-gradient(90deg,transparent 5%,rgba(183,17,158,.1) 50%,transparent 95%);margin:0 48px}

/* Scroll snap mobile - TikTok style */
@media(max-width:640px){
  html{scroll-snap-type:y mandatory;scroll-padding-top:90px}
  .hero{scroll-snap-align:start;scroll-snap-stop:always}
  .sec{scroll-snap-align:start;scroll-snap-stop:always}
}
@media(max-width:640px){
  .cat-tabs{display:none}
  .sec-head{padding:32px 20px 0}
  .sec-link{display:none}
  .sec-track-wrap{padding:24px 20px 0}
  .sec-foot{
    display:flex;padding:12px 20px;
    align-items:center;justify-content:space-between;gap:0
  }
  .sec-foot-line{display:none}
  .sec-foot-ver{
    display:flex;align-items:center;gap:5px;
    font-size:.68rem;font-weight:500;color:var(--fu);
    letter-spacing:.04em;text-transform:uppercase;
    border-bottom:1px solid rgba(183,17,158,.2);padding-bottom:2px
  }
  .sec-foot-ver svg{width:10px;height:10px;stroke:var(--fu);fill:none;stroke-width:2}
  .sec-flower{display:none}
  .sec-foot-left{display:flex;align-items:center;gap:4px;cursor:pointer;border:none;background:none;padding:0}
  .sec-foot-left .fl{font-size:.9rem;animation:flBounce 2.2s ease-in-out infinite}
  .sec-foot-left .fl-arrow{font-size:.58rem;color:var(--fu);opacity:.7;letter-spacing:.06em;text-transform:uppercase;font-family:'DM Sans'}
  @keyframes flBounce{0%,100%{transform:translateY(0)}55%{transform:translateY(5px)}}
  .sec-foot-right{width:60px;display:flex;justify-content:flex-end}
  .pcard{width:210px}
  footer{padding:44px 20px 36px}
  .ft-grid{grid-template-columns:1fr;gap:32px}
  .prefooter{padding:72px 24px}
  .floats{bottom:18px;right:16px}
  [data-cat="Humidificadores"] .sec-bg{height:360px}
  [data-cat="Humidificadores"] .sec-bg img{width:85%;right:auto;left:0}
  .sec-next{
    display:flex;flex-direction:column;align-items:center;gap:0;
    background:none;border:none;cursor:pointer;
    width:100%;padding:20px 0 20px
  }
  .sec-next .arr-line{width:1px;height:18px;
    background:linear-gradient(to bottom,rgba(183,17,158,.3),transparent)}
  .sec-next .arr-heart{font-size:.85rem;animation:heartBounce 2.2s ease-in-out infinite}
  @keyframes heartBounce{0%,100%{transform:translateY(0)}55%{transform:translateY(7px)}}
}
</style>
</head>
<body>
<div id="pg"></div>

<div class="ticker">
  <div class="ticker-track">
    <?php for($t=0;$t<2;$t++): ?>
    <span class="ticker-item">Distribuidores directos <span class="ticker-sep">·</span> Envío a todo el país <span class="ticker-sep">·</span> Stock permanente <span class="ticker-sep">·</span> Atención directa <span class="ticker-sep">·</span> Sin intermediarios <span class="ticker-sep">·</span></span>
    <?php endfor ?>
  </div>
</div>

<nav id="nav">
  <a href="/" class="nav-logo"><img src="logo.png" alt="Mundo Accesorios"></a>
  <a class="nav-wa" href="<?=$WA?>" target="_blank" rel="noopener">
    <svg viewBox="0 0 24 24"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51H8.1c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.87 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35m-5.42 7.4h-.01a9.87 9.87 0 01-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37A9.86 9.86 0 012.06 12C2.06 6.5 6.5 2.06 12 2.06S21.94 6.5 21.94 12 17.5 21.94 12 21.94m8.41-18.3A11.82 11.82 0 0012.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 005.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89 0-3.18-1.24-6.16-3.48-8.41"/></svg>
    WhatsApp
  </a>
</nav>

<div class="cat-tabs" id="catTabs">
  <div class="tabs-inner">
    <?php foreach($secciones as $i=>$s): ?>
    <button class="ct" onclick="scrollToSec(<?=$i?>)"><?=htmlspecialchars($s['label'])?></button>
    <?php endforeach ?>
    <a class="ct" href="catalogo.php">Ver todo</a>
  </div>
</div>

<!-- HERO -->
<section class="hero">
  <div class="hero-left">
    <div class="hero-proof">
      <span class="hero-proof-dot"></span>
      Respuesta en minutos &middot; Envío nacional
    </div>
    <h1 class="hero-h1">El accesorio<br>que buscas <em>ya&nbsp;está&nbsp;aquí.</em></h1>
    <p class="hero-sub">Termos, audífonos, relojes, cargadores y más. Siempre en stock.</p>
    <div class="hero-btns">
      <a class="hero-cta" href="catalogo.php">Ver catálogo</a>
    </div>
    <a class="hero-explore-link" href="explore.php">✦ modo explorar</a>
  </div>
  <div class="hero-right">
    <?php if(count($hero_fotos)>=2): ?>
    <div class="hero-grid">
      <img class="hg0" src="<?=$hero_fotos[0]?>" alt="" loading="eager" fetchpriority="high">
      <img src="<?=$hero_fotos[1]?>" alt="" loading="eager">
      <img src="<?=($hero_fotos[2]??$hero_fotos[1])?>" alt="" loading="eager">
    </div>
    <?php elseif(count($hero_fotos)===1): ?>
    <div class="hero-solo"><img src="<?=$hero_fotos[0]?>" alt="" loading="eager" fetchpriority="high"></div>
    <?php else: ?>
    <div class="hero-ph"></div>
    <?php endif ?>
    <div class="hero-badge">Stock permanente &middot; Envío a todo el país</div>
  </div>
  <button class="hero-scroll" onclick="nextSec(-1)">
    <span>descubre</span>
    <svg viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </button>
</section>

<?php
$WA_SVG='<svg viewBox="0 0 24 24"><path fill="#fff" d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51H8.1c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.87 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35m-5.42 7.4h-.01a9.87 9.87 0 01-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37A9.86 9.86 0 012.06 12C2.06 6.5 6.5 2.06 12 2.06S21.94 6.5 21.94 12 17.5 21.94 12 21.94m8.41-18.3A11.82 11.82 0 0012.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 005.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89 0-3.18-1.24-6.16-3.48-8.41"/></svg>';
$ARR_SVG='<svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>

<!-- SECCIONES -->
<?php foreach($secciones as $i=>$sec):
  $url='catalogo.php?cat='.urlencode($sec['cat']);
  // Buscar foto lifestyle (slot 2, 3, 4) para el fondo — no el slot 1 (producto solo)
  $bg_foto = null;
  foreach(array_slice($sec['items'],0,4) as $bp){
      foreach([2,3,4] as $bslot){
          $bf=foto($bp['id'],$bslot); if($bf){ $bg_foto=$bf; break 2; }
      }
  }
?>
<section class="sec" id="sec-<?=$i?>" data-reveal data-cat="<?=htmlspecialchars($sec['cat'])?>">
  <?php if($bg_foto): ?>
  <div class="sec-bg"><img src="<?=$bg_foto?>" alt="" loading="lazy"></div>
  <?php endif ?>
  <div class="sec-head">
    <div class="sec-head-text">
      <div class="sec-pill"><?=htmlspecialchars($sec['label'])?></div>
      <h2 class="sec-h2"><?=$sec['h2']?></h2>
      <p class="sec-desc"><?=htmlspecialchars($sec['desc'])?></p>
    </div>
    <a class="sec-link" href="<?=$url?>">
      Ver colección <?=$ARR_SVG?>
    </a>
    <a class="sec-explore-btn" href="explore.php?cat=<?=urlencode($sec['cat'])?>" title="Ver en modo explorar">✦</a>
  </div>

  <div class="sec-track-wrap">
    <div class="track" id="t<?=$i?>">
      <?php foreach($sec['items'] as $p):
        $id=$p['id']; $f=foto_home($p); $pr=$p['precio']??0;
        $wa_msg=urlencode('Hola, me interesa: '.$p['nombre'].($pr?' — '.pf($pr):''));
      ?>
      <div class="pcard" onclick="location.href='producto.php?id=<?=$id?>'">
        <div class="pcard-img">
          <img src="<?=$f?>" alt="<?=htmlspecialchars($p['nombre'])?>" loading="lazy">
        </div>
        <div class="pcard-body">
          <div class="pcard-name"><?=htmlspecialchars($p['nombre'])?></div>
<?php $pr_cls=$pr>0?'':'nopr'; $pr_val=$pr>0?pf($pr):''; ?>
          <div class="pcard-price <?=$pr_cls?>"><?=$pr_val?></div>
          <div class="pcard-btns">
            <a class="pcard-btn-ver" href="producto.php?id=<?=$id?>" onclick="event.stopPropagation()">Ver</a>
            <a class="pcard-btn-wa" href="https://wa.me/573233453004?text=<?=$wa_msg?>" onclick="event.stopPropagation()" target="_blank">
              <?=$WA_SVG?> Pedir
            </a>
          </div>
        </div>
      </div>
      <?php endforeach ?>
      <div class="pcard card-all" style="aspect-ratio:3/4" onclick="location.href='<?=$url?>'">
        <div class="card-all-in">
          <div class="card-all-ring"><?=$ARR_SVG?></div>
          <div class="card-all-name">Ver toda<br>la colección</div>
          <div class="card-all-n"><?=$sec['total']?> referencias</div>
          <a class="card-all-btn" href="<?=$url?>" onclick="event.stopPropagation()">Explorar &rarr;</a>
        </div>
      </div>
    </div>
  </div>

  <div class="sec-foot">
    <div class="sec-foot-line"></div>
    <button class="sec-foot-left" onclick="nextSec(<?=$i?>)">
      <span class="fl">🌸</span>
      <span class="fl-arrow">descubre</span>
    </button>
    <a class="sec-foot-ver" href="<?=$url?>">
      Ver <?=htmlspecialchars($sec['label'])?>
      <svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </a>
    <div class="sec-foot-right"></div>
    <div class="sec-foot-line"></div>
  </div>
  <button class="sec-next" onclick="nextSec(<?=$i?>)" aria-label="Ver siguiente" style="display:none">
    <span class="arr-line"></span>
    <span class="arr-heart">🌸</span>
  </button>
</section>
<div class="sec-divider"></div>
<?php endforeach ?>

<!-- PREFOOTER -->
<div class="prefooter" data-reveal>
  <div class="pf-in">
    <div class="pf-badge">Atención directa &middot; Sin intermediarios</div>
    <h2>¿Lista para<br><em>encontrarlo?</em></h2>
    <p>Stock real. Precio directo. Respuesta en minutos. Elegí cómo seguir:</p>
    <div class="pf-actions">
      <a class="pf-btn wa" href="<?=$WA?>" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51H8.1c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.87 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35m-5.42 7.4h-.01a9.87 9.87 0 01-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37A9.86 9.86 0 012.06 12C2.06 6.5 6.5 2.06 12 2.06S21.94 6.5 21.94 12 17.5 21.94 12 21.94m8.41-18.3A11.82 11.82 0 0012.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 005.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89 0-3.18-1.24-6.16-3.48-8.41"/></svg>
        Escribir por WhatsApp
      </a>
      <a class="pf-btn cat" href="catalogo.php">
        <svg viewBox="0 0 24 24"><path d="M3 5h18M3 12h18M3 19h18" stroke-linecap="round"/></svg>
        Ver catálogo
      </a>
      <a class="pf-btn asesor" href="asesor.php">
        <span class="pf-new">nuevo</span>
        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M12 14c-4 0-7 2-7 5v1h14v-1c0-3-3-5-7-5z"/><path d="M8 18l1 2M16 18l-1 2" stroke-linecap="round"/></svg>
        Asesor virtual
      </a>
    </div>
    <p class="pf-note">
      <strong>Atención humana por WhatsApp</strong> o probá nuestro asesor virtual que te ayuda a elegir según tu presupuesto.
    </p>
  </div>
</div>

<footer>
  <div class="ft-grid">
    <div class="ft-brand">
      <img src="logo.png" alt="Mundo Accesorios">
      <p>Distribuidores directos de accesorios tech. Stock real, precio directo, atención personalizada.</p>
    </div>
    <div class="ft-col">
      <h4>Categorías</h4>
      <?php foreach(array_slice($secciones,0,5) as $s): ?>
      <a href="catalogo.php?cat=<?=urlencode($s['cat'])?>"><?=htmlspecialchars($s['label'])?></a>
      <?php endforeach ?>
      <a href="catalogo.php">Ver todo &rarr;</a>
    </div>
    <div class="ft-col">
      <h4>Contacto</h4>
      <a href="<?=$WA?>" target="_blank">WhatsApp directo</a>
      <a href="catalogo.php">Catálogo completo</a>
    </div>
  </div>
  <div class="ft-bottom">
    <p>&copy; <?=date('Y')?> Mundo Accesorios</p>
  </div>
</footer>

<div class="floats">
  <a class="wa-float" href="<?=$WA?>" target="_blank" rel="noopener">
    <svg viewBox="0 0 24 24"><path fill="#fff" d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51H8.1c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.87 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35m-5.42 7.4h-.01a9.87 9.87 0 01-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37A9.86 9.86 0 012.06 12C2.06 6.5 6.5 2.06 12 2.06S21.94 6.5 21.94 12 17.5 21.94 12 21.94m8.41-18.3A11.82 11.82 0 0012.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 005.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89 0-3.18-1.24-6.16-3.48-8.41"/></svg>
  </a>
</div>

<script>
var WA='<?=$WA?>';
// Tabs se ocultan al bajar, reaparecen al subir
(function(){
  var tabs = document.getElementById('catTabs');
  var lastY = 0, ticking = false;
  window.addEventListener('scroll', function(){
    if(!ticking){
      requestAnimationFrame(function(){
        var y = window.scrollY;
        if(y > lastY && y > 160) tabs.classList.add('hidden');
        else tabs.classList.remove('hidden');
        lastY = y;
        ticking = false;
      });
      ticking = true;
    }
  },{passive:true});
})();

// Progress bar
(function(){
  var pg=document.getElementById('pg');
  window.addEventListener('scroll',function(){
    var p=window.scrollY/(document.body.scrollHeight-window.innerHeight)*100;
    pg.style.width=Math.min(p,100)+'%';
  },{passive:true});
})();

// Cat tabs scroll
function scrollToSec(i){
  var el=document.getElementById('sec-'+i);
  if(!el) return;
  var offset=62+28+48;
  window.scrollTo({top:el.offsetTop-offset,behavior:'smooth'});
}
function nextSec(i){
  var target = i < 0
    ? document.getElementById('sec-0')
    : document.getElementById('sec-'+(i+1));
  if(!target){ window.scrollTo({top:document.body.scrollHeight,behavior:'smooth'}); return; }
  var pill = target.querySelector('.sec-pill');
  (pill || target).scrollIntoView({behavior:'smooth', block:'start'});
}

// Active tab on scroll
(function(){
  var tabs=document.querySelectorAll('.ct');
  var secs=document.querySelectorAll('.sec[data-reveal]');
  window.addEventListener('scroll',function(){
    var mid=window.scrollY+window.innerHeight/2;
    var active=-1;
    secs.forEach(function(s,i){ if(s.offsetTop<=mid) active=i; });
    tabs.forEach(function(t,i){ t.classList.toggle('act',i===active); });
  },{passive:true});
})();

// Reveal on scroll — solo anima cuando el usuario llega
(function(){
  var io=new IntersectionObserver(function(entries){
    entries.forEach(function(x){
      if(x.isIntersecting){
        x.target.classList.add('on');
        io.unobserve(x.target);
      }
    });
  },{threshold:0.08,rootMargin:'0px 0px -60px 0px'});
  document.querySelectorAll('[data-reveal]').forEach(function(el){
    var rect=el.getBoundingClientRect();
    // Solo añadir .on inmediatamente si ya es visible ahora mismo
    if(rect.top < window.innerHeight*0.75 && rect.bottom > 0){
      el.classList.add('on');
    } else {
      io.observe(el);
    }
  });
})();



// Drag scroll on tracks
document.querySelectorAll('.track').forEach(function(t){
  var dn=false,sx=0,sl=0;
  t.addEventListener('mousedown',function(e){dn=true;sx=e.pageX-t.offsetLeft;sl=t.scrollLeft;t.classList.add('dragging')});
  t.addEventListener('mouseleave',function(){dn=false;t.classList.remove('dragging')});
  t.addEventListener('mouseup',function(){dn=false;t.classList.remove('dragging')});
  t.addEventListener('mousemove',function(e){if(!dn)return;e.preventDefault();t.scrollLeft=sl-(e.pageX-t.offsetLeft-sx)});
});
</script>
</body>
</html>
