<?php
require_once __DIR__ . '/afiliados_lib.php';
afil_handle_incoming_ref();

$WA_BASE = 'https://wa.me/573233453004';
$data    = file_exists(__DIR__.'/catalogo.json')
           ? json_decode(file_get_contents(__DIR__.'/catalogo.json'),true)
           : ['v'=>1,'productos'=>[]];
$activos = array_values(array_filter($data['productos']??[], fn($p)=>($p['activo']??false)&&($p['precio']??0)>0));

function foto(int $id, int $slot=1):?string{
    $base=$slot===1?$id:"{$id}_{$slot}";
    foreach(['jpg','jpeg','png','webp'] as $e)
        if(file_exists(__DIR__."/uploads/fotos-productos/{$base}.{$e}"))
            return "/uploads/fotos-productos/{$base}.{$e}";
    return null;
}
function pf(int $n):string{ return '$'.number_format($n,0,',','.'); }

// Categorías — SIN vidrios ni protectores
// Mismo orden que el home — sin Vidrios ni Cases
$CATS=[
  ['cat'=>'Termos',               'palettes'=>[['#B5179E','#F72585'],['#7B0080','#C4007A'],['#E91E8C','#FF6BB5']]],
  ['cat'=>'Audífonos y Diademas', 'palettes'=>[['#002855','#0066CC'],['#003A80','#0099FF'],['#001A44','#0044AA']]],
  ['cat'=>'Relojes',              'palettes'=>[['#3D0B68','#7F1FDB'],['#1A0033','#5500AA'],['#5C0F8B','#9B35E8']]],
  ['cat'=>'Combos',               'palettes'=>[['#5C0A34','#B5179E'],['#7B0A4A','#D4209E'],['#3D0022','#8B0060']]],
  ['cat'=>'Parlantes',            'palettes'=>[['#0A1A2A','#1A4A7A'],['#001020','#003060'],['#0D2137','#1E5C9B']]],
  ['cat'=>'Cargadores y Cables',  'palettes'=>[['#1A1A0A','#4A6010'],['#0A1A00','#2A4A00'],['#2A2200','#606020']]],
  ['cat'=>'Aros de Luz y Tripodes','palettes'=>[['#2A1A00','#7A4400'],['#1A0A00','#5A3000'],['#3A2200','#8A5500']]],
  ['cat'=>'Micrófonos',           'palettes'=>[['#1A0A2E','#6A0DAD'],['#0D0020','#4A0080'],['#2A0A45','#8A20CC']]],
  ['cat'=>'Power Bank',           'palettes'=>[['#003322','#007755'],['#001A11','#005533'],['#004433','#009966']]],
  ['cat'=>'Humidificadores',      'palettes'=>[['#003322','#006644'],['#004422','#008855'],['#002211','#005533']]],
  ['cat'=>'Soporte Celular',      'palettes'=>[['#2A1A00','#7A4A00'],['#1A0A00','#5A3000'],['#3A2200','#8A5500']]],
  ['cat'=>'Tecnología',           'palettes'=>[['#003050','#0066AA'],['#001A30','#004480'],['#004466','#0088CC']]],
  ['cat'=>'Otros',                'palettes'=>[['#1A0A1E','#5A2A6E'],['#0A0010','#3A1A4E'],['#2A1A30','#6A3A80']]],
];
$micPro=['bm-','bm800','bm-800','condenser','estudio','profesional','xlr','cardioide','phantom'];

