<?php
$data    = file_exists(__DIR__.'/catalogo.json') ? json_decode(file_get_contents(__DIR__.'/catalogo.json'),true) : ['v'=>1,'productos'=>[]];
$todos   = array_values(array_filter($data['productos']??[], fn($p)=>$p['activo']??false));
$cat_sel = trim($_GET['cat']??'');
$sg_sel  = trim($_GET['sg']??'');
$q       = trim($_GET['q']??'');

function foto(int $id, int $slot=1):?string{
    $base=$slot===1?$id:"{$id}_{$slot}";
    foreach(['jpg','jpeg','png','webp'] as $e)
        if(file_exists(__DIR__."/uploads/fotos-productos/{$base}.{$e}"))
            return "/uploads/fotos-productos/{$base}.{$e}";
    return null;
}
function pf(int $n):string{ return '$'.number_format($n,0,',','.'); }

$H1=[
    'Termos'=>'Ese que preguntan <em>dónde lo compraste.</em>',
    'Audífonos y Diademas'=>'Ponlo. El mundo <em>puede esperar.</em>',
    'Relojes'=>'La tecnología <em>que llevas puesta.</em>',
    'Combos'=>'El regalo <em>que siempre acierta.</em>',
    'Parlantes'=>'Sube el volumen. <em>Ya.</em>',
    'Cargadores y Cables'=>'Batería llena. <em>Siempre.</em>',
    'Aros de Luz y Tripodes'=>'Para el que <em>crea en serio.</em>',
    'Micrófonos'=>'Graba <em>como los grandes.</em>',
    'Power Bank'=>'Nunca más <em>sin batería.</em>',
    'Humidificadores'=>'El ambiente que nadie <em>sabe de dónde vino.</em>',
    'Soporte Celular'=>'Libera <em>las manos.</em>',
    'Cases y Estuches'=>'Protege. <em>Con estilo.</em>',
    'Vidrios Templados'=>'La pantalla <em>protegida.</em>',
    'Tecnología'=>'Gadgets que <em>hacen la diferencia.</em>',
    'Otros'=>'Lo que también <em>necesitas.</em>',
];

$CATS=[
  ['label'=>'Termos',               'cat'=>'Termos',               'c1'=>'#B5179E','c2'=>'#F72585'],
  ['label'=>'Audífonos y Diademas', 'cat'=>'Audífonos y Diademas', 'c1'=>'#002855','c2'=>'#0066CC'],
  ['label'=>'Relojes',              'cat'=>'Relojes',              'c1'=>'#3D0B68','c2'=>'#7F1FDB'],
  ['label'=>'Combos',               'cat'=>'Combos',               'c1'=>'#5C0A34','c2'=>'#B5179E'],
  ['label'=>'Parlantes',            'cat'=>'Parlantes',            'c1'=>'#0A1A2A','c2'=>'#1A4A7A'],
  ['label'=>'Cargadores y Cables',  'cat'=>'Cargadores y Cables',  'c1'=>'#1A2A0A','c2'=>'#3A6010'],
  ['label'=>'Micrófonos',           'cat'=>'Micrófonos',           'c1'=>'#1A0A2E','c2'=>'#6A0DAD'],
  ['label'=>'Power Bank',           'cat'=>'Power Bank',           'c1'=>'#003322','c2'=>'#007755'],
  ['label'=>'Humidificadores',      'cat'=>'Humidificadores',      'c1'=>'#003322','c2'=>'#006644'],
  ['label'=>'Aros de Luz y Tripodes','cat'=>'Aros de Luz y Tripodes','c1'=>'#2A1A00','c2'=>'#7A4400'],
  ['label'=>'Soporte Celular',      'cat'=>'Soporte Celular',      'c1'=>'#1A1A00','c2'=>'#5A4A00'],
  ['label'=>'Tecnología',           'cat'=>'Tecnología',           'c1'=>'#003050','c2'=>'#0066AA'],
  ['label'=>'Cases y Estuches',     'cat'=>'Cases y Estuches',     'c1'=>'#4A0A3E','c2'=>'#9A1F80'],
  ['label'=>'Vidrios Templados',    'cat'=>'Vidrios Templados',    'c1'=>'#1A1A2E','c2'=>'#3F37C9'],
  ['label'=>'Otros',                'cat'=>'Otros',                'c1'=>'#1A0A1E','c2'=>'#5A2A6E'],
];

