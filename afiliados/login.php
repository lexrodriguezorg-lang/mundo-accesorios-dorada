<?php
require_once __DIR__ . '/../afiliados_lib.php';

// Sesión aislada del admin (no compartir cookie PHPSESSID)
session_name('afil_sess');
session_start();

if (($_GET['salir'] ?? '') === '1') {
    unset($_SESSION['afil_ok'], $_SESSION['afil_user']);
    header('Location: login.php'); exit;
}

$err = '';
if ($_POST && isset($_POST['clave'])) {
    $u = trim($_POST['usuario'] ?? '');
    $c = (string)($_POST['clave'] ?? '');
    $afil = afil_find_user($u);
    if ($afil && !empty($afil['activa']) && !empty($afil['clave_hash'])
        && password_verify($c, $afil['clave_hash'])) {
        $_SESSION = [];                  // limpiar cualquier dato previo
        session_regenerate_id(true);
        $_SESSION['afil_ok']   = true;
        $_SESSION['afil_user'] = $afil['user'];
        header('Location: dashboard.php'); exit;
    }
    $err = 'Usuario o clave incorrectos, o cuenta desactivada';
}

if (!empty($_SESSION['afil_ok'])) {
    header('Location: dashboard.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Portal de afiliadas · Mundo Accesorios</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;1,400;1,500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
:root{--ink:#0A0410;--fu:#7209B7;--mag:#B5179E;--pink:#F72585;
  --grad:linear-gradient(135deg,#B5179E,#7209B7);--gs:linear-gradient(135deg,#F72585,#B5179E)}
html,body{min-height:100dvh;background:var(--ink);font-family:'DM Sans',sans-serif;color:#fff;-webkit-font-smoothing:antialiased}
body{display:flex;align-items:center;justify-content:center;padding:clamp(20px,5vw,48px);position:relative;overflow:hidden}
body::before{content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse 60% 70% at 20% 30%,rgba(247,37,133,.16),transparent),
             radial-gradient(ellipse 55% 60% at 80% 70%,rgba(114,9,183,.22),transparent)}
.box{position:relative;z-index:1;max-width:380px;width:100%;
  background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
  border-radius:22px;padding:clamp(28px,6vw,40px);backdrop-filter:blur(10px)}
.icon{width:66px;height:66px;margin:0 auto 18px;border-radius:50%;background:var(--grad);
  display:flex;align-items:center;justify-content:center;box-shadow:0 10px 34px rgba(181,23,158,.32)}
.icon svg{width:32px;height:32px;stroke:#fff;fill:none;stroke-width:1.6}
h1{font-family:'Cormorant Garamond',serif;font-size:1.9rem;font-weight:500;text-align:center;line-height:1.05;margin-bottom:6px}
h1 em{font-style:italic;background:var(--gs);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.sub{text-align:center;font-size:.74rem;color:rgba(255,255,255,.45);letter-spacing:.04em;margin-bottom:22px}
.err{font-size:.72rem;background:rgba(247,37,133,.14);border:1px solid rgba(247,37,133,.32);
  color:#F8B4D4;border-radius:10px;padding:9px 12px;margin-bottom:12px;text-align:center}
.inp{width:100%;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);
  border-radius:12px;padding:12px 14px;color:#fff;font-family:inherit;font-size:.85rem;
  outline:none;margin-bottom:10px;transition:.15s}
.inp::placeholder{color:rgba(255,255,255,.35)}
.inp:focus{border-color:#F72585;background:rgba(255,255,255,.08)}
.btn{width:100%;background:var(--grad);border:none;color:#fff;font-family:inherit;
  font-weight:500;font-size:.85rem;padding:13px;border-radius:12px;cursor:pointer;
  letter-spacing:.03em;margin-top:6px;box-shadow:0 8px 26px rgba(181,23,158,.28);transition:.15s}
.btn:hover{transform:translateY(-2px);box-shadow:0 12px 34px rgba(181,23,158,.4)}
.foot{text-align:center;font-size:.68rem;color:rgba(255,255,255,.3);margin-top:18px;line-height:1.7}
.foot a{color:#F72585;text-decoration:none}
</style>
</head>
<body>
<div class="box">
  <div class="icon">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M12 14c-4 0-7 2-7 5v1h14v-1c0-3-3-5-7-5z"/></svg>
  </div>
  <h1>Portal de <em>afiliadas</em></h1>
  <div class="sub">Tus clics, ventas y comisiones</div>
  <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif ?>
  <form method="POST" autocomplete="off">
    <input class="inp" type="text" name="usuario" placeholder="Usuario" autofocus>
    <input class="inp" type="password" name="clave" placeholder="Clave">
    <button class="btn" type="submit">Entrar</button>
  </form>
  <div class="foot">
    ¿Sin cuenta? Escribinos por <a href="https://wa.me/573233453004" target="_blank">WhatsApp</a><br>
    <a href="/">&larr; Volver al sitio</a>
  </div>
</div>
</body>
</html>
