<?php
require_once __DIR__ . '/afiliados_lib.php';
afil_handle_incoming_ref();

$data  = file_exists(__DIR__.'/catalogo.json') ? json_decode(file_get_contents(__DIR__.'/catalogo.json'),true) : ['v'=>1,'productos'=>[]];
$todos = array_values(array_filter($data['productos']??[], fn($p)=>$p['activo']??false));
$id    = intval($_GET['id']??0);
$prod  = null;
foreach($todos as $p){ if($p['id']===$id){ $prod=$p; break; } }
if(!$prod){ header('Location: catalogo.php'); exit; }

function foto(int $id,int $slot=1):?string{
    $base=$slot===1?$id:"{$id}_{$slot}";
    foreach(['jpg','jpeg','png','webp'] as $e)
        if(file_exists(__DIR__."/uploads/fotos-productos/{$base}.{$e}"))
            return "/uploads/fotos-productos/{$base}.{$e}";
    return null;
}
function pf(int $n):string{ return '$'.number_format($n,0,',','.'); }

$fotos=[]; for($s=1;$s<=6;$s++){ $f=foto($id,$s); if($f) $fotos[]=$f; }
$precio=$prod['precio']??0;
$wa_msg=urlencode('Hola, quiero pedir: '.$prod['nombre'].($precio?' — '.pf($precio):'').'. Me pueden dar más información?');
$WA='https://wa.me/573233453004';