$cat_counts=[];
foreach($todos as $p){ $c=$p['categoria']??''; $cat_counts[$c]=($cat_counts[$c]??0)+1; }

$lista=$todos;
if($q){ $ql=mb_strtolower($q); $lista=array_values(array_filter($lista,fn($p)=>mb_strpos(mb_strtolower($p['nombre']),$ql)!==false)); }
if($cat_sel) $lista=array_values(array_filter($lista,fn($p)=>($p['categoria']??'')===$cat_sel));
if($sg_sel)  $lista=array_values(array_filter($lista,fn($p)=>($p['subgrupo']??'')===$sg_sel));
usort($lista, fn($a,$b)=>(foto($b['id'])!==null)-(foto($a['id'])!==null));

$subgrupos=[];
if($cat_sel){ $tmp=array_unique(array_column(array_filter($todos,fn($p)=>($p['categoria']??'')===$cat_sel),'subgrupo')); $subgrupos=array_values(array_filter($tmp)); sort($subgrupos); }
$precio_min=0; $precio_max=0;
if($cat_sel&&$lista){ $ps=array_filter(array_column($lista,'precio'),fn($v)=>$v>0); $precio_min=$ps?min($ps):0; $precio_max=$ps?max($ps):0; }

$cat_cfg=['c1'=>'#B5179E','c2'=>'#F72585'];
foreach($CATS as $cc){ if($cc['cat']===$cat_sel){ $cat_cfg=$cc; break; } }

$PER_PAGE=24;
$total_lista=count($lista);
$lista_page=array_slice($lista,0,$PER_PAGE);
$hay_mas=$total_lista>$PER_PAGE;

$WA='https://wa.me/573233453004';
$WA_SVG='<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51H8.1c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.87 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35m-5.42 7.4h-.01a9.87 9.87 0 01-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37A9.86 9.86 0 012.06 12C2.06 6.5 6.5 2.06 12 2.06S21.94 6.5 21.94 12 17.5 21.94 12 21.94m8.41-18.3A11.82 11.82 0 0012.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 005.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89 0-3.18-1.24-6.16-3.48-8.41"/></svg>';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($cat_sel?:($q?'Búsqueda':'Catálogo'))?> · Mundo Accesorios</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500;1,600&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --fu:#B5179E;--vi:#7209B7;--ro:#F72585;
  --grad:linear-gradient(135deg,#F72585,#7209B7,#B5179E);
  --ink:#1a0a1e;--mid:#5a4060;--muted:#9a7aaa;
  --pale:#FFFAFF;--soft:#F8F0FF;--border:#EDD9F5
}
html{scroll-behavior:smooth}
body{background:var(--pale);color:var(--ink);font-family:'DM Sans',sans-serif;font-weight:300;overflow-x:hidden;-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
img{display:block}

/* NAV */
nav{position:sticky;top:0;z-index:300;height:66px;
  display:flex;align-items:center;justify-content:space-between;padding:0 32px;
  background:rgba(255,250,255,.96);backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border)}
