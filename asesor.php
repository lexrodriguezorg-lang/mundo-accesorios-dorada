<?php
$WA_BASE = 'https://wa.me/573233453004';
$waMsg   = urlencode('Hola linda 🌸 quiero que me ayudes a elegir. Cuéntame de tu asesor virtual.');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Asesor Virtual · Mundo Accesorios</title>
<meta name="description" content="Un asesor virtual que te ayuda a elegir el accesorio perfecto según tu presupuesto y gustos.">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;1,400;1,500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
:root{
  --ink:#0A0410;--fu:#7209B7;--mag:#B5179E;--pink:#F72585;
  --grad:linear-gradient(135deg,#B5179E,#7209B7);
  --gs:linear-gradient(135deg,#F72585,#B5179E);
}
html,body{min-height:100dvh;background:var(--ink);font-family:'DM Sans',sans-serif;color:#fff;-webkit-font-smoothing:antialiased}
body{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:clamp(24px,6vw,60px);
  position:relative;overflow:hidden
}
body::before{content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse 60% 70% at 20% 30%,rgba(247,37,133,.18),transparent),
             radial-gradient(ellipse 55% 60% at 80% 70%,rgba(114,9,183,.22),transparent)}

.top{position:absolute;top:max(env(safe-area-inset-top),18px);left:18px;z-index:10}
.back{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:50px;
  background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);
  color:rgba(255,255,255,.75);font-size:.72rem;text-decoration:none;transition:.2s}
.back:hover{background:rgba(255,255,255,.11);color:#fff}
.back svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.5}

.card{
  position:relative;z-index:1;
  max-width:480px;width:100%;
  background:rgba(255,255,255,.03);
  border:1px solid rgba(255,255,255,.08);
  border-radius:24px;
  padding:clamp(28px,6vw,48px) clamp(22px,5vw,42px);
  text-align:center;
  backdrop-filter:blur(12px)
}
.icon{
  width:84px;height:84px;margin:0 auto 22px;
  border-radius:50%;
  background:var(--grad);
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 12px 40px rgba(181,23,158,.35);
  animation:float 3.5s ease-in-out infinite
}
.icon svg{width:40px;height:40px;stroke:#fff;fill:none;stroke-width:1.6}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}

.badge{
  display:inline-block;padding:5px 14px;border-radius:50px;
  background:linear-gradient(135deg,rgba(247,37,133,.18),rgba(114,9,183,.18));
  border:1px solid rgba(247,37,133,.28);
  font-size:.55rem;font-weight:600;letter-spacing:.2em;text-transform:uppercase;
  color:#F72585;margin-bottom:20px
}
h1{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,7vw,2.8rem);
  font-weight:500;line-height:1.05;letter-spacing:-.01em;margin-bottom:12px}
h1 em{font-style:italic;background:var(--gs);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.lead{font-size:clamp(.82rem,2.4vw,.95rem);font-weight:300;
  color:rgba(255,255,255,.65);line-height:1.7;margin-bottom:24px}

.feat{text-align:left;margin:22px 0 28px;display:flex;flex-direction:column;gap:10px}
.feat-row{display:flex;align-items:flex-start;gap:10px;font-size:.82rem;
  color:rgba(255,255,255,.72);font-weight:300;line-height:1.5}
.feat-dot{width:18px;height:18px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,rgba(247,37,133,.3),rgba(114,9,183,.3));
  border:1px solid rgba(247,37,133,.4);
  display:flex;align-items:center;justify-content:center;margin-top:1px}
.feat-dot::before{content:'';width:5px;height:5px;border-radius:50%;background:#F72585}
.feat-row strong{color:#fff;font-weight:500}

.actions{display:flex;flex-direction:column;gap:10px;width:100%;max-width:340px;margin:0 auto}
.btn{
  display:flex;align-items:center;justify-content:center;gap:9px;
  padding:14px 20px;border-radius:14px;
  font-size:.82rem;font-weight:500;letter-spacing:.03em;text-decoration:none;
  transition:.2s;border:none;cursor:pointer;font-family:inherit
}
.btn svg{width:16px;height:16px;flex-shrink:0}
.btn.wa{background:linear-gradient(135deg,#25D366,#1AAF55);color:#fff;
  box-shadow:0 6px 24px rgba(37,211,102,.28)}
.btn.wa:hover{transform:translateY(-2px);box-shadow:0 12px 36px rgba(37,211,102,.38)}
.btn.wa svg{fill:#fff}
.btn.sec{background:rgba(255,255,255,.06);color:#fff;border:1px solid rgba(255,255,255,.14)}
.btn.sec:hover{background:rgba(255,255,255,.11);border-color:rgba(255,255,255,.26)}

.footline{margin-top:26px;font-size:.65rem;color:rgba(255,255,255,.35);letter-spacing:.04em}
</style>
</head>
<body>

<div class="top">
  <a class="back" href="/">
    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
    Inicio
  </a>
</div>

<div class="card">
  <div class="icon">
    <svg viewBox="0 0 24 24">
      <circle cx="12" cy="8" r="4"/>
      <path d="M12 14c-4 0-7 2-7 5v1h14v-1c0-3-3-5-7-5z"/>
      <path d="M9 7.5h0M15 7.5h0" stroke-linecap="round" stroke-width="2"/>
    </svg>
  </div>
  <div class="badge">Próximamente</div>
  <h1>Asesor <em>virtual</em></h1>
  <p class="lead">Estamos entrenando a nuestra asesora digital para que te ayude a elegir el accesorio perfecto según tu presupuesto, estilo y para quién es.</p>

  <div class="feat">
    <div class="feat-row"><span class="feat-dot"></span><span>Te recomienda productos <strong>según tu presupuesto</strong></span></div>
    <div class="feat-row"><span class="feat-dot"></span><span>Arma <strong>combos y kits</strong> para regalo</span></div>
    <div class="feat-row"><span class="feat-dot"></span><span>Responde dudas sobre <strong>stock y envíos</strong> al instante</span></div>
  </div>

  <div class="actions">
    <a class="btn wa" href="<?=$WA_BASE?>?text=<?=$waMsg?>" target="_blank" rel="noopener">
      <svg viewBox="0 0 24 24"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51H8.1c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.87 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35m-5.42 7.4h-.01a9.87 9.87 0 01-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37A9.86 9.86 0 012.06 12C2.06 6.5 6.5 2.06 12 2.06S21.94 6.5 21.94 12 17.5 21.94 12 21.94m8.41-18.3A11.82 11.82 0 0012.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 005.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89 0-3.18-1.24-6.16-3.48-8.41"/></svg>
      Hablar con una asesora humana
    </a>
    <a class="btn sec" href="catalogo.php">Explorar catálogo completo</a>
  </div>

  <p class="footline">Mientras tanto, nuestro equipo responde por WhatsApp en minutos.</p>
</div>

</body>
</html>