$cats_data=[];
$BATCH=10;
foreach($CATS as $cfg){
    $prods=array_values(array_filter($activos,fn($p)=>($p['categoria']??'')===$cfg['cat']));
    if(!$prods) continue;

    // Orden especial por categoría
    if($cfg['cat']==='Termos'){
        usort($prods,fn($a,$b)=>
            (int)(stripos($b['nombre'],'rmol')!==false)-(int)(stripos($a['nombre'],'rmol')!==false));
    }
    if($cfg['cat']==='Micrófonos'){
        usort($prods,function($a,$b) use($micPro){
            $aP=array_filter($micPro,fn($k)=>stripos($a['nombre'],$k)!==false);
            $bP=array_filter($micPro,fn($k)=>stripos($b['nombre'],$k)!==false);
            return count($bP)-count($aP);
        });
    }

    // Recoger todos con foto (arrastramos "destacado" para priorizar)
    $all=[];
    foreach($prods as $p){
        $f=foto($p['id'],1); if(!$f) continue;
        $all[]=[
            'id'=>$p['id'],
            'nombre'=>$p['nombre'],
            'precio'=>$p['precio'],
            'foto'=>$f,
            'destacado'=>!empty($p['destacado']),
        ];
    }
    if(count($all)<2) continue;

    // Variedad por visita: destacados primero, luego el resto aleatorio.
    // Así la primera tanda muestra lo mejor y en cada recarga aparece una mezcla distinta.
    $destacados=array_values(array_filter($all, fn($i)=>$i['destacado']));
    $resto     =array_values(array_filter($all, fn($i)=>!$i['destacado']));
    shuffle($destacados);
    shuffle($resto);
    $all=array_merge($destacados,$resto);

    // Dividir en tandas — cada tanda tiene su propia paleta
    $batches=array_chunk($all,$BATCH);
    $totalB=count($batches);
    $pals=$cfg['palettes'];
    foreach($batches as $bi=>$items){
        $pal=$pals[$bi % count($pals)];
        $cats_data[]=[
            'cat'=>$cfg['cat'],
            'color1'=>$pal[0],
            'color2'=>$pal[1],
            'items'=>$items,
            'batch'=>$bi,
            'total_batches'=>$totalB,
            'cat_total'=>count($all),
        ];
    }
}
// Cat de inicio desde URL
$startCat=0;
$reqCat=$_GET['cat']??'';
if($reqCat) foreach($cats_data as $i=>$c) if($c['cat']===$reqCat&&$c['batch']===0){$startCat=$i;break;}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Explorar · Mundo Accesorios</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;1,400;1,500&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{height:100%;overflow:hidden;background:#000;font-family:'DM Sans',sans-serif;-webkit-font-smoothing:antialiased}

/* FEED VERTICAL — JS controla el scroll */
#feed{
  height:100dvh;width:100%;
  overflow:hidden;
  position:relative
}

/* CAT SLIDE */
.cat-slide{
  height:100dvh;width:100%;
  position:absolute;top:0;left:0;
  overflow:hidden;
  transition:transform .45s cubic-bezier(.16,1,.3,1);
  will-change:transform
}

/* TRACK HORIZONTAL */
.prod-track{
  display:flex;
  width:100%;height:100%;
  overflow-x:scroll;overflow-y:hidden;
  scroll-snap-type:x mandatory;
  scrollbar-width:none;
  overscroll-behavior:contain;
  -webkit-overflow-scrolling:touch
}
.prod-track::-webkit-scrollbar{display:none}

/* PRODUCTO */
.prod-slide{
  flex-shrink:0;
  width:100dvw;height:100dvh;
  scroll-snap-align:start;
  scroll-snap-stop:always;
  position:relative;
  overflow:hidden
}

/* FONDO */
.prod-bg{position:absolute;inset:0;z-index:0}
.prod-bg img{width:100%;height:100%;object-fit:cover;object-position:center top}
.prod-bg::after{content:'';position:absolute;inset:0;
  background:linear-gradient(to bottom,rgba(0,0,0,.28) 0%,rgba(0,0,0,.0) 28%,rgba(0,0,0,.0) 52%,rgba(0,0,0,.68) 75%,rgba(0,0,0,.9) 100%)}

/* TOP */
.prod-top{position:absolute;top:0;left:0;right:0;z-index:20;
  padding:max(env(safe-area-inset-top),14px) 16px 0;
  display:flex;align-items:center;justify-content:space-between}
.btn-back{display:flex;align-items:center;gap:5px;color:rgba(255,255,255,.9);
  font-size:.72rem;text-decoration:none;
  background:rgba(0,0,0,.32);backdrop-filter:blur(10px);
  padding:7px 14px;border-radius:50px;border:1px solid rgba(255,255,255,.18)}