$rel_all=array_values(array_filter($todos,fn($p)=>$p['id']!==$id&&($p['categoria']??'')===$prod['categoria']));
usort($rel_all,fn($a,$b)=>(foto($b['id'])!==null)-(foto($a['id'])!==null));
$rel=array_slice($rel_all,0,4);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($prod['nombre'])?> · Mundo Accesorios</title>
<meta name="description" content="<?=htmlspecialchars($prod['desc']??$prod['nombre'])?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400;1,500&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --grad:linear-gradient(135deg,#00AAFF 0%,#4921D8 55%,#7F1FDB 100%);
  --gs:linear-gradient(135deg,#C026D3 0%,#7C3AED 100%);
  --vi:#4921D8;--fu:#7F1FDB;
  --ink:#09090F;--mid:#44445A;--muted:#888899;
  --pale:#FAF8FF;--soft:#F3EFFF;--border:#EAE6F8;--white:#FFFFFF;
  --wa:#25D366;
}
html{scroll-behavior:smooth}
body{font-family:'DM Sans',sans-serif;font-weight:300;background:var(--pale);color:var(--ink);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
.ambient-bg{position:fixed;inset:0;z-index:-1;pointer-events:none;
  background:radial-gradient(ellipse 80% 60% at 20% 10%,rgba(0,170,255,.07),transparent),
             radial-gradient(ellipse 70% 55% at 85% 40%,rgba(73,33,216,.08),transparent),#FAFAFF}

/* NAV */
nav{position:sticky;top:0;z-index:300;height:72px;padding:0 40px;
  display:flex;align-items:center;justify-content:space-between;
  background:rgba(255,255,255,.97);backdrop-filter:blur(24px);border-bottom:1px solid var(--border)}
nav::after{content:'';position:absolute;bottom:0;left:0;right:0;height:3px;background:var(--grad)}
.nav-logo{height:48px;overflow:hidden;display:flex;align-items:center;flex-shrink:0}
.nav-logo img{height:48px;width:auto;max-width:160px;object-fit:contain;display:block}
.nav-right{display:flex;align-items:center;gap:14px}
.nav-back{font-size:.78rem;font-weight:500;color:var(--vi);display:flex;align-items:center;gap:5px}
.nav-back:hover{opacity:.7}
.nav-wa{display:flex;align-items:center;gap:8px;padding:10px 20px;border-radius:50px;
  background:var(--grad);color:#fff;font-size:.78rem;font-weight:500;
  box-shadow:0 4px 18px rgba(73,33,216,.25);transition:.2s}
.nav-wa:hover{opacity:.9;transform:translateY(-1px)}
.nav-wa svg{width:16px;height:16px;fill:#fff}
@media(max-width:640px){nav{padding:0 16px;height:60px}.nav-logo{height:40px}.nav-logo img{height:40px;max-width:130px}}

/* BREADCRUMB */
.bc{padding:14px 48px;font-size:.75rem;color:var(--muted);display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.bc a{color:var(--vi);font-weight:500}.bc a:hover{text-decoration:underline}
.bc-sep{opacity:.35}
@media(max-width:640px){.bc{padding:10px 16px}}

/* PRODUCT LAYOUT */
.prod-wrap{max-width:1200px;margin:0 auto;padding:0 48px 60px;display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:start}
@media(max-width:800px){.prod-wrap{grid-template-columns:1fr;gap:32px;padding:0 16px 48px}}

/* GALLERY */
.gallery-main{border-radius:20px;overflow:hidden;background:var(--white);
  box-shadow:0 8px 40px rgba(0,0,0,.1);aspect-ratio:1;margin-bottom:12px;position:relative}
.gallery-main img{width:100%;height:100%;object-fit:contain;padding:24px;transition:.3s}
.gallery-no-foto{width:100%;height:100%;display:flex;align-items:center;justify-content:center;
  background:linear-gradient(135deg,var(--soft),#e8e0ff)}
.gallery-no-foto-txt{font-family:'Playfair Display',serif;font-style:italic;font-size:1rem;color:rgba(0,0,0,.2)}
.gallery-thumbs{display:flex;gap:8px;flex-wrap:wrap}
.thumb{width:64px;height:64px;border-radius:10px;overflow:hidden;background:var(--white);
  box-shadow:0 2px 10px rgba(0,0,0,.08);cursor:pointer;
  border:2px solid transparent;transition:.2s;flex-shrink:0}
.thumb:hover,.thumb.act{border-color:var(--vi)}
.thumb img{width:100%;height:100%;object-fit:contain;padding:4px}

/* PRODUCT INFO */
.prod-cat-tag{display:inline-flex;align-items:center;gap:6px;background:var(--grad);
  color:#fff;border-radius:100px;padding:4px 14px;font-size:.65rem;font-weight:500;
  letter-spacing:.08em;text-transform:uppercase;margin-bottom:14px;text-decoration:none}
.prod-name{font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3rem);
  font-weight:500;line-height:1;letter-spacing:-.02em;margin-bottom:10px}
.prod-sg{font-size:.8rem;color:var(--muted);margin-bottom:20px}
.prod-sg strong{color:var(--vi);font-weight:500}
.prod-price{font-family:'Playfair Display',serif;font-size:clamp(1.8rem,3vw,2.6rem);
  font-weight:500;line-height:1;margin-bottom:6px;
  background:var(--gs);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.prod-price.sin{font-family:'DM Sans';font-size:1rem;color:var(--muted);
  -webkit-text-fill-color:var(--muted);font-style:italic;font-weight:300}
.prod-price-note{font-size:.72rem;color:var(--muted);margin-bottom:24px}
.prod-colores{background:var(--soft);border-radius:12px;padding:14px 18px;margin-bottom:20px}
.prod-colores-title{font-size:.6rem;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--fu);margin-bottom:6px}
.prod-colores-val{font-size:.88rem;color:var(--ink);font-weight:300}
.prod-desc{font-size:.88rem;color:var(--mid);line-height:1.75;margin-bottom:28px;font-weight:300}
.prod-stk{font-size:.78rem;color:#00A868;font-weight:600;margin-bottom:24px;
  display:flex;align-items:center;gap:6px}
.prod-stk::before{content:'';width:7px;height:7px;border-radius:50%;background:#00A868}

/* CTA */
.cta-wa{display:flex;align-items:center;justify-content:center;gap:10px;
  background:linear-gradient(135deg,#25D366,#1AAF55);color:#fff;
  border-radius:14px;padding:18px 28px;font-size:1rem;font-weight:500;letter-spacing:.04em;
  box-shadow:0 8px 28px rgba(37,211,102,.35);transition:.25s;margin-bottom:10px}
.cta-wa:hover{transform:translateY(-2px);box-shadow:0 14px 40px rgba(37,211,102,.4)}
.cta-wa svg{width:22px;height:22px;fill:#fff}
.cta-note{text-align:center;font-size:.72rem;color:var(--muted)}

/* RELATED */
.rel-wrap{max-width:1200px;margin:0 auto;padding:0 48px 80px}
.rel-title{font-family:'Playfair Display',serif;font-size:clamp(1.6rem,3vw,2.4rem);
  font-weight:500;font-style:italic;margin-bottom:24px}
.rel-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}
.rel-card{border-radius:14px;overflow:hidden;background:var(--grad);padding:2px;transition:.25s;display:block}
.rel-card:hover{transform:translateY(-3px);box-shadow:0 12px 36px rgba(73,33,216,.2)}
.rel-card-inner{border-radius:12px;overflow:hidden;background:var(--white)}
.rel-img{aspect-ratio:3/4;overflow:hidden;background:var(--soft)}
.rel-img img{width:100%;height:100%;object-fit:cover}
.rel-img-ph{width:100%;height:100%;background:linear-gradient(135deg,var(--soft),#e8e0ff)}
.rel-body{padding:12px 14px 16px}
.rel-name{font-size:.85rem;font-weight:400;line-height:1.35;margin-bottom:6px;color:var(--ink)}
.rel-price{font-family:'Playfair Display',serif;font-size:1rem;font-weight:500;
  background:var(--gs);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
@media(max-width:640px){.rel-wrap{padding:0 16px 60px}}

/* FOOTER LINE */
.ft-line{background:var(--ink);color:rgba(255,255,255,.3);text-align:center;
  padding:24px;font-size:.72rem;border-top:1px solid rgba(255,255,255,.06)}
.ft-line a{color:rgba(255,255,255,.5)}.ft-line a:hover{color:#fff}
.ft-line strong{color:#fff}

/* WA FLOAT */
.wa-float{position:fixed;bottom:28px;right:28px;z-index:400;width:56px;height:56px;
  border-radius:50%;background:linear-gradient(135deg,#25D366,#1AAF55);
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 8px 28px rgba(37,211,102,.42);text-decoration:none;transition:.25s}
.wa-float:hover{transform:scale(1.1)}
.wa-float svg{width:28px;height:28px;fill:#fff}
</style>
</head>
<body>
<div class="ambient-bg"></div>

<nav>
  <a href="/" class="nav-logo"><img src="logo.png" alt="Mundo Accesorios"></a>
  <div class="nav-right">
    <a class="nav-back" href="catalogo.php?cat=<?=urlencode($prod['categoria'])?>">
      ← <?=htmlspecialchars($prod['categoria'])?>
    </a>
    <a class="nav-wa" href="<?=$WA?>?text=<?=$wa_msg?>" target="_blank">
      <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
      Pedir este
    </a>
  </div>
</nav>

<div class="bc">
  <a href="/">Inicio</a><span class="bc-sep">/</span>
  <a href="catalogo.php">Catálogo</a><span class="bc-sep">/</span>
  <a href="catalogo.php?cat=<?=urlencode($prod['categoria'])?>"><?=htmlspecialchars($prod['categoria'])?></a>
  <span class="bc-sep">/</span><span><?=htmlspecialchars($prod['nombre'])?></span>
</div>

<div class="prod-wrap">
  <!-- GALLERY -->
  <div class="gallery">
    <div class="gallery-main">
      <?php if($fotos): ?>
        <img id="mainImg" src="<?=$fotos[0]?>" alt="<?=htmlspecialchars($prod['nombre'])?>" fetchpriority="high">
      <?php else: ?>
        <div class="gallery-no-foto"><div class="gallery-no-foto-txt">Fotos próximamente</div></div>
      <?php endif ?>
    </div>
    <?php if(count($fotos)>1): ?>
    <div class="gallery-thumbs">
      <?php foreach($fotos as $i=>$f): ?>
      <div class="thumb <?=$i===0?'act':''?>" onclick="cambiarFoto('<?=$f?>',this)">
        <img src="<?=$f?>" alt="<?=$i+1?>" loading="lazy">
      </div>
      <?php endforeach ?>
    </div>
    <?php endif ?>
  </div>

  <!-- INFO -->
  <div class="prod-info">
    <a class="prod-cat-tag" href="catalogo.php?cat=<?=urlencode($prod['categoria'])?>">
      <?=htmlspecialchars($prod['categoria'])?>
    </a>
    <h1 class="prod-name"><?=htmlspecialchars($prod['nombre'])?></h1>
    <?php if(!empty($prod['subgrupo'])): ?>
    <p class="prod-sg">Tipo: <strong><?=htmlspecialchars($prod['subgrupo'])?></strong></p>
    <?php endif ?>
    <div class="prod-price <?=$precio>0?'':'sin'?>">
      <?=$precio>0 ? pf($precio) : 'Consultar precio'?>
    </div>
    <?php if($precio>0): ?><div class="prod-price-note">Precio unitario &middot; Consultar disponibilidad</div><?php endif ?>
    <?php if(!empty($prod['color'])): ?>
    <div class="prod-colores">
      <div class="prod-colores-title">Colores disponibles</div>
      <div class="prod-colores-val"><?=htmlspecialchars($prod['color'])?></div>
    </div>
    <?php endif ?>
    <?php $desc=$prod['desc']??''; if($desc && $desc!==$prod['color']): ?>
    <p class="prod-desc"><?=htmlspecialchars($desc)?></p>
    <?php endif ?>
    <?php if(($prod['stk']??0)>0): ?>
    <div class="prod-stk"><?=$prod['stk']?> unidades disponibles</div>
    <?php endif ?>
    <a class="cta-wa" href="<?=$WA?>?text=<?=$wa_msg?>" target="_blank">
      <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
      Pedir por WhatsApp
    </a>
    <p class="cta-note">Respondemos en minutos &middot; Lunes a sábado</p>
  </div>
</div>

<?php if(!empty($rel)): ?>
<div class="rel-wrap">
  <h2 class="rel-title">Más en <?=htmlspecialchars($prod['categoria'])?></h2>
  <div class="rel-grid">
    <?php foreach($rel as $r):
      $rf=foto($r['id']); $rp=$r['precio']??0;
    ?>
    <a class="rel-card" href="producto.php?id=<?=$r['id']?>">
      <div class="rel-card-inner">
        <div class="rel-img">
          <?php if($rf): ?><img src="<?=$rf?>" alt="<?=htmlspecialchars($r['nombre'])?>" loading="lazy">
          <?php else: ?><div class="rel-img-ph"></div><?php endif?>
        </div>
        <div class="rel-body">
          <div class="rel-name"><?=htmlspecialchars($r['nombre'])?></div>
          <?php if($rp>0): ?><div class="rel-price"><?=pf($rp)?></div><?php endif?>
        </div>
      </div>
    </a>
    <?php endforeach ?>
  </div>
</div>
<?php endif ?>

<div class="ft-line">
  <strong>Mundo Accesorios</strong> &middot; Accesorios para celular &middot;
  WhatsApp <a href="<?=$WA?>">+57 323 345 3004</a>
</div>

<a class="wa-float" href="<?=$WA?>?text=<?=$wa_msg?>" target="_blank">
  <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
</a>

<script>
function cambiarFoto(src,thumb){
  var img=document.getElementById('mainImg');
  if(img){img.style.opacity='.3';setTimeout(function(){img.src=src;img.style.opacity='1';img.style.transition='opacity .2s';},150);}
  document.querySelectorAll('.thumb').forEach(function(t){t.classList.remove('act');});
  thumb.classList.add('act');
}
</script>
</body>
</html>