.nav-logo{height:56px;display:flex;align-items:center}
.nav-logo img{height:56px;width:auto;max-width:170px;object-fit:contain}
.nav-right{display:flex;align-items:center;gap:12px}
.nav-back{font-size:.72rem;color:var(--muted);display:flex;align-items:center;gap:5px;transition:.2s}
.nav-back:hover{color:var(--fu)}
.nav-back svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2}
.nav-wa{display:flex;align-items:center;gap:6px;background:linear-gradient(135deg,#25D366,#1AAF55);
  color:#fff;padding:8px 16px;border-radius:50px;font-size:.72rem;font-weight:500}
.nav-wa svg{width:15px;height:15px}
@media(max-width:640px){nav{padding:0 16px}.nav-wa span{display:none}.nav-wa{padding:8px 12px}}

/* SEARCH */
.search-wrap{padding:24px 32px 0;max-width:600px}
.search-form{display:flex;gap:0;border-bottom:2px solid var(--border);transition:.2s}
.search-form:focus-within{border-color:var(--fu)}
.search-inp{flex:1;border:none;background:transparent;font-family:'DM Sans';font-size:.9rem;
  color:var(--ink);padding:10px 0;outline:none;font-weight:300}
.search-inp::placeholder{color:var(--muted)}
.search-btn{background:none;border:none;cursor:pointer;padding:8px 12px;color:var(--fu)}
.search-btn svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2}
@media(max-width:640px){.search-wrap{padding:16px 16px 0}}

/* BENTO - título debajo */
.cats-header{padding:32px 32px 20px;display:flex;align-items:flex-end;justify-content:space-between}
.cats-h1{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,4vw,3rem);font-weight:500;line-height:.95;color:var(--ink)}
.cats-h1 em{font-style:italic;background:var(--grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.cats-sub{font-size:.72rem;color:var(--muted)}
@media(max-width:640px){.cats-header{padding:20px 16px 16px;flex-direction:column;gap:4px}.cats-sub{display:none}}

.bento{display:grid;grid-template-columns:repeat(12,1fr);gap:4px;padding:0 32px 48px}
@media(max-width:900px){.bento{grid-template-columns:repeat(6,1fr);padding:0 12px 40px}}
@media(max-width:480px){.bento{grid-template-columns:1fr 1fr;gap:4px;padding:0 12px 40px}}

.btile{border-radius:14px;overflow:hidden;cursor:pointer;display:flex;flex-direction:column;
  transition:transform .25s cubic-bezier(.16,1,.3,1);background:var(--soft);
  text-decoration:none}
.btile:hover{transform:translateY(-3px)}
.btile:active{transform:scale(.97)}

/* FOTO */
.btile-photo{width:100%;flex:1;overflow:hidden;position:relative;min-height:0}
.btile-photo img{width:100%;height:100%;object-fit:cover;display:block;
  transition:transform .5s cubic-bezier(.16,1,.3,1)}
.btile:hover .btile-photo img{transform:scale(1.05)}
.btile-photo-ph{width:100%;height:100%;display:flex;align-items:center;justify-content:center}

/* LABEL - fuera de la foto */
.btile-info{
  padding:8px 12px 10px;
  background:#fff;
  border-top:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;gap:8px;
  flex-shrink:0
}
.btile-label{font-family:'Cormorant Garamond',serif;font-style:italic;
  font-weight:600;color:var(--ink);font-size:inherit;line-height:1.1}
.btile-meta{display:flex;align-items:center;gap:6px;flex-shrink:0}
.btile-count{font-size:.55rem;color:var(--muted);white-space:nowrap}
.btile-explore{width:22px;height:22px;border-radius:50%;
  background:rgba(183,17,158,.08);border:1px solid rgba(183,17,158,.2);
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
  font-family:'Cormorant Garamond',serif;font-style:italic;font-size:.62rem;color:var(--fu);
  transition:.2s;text-decoration:none}
.btile-explore:hover{background:var(--grad);color:#fff;border-color:transparent}

/* Tamaños */
.btile:nth-child(1){grid-column:span 5;grid-row:span 1}.btile:nth-child(1) .btile-photo{height:280px}.btile:nth-child(1) .btile-label{font-size:1.3rem}
.btile:nth-child(2){grid-column:span 7;grid-row:span 1}.btile:nth-child(2) .btile-photo{height:280px}.btile:nth-child(2) .btile-label{font-size:1.15rem}
.btile:nth-child(3),.btile:nth-child(4),.btile:nth-child(5){grid-column:span 4;grid-row:span 1}.btile:nth-child(3) .btile-photo,.btile:nth-child(4) .btile-photo,.btile:nth-child(5) .btile-photo{height:200px}.btile:nth-child(3) .btile-label,.btile:nth-child(4) .btile-label,.btile:nth-child(5) .btile-label{font-size:1rem}
.btile:nth-child(n+6){grid-column:span 3;grid-row:span 1}.btile:nth-child(n+6) .btile-photo{height:160px}.btile:nth-child(n+6) .btile-label{font-size:.88rem}

@media(max-width:900px){
  .btile:nth-child(1){grid-column:span 3}.btile:nth-child(1) .btile-photo{height:200px}
  .btile:nth-child(2){grid-column:span 3}.btile:nth-child(2) .btile-photo{height:200px}
  .btile:nth-child(3),.btile:nth-child(4),.btile:nth-child(5){grid-column:span 2}.btile:nth-child(3) .btile-photo,.btile:nth-child(4) .btile-photo,.btile:nth-child(5) .btile-photo{height:160px}
  .btile:nth-child(n+6){grid-column:span 3}.btile:nth-child(n+6) .btile-photo{height:140px}
}
@media(max-width:480px){
  .btile:nth-child(1){grid-column:1/-1}.btile:nth-child(1) .btile-photo{height:220px}.btile:nth-child(1) .btile-label{font-size:1.2rem}
  .btile:nth-child(n+2){grid-column:span 1}.btile:nth-child(n+2) .btile-photo{height:160px}.btile:nth-child(n+2) .btile-label{font-size:.85rem}
}

/* CATEGORY BANNER */
.cat-banner{position:relative;height:280px;overflow:hidden;margin-bottom:0}
.cat-banner-bg{position:absolute;inset:0;background-size:cover;background-position:center;
  filter:blur(18px) brightness(.6) saturate(1.3);transform:scale(1.08)}
.cat-banner-overlay{position:absolute;inset:0;background:linear-gradient(135deg,rgba(0,0,0,.55),rgba(0,0,0,.3))}
.cat-banner-in{position:relative;z-index:2;height:100%;display:flex;flex-direction:column;
  justify-content:flex-end;padding:32px 48px}
.cat-banner-eyebrow{font-size:.55rem;font-weight:500;letter-spacing:.2em;text-transform:uppercase;
  color:rgba(255,255,255,.55);margin-bottom:8px}
.cat-banner-h1{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,5vw,3.8rem);
  font-weight:500;line-height:.95;color:#fff}
.cat-banner-h1 em{font-style:italic;font-weight:400}
.cat-banner-meta{display:flex;align-items:center;gap:16px;margin-top:12px}
.cat-banner-stat{font-size:.72rem;color:rgba(255,255,255,.65)}
.cat-banner-explore{display:flex;align-items:center;gap:5px;font-family:'Cormorant Garamond',serif;
  font-style:italic;font-size:.85rem;color:rgba(255,255,255,.8);
  border-bottom:1px solid rgba(255,255,255,.35);padding-bottom:2px;transition:.2s}
.cat-banner-explore:hover{color:#fff;border-color:#fff}
@media(max-width:640px){.cat-banner{height:220px}.cat-banner-in{padding:20px 20px}}

/* SUBCAT TABS */
.tabs-strip{overflow-x:auto;scrollbar-width:none;border-bottom:1px solid var(--border);
  background:var(--pale);position:sticky;top:66px;z-index:200}
.tabs-strip::-webkit-scrollbar{display:none}
.tabs-inner{display:flex;padding:0 32px;gap:4px;min-width:max-content}
.tab{padding:12px 18px;font-size:.72rem;font-weight:400;color:var(--muted);
  border-bottom:2px solid transparent;transition:.2s;white-space:nowrap}
.tab:hover{color:var(--fu)}
.tab.act{color:var(--fu);border-color:var(--fu);font-weight:500}
@media(max-width:640px){.tabs-inner{padding:0 12px}.tab{padding:10px 12px}}

/* PRODUCT GRID */
.pgrid-wrap{padding:20px 32px 60px}
.pgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px}
@media(max-width:640px){.pgrid-wrap{padding:14px 12px 60px}.pgrid{grid-template-columns:1fr 1fr;gap:8px}}

/* Primera card con foto — destacada */
.pcard-hero{grid-column:1/-1;aspect-ratio:16/7;border-radius:20px}
@media(max-width:640px){.pcard-hero{aspect-ratio:4/3}}

/* PRODUCT CARD — fullbleed editorial */
.pcard{border-radius:16px;overflow:hidden;position:relative;
  background:var(--soft);cursor:pointer;display:block;
  aspect-ratio:3/4;
  transition:transform .3s cubic-bezier(.16,1,.3,1)}
.pcard:hover{transform:translateY(-4px)}
.pcard:active{transform:scale(.97)}

.pcard-photo{position:absolute;inset:0}
.pcard-photo img{width:100%;height:100%;object-fit:cover;transition:transform .55s cubic-bezier(.16,1,.3,1)}
.pcard:hover .pcard-photo img{transform:scale(1.06)}
.pcard-photo-ph{width:100%;height:100%;
  background:linear-gradient(135deg,#F8F0FF,#EDD9F5);
  display:flex;align-items:center;justify-content:center}
.pcard-photo-ph svg{width:32px;height:32px;opacity:.25}

/* Gradient overlay */
.pcard-grad{position:absolute;inset:0;
  background:linear-gradient(to top,rgba(0,0,0,.8) 0%,rgba(0,0,0,.25) 35%,transparent 60%);
  pointer-events:none}

/* Price pill top-right */
.pcard-price-pill{position:absolute;top:10px;right:10px;
  background:rgba(0,0,0,.55);backdrop-filter:blur(8px);
  border:1px solid rgba(255,255,255,.2);border-radius:50px;
  padding:4px 10px;font-size:.65rem;font-weight:500;color:#fff;
  opacity:0;transform:translateY(-4px);transition:.25s}
.pcard:hover .pcard-price-pill{opacity:1;transform:none}

/* Bottom info */
.pcard-info{position:absolute;bottom:0;left:0;right:0;
  padding:12px 14px 14px;z-index:2}
.pcard-name{font-family:'Cormorant Garamond',serif;font-size:1rem;font-weight:500;
  color:#fff;line-height:1.2;margin-bottom:3px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.pcard-pr{font-size:.78rem;font-weight:500;color:rgba(255,255,255,.75)}

/* CTA desliza hacia arriba en hover */
.pcard-cta{position:absolute;bottom:0;left:0;right:0;
  padding:10px 12px 12px;z-index:3;
  background:linear-gradient(to top,rgba(0,0,0,.9),rgba(0,0,0,.6));
  transform:translateY(100%);transition:transform .3s cubic-bezier(.16,1,.3,1)}
.pcard:hover .pcard-cta{transform:translateY(0)}
.pcard-cta-btns{display:flex;gap:6px}
.btn-ver{flex:1;padding:9px;border-radius:10px;
  border:1.5px solid rgba(255,255,255,.35);background:rgba(255,255,255,.1);
  backdrop-filter:blur(8px);color:#fff;font-family:'DM Sans';font-size:.68rem;
  text-align:center;transition:.18s}
.btn-ver:hover{background:rgba(255,255,255,.22);border-color:#fff}
.btn-wa{flex:1.3;padding:9px;border-radius:10px;
  background:linear-gradient(135deg,#25D366,#1AAF55);
  color:#fff;font-family:'DM Sans';font-size:.68rem;font-weight:500;
  display:flex;align-items:center;justify-content:center;gap:5px}
.btn-wa svg{width:12px;height:12px;flex-shrink:0}

/* Mobile: CTA siempre visible */
@media(hover:none){
  .pcard-cta{transform:none;background:linear-gradient(to top,rgba(0,0,0,.88),transparent)}
  .pcard-price-pill{opacity:1;transform:none}
}

/* EMPTY */
.empty{text-align:center;padding:72px 24px;grid-column:1/-1}
.empty h2{font-family:'Cormorant Garamond',serif;font-size:2.2rem;font-style:italic;color:var(--fu);margin-bottom:10px}
.empty p{font-size:.88rem;color:var(--muted);margin-bottom:24px}
.empty a{display:inline-block;background:var(--grad);color:#fff;border-radius:50px;padding:11px 28px;font-size:.82rem}

/* SEARCH RESULTS BANNER */
.q-banner{padding:24px 32px 0;font-family:'Cormorant Garamond',serif;font-size:1.4rem;font-style:italic;color:var(--muted)}
.q-banner strong{color:var(--fu);font-style:normal}

/* WA FLOAT */
.wa-float{position:fixed;bottom:24px;right:24px;z-index:400;width:50px;height:50px;
  border-radius:50%;background:linear-gradient(135deg,#25D366,#1AAF55);
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 6px 24px rgba(37,211,102,.38);transition:.25s}
.wa-float:hover{transform:scale(1.1)}
.wa-float svg{width:24px;height:24px}

/* ANIMATIONS */
@keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:none}}
.pgrid .pcard{animation:fadeUp .42s ease both}
<?php for($i=1;$i<=12;$i++): ?>.pgrid .pcard:nth-child(<?=$i?>){animation-delay:<?=($i-1)*.05?>s}
<?php endfor ?>
@keyframes slideIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
.btile{animation:slideIn .4s ease both}
<?php for($i=1;$i<=15;$i++): ?>.btile:nth-child(<?=$i?>){animation-delay:<?=($i-1)*.04?>s}
<?php endfor ?>
</style>
</head>
<body>

<nav>
  <a href="/" class="nav-logo"><img src="logo.png" alt="Mundo Accesorios"></a>
  <div class="nav-right">
    <?php if($cat_sel||$q): ?>
    <a class="nav-back" href="catalogo.php">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Catálogo
    </a>
    <?php endif ?>
    <a class="nav-wa" href="<?=$WA?>?text=<?=urlencode('Hola, me interesa un producto 🌸')?>" target="_blank">
      <?=$WA_SVG?><span>WhatsApp</span>
    </a>
  </div>
</nav>

<?php if($cat_sel): ?>
<?php
// Banner con foto real de la categoría (slot 2 primero para lifestyle)
$banner_foto=null;
foreach($todos as $tp){
    if(($tp['categoria']??'')===$cat_sel){
        foreach([2,3,1] as $s){ $f=foto($tp['id'],$s); if($f){$banner_foto=$f;break 2;} }
    }
}
?>
<div class="cat-banner">
  <?php if($banner_foto): ?>
  <div class="cat-banner-bg" style="background-image:url('<?=htmlspecialchars($banner_foto)?>')"></div>
  <?php else: ?>
  <div class="cat-banner-bg" style="background:linear-gradient(135deg,<?=$cat_cfg['c1']?>,<?=$cat_cfg['c2']?>)"></div>
  <?php endif ?>
  <div class="cat-banner-overlay"></div>
  <div class="cat-banner-in">
    <div class="cat-banner-eyebrow">Colección</div>
    <h1 class="cat-banner-h1"><?=$H1[$cat_sel]??htmlspecialchars($cat_sel)?></h1>
    <div class="cat-banner-meta">
      <?php if($precio_min>0): ?>
      <span class="cat-banner-stat">Desde <strong style="color:#fff"><?=pf($precio_min)?></strong></span>
      <span class="cat-banner-stat">&middot; <?=count($lista)?> productos</span>
      <?php else: ?>
      <span class="cat-banner-stat"><?=count($lista)?> productos disponibles</span>
      <?php endif ?>
      <a class="cat-banner-explore" href="explore.php?cat=<?=urlencode($cat_sel)?>">
        ✦ modo explorar
      </a>
    </div>
  </div>
</div>

<?php if(count($subgrupos)>1): ?>
<div class="tabs-strip">
  <div class="tabs-inner">
    <a class="tab <?=!$sg_sel?'act':''?>" href="catalogo.php?cat=<?=urlencode($cat_sel)?>">Todos</a>
    <?php foreach($subgrupos as $sg): ?>
    <a class="tab <?=$sg_sel===$sg?'act':''?>" href="catalogo.php?cat=<?=urlencode($cat_sel)?>&sg=<?=urlencode($sg)?>"><?=htmlspecialchars($sg)?></a>
    <?php endforeach ?>
  </div>
</div>
<?php endif ?>

<div class="pgrid-wrap">
  <?php if(empty($lista)): ?>
  <div class="pgrid"><div class="empty"><h2>Sin productos aún</h2><p>Esta categoría está en construcción.</p><a href="catalogo.php">Ver todo</a></div></div>
  <?php else: ?>
  <div class="pgrid" id="pgrid">
    <?php
    // Separar primer producto CON foto como hero
    $hero=null; $resto=[];
    foreach($lista_page as $p){
        if(!$hero && foto($p['id'])){ $hero=$p; }
        else { $resto[]=$p; }
    }
    if(!$hero && !empty($lista_page)){ $hero=$lista_page[0]; $resto=array_slice($lista_page,1); }

    // Hero card
    if($hero):
      $id=$hero['id']; $f=foto($id); $pr=$hero['precio']??0;
      $wa_msg=urlencode('Hola linda 🌸 me interesa: '.$hero['nombre'].($pr?' — '.pf($pr):''));
    ?>
    <a class="pcard pcard-hero" href="producto.php?id=<?=$id?>">
      <div class="pcard-photo">
        <?php if($f): ?><img src="<?=$f?>" alt="<?=htmlspecialchars($hero['nombre'])?>" loading="eager">
        <?php else: ?><div class="pcard-photo-ph"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div><?php endif ?>
      </div>
      <div class="pcard-grad"></div>
      <?php if($pr>0): ?><div class="pcard-price-pill" style="opacity:1;transform:none"><?=pf($pr)?></div><?php endif ?>
      <div class="pcard-info" style="-webkit-line-clamp:1">
        <div class="pcard-name" style="font-size:clamp(1.2rem,3vw,1.8rem)"><?=htmlspecialchars($hero['nombre'])?></div>
        <?php if($pr>0): ?><div class="pcard-pr"><?=pf($pr)?></div><?php endif ?>
      </div>
      <div class="pcard-cta" style="transform:none;background:linear-gradient(to top,rgba(0,0,0,.85),transparent)">
        <div class="pcard-cta-btns">
          <span class="btn-ver">Ver detalles</span>
          <a class="btn-wa" href="<?=$WA?>?text=<?=$wa_msg?>" onclick="event.stopPropagation()" target="_blank"><?=$WA_SVG?> Lo quiero</a>
        </div>
      </div>
    </a>
    <?php endif ?>

    <?php foreach($resto as $li=>$p):
      $id=$p['id']; $f=foto($id); $pr=$p['precio']??0;
      $wa_msg=urlencode('Hola linda 🌸 me interesa: '.$p['nombre'].($pr?' — '.pf($pr):''));
    ?>
    <a class="pcard" href="producto.php?id=<?=$id?>">
      <div class="pcard-photo">
        <?php if($f): ?><img src="<?=$f?>" alt="<?=htmlspecialchars($p['nombre'])?>" loading="<?=$li<8?'eager':'lazy'?>">
        <?php else: ?><div class="pcard-photo-ph"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div><?php endif ?>
      </div>
      <div class="pcard-grad"></div>
      <?php if($pr>0): ?><div class="pcard-price-pill"><?=pf($pr)?></div><?php endif ?>
      <div class="pcard-info">
        <div class="pcard-name"><?=htmlspecialchars($p['nombre'])?></div>
        <?php if($pr>0): ?><div class="pcard-pr"><?=pf($pr)?></div><?php endif ?>
      </div>
      <div class="pcard-cta">
        <div class="pcard-cta-btns">
          <span class="btn-ver">Ver detalles</span>
          <a class="btn-wa" href="<?=$WA?>?text=<?=$wa_msg?>" onclick="event.stopPropagation()" target="_blank"><?=$WA_SVG?> Lo quiero</a>
        </div>
      </div>
    </a>
    <?php endforeach ?>
  </div>
  <?php if($hay_mas): ?>
  <div style="text-align:center;padding:32px 0">
    <button onclick="loadMore()" id="load-more"
      style="padding:12px 36px;border-radius:50px;border:1.5px solid var(--border);
      background:transparent;font-family:'DM Sans';font-size:.8rem;color:var(--mid);
      cursor:pointer;transition:.2s">
      Ver más productos · <?=($total_lista-$PER_PAGE)?> restantes
    </button>
  </div>
  <script>
  var allIds=<?=json_encode(array_column(array_slice($lista,$PER_PAGE),'id'))?>;
  var loaded=0;
  function loadMore(){
    var btn=document.getElementById('load-more');
    var grid=document.getElementById('pgrid');
    btn.textContent='Cargando...';
    var batch=allIds.slice(loaded,loaded+<?=$PER_PAGE?>);
    loaded+=batch.length;
    batch.forEach(function(id,i){
      var a=document.createElement('a');
      a.href='producto.php?id='+id;
      a.className='pcard';
      a.style.animationDelay=(i*0.04)+'s';
      a.innerHTML='<div class="pcard-photo"><img src="/uploads/fotos-productos/'+id+'.jpg" loading="lazy" onerror="this.parentNode.innerHTML=\'<div class=pcard-photo-ph></div>\'" alt="Producto"></div><div class="pcard-grad"></div><div class="pcard-cta"><div class="pcard-cta-btns"><span class="btn-ver">Ver detalles</span></div></div>';
      grid.appendChild(a);
    });
    if(loaded>=allIds.length) btn.remove();
    else btn.textContent='Ver más · '+(allIds.length-loaded)+' restantes';
  }
  </script>
  <?php endif ?>
  <?php endif ?>
</div>

<?php elseif($q): ?>
<div class="q-banner">Resultados para <strong>&ldquo;<?=htmlspecialchars($q)?>&rdquo;</strong> — <?=count($lista)?> productos</div>
<div class="pgrid-wrap">
  <?php if(empty($lista)): ?>
  <div class="pgrid"><div class="empty"><h2>Sin resultados</h2><p>Intenta con otro término.</p><a href="catalogo.php">Ver catálogo</a></div></div>
  <?php else: ?>
  <div class="pgrid">
    <?php foreach($lista as $li=>$p):
      $id=$p['id']; $f=foto($id); $pr=$p['precio']??0;
      $wa_msg=urlencode('Hola linda 🌸 me interesa: '.$p['nombre'].($pr?' — '.pf($pr):''));
    ?>
    <a class="pcard" href="producto.php?id=<?=$id?>">
      <div class="pcard-photo"><?php if($f): ?><img src="<?=$f?>" alt="<?=htmlspecialchars($p['nombre'])?>" loading="<?=$li<6?'eager':'lazy'?>"><?php else: ?><div class="pcard-photo-ph"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div><?php endif ?></div>
      <div class="pcard-grad"></div>
      <?php if($pr>0): ?><div class="pcard-price-pill"><?=pf($pr)?></div><?php endif ?>
      <div class="pcard-info"><div class="pcard-name"><?=htmlspecialchars($p['nombre'])?></div><?php if($pr>0): ?><div class="pcard-pr"><?=pf($pr)?></div><?php endif ?></div>
      <div class="pcard-cta"><div class="pcard-cta-btns"><span class="btn-ver">Ver detalles</span><a class="btn-wa" href="<?=$WA?>?text=<?=$wa_msg?>" onclick="event.stopPropagation()" target="_blank"><?=$WA_SVG?> Lo quiero</a></div></div>
    </a>
    <?php endforeach ?>
  </div>
  <?php endif ?>
</div>

<?php else: ?>
<div class="search-wrap">
  <form class="search-form" action="catalogo.php" method="get">
    <input class="search-inp" type="text" name="q" placeholder="Buscar en el catálogo..." value="<?=htmlspecialchars($q)?>" autocomplete="off">
    <button class="search-btn" type="submit"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></button>
  </form>
</div>

<div class="cats-header">
  <h1 class="cats-h1">Todo el<br><em>catálogo.</em></h1>
  <p class="cats-sub">Selecciona una categoría</p>
</div>

<div class="bento">
  <?php foreach($CATS as $cc):
    if(!isset($cat_counts[$cc['cat']])) continue;
    $prods_cat=array_values(array_filter($todos,fn($p)=>($p['categoria']??'')===$cc['cat']));
    $foto_cat=null;
    // Para Relojes y Combos: saltar los primeros (suelen ser fotos oscuras)
    $skip = in_array($cc['cat'],['Relojes','Combos']) ? 3 : 0;
    $sample=array_slice($prods_cat,$skip,min(30,count($prods_cat)));
    if(empty($sample)) $sample=$prods_cat; // fallback
    // Priorizar slot 2/3 (lifestyle, más colorido)
    foreach($sample as $tp){ foreach([2,3] as $s){ $f=foto($tp['id'],$s); if($f){$foto_cat=$f;break 2;} } }
    if(!$foto_cat){ foreach($sample as $tp){ $f=foto($tp['id'],1); if($f){$foto_cat=$f;break;} } }
    if(!$foto_cat){ foreach($prods_cat as $tp){ $f=foto($tp['id'],1); if($f){$foto_cat=$f;break;} } }
  ?>
  <a class="btile" href="catalogo.php?cat=<?=urlencode($cc['cat'])?>">
    <div class="btile-photo"
      style="background:linear-gradient(135deg,<?=$cc['c1']?>,<?=$cc['c2']?>)">
      <?php if($foto_cat): ?>
      <img src="<?=htmlspecialchars($foto_cat)?>" alt="<?=htmlspecialchars($cc['label'])?>">
      <?php else: ?>
      <div class="btile-photo-ph" style="background:linear-gradient(135deg,<?=$cc['c1']?>,<?=$cc['c2']?>)"></div>
      <?php endif ?>
    </div>
    <div class="btile-info">
      <span class="btile-label"><?=htmlspecialchars($cc['label'])?></span>
      <div class="btile-meta">
        <span class="btile-count"><?=$cat_counts[$cc['cat']]?></span>
        <a class="btile-explore" href="explore.php?cat=<?=urlencode($cc['cat'])?>" onclick="event.stopPropagation()">✦</a>
      </div>
    </div>
  </a>
  <?php endforeach ?>
</div>
<?php endif ?>

<a class="wa-float" href="<?=$WA?>?text=<?=urlencode('Hola linda 🌸 me interesa un producto')?>" target="_blank"><?=$WA_SVG?></a>

</body>
</html>