.btn-back svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.5}
.cat-badge{display:flex;align-items:center;gap:6px;
  background:rgba(0,0,0,.32);backdrop-filter:blur(10px);
  padding:6px 14px;border-radius:50px;border:1px solid rgba(255,255,255,.18);
  font-size:.7rem;font-weight:500;color:#fff}
.cat-logo{height:20px;filter:brightness(0) invert(1);opacity:.9}

/* ACCIONES LATERALES - DERECHA */
.side-actions{
  position:absolute;right:14px;
  bottom:calc(env(safe-area-inset-bottom,0px) + 148px);
  z-index:30;
  display:flex;flex-direction:column;align-items:center;gap:14px;
  touch-action:manipulation
}
.side-btn{display:flex;flex-direction:column;align-items:center;gap:3px;
  background:none;border:none;cursor:pointer;text-decoration:none;
  touch-action:manipulation;-webkit-user-select:none}
.side-icon{width:44px;height:44px;border-radius:50%;
  background:rgba(0,0,0,.42);backdrop-filter:blur(12px);
  border:1.5px solid rgba(255,255,255,.22);
  display:flex;align-items:center;justify-content:center;
  font-size:1.1rem;transition:.15s}
.side-icon svg{width:18px;height:18px;stroke:#fff;fill:none;stroke-width:1.8}
.side-icon:active{transform:scale(.85)}
.side-icon.liked{background:rgba(220,38,86,.38);border-color:rgba(255,80,120,.6)}
.side-lbl{font-size:.52rem;color:rgba(255,255,255,.65);font-weight:500;letter-spacing:.04em}

/* INFO INFERIOR */
.prod-info{position:absolute;bottom:0;left:0;right:0;z-index:20;
  padding:0 20px max(env(safe-area-inset-bottom),18px)}
.prod-account{display:flex;align-items:center;gap:8px;margin-bottom:10px}
.prod-avatar{width:30px;height:30px;border-radius:50%;overflow:hidden;
  border:1.5px solid rgba(255,255,255,.45);flex-shrink:0;
  background:linear-gradient(135deg,#7209B7,#F72585);
  display:flex;align-items:center;justify-content:center;
  font-size:9px;font-weight:600;color:#fff;letter-spacing:.03em}
.prod-user{font-size:.75rem;font-weight:600;color:#fff;line-height:1}
.prod-handle{font-size:.6rem;color:rgba(255,255,255,.5);margin-top:1px}
.prod-name{font-family:'Cormorant Garamond',serif;
  font-size:clamp(1.5rem,5.5vw,2rem);font-weight:500;line-height:1.1;
  color:#fff;margin-bottom:5px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.prod-price-row{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.prod-price{font-size:1.15rem;font-weight:600;color:#fff}
.prod-tag{font-size:.6rem;color:rgba(255,255,255,.45);letter-spacing:.06em;text-transform:uppercase}
.prod-btns{display:flex;gap:9px;margin-bottom:12px}
.btn-wa{flex:1;display:flex;align-items:center;justify-content:center;gap:7px;
  padding:13px;border-radius:14px;
  background:linear-gradient(135deg,#25D366,#1AAF55);
  color:#fff;font-size:.8rem;font-weight:600;text-decoration:none;
  box-shadow:0 4px 18px rgba(37,211,102,.3);touch-action:manipulation}
.btn-wa svg{width:15px;height:15px;fill:#fff;flex-shrink:0}
.btn-ver{width:50px;display:flex;align-items:center;justify-content:center;
  border-radius:14px;background:rgba(255,255,255,.14);
  backdrop-filter:blur(8px);border:1.5px solid rgba(255,255,255,.22);
  color:#fff;font-size:.6rem;text-decoration:none;
  flex-direction:column;gap:2px;touch-action:manipulation}
.btn-ver svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2}

/* DOTS HORIZONTALES */
.h-dots{display:flex;justify-content:center;gap:5px;margin-bottom:2px}
.h-dot{width:5px;height:5px;border-radius:50%;background:rgba(255,255,255,.3);transition:.25s}
.h-dot.act{background:#fff;width:16px;border-radius:3px}

/* DOTS VERTICALES - IZQUIERDA */
.v-dots{position:fixed;left:8px;top:50%;transform:translateY(-50%);
  z-index:100;display:flex;flex-direction:column;gap:6px}
.v-dot{width:3px;height:14px;border-radius:2px;
  background:rgba(255,255,255,.25);transition:.3s;cursor:pointer}
.v-dot.act{background:#fff;height:24px}

/* CAPTION OVERLAY en la pieza */
.caption-overlay{
  position:absolute;left:0;right:0;bottom:0;z-index:40;
  background:linear-gradient(to top,rgba(0,0,0,.92) 0%,rgba(0,0,0,.7) 60%,transparent 100%);
  padding:20px 20px max(env(safe-area-inset-bottom),20px);
  transform:translateY(100%);
  transition:transform .35s cubic-bezier(.16,1,.3,1);
  touch-action:manipulation
}
.caption-overlay.open{transform:translateY(0)}
.caption-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.caption-hdr-txt{font-size:.7rem;font-weight:600;color:rgba(255,255,255,.6);letter-spacing:.06em;text-transform:uppercase}
.caption-close{background:none;border:none;color:rgba(255,255,255,.5);font-size:1.2rem;cursor:pointer;padding:0}
.caption-txt{font-size:.78rem;line-height:1.65;color:rgba(255,255,255,.9);
  white-space:pre-wrap;margin-bottom:12px;
  background:rgba(255,255,255,.06);border-radius:10px;padding:10px 12px}
.caption-btns{display:flex;gap:8px;margin-bottom:8px}
.btn-cap-copy{flex:1;padding:10px;border-radius:10px;
  background:linear-gradient(135deg,#B5179E,#F72585);
  border:none;color:#fff;font-size:.75rem;font-weight:600;cursor:pointer;font-family:'DM Sans'}
.btn-cap-wa{flex:1;padding:10px;border-radius:10px;
  background:linear-gradient(135deg,#25D366,#1AAF55);
  border:none;color:#fff;font-size:.75rem;font-weight:600;cursor:pointer;
  font-family:'DM Sans';text-decoration:none;
  display:flex;align-items:center;justify-content:center;gap:5px}
.btn-cap-next{width:100%;padding:8px;border-radius:10px;
  background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);
  color:rgba(255,255,255,.6);font-size:.72rem;cursor:pointer;font-family:'DM Sans'}
.btn-cap-buy{width:100%;margin-top:6px;padding:11px;border-radius:10px;
  background:linear-gradient(135deg,#25D366,#1AAF55);
  border:none;color:#fff;font-size:.78rem;font-weight:600;
  font-family:'DM Sans';text-decoration:none;
  display:flex;align-items:center;justify-content:center;gap:6px}

/* HINT nav (solo flechas) */
.nav-hint{position:absolute;left:50%;transform:translateX(-50%);
  bottom:calc(env(safe-area-inset-bottom,0px)+6px);
  z-index:25;display:flex;flex-direction:column;align-items:center;gap:0;
  pointer-events:none;opacity:.4}
.nav-hint-row{display:flex;gap:10px}
.nav-hint svg{width:11px;height:11px;stroke:#fff;fill:none;stroke-width:2}
.arr-v{animation:nhV 2.3s ease-in-out infinite}
.arr-h{animation:nhH 2.3s ease-in-out infinite .4s}
@keyframes nhV{0%,100%{transform:translateY(0)}55%{transform:translateY(5px)}}
@keyframes nhH{0%,100%{transform:translateX(0)}55%{transform:translateX(4px)}}
</style>
</head>
<body>
<div id="feed">
<?php foreach($cats_data as $ci=>$cat): ?>
<div class="cat-slide" id="cat-<?=$ci?>" style="transform:translateY(<?=($ci-$startCat)*100?>dvh)">
  <div class="prod-track" id="track-<?=$ci?>">
  <?php foreach($cat['items'] as $pi=>$p):
    $wa=urlencode('Hola linda 🌸 me interesa: '.$p['nombre'].' — '.pf($p['precio']).' ¿está disponible?');
    $wa_duda=urlencode('Hola linda 🌸 tengo una duda sobre: '.$p['nombre']);
  ?>
  <div class="prod-slide" id="ps-<?=$ci?>-<?=$pi?>">
    <div class="prod-bg">
      <img src="<?=$p['foto']?>" alt="<?=htmlspecialchars($p['nombre'])?>" loading="<?=($ci===0&&$pi===0)?'eager':'lazy'?>">
    </div>

    <!-- TOP -->
    <div class="prod-top">
      <?php if($ci===0&&$pi===0): ?>
      <a href="/" class="btn-back"><svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg> Inicio</a>
      <?php else: ?><div style="width:72px"></div><?php endif ?>
      <div class="cat-badge">
        <img src="logo.png" alt="" class="cat-logo">
        <span><?=htmlspecialchars($cat['cat'])?></span>
        <?php if($cat['total_batches']>1): ?>
        <span style="opacity:.6;font-size:.62rem"><?=($cat['batch']+1)?>/<?=$cat['total_batches']?></span>
        <?php endif ?>
      </div>
    </div>

    <!-- ACCIONES LATERALES -->
    <div class="side-actions">
      <button class="side-btn" onclick="toggleLike(this)" aria-label="Me gusta">
        <div class="side-icon">
          <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        </div>
        <span class="side-lbl">me gusta</span>
      </button>
      <a class="side-btn" href="<?=$WA_BASE?>?text=<?=$wa_duda?>" target="_blank" aria-label="Preguntar">
        <div class="side-icon">
          <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <span class="side-lbl">preguntar</span>
      </a>
      <button class="side-btn" onclick="pedirRegalo('<?=$p['id']?>','<?=addslashes($p['nombre'])?>','<?=pf($p['precio'])?>')" aria-label="Pedir regalo">
        <div class="side-icon">
          <svg viewBox="0 0 24 24"><path d="M20 12v10H4V12"/><path d="M22 7H2v5h20V7z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
        </div>
        <span class="side-lbl">pedir 🎁</span>
      </button>
    </div>

    <!-- INFO -->
    <div class="prod-info">
      <div class="prod-account">
        <div class="prod-avatar">MA</div>
        <div>
          <div class="prod-user">Mundo Accesorios</div>
          <div class="prod-handle">@mundoaccesoriosdorada · La Dorada</div>
        </div>
      </div>
      <div class="prod-name"><?=htmlspecialchars($p['nombre'])?></div>
      <div class="prod-price-row">
        <span class="prod-price"><?=pf($p['precio'])?></span>
        <span class="prod-tag">disponible · envío nacional</span>
      </div>
      <div class="prod-btns">
        <a class="btn-wa" href="<?=$WA_BASE?>?text=<?=$wa?>" target="_blank">
          <svg viewBox="0 0 24 24"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51H8.1c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.87 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35m-5.42 7.4h-.01a9.87 9.87 0 01-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37A9.86 9.86 0 012.06 12C2.06 6.5 6.5 2.06 12 2.06S21.94 6.5 21.94 12 17.5 21.94 12 21.94m8.41-18.3A11.82 11.82 0 0012.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 005.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89 0-3.18-1.24-6.16-3.48-8.41"/></svg>
          Lo quiero 🌸
        </a>
        <a class="btn-ver" href="producto.php?id=<?=$p['id']?>">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <span>Ver</span>
        </a>
      </div>
      <div class="h-dots" id="hdots-<?=$ci?>">
        <?php for($d=0;$d<count($cat['items']);$d++): ?>
        <div class="h-dot <?=$d===0?'act':''?>" id="hdot-<?=$ci?>-<?=$d?>"></div>
        <?php endfor ?>
      </div>
    </div>

    <!-- CAPTION OVERLAY -->
    <div class="caption-overlay" id="cap-<?=$ci?>-<?=$pi?>">
      <div class="caption-hdr">
        <span class="caption-hdr-txt">✨ Caption para compartir</span>
        <button class="caption-close" onclick="cerrarCaption(<?=$ci?>,<?=$pi?>)">×</button>
      </div>
      <div class="caption-txt" id="captxt-<?=$ci?>-<?=$pi?>"></div>
      <div class="caption-btns">
        <button class="btn-cap-copy" onclick="copiarCaption(<?=$ci?>,<?=$pi?>)">📋 Copiar</button>
        <a class="btn-cap-wa" id="capwa-<?=$ci?>-<?=$pi?>" href="#" target="_blank">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="#fff"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51H8.1c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.87 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35m-5.42 7.4h-.01a9.87 9.87 0 01-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37A9.86 9.86 0 012.06 12C2.06 6.5 6.5 2.06 12 2.06S21.94 6.5 21.94 12 17.5 21.94 12 21.94m8.41-18.3A11.82 11.82 0 0012.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 005.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89 0-3.18-1.24-6.16-3.48-8.41"/></svg>
          Enviar WA
        </a>
      </div>
      <button class="btn-cap-next" onclick="siguienteCaption(<?=$ci?>,<?=$pi?>,'<?=addslashes($p['nombre'])?>','<?=pf($p['precio'])?>')">✨ Otro mensaje</button>
      <a class="btn-cap-buy" href="<?=$WA_BASE?>?text=<?=urlencode('Hola linda 🌸 quiero comprar: '.$p['nombre'].' ¿está disponible?')?>" target="_blank">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="#fff"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51H8.1c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.87 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35m-5.42 7.4h-.01a9.87 9.87 0 01-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37A9.86 9.86 0 012.06 12C2.06 6.5 6.5 2.06 12 2.06S21.94 6.5 21.94 12 17.5 21.94 12 21.94m8.41-18.3A11.82 11.82 0 0012.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 005.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89 0-3.18-1.24-6.16-3.48-8.41"/></svg>
        Comprar ahora · Respuesta inmediata
      </a>
    </div>

    <!-- HINT -->
    <?php if($pi===0): ?>
    <div class="nav-hint">
      <div class="nav-hint-row">
        <svg class="arr-h" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <svg class="arr-h" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
      <?php if($ci<count($cats_data)-1): ?>
      <svg class="arr-v" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <?php endif ?>
    </div>
    <?php endif ?>
  </div>
  <?php endforeach ?>
  </div>
</div>
<?php endforeach ?>
</div>

<!-- DOTS VERTICALES IZQUIERDA -->
<div class="v-dots" id="vdots">
<?php foreach($cats_data as $i=>$c): ?>
<div class="v-dot <?=$i===0?'act':''?>" id="vdot-<?=$i?>" onclick="goCategory(<?=$i?>)"></div>
<?php endforeach ?>
</div>

<script>
var CATS_N=<?=count($cats_data)?>;
var PRODS_N=[<?=implode(',',array_map(fn($c)=>count($c['items']),$cats_data))?>];
var curCat=<?=$startCat?>,curProds=PRODS_N.map(()=>0);
var feed=document.getElementById('feed');
var animating=false;
var autoTimer=null,capIdx={};

// Posicionar slides — solo la activa es visible
function posSlides(animate){
  for(var i=0;i<CATS_N;i++){
    var el=document.getElementById('cat-'+i);
    if(!el) continue;
    if(!animate) el.style.transition='none';
    el.style.transform='translateY('+(i-curCat)*100+'dvh)';
    if(!animate) setTimeout(function(){el.style.transition=''},50);
  }
}
// Dot inicial correcto
document.getElementById('vdot-0')?.classList.remove('act');
document.getElementById('vdot-'+curCat)?.classList.add('act');
posSlides(false);

function goCategory(i,animate){
  if(i<0||i>=CATS_N||animating) return;
  animating=true;
  var prev=curCat;
  curCat=i;
  // Actualizar dots
  document.getElementById('vdot-'+prev)?.classList.remove('act');
  document.getElementById('vdot-'+i)?.classList.add('act');
  posSlides();
  setTimeout(function(){animating=false;},480);
}

// Swipe detection
var tStartX,tStartY,tLocked=null;
document.addEventListener('touchstart',function(e){
  tStartX=e.touches[0].clientX;
  tStartY=e.touches[0].clientY;
  tLocked=null;
},{passive:true});

document.addEventListener('touchmove',function(e){
  if(tLocked) return;
  var dx=Math.abs(e.touches[0].clientX-tStartX);
  var dy=Math.abs(e.touches[0].clientY-tStartY);
  if(dy>dx&&dy>12) tLocked='v';
  else if(dx>dy&&dx>12) tLocked='h';
},{passive:true});

document.addEventListener('touchend',function(e){
  if(tLocked!=='v') return;
  var dy=e.changedTouches[0].clientY-tStartY;
  if(Math.abs(dy)<35) return;
  stopAuto();
  if(dy<0) goCategory(curCat+1);
  else goCategory(curCat-1);
  clearTimeout(window._rt);
  window._rt=setTimeout(startAuto,15000);
},{passive:true});

// Dots horizontales
<?php foreach($cats_data as $ci=>$cat): ?>
(function(){
  var ci=<?=$ci?>;
  var t=document.getElementById('track-'+ci);
  t.addEventListener('scroll',function(){
    var pi=Math.round(t.scrollLeft/t.clientWidth);
    if(pi!==curProds[ci]){
      document.getElementById('hdot-'+ci+'-'+curProds[ci])?.classList.remove('act');
      document.getElementById('hdot-'+ci+'-'+pi)?.classList.add('act');
      curProds[ci]=pi;
    }
  },{passive:true});
})();
<?php endforeach ?>

// Auto-scroll 10s
function autoNext(){
  var ci=curCat,t=document.getElementById('track-'+ci);
  if(!t) return;
  var pi=curProds[ci],total=PRODS_N[ci];
  if(pi<total-1){
    t.scrollTo({left:(pi+1)*t.clientWidth,behavior:'smooth'});
  } else {
    var next=(ci+1)%CATS_N;
    goCategory(next);
    // reset track al inicio
    var nt=document.getElementById('track-'+next);
    if(nt) nt.scrollTo({left:0,behavior:'instant'});
    curProds[next]=0;
    document.getElementById('hdot-'+next+'-0') && (() => {
      for(var d=0;d<PRODS_N[next];d++) document.getElementById('hdot-'+next+'-'+d)?.classList.remove('act');
      document.getElementById('hdot-'+next+'-0')?.classList.add('act');
    })();
  }
}
function startAuto(){clearInterval(autoTimer);autoTimer=setInterval(autoNext,10000);}
function stopAuto(){clearInterval(autoTimer);}

feed.addEventListener('touchstart',function(){
  stopAuto();
  clearTimeout(window._rt);
},{passive:true});
feed.addEventListener('touchend',function(){
  window._rt=setTimeout(startAuto,15000);
},{passive:true});
startAuto();

// Like — corazón rojo
function toggleLike(btn){
  var path=btn.querySelector('path');
  var ic=btn.querySelector('.side-icon');
  var lbl=btn.querySelector('.side-lbl');
  var on=btn.dataset.liked==='1';
  btn.dataset.liked=on?'0':'1';
  if(!on){
    path.setAttribute('fill','rgba(220,38,86,.9)');
    path.setAttribute('stroke','#dc2656');
    ic.style.background='rgba(220,38,86,.25)';
    ic.style.borderColor='rgba(255,80,120,.5)';
    lbl.textContent='¡me gusta!';
    ic.style.transform='scale(1.35)';
    setTimeout(function(){ic.style.transform='';},280);
  }else{
    path.setAttribute('fill','none');
    path.setAttribute('stroke','#fff');
    ic.style.background='';ic.style.borderColor='';
    lbl.textContent='me gusta';
  }
}

// Pedir regalo — genera copy para que la clienta le pida el producto a alguien
var regaloTemplates=[
  function(n,p,url){return '💝 Amor, hay algo que me tiene enamorada y estaría súper feliz si fuera tu regalo\n\n'+n+'\n\n¿Me lo regalas? 👉 '+url;},
  function(n,p,url){return '✨ Bestie! Si me quieres regalar algo YA SÉ QUÉ QUIERO 💅\n\n'+n+'\n\nCómpralo aquí 👇\n'+url+'\n\n¡Sería el mejor regalo ever! 🌸';},
  function(n,p,url){return '🎁 Ma, te paso la lista de deseos jajaja\n\nEste me tiene enamorada:\n'+n+'\n\n'+url+'\n\n¡No tienes que buscar más! 🌸';},
  function(n,p,url){return '💌 Por si alguien quiere sorprenderme...\n\nEstoy enamorada de esto:\n'+n+'\n\nUna personita muy linda podría regalármelo 🥺\n'+url;},
  function(n,p,url){return '🌸 Wishlist activada\n\n'+n+'\n\nEsto es todo lo que necesito en la vida\n\n¿Quién me lo regala? 👀\n'+url;},
];
var regaloIdx=0;
var regaloData={};

function pedirRegalo(id,nombre,precio){
  var url=window.location.origin+'/producto.php?id='+id;
  regaloData={id:id,nombre:nombre,precio:precio,url:url};
  regaloIdx=Math.floor(Math.random()*regaloTemplates.length);
  // Usar el caption overlay del producto actual
  mostrarRegalo();
}
function mostrarRegalo(){
  var txt=regaloTemplates[regaloIdx](regaloData.nombre,regaloData.precio,regaloData.url);
  // Abrir un mini modal reutilizando el caption overlay del slide activo
  var ci=curCat,pi=curProds[ci];
  document.getElementById('captxt-'+ci+'-'+pi).textContent=txt;
  document.getElementById('capwa-'+ci+'-'+pi).href='https://wa.me/?text='+encodeURIComponent(txt);
  // Cambiar título
  var overlay=document.getElementById('cap-'+ci+'-'+pi);
  overlay.querySelector('.caption-hdr-txt').textContent='🎁 Pide tu regalo';
  overlay.querySelector('.btn-cap-next').textContent='✨ Otro mensaje';
  overlay.querySelector('.btn-cap-next').onclick=function(){
    regaloIdx=(regaloIdx+1)%regaloTemplates.length;
    mostrarRegalo();
  };
  overlay.classList.add('open');
  stopAuto();
}

// Caption
var templates=[
  function(n,p){return n+'\n💰 '+p+'\n📦 Envío a todo el país\n✅ Stock disponible\n\n¿Lo quieres? Escríbeme ahora 👇\n@mundoaccesoriosdorada 🌸';},
  function(n,p){return '✨ Porque tú mereces lo mejor\n\n'+n+'\nSolo '+p+'\n\n🌸 Distribuidores directos\n📲 Pide el tuyo — envío nacional\n@mundoaccesoriosdorada';},
  function(n,p){return 'Las que saben, ya lo tienen 💅\n\n'+n+'\n'+p+' · Precio directo\n\nEscríbeme y te lo consigo 🌸\n@mundoaccesoriosdorada';},
  function(n,p){return '🌟 '+n+'\n💸 '+p+'\n📦 Envío a todo Colombia\n⚡ Respuesta inmediata\n\nEscribe LO QUIERO 💌\n@mundoaccesoriosdorada';},
  function(n,p){return 'Brillante, esto te lo mereces ✨\n\n'+n+'\nDesde '+p+' con envío\n\n¿Para ti o para regalar? 🎁\nEscríbeme 🌸 @mundoaccesoriosdorada';},
];

function getCapIdx(ci,pi){var k=ci+'-'+pi; if(!capIdx[k]) capIdx[k]=Math.floor(Math.random()*templates.length); return capIdx[k];}

function buildCaption(ci,pi,n,p){
  var idx=getCapIdx(ci,pi);
  var txt=templates[idx](n,p);
  document.getElementById('captxt-'+ci+'-'+pi).textContent=txt;
  document.getElementById('capwa-'+ci+'-'+pi).href='https://wa.me/?text='+encodeURIComponent(txt);
}

function abrirCaption(ci,pi,n,p){
  buildCaption(ci,pi,n,p);
  document.getElementById('cap-'+ci+'-'+pi)?.classList.add('open');
  stopAuto();
}
function cerrarCaption(ci,pi){
  document.getElementById('cap-'+ci+'-'+pi)?.classList.remove('open');
  clearTimeout(window._rt);window._rt=setTimeout(startAuto,15000);
}
function siguienteCaption(ci,pi,n,p){
  var k=ci+'-'+pi;
  capIdx[k]=(getCapIdx(ci,pi)+1)%templates.length;
  buildCaption(ci,pi,n,p);
}
function copiarCaption(ci,pi){
  var txt=document.getElementById('captxt-'+ci+'-'+pi).textContent;
  var btn=event.target;
  navigator.clipboard?.writeText(txt).then(()=>{btn.textContent='✓ Copiado!';setTimeout(()=>btn.textContent='📋 Copiar',2000);})
  .catch(()=>{var ta=document.createElement('textarea');ta.value=txt;document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta);btn.textContent='✓ Copiado!';setTimeout(()=>btn.textContent='📋 Copiar',2000);});
}
</script>
</body>
</html>
