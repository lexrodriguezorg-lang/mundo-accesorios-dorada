<?php
// ══════════════════════════════════════════════════════════════
//  PANEL ADMIN · MUNDO ACCESORIOS DORADA
//  Fuente de datos: catalogo.json  (sin MySQL)
//  admin.php
// ══════════════════════════════════════════════════════════════
define('MAX_MB',    8);
define('MAX_SLOTS', 6);
define('DATA',      __DIR__ . '/catalogo.json');

// ── Usuarios del panel ───────────────────────────────────────
// admin: control total · vendedora: solo auditoría/inventario del local
const USUARIOS = [
    'admin'     => ['clave' => 'mundoacc2026', 'rol' => 'admin',     'nombre' => 'Administrador'],
    'vendedora' => ['clave' => 'dorada2026',   'rol' => 'vendedora', 'nombre' => 'Vendedora Local'],
];
// Compatibilidad con la clave histórica de un solo campo (sin usuario)
const CLAVE_LEGACY = 'mundoacc2026';

session_start();
if (($_GET['salir'] ?? '') === '1') { session_destroy(); header('Location: admin.php'); exit; }

$loginErr = '';
if ($_POST && isset($_POST['clave'])) {
    $u = trim($_POST['usuario'] ?? '');
    $c = (string)($_POST['clave'] ?? '');
    if ($u === '' && $c === CLAVE_LEGACY) {
        $_SESSION['ok'] = true; $_SESSION['user'] = 'admin'; $_SESSION['rol'] = 'admin';
    } elseif (isset(USUARIOS[$u]) && hash_equals(USUARIOS[$u]['clave'], $c)) {
        $_SESSION['ok'] = true; $_SESSION['user'] = $u; $_SESSION['rol'] = USUARIOS[$u]['rol'];
    } else {
        $loginErr = 'Usuario o clave incorrectos';
    }
}
$auth = $_SESSION['ok'] ?? false;
$user = $_SESSION['user'] ?? '';
$rol  = $_SESSION['rol']  ?? '';
$esAdmin     = $rol === 'admin';
$esVendedora = $rol === 'vendedora';

// ── JSON helpers ─────────────────────────────────────────────
function readData(): array {
    if (!file_exists(DATA)) return ['v'=>1,'productos'=>[]];
    return json_decode(file_get_contents(DATA), true) ?: ['v'=>1,'productos'=>[]];
}
function writeData(array $data): bool {
    return file_put_contents(DATA,
        json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        LOCK_EX) !== false;
}
function nextId(array $data): int {
    if (empty($data['productos'])) return 1001;
    return max(array_column($data['productos'], 'id')) + 1;
}
function findIdx(array $data, int $id): int {
    foreach ($data['productos'] as $i => $p) if ($p['id'] === $id) return $i;
    return -1;
}

// ── Foto helpers ──────────────────────────────────────────────
function upDir(): string { return __DIR__ . '/uploads/fotos-productos/'; }
function url_f(int $id, int $slot = 1): ?string {
    $base = $slot === 1 ? $id : "{$id}_{$slot}";
    foreach (['jpg','png','jpeg','webp'] as $e)
        if (file_exists(upDir()."{$base}.{$e}")) return "/uploads/fotos-productos/{$base}.{$e}";
    return null;
}
function fotos_prod(int $id): array {
    $out = [];
    for ($s = 1; $s <= MAX_SLOTS; $s++) $out[$s] = url_f($id, $s);
    return $out;
}
function foto_count(int $id): int {
    $n = 0;
    for ($s = 1; $s <= MAX_SLOTS; $s++) if (url_f($id, $s)) $n++;
    return $n;
}
function tiene(int $id): bool { return url_f($id) !== null; }

// ── Enriquecer producto con fotos ─────────────────────────────
function enrichProd(array $p): array {
    $id = $p['id'];
    $p['fotos']   = fotos_prod($id);
    $p['n_fotos'] = foto_count($id);
    $p['tiene']   = tiene($id);
    return $p;
}

// ════════════════════════════════════════════════════════════
//  AJAX ENDPOINTS
// ════════════════════════════════════════════════════════════
$action = $_GET['action'] ?? '';

// ── CREAR PRODUCTO ───────────────────────────────────────────
if ($auth && $action === 'crear') {
    header('Content-Type: application/json');
    $nombre   = trim($_POST['nombre'] ?? '');
    $cat      = trim($_POST['categoria'] ?? '');
    $subgrupo = trim($_POST['subgrupo'] ?? '');
    $precio   = max(0, intval($_POST['precio'] ?? 0));
    $stk      = max(0, intval($_POST['stk'] ?? 0));
    $desc     = trim($_POST['desc'] ?? '');
    if (!$nombre || !$cat) die(json_encode(['ok'=>false,'msg'=>'Nombre y categoría requeridos']));
    $data = readData();
    $id   = nextId($data);
    $prod = [
        'id'        => $id,
        'nombre'    => $nombre,
        'categoria' => $cat,
        'subgrupo'  => $subgrupo,
        'precio'    => $precio,
        'stk'       => $stk,
        'desc'      => $desc,
        'activo'    => true,
        'destacado' => false,
        'creado'    => date('Y-m-d'),
        'updated'   => date('Y-m-d'),
    ];
    $data['productos'][] = $prod;
    $ok = writeData($data);
    echo json_encode(['ok'=>$ok, 'producto'=>enrichProd($prod)]);
    exit;
}

// ── EDITAR PRODUCTO ──────────────────────────────────────────
if ($auth && $action === 'editar') {
    header('Content-Type: application/json');
    $id = intval($_GET['id'] ?? 0);
    if (!$id) die(json_encode(['ok'=>false]));
    $data = readData();
    $idx  = findIdx($data, $id);
    if ($idx < 0) die(json_encode(['ok'=>false,'msg'=>'No encontrado']));
    $p = &$data['productos'][$idx];
    foreach (['nombre','categoria','desc','subgrupo'] as $f)
        if (array_key_exists($f, $_POST)) $p[$f] = trim($_POST[$f]);
    if (array_key_exists('precio', $_POST))    $p['precio']    = max(0, intval($_POST['precio']));
    if (array_key_exists('stk', $_POST))       $p['stk']       = max(0, intval($_POST['stk']));
    if (array_key_exists('activo', $_POST))    $p['activo']    = $_POST['activo'] === '1';
    if (array_key_exists('destacado', $_POST)) $p['destacado'] = $_POST['destacado'] === '1';
    if (array_key_exists('foto_home', $_POST)) $p['foto_home'] = max(1, min(6, intval($_POST['foto_home'])));
    if (array_key_exists('en_home', $_POST))   $p['en_home']   = $_POST['en_home'] === '1';
    if (array_key_exists('pos_home', $_POST))  $p['pos_home']  = max(0, intval($_POST['pos_home']));
    $p['updated'] = date('Y-m-d');
    $ok = writeData($data);
    echo json_encode(['ok'=>$ok]);
    exit;
}

// ── ELIMINAR PRODUCTO (soft delete) ──────────────────────────
if ($auth && $action === 'eliminar') {
    header('Content-Type: application/json');
    $id = intval($_GET['id'] ?? 0);
    if (!$id) die(json_encode(['ok'=>false]));
    $data = readData();
    $idx  = findIdx($data, $id);
    if ($idx < 0) die(json_encode(['ok'=>false]));
    $data['productos'][$idx]['activo']  = false;
    $data['productos'][$idx]['updated'] = date('Y-m-d');
    echo json_encode(['ok'=>writeData($data)]);
    exit;
}

// ── PURGE (eliminar definitivo) ──────────────────────────────
if ($auth && $action === 'purge') {
    header('Content-Type: application/json');
    $id = intval($_GET['id'] ?? 0);
    if (!$id) die(json_encode(['ok'=>false]));
    $data = readData();
    $idx  = findIdx($data, $id);
    if ($idx < 0) die(json_encode(['ok'=>false]));
    array_splice($data['productos'], $idx, 1);
    // Borrar fotos del disco
    $dir = upDir();
    for ($s = 1; $s <= MAX_SLOTS; $s++) {
        $base = $s === 1 ? $id : "{$id}_{$s}";
        foreach (['jpg','png','jpeg','webp'] as $e) {
            $f = $dir."{$base}.{$e}";
            if (file_exists($f)) unlink($f);
        }
    }
    echo json_encode(['ok'=>writeData($data)]);
    exit;
}

// ── POR CATEGORIA ─────────────────────────────────────────────
if ($auth && $action === 'por_categoria') {
    header('Content-Type: application/json');
    $cat      = trim($_GET['cat'] ?? '');
    $mostrar  = $_GET['inactivos'] ?? '0';
    $data     = readData();
    $result   = [];
    foreach ($data['productos'] as $p) {
        if ($p['categoria'] !== $cat) continue;
        if (!$p['activo'] && $mostrar !== '1') continue;
        $result[] = enrichProd($p);
    }
    usort($result, fn($a,$b) => strcmp($a['nombre'], $b['nombre']));
    echo json_encode($result);
    exit;
}

// ── CATEGORIAS (stats) ────────────────────────────────────────
if ($auth && $action === 'categorias') {
    header('Content-Type: application/json');
    $data   = readData();
    $cats   = [];
    foreach ($data['productos'] as $p) {
        if (!$p['activo']) continue;
        $c = $p['categoria'];
        if (!isset($cats[$c])) $cats[$c] = ['nombre'=>$c,'total'=>0,'con_foto'=>0];
        $cats[$c]['total']++;
        if (tiene($p['id'])) $cats[$c]['con_foto']++;
    }
    $result = array_values($cats);
    foreach ($result as &$r) $r['pct'] = $r['total'] ? round($r['con_foto']/$r['total']*100) : 0;
    echo json_encode($result);
    exit;
}

// ── BUSCAR ───────────────────────────────────────────────────
if ($auth && $action === 'buscar') {
    header('Content-Type: application/json');
    $q = mb_strtolower(trim($_GET['q'] ?? ''));
    if (strlen($q) < 2) die(json_encode([]));
    $data   = readData();
    $result = [];
    foreach ($data['productos'] as $p) {
        if (!$p['activo']) continue;
        $hay = mb_strpos(mb_strtolower($p['nombre']), $q) !== false
            || mb_strpos(mb_strtolower($p['categoria']), $q) !== false
            || mb_strpos(mb_strtolower($p['desc'] ?? ''), $q) !== false
            || (string)$p['id'] === $q;
        if ($hay) $result[] = enrichProd($p);
    }
    usort($result, fn($a,$b) => strcmp($a['nombre'], $b['nombre']));
    echo json_encode(array_slice($result, 0, 40));
    exit;
}

// ── UPLOAD FOTO ───────────────────────────────────────────────
if ($auth && $action === 'upload') {
    header('Content-Type: application/json');
    $id   = intval($_GET['id'] ?? 0);
    $slot = max(1, min(MAX_SLOTS, intval($_GET['slot'] ?? 1)));
    $file = $_FILES['foto'] ?? null;
    if (!$id || !$file || $file['error'] !== UPLOAD_ERR_OK)
        die(json_encode(['ok'=>false,'msg'=>'Error en archivo']));
    if ($file['size'] > MAX_MB*1024*1024)
        die(json_encode(['ok'=>false,'msg'=>'Supera '.MAX_MB.'MB']));
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/gif']))
        die(json_encode(['ok'=>false,'msg'=>'Solo JPG/PNG/WEBP']));
    $dir = upDir();
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fname = $slot === 1 ? "{$id}.jpg" : "{$id}_{$slot}.jpg";
    $img = match($mime) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => imagecreatefrompng($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
        'image/gif'  => imagecreatefromgif($file['tmp_name']),
        default      => null
    };
    if (!$img) die(json_encode(['ok'=>false,'msg'=>'No se pudo procesar']));
    $w = imagesx($img); $h = imagesy($img);
    if ($w > 1200) {
        $nh  = intval($h * 1200 / $w);
        $res = imagecreatetruecolor(1200, $nh);
        imagefill($res, 0, 0, imagecolorallocate($res, 255, 255, 255));
        imagecopyresampled($res, $img, 0,0, 0,0, 1200,$nh, $w,$h);
        imagedestroy($img); $img = $res;
    }
    $ok  = imagejpeg($img, $dir.$fname, 88);
    imagedestroy($img);
    $url = "/uploads/fotos-productos/{$fname}";
    echo json_encode($ok
        ? ['ok'=>true,'url'=>$url.'?v='.time(),'nfotos'=>foto_count($id)]
        : ['ok'=>false,'msg'=>'Permisos de carpeta']);
    exit;
}

// ── BORRAR FOTO ───────────────────────────────────────────────
if ($auth && $action === 'delete_foto') {
    header('Content-Type: application/json');
    $id   = intval($_GET['id'] ?? 0);
    $slot = max(1, min(MAX_SLOTS, intval($_GET['slot'] ?? 1)));
    $dir  = upDir(); $del = false;
    foreach (['jpg','png','jpeg','webp'] as $e) {
        $f = $slot === 1 ? "{$id}.{$e}" : "{$id}_{$slot}.{$e}";
        if (file_exists($dir.$f)) { unlink($dir.$f); $del = true; }
    }
    echo json_encode(['ok'=>$del,'nfotos'=>foto_count($id)]);
    exit;
}

// ── LEER HOME SECCIONES ──────────────────────────────────────
if ($auth && $action === 'home_get') {
    header('Content-Type: application/json');
    $data = readData();
    echo json_encode(['ok'=>true,'secciones'=>$data['home_secciones']??[]]);
    exit;
}

// ── GUARDAR HOME SECCIONES ───────────────────────────────────
if ($auth && $action === 'home_secciones') {
    header('Content-Type: application/json');
    $raw = json_decode(file_get_contents('php://input'), true);
    if (!is_array($raw)) die(json_encode(['ok'=>false,'msg'=>'Datos inválidos']));
    $valid = [];
    foreach($raw as $item){
        $cat = trim($item['cat'] ?? '');
        $max = max(1, min(20, intval($item['max'] ?? 8)));
        if($cat) $valid[] = ['cat'=>$cat,'max'=>$max];
    }
    $d2 = readData();
    $d2['home_secciones'] = $valid;
    $ok = writeData($d2);
    echo json_encode(['ok'=>$ok,'n'=>count($valid)]);
    exit;
}

// ── AUDITORÍA DE INVENTARIO (semáforo foto + precio) ─────────
if ($auth && $action === 'auditoria') {
    header('Content-Type: application/json');
    $data = readData();
    // Umbrales (ajustables)
    $precioMin   = 1000;   // por debajo se considera precio sospechoso (rojo si 0, amarillo si <min)
    $rows = [];
    $cnt = ['foto'=>['v'=>0,'a'=>0,'r'=>0], 'precio'=>['v'=>0,'a'=>0,'r'=>0], 'total'=>0];
    foreach ($data['productos'] as $p) {
        if (empty($p['activo'])) continue;
        $id     = (int)$p['id'];
        $nf     = foto_count($id);
        $precio = (int)($p['precio'] ?? 0);

        // Semáforo foto: rojo = 0, amarillo = solo principal (1), verde = 2+
        if      ($nf === 0) $sf = 'r';
        elseif  ($nf === 1) $sf = 'a';
        else                $sf = 'v';

        // Semáforo precio: rojo = 0, amarillo = >0 y <min, verde = >=min
        if      ($precio <= 0)         $sp = 'r';
        elseif  ($precio < $precioMin) $sp = 'a';
        else                            $sp = 'v';

        $cnt['foto'][$sf]++;
        $cnt['precio'][$sp]++;
        $cnt['total']++;

        $rows[] = [
            'id'        => $id,
            'nombre'    => $p['nombre'] ?? '',
            'categoria' => $p['categoria'] ?? '',
            'subgrupo'  => $p['subgrupo'] ?? '',
            'precio'    => $precio,
            'stk'       => (int)($p['stk'] ?? 0),
            'n_fotos'   => $nf,
            'foto_url'  => url_f($id, 1),
            'sem_foto'  => $sf,
            'sem_precio'=> $sp,
        ];
    }
    usort($rows, function($a,$b){
        // Ordenar por gravedad: rojos primero, luego amarillos, luego verdes
        $rank = ['r'=>0,'a'=>1,'v'=>2];
        $sa = min($rank[$a['sem_foto']], $rank[$a['sem_precio']]);
        $sb = min($rank[$b['sem_foto']], $rank[$b['sem_precio']]);
        if ($sa !== $sb) return $sa - $sb;
        return strcmp($a['categoria'].$a['nombre'], $b['categoria'].$b['nombre']);
    });
    echo json_encode(['ok'=>true,'rows'=>$rows,'cnt'=>$cnt,'precio_min'=>$precioMin]);
    exit;
}

// ── STATS GLOBALES ────────────────────────────────────────────
$data      = readData();
$activos   = array_filter($data['productos'], fn($p) => $p['activo']);
$gTotal    = count($activos);
$gCon      = count(array_filter($activos, fn($p) => tiene($p['id'])));
$gSin      = $gTotal - $gCon;
$gPct      = $gTotal ? round($gCon/$gTotal*100) : 0;
$gDest     = count(array_filter($activos, fn($p) => $p['destacado']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Panel Admin · Mundo Accesorios</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@400;500;600;700&family=Space+Mono&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{
  --azul:#00AAFF;--vi:#4921D8;--fu:#7F1FDB;
  --grad:linear-gradient(135deg,#00AAFF 0%,#4921D8 55%,#7F1FDB 100%);
  --ok:#00C878;--err:#D42356;--warn:#F5A623;
  --bg:#F0F4FF;--bd:#E8ECF8;--mu:#606880;--txt:#09101F;
}
body{font-family:"Poppins",sans-serif;background:var(--bg);color:var(--txt);min-height:100vh}

/* ── LOGIN ── */
.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--grad)}
.lbox{background:#fff;border-radius:20px;padding:40px;width:340px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.lbox h1{font-family:"Bebas Neue";font-size:28px;letter-spacing:2px;color:var(--vi);margin-bottom:4px}
.lbox p{font-size:12px;color:var(--mu);margin-bottom:22px}
.lbox input{width:100%;border:1.5px solid var(--bd);border-radius:10px;padding:12px 16px;font-family:"Poppins";font-size:14px;outline:none;margin-bottom:12px}
.lbox input:focus{border-color:var(--fu)}
.lbox button{width:100%;background:var(--grad);border:none;border-radius:10px;padding:13px;color:#fff;font-family:"Poppins";font-size:14px;font-weight:700;cursor:pointer}
.lbox .err{color:var(--err);font-size:12px;margin-bottom:10px}

/* ── HEADER ── */
.hdr{background:var(--grad);padding:14px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;position:sticky;top:0;z-index:200;box-shadow:0 2px 20px rgba(73,33,216,.3)}
.hdr-title{font-family:"Bebas Neue";font-size:22px;letter-spacing:2px;color:#fff}
.hdr-sub{color:rgba(255,255,255,.6);font-size:10px;margin-top:1px}
.hbtns{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.hbtn{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:8px;padding:6px 14px;color:#fff;font-family:"Poppins";font-size:11px;font-weight:600;cursor:pointer;text-decoration:none;transition:.2s}
.hbtn:hover{background:rgba(255,255,255,.28)}
.hbtn.w{background:#fff;color:var(--vi)}
.dest-chip{background:rgba(245,166,35,.2);color:#a07010;border:1px solid rgba(245,166,35,.35);border-radius:100px;padding:3px 10px;font-size:10px;font-weight:700}

/* ── STATS ── */
.gstats{background:#fff;border-bottom:1px solid var(--bd);padding:10px 24px;display:flex;align-items:center;gap:24px;flex-wrap:wrap}
.gstat{display:flex;align-items:center;gap:8px}
.gn{font-family:"Bebas Neue";font-size:28px;line-height:1}
.gn.t{color:var(--vi)}.gn.ok{color:var(--ok)}.gn.no{color:var(--err)}
.gl{font-size:10px;color:var(--mu);line-height:1.4}
.gl strong{display:block;font-size:11px;color:var(--txt)}
.gprog{flex:1;min-width:180px}
.pb-o{background:var(--bg);border-radius:100px;height:7px;overflow:hidden;margin:3px 0}
.pb-i{height:100%;border-radius:100px;background:linear-gradient(90deg,var(--ok),#00E090);transition:width .6s}

/* ── SEARCH ── */
.search-wrap{background:#fff;padding:12px 24px;border-bottom:1px solid var(--bd);position:sticky;top:60px;z-index:150}
.search-inner{display:flex;align-items:center;gap:10px;background:#F8F3FF;border:2px solid #E0D8FF;border-radius:14px;padding:9px 16px;transition:.25s}
.search-inner:focus-within{border-color:var(--fu);background:#fff;box-shadow:0 0 0 3px rgba(127,31,219,.1)}
.si-inp{flex:1;border:none;background:none;outline:none;font-family:"Poppins";font-size:14px;color:var(--txt)}
.si-inp::placeholder{color:#B0A8C8}
.si-cnt{font-size:11px;color:var(--vi);font-weight:700;white-space:nowrap}
.si-clr,.si-go{border:none;border-radius:8px;cursor:pointer;font-family:"Poppins";font-size:11px;font-weight:600;padding:6px 14px;transition:.2s}
.si-clr{background:none;color:#aaa;padding:0 4px;font-size:16px}
.si-clr:hover{color:var(--err)}
.si-go{background:var(--grad);color:#fff}
.search-hint{font-size:10px;color:var(--mu);margin-top:5px}

/* ── MAIN ── */
.main{max-width:1600px;margin:0 auto;padding:18px 24px}

/* ── SEARCH RESULTS HEADER ── */
.srb{background:#fff;border-radius:10px;padding:10px 16px;margin-bottom:14px;border:1px solid #E0D8FF;display:flex;align-items:center;justify-content:space-between;gap:10px}
.srb-txt{font-size:13px;font-weight:600;color:var(--vi)}
.srb-clr{background:none;border:1px solid var(--bd);border-radius:8px;padding:4px 12px;font-family:"Poppins";font-size:11px;cursor:pointer;color:var(--mu)}
.srb-clr:hover{color:var(--err);border-color:var(--err)}

/* ── ACCORDION ── */
.cat-block{margin-bottom:10px;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.07)}
.cat-hdr{display:flex;align-items:center;gap:10px;padding:12px 18px;cursor:pointer;user-select:none;color:#fff;transition:.15s}
.cat-hdr:hover{filter:brightness(1.08)}
.cat-name{font-family:"Bebas Neue";font-size:20px;letter-spacing:2px;flex:1}
.cat-badge{font-size:10px;background:rgba(255,255,255,.2);border-radius:100px;padding:2px 9px;font-weight:700}
.cat-miss{font-size:10px;background:rgba(255,60,60,.35);border-radius:100px;padding:2px 9px;font-weight:700}
.cat-mini-bar{width:56px;height:5px;background:rgba(255,255,255,.2);border-radius:100px;overflow:hidden}
.cat-mini-fill{height:100%;background:#fff;border-radius:100px}
.cat-pct{font-size:10px;font-weight:700;white-space:nowrap}
.cat-arrow{font-size:13px;opacity:.8;transition:transform .25s}
.cat-arrow.open{transform:rotate(90deg)}
.cat-body{background:#fff}
.cat-loading{text-align:center;padding:28px;color:var(--mu);font-size:13px}
.cat-sub-bar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;padding:10px 14px;background:#F8F3FF;border-bottom:1px solid var(--bd)}
.cat-sub-info{font-size:12px;color:var(--mu)}
.cat-sub-info strong{color:var(--vi)}
.cat-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.cf-btn{padding:3px 10px;border-radius:100px;border:1.5px solid var(--bd);background:#fff;font-family:"Poppins";font-size:10px;font-weight:600;color:var(--mu);cursor:pointer;transition:.15s}
.cf-btn:hover,.cf-btn.act{background:var(--vi);border-color:var(--vi);color:#fff}
.sg-bar{display:flex;gap:6px;flex-wrap:wrap;padding:8px 14px;background:#F0EEFF;border-bottom:1px solid var(--bd)}

/* HOME CONFIG PANEL */
.hcp-wrap{background:#fff;border:1.5px solid var(--bd);border-radius:16px;overflow:hidden;margin:20px 0}
.hcp-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:var(--soft);border-bottom:1px solid var(--bd);cursor:pointer;user-select:none}
.hcp-head h3{font-size:.8rem;font-weight:700;color:var(--vi);letter-spacing:.04em}
.hcp-body{padding:16px 20px;display:none}
.hcp-body.open{display:block}
.hcp-list{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.hcp-item{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--soft);border-radius:10px;border:1.5px solid var(--bd);transition:.15s}
.hcp-item.drag-over{border-color:var(--fu);background:#ede8ff}
.hcp-item.dragging-src{opacity:.4}
.hcp-drag{cursor:grab;color:var(--mu);font-size:16px;flex-shrink:0;line-height:1}
.hcp-drag:active{cursor:grabbing}
.hcp-cat-name{flex:1;font-size:.8rem;font-weight:500;color:var(--ink)}
.hcp-max-wrap{display:flex;align-items:center;gap:6px;flex-shrink:0}
.hcp-max-wrap label{font-size:.65rem;color:var(--mu);white-space:nowrap}
.hcp-max{width:44px;padding:4px 6px;border:1.5px solid var(--bd);border-radius:6px;font-size:.75rem;text-align:center;background:#fff;color:var(--ink);font-family:"Poppins";-moz-appearance:textfield}
.hcp-max::-webkit-inner-spin-button{display:none}
.hcp-del{width:22px;height:22px;border-radius:50%;background:rgba(220,38,38,.1);border:none;color:#dc2626;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:.15s}
.hcp-del:hover{background:#dc2626;color:#fff}
.hcp-add{width:100%;padding:9px;border-radius:10px;border:1.5px dashed var(--bd);background:transparent;font-family:"Poppins";font-size:.75rem;color:var(--vi);cursor:pointer;transition:.15s}
.hcp-add:hover{border-color:var(--vi);background:var(--soft)}
.hcp-save{width:100%;margin-top:10px;padding:10px;border-radius:10px;background:var(--grad);border:none;color:#fff;font-family:"Poppins";font-size:.78rem;font-weight:600;cursor:pointer;transition:opacity .15s}
.hcp-save:hover{opacity:.88}
.hcp-status{font-size:.7rem;color:var(--vi);text-align:center;margin-top:6px;height:16px}
.sg-bar-label{font-size:9px;font-weight:700;color:var(--fu);letter-spacing:1px;text-transform:uppercase;align-self:center;margin-right:4px}
.sg-btn{padding:3px 10px;border-radius:100px;border:1.5px solid rgba(127,31,219,.2);background:#fff;font-family:"Poppins";font-size:10px;font-weight:600;color:var(--fu);cursor:pointer;transition:.15s}
.sg-btn:hover,.sg-btn.act{background:var(--fu);border-color:var(--fu);color:#fff}
.btn-new{padding:5px 14px;border-radius:8px;border:none;background:var(--grad);color:#fff;font-family:"Poppins";font-size:11px;font-weight:700;cursor:pointer;transition:.2s}
.btn-new:hover{opacity:.88}

/* ── FORM NUEVO PRODUCTO ── */
.new-prod-form{background:#F8F3FF;border:2px dashed #D0C8FF;border-radius:10px;padding:16px;margin:10px 14px}
.npf-title{font-family:"Bebas Neue";font-size:16px;letter-spacing:1px;color:var(--vi);margin-bottom:12px}
.npf-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px}
.npf-full{grid-column:1/-1}
.npf-label{font-size:10px;font-weight:600;color:var(--mu);margin-bottom:3px;display:block}
.npf-inp{width:100%;border:1.5px solid var(--bd);border-radius:8px;padding:8px 10px;font-family:"Poppins";font-size:12px;outline:none;background:#fff;transition:.2s}
.npf-inp:focus{border-color:var(--fu)}
.npf-sel{width:100%;border:1.5px solid var(--bd);border-radius:8px;padding:8px 10px;font-family:"Poppins";font-size:12px;outline:none;background:#fff;cursor:pointer}
.npf-btns{display:flex;gap:8px}
.npf-save{background:var(--grad);border:none;border-radius:8px;padding:9px 20px;color:#fff;font-family:"Poppins";font-size:12px;font-weight:700;cursor:pointer}
.npf-cancel{background:none;border:1px solid var(--bd);border-radius:8px;padding:9px 16px;font-family:"Poppins";font-size:12px;cursor:pointer;color:var(--mu)}
.npf-cancel:hover{color:var(--err);border-color:var(--err)}

/* ── GRID ── */
.pgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px;padding:12px 14px}

/* ── CARD ── */
.pcard{background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.07);border:2px solid transparent;transition:transform .2s,box-shadow .2s;position:relative}
.pcard:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.1)}
.pcard.ok{border-color:#b3f5db}.pcard.no{border-color:#ffd5d5;background:#fffafa}
.pcard.inactive{opacity:.55;border-color:#ddd;background:#f8f8f8}

/* ── FOTOS AREA: 6 slots con formato correcto por slot ── */
.fotos-area{display:grid;grid-template-columns:3fr 1fr 1fr;grid-template-rows:auto auto auto;gap:2px;background:#ddd8f0}
.fslot{position:relative;overflow:hidden;background:#f8f3ff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:2px}
.fslot:hover{background:#f0eaff}
/* Slots 1-3: cuadrados 1:1 */
.fslot[data-slot="1"]{grid-column:1;grid-row:1;aspect-ratio:1/1}
.fslot[data-slot="2"]{grid-column:2;grid-row:1;aspect-ratio:1/1}
.fslot[data-slot="3"]{grid-column:3;grid-row:1;aspect-ratio:1/1}
/* Slot 4: banner horizontal 16:9 — ancho completo */
.fslot[data-slot="4"]{grid-column:1/-1;grid-row:2;aspect-ratio:16/9}
/* Slots 5-6: story vertical 9:16 — mitad del ancho */
.fslot[data-slot="5"]{grid-column:1/3;grid-row:3;height:80px}
.fslot[data-slot="6"]{grid-column:3;grid-row:3;height:80px}
/* Foto dentro del slot */
.fslot img{width:100%;height:100%;object-fit:contain;padding:4px}
.fslot[data-slot="4"] img{object-fit:cover;padding:0}
.fslot[data-slot="5"] img,.fslot[data-slot="6"] img{object-fit:cover;padding:0}
.fslot img{width:100%;height:100%;object-fit:contain;padding:4px}
.fslot-lbl{font-size:8px;font-weight:700;color:#b0a0d0;text-align:center;padding:2px;line-height:1.2}
.fslot[data-slot="1"] .fslot-lbl{font-size:10px}
.fslot[data-slot="4"] .fslot-lbl{font-size:9px;color:rgba(73,33,216,.5)}
.fslot[data-slot="5"] .fslot-lbl,.fslot[data-slot="6"] .fslot-lbl{font-size:7px;color:rgba(127,31,219,.5)}
.slot-ovl{position:absolute;inset:0;background:rgba(73,33,216,.72);display:flex;align-items:center;justify-content:center;opacity:0;transition:.2s;color:#fff;font-size:11px;font-weight:700;pointer-events:none}
.fslot:hover .slot-ovl{opacity:1}
.slot-del{position:absolute;top:3px;right:3px;background:rgba(212,35,86,.9);border:none;color:#fff;border-radius:50%;width:17px;height:17px;font-size:11px;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:2;padding:0;line-height:1}
/* slot-home system replaced by .home-row */
.home-toggle{padding:3px 9px;border-radius:100px;border:1.5px solid #ddd;background:#f5f5f5;font-family:"Poppins";font-size:9px;font-weight:700;cursor:pointer;transition:.2s;color:#aaa}
.home-toggle.on{background:linear-gradient(135deg,#00AAFF,#4921D8);border-color:transparent;color:#fff}
.pos-wrap{display:flex;align-items:center;gap:4px;margin-bottom:4px}
.pos-lbl{font-size:9px;color:var(--mu);font-weight:600}
.pos-inp{width:48px;border:1.5px solid var(--bd);border-radius:6px;padding:3px 6px;font-family:"Space Mono";font-size:10px;text-align:center;outline:none;background:#fff}
.pos-inp:focus{border-color:var(--fu)}
.fc-badge{position:absolute;bottom:4px;left:4px;background:rgba(73,33,216,.82);color:#fff;font-family:"Space Mono";font-size:8px;font-weight:700;border-radius:100px;padding:1px 6px;pointer-events:none}

/* ── UPLOAD PROGRESS ── */
.uprog{position:absolute;inset:0;background:rgba(255,255,255,.96);display:none;flex-direction:column;align-items:center;justify-content:center;gap:8px;z-index:20}
.uprog.show{display:flex}
.uprog-bar{width:80%;height:5px;background:#E0E4F0;border-radius:100px;overflow:hidden}
.uprog-fill{height:100%;background:var(--grad);border-radius:100px;width:0;transition:width .15s}

/* ── CARD BODY ── */
.cinfo{padding:8px 10px 10px}
.cpid{font-family:"Space Mono";font-size:8px;color:var(--fu);font-weight:700;background:rgba(127,31,219,.07);padding:1px 5px;border-radius:4px;display:inline-block;margin-bottom:4px}
.cname{font-size:11px;font-weight:600;line-height:1.35;margin-bottom:4px}
.cname-inp{width:100%;border:1.5px solid var(--fu);border-radius:6px;padding:4px 7px;font-family:"Poppins";font-size:11px;font-weight:600;outline:none;margin-bottom:4px}
.cmeta{display:flex;align-items:center;justify-content:space-between;gap:4px;margin-bottom:5px}
.cprice{font-family:"Space Mono";font-size:10px;color:var(--vi);font-weight:700;cursor:pointer;border-bottom:1px dashed rgba(73,33,216,.3);transition:.15s}
.cprice:hover{color:var(--fu)}
.cprice-inp{font-family:"Space Mono";font-size:10px;color:var(--vi);font-weight:700;border:1.5px solid var(--vi);border-radius:5px;padding:2px 6px;width:90px;outline:none;background:#f0eeff}
.cstk-wrap{display:flex;align-items:center;gap:4px}
.cstk{font-size:9px;color:var(--mu);cursor:pointer}
.cstk:hover{color:var(--vi)}
.cstk-inp{font-family:"Space Mono";font-size:9px;color:var(--mu);border:1.5px solid var(--bd);border-radius:5px;padding:2px 5px;width:55px;outline:none}
.cdesc{font-size:10px;color:var(--mu);font-style:italic;cursor:pointer;min-height:16px;border-radius:4px;padding:2px 4px;margin-bottom:5px;line-height:1.4}
.cdesc:hover{background:#f8f3ff;font-style:normal}
.cdesc.empty{color:#ccc}
.cdesc-inp{width:100%;border:1.5px solid var(--fu);border-radius:6px;padding:4px 6px;font-family:"Poppins";font-size:10px;outline:none;resize:none;min-height:38px;margin-bottom:5px}
.cactions{display:flex;gap:4px;flex-wrap:wrap}
.cbtn{padding:4px 8px;border:none;border-radius:6px;font-family:"Poppins";font-size:9px;font-weight:700;cursor:pointer;transition:.2s;white-space:nowrap}
.cbtn.up{background:var(--grad);color:#fff;flex:1}
.cbtn.dest{background:#fff8e6;color:#a07010;border:1px solid rgba(245,166,35,.35)}
.cbtn.dest.on{background:var(--warn);color:#fff;border-color:var(--warn)}
.cbtn.off{background:#f0f0f0;color:#888;border:1px solid #ddd;font-size:8px}
.cbtn.off.on{background:var(--ok);color:#fff;border-color:var(--ok)}
.cbtn.del{background:#fff0f0;color:var(--err);border:1px solid rgba(212,35,86,.2);font-size:8px}
.cbtn.del:hover{background:var(--err);color:#fff}
.cstatus{height:4px}
.cstatus.ok{background:var(--ok)}.cstatus.no{background:#ffd5d5}
.home-row{display:flex;align-items:center;gap:5px;padding:5px 8px;background:#F8F3FF;border-top:1px solid #E8E0FF;flex-wrap:wrap;min-height:28px}
.home-row-lbl{font-size:9px;font-weight:700;color:var(--fu);letter-spacing:.5px;text-transform:uppercase;white-space:nowrap;flex-shrink:0}
.home-pill{padding:3px 9px;border-radius:100px;border:1.5px solid #D0C8FF;background:#fff;font-family:"Poppins";font-size:10px;font-weight:600;color:var(--vi);cursor:pointer;transition:.15s;line-height:1.4}
.home-pill:hover{border-color:var(--fu);color:var(--fu)}
.home-pill.act{background:linear-gradient(135deg,#F59E0B,#EF4444);border-color:transparent;color:#fff}
.home-row-empty{font-size:9px;color:#C0B8D8;font-style:italic}

/* ── DRAG OVER ── */
.pcard.drag-over .fotos-area{outline:3px dashed var(--fu);outline-offset:-3px}

/* ── TOAST ── */
.toast{position:fixed;bottom:22px;left:50%;transform:translateX(-50%) translateY(80px);background:var(--txt);color:#fff;padding:10px 22px;border-radius:100px;font-size:12px;font-weight:600;z-index:9999;transition:.3s;opacity:0;white-space:nowrap;pointer-events:none}
.toast.show{transform:translateX(-50%) translateY(0);opacity:1}
.toast.err{background:var(--err)}.toast.suc{background:var(--ok)}.toast.warn{background:var(--warn);color:#333}

.empty-state{text-align:center;padding:48px 20px;color:var(--mu)}
.empty-state h3{font-size:15px;font-weight:600;color:var(--vi);margin-bottom:6px}

@media(max-width:600px){
  .hdr,.gstats,.search-wrap,.main{padding-left:12px;padding-right:12px}
  .pgrid{grid-template-columns:repeat(2,1fr);gap:8px;padding:8px}
  .fotos-area{grid-template-rows:125px 44px}
  .npf-grid{grid-template-columns:1fr}
}

/* ── AUDITORÍA INVENTARIO ─────────────────────────────────── */
.aud-back{position:fixed;inset:0;background:rgba(9,16,31,.55);z-index:500;display:none;align-items:flex-start;justify-content:center;padding:24px;overflow-y:auto}
.aud-back.show{display:flex}
.aud-modal{background:#fff;border-radius:16px;width:100%;max-width:1200px;box-shadow:0 30px 80px rgba(0,0,0,.35);overflow:hidden;display:flex;flex-direction:column;max-height:calc(100vh - 48px)}
.aud-hdr{background:var(--grad);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.aud-hdr h2{font-family:"Bebas Neue";font-size:22px;letter-spacing:2px}
.aud-hdr .sub{font-size:11px;color:rgba(255,255,255,.75);margin-top:2px}
.aud-close{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:8px;width:32px;height:32px;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.aud-close:hover{background:rgba(255,255,255,.3)}
.aud-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;padding:14px 20px;background:#F8F3FF;border-bottom:1px solid var(--bd)}
.aud-stat-card{background:#fff;border:1.5px solid var(--bd);border-radius:12px;padding:12px 14px}
.aud-stat-card h4{font-size:11px;font-weight:700;color:var(--mu);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px}
.aud-pills{display:flex;gap:6px;align-items:center}
.aud-pill{flex:1;text-align:center;padding:7px 4px;border-radius:8px;font-family:"Space Mono";font-size:14px;font-weight:700;color:#fff;line-height:1.1;display:flex;flex-direction:column;gap:2px}
.aud-pill .lab{font-size:8px;font-family:"Poppins";font-weight:600;opacity:.85;letter-spacing:.5px;text-transform:uppercase}
.aud-pill.v{background:linear-gradient(135deg,#10B981,#059669)}
.aud-pill.a{background:linear-gradient(135deg,#F59E0B,#D97706)}
.aud-pill.r{background:linear-gradient(135deg,#EF4444,#DC2626)}
.aud-tools{display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:12px 20px;background:#fff;border-bottom:1px solid var(--bd);position:sticky;top:0;z-index:5}
.aud-search{flex:1;min-width:180px;border:1.5px solid var(--bd);border-radius:8px;padding:7px 12px;font-family:"Poppins";font-size:12px;outline:none}
.aud-search:focus{border-color:var(--fu)}
.aud-filt{display:flex;gap:5px;align-items:center;flex-wrap:wrap}
.aud-filt-lbl{font-size:10px;color:var(--mu);font-weight:600;margin-right:4px}
.aud-filt-btn{padding:5px 11px;border-radius:100px;border:1.5px solid var(--bd);background:#fff;font-family:"Poppins";font-size:10px;font-weight:600;color:var(--mu);cursor:pointer;transition:.15s}
.aud-filt-btn:hover{border-color:var(--vi);color:var(--vi)}
.aud-filt-btn.act{background:var(--vi);border-color:var(--vi);color:#fff}
.aud-filt-btn.v.act{background:#10B981;border-color:#10B981}
.aud-filt-btn.a.act{background:#F59E0B;border-color:#F59E0B}
.aud-filt-btn.r.act{background:#EF4444;border-color:#EF4444}
.aud-body{flex:1;overflow-y:auto;background:var(--bg)}
.aud-table{width:100%;border-collapse:collapse;background:#fff;font-size:12px}
.aud-table thead th{position:sticky;top:0;background:#F0EEFF;color:var(--vi);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:10px 12px;text-align:left;border-bottom:2px solid var(--bd);z-index:2}
.aud-table tbody tr{border-bottom:1px solid #F0F0F8;transition:background .15s}
.aud-table tbody tr:hover{background:#FAF8FF}
.aud-table td{padding:9px 12px;vertical-align:middle}
.aud-thumb{width:42px;height:42px;border-radius:8px;background:#f0f0f5;object-fit:cover;display:block}
.aud-thumb-empty{width:42px;height:42px;border-radius:8px;background:#FEE2E2;color:#DC2626;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700}
.aud-name{font-weight:600;color:var(--txt);font-size:12px;line-height:1.3}
.aud-id{font-family:"Space Mono";font-size:9px;color:var(--fu);background:rgba(127,31,219,.07);padding:1px 5px;border-radius:4px;display:inline-block;margin-top:2px}
.aud-cat{font-size:10px;color:var(--mu);text-transform:uppercase;letter-spacing:.04em;font-weight:600}
.aud-precio{font-family:"Space Mono";font-size:12px;font-weight:700;color:var(--vi)}
.aud-precio.zero{color:var(--err)}
.aud-stk{font-family:"Space Mono";font-size:11px;color:var(--mu)}
.aud-sem{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700}
.aud-dot{display:inline-block;width:14px;height:14px;border-radius:50%;flex-shrink:0;box-shadow:0 0 0 2px rgba(0,0,0,.05)}
.aud-dot.v{background:#10B981}
.aud-dot.a{background:#F59E0B}
.aud-dot.r{background:#EF4444}
.aud-msg{padding:38px 20px;text-align:center;color:var(--mu);font-size:13px}
.aud-loading{padding:48px 20px;text-align:center;color:var(--vi);font-weight:600}
@media(max-width:600px){
  .aud-back{padding:0}
  .aud-modal{border-radius:0;max-height:100vh;height:100vh}
  .aud-hdr h2{font-size:18px}
  .aud-stats{grid-template-columns:1fr}
  .aud-table thead th:nth-child(2),
  .aud-table tbody td:nth-child(2){display:none}
  .aud-table td,.aud-table th{padding:7px 8px;font-size:11px}
}
</style>
</head>
<body>

<?php if (!$auth): ?>
<div class="login-wrap">
  <div class="lbox">
    <h1>Panel Admin</h1>
    <p>Mundo Accesorios Dorada</p>
    <?php if ($loginErr): ?><div class="err"><?= htmlspecialchars($loginErr) ?></div><?php endif ?>
    <form method="POST" autocomplete="off">
      <input type="text" name="usuario" placeholder="Usuario (admin o vendedora)" autofocus>
      <input type="password" name="clave" placeholder="Clave">
      <button type="submit">Entrar</button>
    </form>
    <p style="margin-top:14px;font-size:11px;color:var(--mu);line-height:1.5">
      <strong style="color:var(--vi)">Vendedora del local:</strong><br>
      usá tu usuario y clave para revisar inventario.
    </p>
  </div>
</div>

<?php else: ?>

<div class="hdr">
  <div>
    <div class="hdr-title">Panel Admin · <?= $gCon ?>/<?= $gTotal ?> con foto</div>
    <div class="hdr-sub">catalogo.json · <?= date('d/m/Y H:i') ?></div>
  </div>
  <div class="hbtns">
    <span class="dest-chip" id="destChip"><?= $gDest ?> destacados</span>
    <span class="dest-chip" style="background:rgba(255,255,255,.18);color:#fff;border-color:rgba(255,255,255,.3)">
      <?= htmlspecialchars(USUARIOS[$user]['nombre'] ?? $user) ?> · <?= htmlspecialchars($rol) ?>
    </span>
    <button class="hbtn w" onclick="abrirAuditoria()" title="Auditoría de inventario">Auditoría</button>
    <a class="hbtn" href="index.php" target="_blank">Ver sitio</a>
    <button class="hbtn w" onclick="location.reload()">Actualizar</button>
    <a class="hbtn" href="?salir=1">Salir</a>
  </div>
</div>

<!-- HOME CONFIG PANEL -->
<div class="hcp-wrap" id="hcpWrap" style="background:#fff;border:1.5px solid var(--bd);border-radius:16px;overflow:hidden;margin:16px 0">
  <div class="hcp-head" onclick="toggleHcp()" style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:var(--soft);border-bottom:1px solid var(--bd);cursor:pointer;user-select:none">
    <span style="font-size:.8rem;font-weight:700;color:var(--vi)">Secciones del home</span>
    <span id="hcpArrow" style="font-size:.8rem;color:var(--vi)">&#9660;</span>
  </div>
  <div id="hcpBody" class="hcp-body" style="padding:14px 18px">
    <p style="font-size:.7rem;color:var(--mu);margin-bottom:12px">Arrastra para reordenar. Controla cuántos productos muestra cada sección.</p>
    <div id="hcpList" style="display:flex;flex-direction:column;gap:6px;margin-bottom:10px"></div>
    <button onclick="hcpAddRow()" style="width:100%;padding:8px;border-radius:10px;border:1.5px dashed var(--bd);background:transparent;font-family:'Poppins';font-size:.73rem;color:var(--vi);cursor:pointer;margin-bottom:8px">+ Agregar categoría</button>
    <button class="hcp-save" onclick="hcpSave()" style="width:100%;padding:10px;border-radius:10px;background:var(--grad);border:none;color:#fff;font-family:'Poppins';font-size:.78rem;font-weight:600;cursor:pointer">Guardar orden del home</button>
    <div id="hcpStatus" style="font-size:.7rem;color:var(--vi);text-align:center;margin-top:6px;min-height:16px"></div>
  </div>
</div>

<div class="gstats">
  <div class="gstat"><div class="gn t" id="gTotal"><?= $gTotal ?></div><div class="gl"><strong>Total</strong>productos</div></div>
  <div class="gstat"><div class="gn ok" id="gCon"><?= $gCon ?></div><div class="gl"><strong>Con foto</strong>listos</div></div>
  <div class="gstat"><div class="gn no" id="gSin"><?= $gSin ?></div><div class="gl"><strong>Sin foto</strong>pendientes</div></div>
  <div class="gprog">
    <div style="font-size:10px;font-weight:600">Cobertura <span id="gPct"><?= $gPct ?></span>%</div>
    <div class="pb-o"><div class="pb-i" id="gBar" style="width:<?= $gPct ?>%"></div></div>
    <div style="font-size:10px;color:var(--mu)">Faltan <span id="gFaltan"><?= $gSin ?></span> fotos</div>
  </div>
</div>

<div class="search-wrap">
  <div class="search-inner">
    <span style="color:var(--fu);font-size:17px">&#9906;</span>
    <input type="text" id="si" class="si-inp"
      placeholder="Buscar por nombre, ID o categoría..."
      oninput="onSearch(this.value)"
      onkeydown="if(event.key==='Escape')clearSearch();if(event.key==='Enter')doSearch(this.value)"
      autocomplete="off" autofocus>
    <span class="si-cnt" id="siCnt"></span>
    <button class="si-clr" id="siClr" onclick="clearSearch()" style="display:none">&#10005;</button>
    <button class="si-go" onclick="doSearch(document.getElementById('si').value)">Buscar</button>
  </div>
  <div class="search-hint">Ctrl+F para enfocar &middot; Escape para limpiar</div>
</div>

<div class="main">
  <div id="searchSection" style="display:none">
    <div class="srb">
      <span class="srb-txt" id="srLabel"></span>
      <button class="srb-clr" onclick="clearSearch()">Ver categorías</button>
    </div>
    <div class="pgrid" id="searchGrid"></div>
  </div>
  <div id="catsSection">
    <div id="catsLoader" class="empty-state"><p>Cargando categorías...</p></div>
    <div id="catsAcc"></div>
  </div>
</div>

<input type="file" id="fi" accept="image/*" style="display:none">
<div class="toast" id="toast"></div>

<!-- ── MODAL AUDITORÍA INVENTARIO ───────────────────────────── -->
<div class="aud-back" id="audBack" onclick="if(event.target===this)cerrarAuditoria()">
  <div class="aud-modal">
    <div class="aud-hdr">
      <div>
        <h2>Auditoría de inventario</h2>
        <div class="sub">Semáforo verde / amarillo / rojo · foto y precio por producto</div>
      </div>
      <button class="aud-close" onclick="cerrarAuditoria()" title="Cerrar (Esc)">&#10005;</button>
    </div>
    <div class="aud-stats" id="audStats"></div>
    <div class="aud-tools">
      <input type="text" class="aud-search" id="audSearch" placeholder="Buscar por nombre, ID o categoría..." oninput="audFiltrar()">
      <div class="aud-filt">
        <span class="aud-filt-lbl">Foto:</span>
        <button class="aud-filt-btn act" data-tipo="foto" data-val="all" onclick="audSetFiltro(this)">Todas</button>
        <button class="aud-filt-btn r"   data-tipo="foto" data-val="r"   onclick="audSetFiltro(this)">Sin foto</button>
        <button class="aud-filt-btn a"   data-tipo="foto" data-val="a"   onclick="audSetFiltro(this)">Solo 1</button>
        <button class="aud-filt-btn v"   data-tipo="foto" data-val="v"   onclick="audSetFiltro(this)">2+</button>
      </div>
      <div class="aud-filt">
        <span class="aud-filt-lbl">Precio:</span>
        <button class="aud-filt-btn act" data-tipo="precio" data-val="all" onclick="audSetFiltro(this)">Todos</button>
        <button class="aud-filt-btn r"   data-tipo="precio" data-val="r"   onclick="audSetFiltro(this)">Sin precio</button>
        <button class="aud-filt-btn a"   data-tipo="precio" data-val="a"   onclick="audSetFiltro(this)">Sospechoso</button>
        <button class="aud-filt-btn v"   data-tipo="precio" data-val="v"   onclick="audSetFiltro(this)">OK</button>
      </div>
    </div>
    <div class="aud-body" id="audBody">
      <div class="aud-loading">Cargando auditoría...</div>
    </div>
  </div>
</div>

<script>
// ── ESTADO ───────────────────────────────────────────────────
let activeId = null, activeSlot = 1;
let gCon  = <?= $gCon ?>, gTotal = <?= $gTotal ?>;
let searchTimer = null;
const fi = document.getElementById('fi');

// ── CATEGORIAS CONFIG (orden y colores) ──────────────────────
const CATS = [
  { label:'Termos',                keys:['termo','stanley','vaso termo'],              c:['#5E0845','#B52496'] },
  { label:'Audífonos y Diademas',  keys:['audifono','diadema','audifonos','balaca','auricular'], c:['#002855','#0066CC'] },
  { label:'Relojes',               keys:['reloj'],                                    c:['#3D0B68','#7F1FDB'] },
  { label:'Combos',                keys:['combo','kit','paquete'],                    c:['#1A2A0A','#3A6010'] },
  { label:'Parlantes',             keys:['parlante','bocina','speaker','altavoz'],    c:['#0A1A2A','#1A4A7A'] },
  { label:'Cargadores y Cables',   keys:['cargador','cable','adaptador','hub'],       c:['#1A0438','#4921D8'] },
  { label:'Aros de Luz y Tripodes',keys:['aro','tripode','trípode','ring','aro de luz'], c:['#1E0A00','#6A3000'] },
  { label:'Micrófonos',            keys:['microfono','micrófono','mic'],              c:['#0A1E1E','#0A5050'] },
  { label:'Power Bank',            keys:['power bank','powerbank','bateria externa'], c:['#001A10','#005A30'] },

  { label:'Humidificadores',       keys:['humidificador','humidif'],                  c:['#00293A','#006677'] },
  { label:'Soporte Celular',       keys:['soporte','holder','stand'],                 c:['#0A001A','#2A0060'] },
  { label:'Cases y Estuches',      keys:['case','funda','estuche','carcasa'],         c:['#4A0A3E','#9A1F80'] },
  { label:'Vidrios Templados',     keys:['vidrio','templado','protector pantalla','lamina'], c:['#1c2136','#3d4d70'] },
  { label:'Pulseras y Straps',     keys:['pulsera','strap','correa','banda'],          c:['#003838','#006060'] },
  { label:'Tecnología',            keys:['tecnologia','tecnología','memoria','lápiz','lapiz','mouse','teclado','webcam','hub usb'], c:['#003322','#007744'] },
  { label:'Otros',                 keys:[],                                           c:['#1a1a1a','#444'] },
];

function matchCat(dbNombre) {
  const n = dbNombre.toLowerCase().trim();
  for (let i = 0; i < CATS.length - 1; i++)
    if (CATS[i].label.toLowerCase() === n) return i;
  return CATS.length - 1;
}
function catGrad(i) {
  const [a,b] = CATS[i]?.c ?? ['#222','#444'];
  return `linear-gradient(135deg,${a},${b})`;
}
function slug(s) { return s.toLowerCase().replace(/[^a-z0-9]/g,'-').replace(/-+/g,'-'); }
function fmt(n) { return '$' + Number(n).toLocaleString('es-CO'); }
function fotoCount(fotos) { return Object.values(fotos).filter(Boolean).length; }

// ── LOAD CATEGORIAS ──────────────────────────────────────────
const loadedCats = {};
async function loadCategorias() {
  try {
    const data = await fetch('admin.php?action=categorias').then(r=>r.json());
    if (!Array.isArray(data)) throw new Error();
    // Buckets según CATS order
    const buckets = CATS.map((cfg,i) => ({ label:cfg.label, ci:i, total:0, con_foto:0, pct:0, dbNames:[] }));
    data.forEach(c => {
      const i = matchCat(c.nombre);
      buckets[i].total    += c.total;
      buckets[i].con_foto += c.con_foto;
      buckets[i].dbNames.push(c.nombre);
    });
    // Agregar buckets vacíos para categorías que existen en CATS pero aún no tienen productos
    // (para poder añadir productos en cualquier categoría)
    CATS.forEach((cfg,i) => { if (!buckets[i].dbNames.length) buckets[i].dbNames = [cfg.label]; });
    buckets.forEach(b => { b.pct = b.total ? Math.round(b.con_foto/b.total*100) : 0; });
    document.getElementById('catsLoader').style.display = 'none';
    document.getElementById('catsAcc').innerHTML = buckets.map(catBlockHtml).join('');
  } catch {
    document.getElementById('catsLoader').innerHTML = '<p style="color:var(--err)">Error cargando categorías</p>';
  }
}
function catBlockHtml(b) {
  const sl   = slug(b.label);
  const sin  = b.total - b.con_foto;
  const dbQ  = encodeURIComponent(b.dbNames.join('|'));
  return `<div class="cat-block" id="cat-${sl}">
    <div class="cat-hdr" style="background:${catGrad(b.ci)}" onclick="toggleCat('${sl}','${b.label}','${dbQ}')">
      <span class="cat-name">${b.label}</span>
      ${b.total ? `<span class="cat-badge">${b.total} prods</span>` : '<span class="cat-badge">vacía</span>'}
      ${sin > 0 ? `<span class="cat-miss">${sin} sin foto</span>` : ''}
      <div class="cat-mini-bar"><div class="cat-mini-fill" style="width:${b.pct}%"></div></div>
      <span class="cat-pct">${b.pct}%</span>
      <span class="cat-arrow" id="arr-${sl}">&#9654;</span>
    </div>
    <div class="cat-body" id="body-${sl}" style="display:none"></div>
  </div>`;
}
async function toggleCat(sl, label, dbQ) {
  const body = document.getElementById('body-'+sl);
  const arr  = document.getElementById('arr-'+sl);
  const open = body.style.display !== 'none';
  body.style.display = open ? 'none' : '';
  arr.classList.toggle('open', !open);
  if (!open && !loadedCats[sl]) {
    loadedCats[sl] = true;
    body.innerHTML = '<div class="cat-loading">Cargando...</div>';
    try {
      const cats  = decodeURIComponent(dbQ).split('|');
      const proms = cats.map(cat => fetch('admin.php?action=por_categoria&cat='+encodeURIComponent(cat)).then(r=>r.json()));
      const res   = await Promise.all(proms);
      const data  = [].concat(...res.filter(Array.isArray));
      data.sort((a,b) => a.nombre.localeCompare(b.nombre,'es'));
      body.innerHTML = catBodyHtml(data, label, sl);
    } catch {
      body.innerHTML = '<div class="cat-loading" style="color:var(--err)">Error al cargar</div>';
    }
  }
}
function catBodyHtml(data, label, sl) {
  const con = data.filter(p=>p.tiene).length;
  const pct = data.length ? Math.round(con/data.length*100) : 0;

  // Subgrupos únicos en esta categoría
  const sgs = [...new Set(data.map(p=>p.subgrupo||'').filter(Boolean))].sort();
  const sgBar = sgs.length > 1
    ? `<div class="sg-bar">
        <span class="sg-bar-label">Tipo</span>
        <button class="sg-btn act" onclick="filterSg(this,'${sl}','')">Todos</button>
        ${sgs.map(sg=>`<button class="sg-btn" onclick="filterSg(this,'${sl}','${sg.replace(/'/g,"\\'")}')">
          ${sg} <span style="opacity:.5;font-size:9px">${data.filter(p=>p.subgrupo===sg).length}</span>
        </button>`).join('')}
      </div>`
    : '';

  return `<div class="cat-sub-bar">
    <span class="cat-sub-info"><strong>${con}/${data.length}</strong> con foto &mdash; ${pct}%</span>
    <div class="cat-actions">
      <button class="cf-btn act" onclick="filterCat(this,'${sl}','all')">Todos</button>
      <button class="cf-btn" onclick="filterCat(this,'${sl}','no')">Sin foto</button>
      <button class="cf-btn" onclick="filterCat(this,'${sl}','ok')">Con foto</button>
      <button class="btn-new" onclick="showNewForm('${sl}','${label}')">+ Nuevo producto</button>
    </div>
  </div>
  ${sgBar}
  <div id="form-${sl}"></div>
  <div class="pgrid" id="grid-${sl}">${data.map(cardHtml).join('')}</div>`;
}
function filterCat(btn, sl, f) {
  btn.closest('.cat-actions').querySelectorAll('.cf-btn').forEach(b=>b.classList.remove('act'));
  btn.classList.add('act');
  // Get active subgrupo filter
  const activeSg = document.getElementById('grid-'+sl)?.dataset.sgFilter || '';
  document.getElementById('grid-'+sl)?.querySelectorAll('.pcard').forEach(card => {
    const estado = card.dataset.estado;
    const sg     = card.dataset.sg || '';
    let show = true;
    if (f === 'ok')  show = estado === 'ok';
    if (f === 'no')  show = estado !== 'ok';
    if (activeSg && sg !== activeSg) show = false;
    card.style.display = show ? '' : 'none';
  });
}
function filterSg(btn, sl, sg) {
  btn.closest('.sg-bar').querySelectorAll('.sg-btn').forEach(b=>b.classList.remove('act'));
  btn.classList.add('act');
  const grid = document.getElementById('grid-'+sl);
  if (!grid) return;
  grid.dataset.sgFilter = sg;
  grid.querySelectorAll('.pcard').forEach(card => {
    const estado = card.dataset.estado;
    const cardSg = card.dataset.sg || '';
    let show = true;
    if (sg && cardSg !== sg) show = false;
    card.style.display = show ? '' : 'none';
  });
}

// ── FORM NUEVO PRODUCTO ──────────────────────────────────────
const SG_MAP = {
  'Termos':                ['Agarradera','Unicolor','Estrella','Mármol'],
  'Audífonos y Diademas':  ['Balacas','Bluetooth','Transmisión Ósea','Con Cable','Accesorios'],
  'Relojes':               ['Smartwatch','Combos'],
  'Combos':                [],
  'Parlantes':             [],
  'Cargadores y Cables':   ['Cables','Cargadores','Holders de Carro','Accesorios'],
  'Aros de Luz y Tripodes':['Aros y Paneles LED','Trípodes'],
  'Micrófonos':            [],
  'Power Bank':            [],

  'Soporte Celular':       ['Soporte Carro','MagSafe','Popsockets y Anillos','Selfie y Estabilizadores','Soporte Mesa','Decorativos'],
  'Cases y Estuches':      [],
  'Vidrios Templados':     [],
  'Humidificadores':       ['Estilo Madera','Cilíndricos','Efecto Lluvia','Efecto Llama','Temáticos','Piedras de Sal','Cubo Minimalista','Altavoz Bluetooth'],
  'Tecnología':            ['Memorias USB','Memorias MicroSD','Lápices Ópticos','Mouse','Teclados','Webcams','Hubs USB','Alexa','Otros gadgets'],
  'Otros':                 [],
};
function showNewForm(sl, catLabel) {
  const wrap = document.getElementById('form-'+sl);
  if (wrap.innerHTML) { wrap.innerHTML = ''; return; }

  // Subgrupos disponibles para esta categoría
  const sgs = SG_MAP[catLabel] || [];
  // También leer subgrupos ya existentes en el grid (por si hay custom)
  const existentes = [...new Set(
    [...(document.getElementById('grid-'+sl)?.querySelectorAll('.pcard')||[])]
      .map(c=>c.dataset.sg).filter(Boolean)
  )];
  const todosLos = [...new Set([...sgs,...existentes])].sort();

  const sgField = todosLos.length
    ? `<div class="npf-full">
        <label class="npf-label">Tipo / Subgrupo</label>
        <select class="npf-sel" id="nf-sg-${sl}">
          <option value="">— Sin subgrupo —</option>
          ${todosLos.map(s=>`<option value="${s}">${s}</option>`).join('')}
          <option value="__nuevo__">+ Escribir uno nuevo...</option>
        </select>
        <input class="npf-inp" id="nf-sg-custom-${sl}" placeholder="Nombre del subgrupo nuevo" style="display:none;margin-top:6px"
          oninput="this.value=this.value">
      </div>`
    : `<div class="npf-full">
        <label class="npf-label">Tipo / Subgrupo (opcional)</label>
        <input class="npf-inp" id="nf-sg-${sl}" placeholder="Ej: Agarradera, Unicolor...">
      </div>`;

  wrap.innerHTML = `<div class="new-prod-form">
    <div class="npf-title">Nuevo producto en ${catLabel}</div>
    <div class="npf-grid">
      <div class="npf-full">
        <label class="npf-label">Nombre del producto</label>
        <input class="npf-inp" id="nf-nombre-${sl}" placeholder="Ej: Termo Stanley Classic 1L Negro" autofocus>
      </div>
      ${sgField}
      <div>
        <label class="npf-label">Precio (COP)</label>
        <input class="npf-inp" id="nf-precio-${sl}" type="number" placeholder="89900">
      </div>
      <div>
        <label class="npf-label">Stock</label>
        <input class="npf-inp" id="nf-stk-${sl}" type="number" placeholder="0" value="0">
      </div>
      <div class="npf-full">
        <label class="npf-label">Descripción breve (opcional)</label>
        <input class="npf-inp" id="nf-desc-${sl}" placeholder="Ej: Acero inoxidable, tapa rosca, 24h frío/calor">
      </div>
    </div>
    <div class="npf-btns">
      <button class="npf-save" onclick="crearProducto('${sl}','${catLabel}')">Crear producto</button>
      <button class="npf-cancel" onclick="document.getElementById('form-${sl}').innerHTML=''">Cancelar</button>
    </div>
  </div>`;

  // Toggle campo custom cuando elige "+ Escribir uno nuevo..."
  const sel = document.getElementById('nf-sg-'+sl);
  const custom = document.getElementById('nf-sg-custom-'+sl);
  if (sel && custom) {
    sel.addEventListener('change', () => {
      custom.style.display = sel.value === '__nuevo__' ? '' : 'none';
      if (sel.value === '__nuevo__') custom.focus();
    });
  }
}
async function crearProducto(sl, catLabel) {
  const nombre = document.getElementById('nf-nombre-'+sl)?.value.trim();
  const precio = parseInt(document.getElementById('nf-precio-'+sl)?.value||'0');
  const stk    = parseInt(document.getElementById('nf-stk-'+sl)?.value||'0');
  const desc   = document.getElementById('nf-desc-'+sl)?.value.trim();
  // Subgrupo: select o input
  const selEl    = document.getElementById('nf-sg-'+sl);
  const customEl = document.getElementById('nf-sg-custom-'+sl);
  let subgrupo = '';
  if (selEl) {
    subgrupo = selEl.tagName === 'SELECT'
      ? (selEl.value === '__nuevo__' ? (customEl?.value.trim()||'') : selEl.value)
      : selEl.value.trim();
  }
  if (!nombre) { toast('El nombre es obligatorio','err'); return; }
  const fd = new FormData();
  fd.append('nombre', nombre); fd.append('categoria', catLabel);
  fd.append('subgrupo', subgrupo);
  fd.append('precio', precio); fd.append('stk', stk); fd.append('desc', desc||'');
  try {
    const d = await fetch('admin.php?action=crear', {method:'POST',body:fd}).then(r=>r.json());
    if (!d.ok) { toast('Error al crear: '+(d.msg||''),'err'); return; }
    // Limpiar form
    document.getElementById('form-'+sl).innerHTML = '';
    // Añadir card al inicio del grid
    const grid = document.getElementById('grid-'+sl);
    if (grid) {
      const div = document.createElement('div');
      div.innerHTML = cardHtml(d.producto);
      grid.prepend(div.firstElementChild);
    }
    updateGlobalStats(1, 0);
    toast('Producto creado — ID #'+d.producto.id, 'suc');
  } catch { toast('Error de conexión','err'); }
}

// ── CARD HTML ────────────────────────────────────────────────
const SLOT_LABELS = {1:'Principal 1:1',2:'Vista 2 1:1',3:'Vista 3 1:1',4:'Banner 16:9',5:'Story 9:16',6:'Story 9:16'};
function slotHtml(id, slot, url) {
  const lbl = SLOT_LABELS[slot] || 'Foto '+slot;
  const inner = url
    ? '<img src="'+url+'?v='+Date.now()+'" alt="">'
    : '<div class="fslot-lbl">'+lbl+'</div>';
  const delBtn = url ? '<button class="slot-del" onclick="event.stopPropagation();borrar('+id+','+slot+')">&#215;</button>' : '';
  const ovl = '<div class="slot-ovl">'+(url?'Cambiar':'+ Foto')+'</div>';
  return '<div class="fslot" data-slot="'+slot+'" id="slot-'+id+'-'+slot+'" onclick="triggerUpload('+id+','+slot+')" title="'+(url?'Cambiar':'Subir')+' '+lbl+'">'
    + inner + ovl + delBtn + '</div>';
}
function cardHtml(p) {
  const id  = p.id;
  const nf  = fotoCount(p.fotos||{});
  const cls = !p.activo ? 'inactive' : (p.tiene ? 'ok' : 'no');
  const sgAttr = (p.subgrupo||'').replace(/"/g,'&quot;');
  return `<div class="pcard ${cls}" id="card-${id}" data-id="${id}" data-estado="${p.tiene?'ok':'no'}" data-sg="${sgAttr}" data-foto-home="${p.foto_home||1}"
    ondragover="event.preventDefault();this.classList.add('drag-over')"
    ondragleave="this.classList.remove('drag-over')"
    ondrop="onDrop(event,${id})">
    <div class="fotos-area" id="fa-${id}">
      ${[1,2,3,4,5,6].map(s=>slotHtml(id,s,(p.fotos||{})[s]||null)).join('')}
      <div class="fc-badge" id="fc-${id}">${nf}/6${(p.fotos||{})[4]?' · banner':''}${(p.fotos||{})[5]?' · story':''}</div>
    </div>
    <div class="home-row" id="hr-${id}">${buildHomeRow(id,p.fotos||{},p.foto_home||1)}</div>
    <div class="uprog" id="uprog-${id}">
      <span style="font-size:11px;color:var(--mu)">Subiendo...</span>
      <div class="uprog-bar"><div class="uprog-fill" id="ufill-${id}"></div></div>
    </div>
    <div class="cinfo">
      <span class="cpid">#${id}</span>
      <div class="cname" id="cname-${id}" onclick="editName(${id},this)">${p.nombre}</div>
      <div class="cmeta">
        <span class="cprice" id="pr-${id}" onclick="editPrecio(${id},${p.precio},this)">${fmt(p.precio||0)}</span>
        <span class="cstk" id="stk-${id}" onclick="editStk(${id},${p.stk||0},this)">${p.stk||0} uds</span>
      </div>
      <div class="cdesc ${p.desc?'':'empty'}" id="desc-${id}" onclick="editDesc(${id},this)">${p.desc||'Sin descripción — clic para agregar'}</div>
      <div id="sg-wrap-${id}" style="margin-bottom:4px">
        <span id="sg-${id}"
          style="font-size:9px;color:var(--fu);font-weight:600;letter-spacing:.3px;text-transform:uppercase;cursor:pointer;border-bottom:1px dashed rgba(127,31,219,.3)"
          onclick="editarSg(${id},this,'${p.categoria||''}')"
          title="Clic para cambiar subgrupo">
          ${p.subgrupo || '<span style="opacity:.35;font-style:italic;text-transform:none;font-weight:400">Sin subgrupo</span>'}
        </span>
      </div>
      <div style="margin-bottom:6px">
        <button onclick="editarCategoria(${id},this)" id="cat-${id}"
          style="font-size:9px;font-family:'Poppins';font-weight:600;color:var(--vi);background:rgba(73,33,216,.08);border:1.5px solid rgba(73,33,216,.2);border-radius:6px;padding:3px 8px;cursor:pointer;width:100%;text-align:left"
          title="Clic para mover a otra categoría">
          &#128193; ${p.categoria||'Sin categoría'}
        </button>
      </div>
      <div class="pos-wrap">
        <span class="pos-lbl">Orden home:</span>
        <input class="pos-inp" type="number" id="pos-${id}" value="${p.pos_home||0}" min="0" title="0 = sin orden definido. Menor número = aparece primero"
          onchange="savePosHome(${id},this.value)" onkeydown="if(event.key==='Enter')this.blur()">
      </div>
      <div class="cactions">
        <button class="cbtn up" onclick="triggerUpload(${id},1)">+ Foto</button>
        <button class="home-toggle ${(p.en_home!==false)?'on':''}" id="enh-${id}" onclick="toggleEnHome(${id},this)" title="Aparece en el home">${(p.en_home!==false)?'HOME':'No home'}</button>
        <button class="cbtn dest ${p.destacado?'on':''}" id="dest-${id}" onclick="toggleDest(${id},this)">${p.destacado?'★':'☆'}</button>
        <button class="cbtn off ${p.activo?'on':''}" id="act-${id}" onclick="toggleActivo(${id},this)">${p.activo?'ON':'OFF'}</button>
        <button class="cbtn del" onclick="eliminar(${id})">Quitar</button>
      </div>
    </div>
    <div class="cstatus ${p.tiene?'ok':'no'}"></div>
  </div>`;
}

// ── SEARCH ───────────────────────────────────────────────────
function onSearch(q) {
  document.getElementById('siClr').style.display = q ? '' : 'none';
  clearTimeout(searchTimer);
  if (!q.trim()) { clearSearch(); return; }
  searchTimer = setTimeout(() => doSearch(q), 380);
}
async function doSearch(q) {
  q = q.trim(); if (q.length < 2) return;
  document.getElementById('siCnt').textContent = 'buscando...';
  document.getElementById('searchSection').style.display = '';
  document.getElementById('catsSection').style.display   = 'none';
  const data = await fetch('admin.php?action=buscar&q='+encodeURIComponent(q)).then(r=>r.json()).catch(()=>[]);
  document.getElementById('siCnt').textContent = data.length + (data.length===1?' resultado':' resultados');
  document.getElementById('srLabel').textContent = data.length + (data.length===1?' resultado':' resultados') + ' para "'+q+'"';
  document.getElementById('searchGrid').innerHTML = data.length
    ? data.map(cardHtml).join('')
    : '<div class="empty-state"><h3>Sin resultados</h3><p>Revisá el nombre o el ID</p></div>';
}
function clearSearch() {
  document.getElementById('si').value = '';
  document.getElementById('siClr').style.display = 'none';
  document.getElementById('siCnt').textContent = '';
  document.getElementById('searchSection').style.display = 'none';
  document.getElementById('catsSection').style.display   = '';
}

// ── UPLOAD ───────────────────────────────────────────────────
function triggerUpload(id, slot) { activeId=id; activeSlot=slot||1; fi.value=''; fi.click(); }
fi.addEventListener('change', () => { if (fi.files.length && activeId) uploadFoto(activeId, activeSlot, fi.files[0]); });
function onDrop(e, id) {
  e.preventDefault();
  document.getElementById('card-'+id)?.classList.remove('drag-over');
  const f = e.dataTransfer.files[0];
  if (f?.type.startsWith('image/')) uploadFoto(id, 1, f);
}
function uploadFoto(id, slot, file) {
  const uprog = document.getElementById('uprog-'+id);
  const fill  = document.getElementById('ufill-'+id);
  uprog.classList.add('show');
  let prog = 0;
  const tick = setInterval(() => { prog = Math.min(prog+8,85); fill.style.width=prog+'%'; }, 120);
  const fd = new FormData(); fd.append('foto', file);
  fetch(`admin.php?action=upload&id=${id}&slot=${slot}`, {method:'POST',body:fd})
    .then(r=>r.json()).then(d => {
      clearInterval(tick); fill.style.width='100%';
      setTimeout(() => {
        uprog.classList.remove('show'); fill.style.width='0';
        if (d.ok) {
          const slotEl = document.getElementById('slot-'+id+'-'+slot);
          if (slotEl) {
            slotEl.innerHTML = slotHtml(id, slot, d.url, parseInt(document.getElementById('card-'+id)?.dataset.fotoHome||1));
          }
          const fc = document.getElementById('fc-'+id); if(fc) fc.textContent=d.nfotos+'/6';
          // Mark card ok on any slot upload (first photo is enough)
          const card = document.getElementById('card-'+id);
          if (card && card.dataset.estado !== 'ok') {
            card.dataset.estado='ok'; card.classList.replace('no','ok');
            card.querySelector('.cstatus')?.classList.replace('no','ok');
            updateGlobalStats(0, 1);
          }
          // Refrescar home-row
          const hr = document.getElementById('hr-'+id);
          if (hr) {
            const fotos = {};
            for (let s=1;s<=6;s++){
              const img = document.querySelector('#slot-'+id+'-'+s+' img');
              if (img) fotos[s] = img.src;
            }
            const cur = parseInt(card?.dataset.fotoHome||1);
            hr.innerHTML = buildHomeRow(id, fotos, cur);
          }
          toast('Foto subida','suc');
        } else toast('Error: '+(d.msg||'desconocido'),'err');
      }, 350);
    }).catch(() => { clearInterval(tick); uprog.classList.remove('show'); toast('Error de conexión','err'); });
}
function borrar(id, slot) {
  if (!confirm('Eliminar esta foto?')) return;
  fetch(`admin.php?action=delete_foto&id=${id}&slot=${slot}`).then(r=>r.json()).then(d => {
    if (d.ok) {
      const slotEl = document.getElementById('slot-'+id+'-'+slot);
      if (slotEl) {
        const lbl = slot===1?'Principal':'Foto '+slot;
        slotEl.innerHTML = slotHtml(id, slot, null, parseInt(document.getElementById('card-'+id)?.dataset.fotoHome||1));
      }
      const fc = document.getElementById('fc-'+id); if(fc) fc.textContent=d.nfotos+'/6';
      // Only mark card 'no' when zero photos remain
      if (d.nfotos === 0) {
        const card = document.getElementById('card-'+id);
        if (card && card.dataset.estado === 'ok') {
          card.dataset.estado='no'; card.classList.replace('ok','no');
          card.querySelector('.cstatus')?.classList.replace('ok','no');
          updateGlobalStats(0,-1);
        }
      }
      // Refrescar home-row
      const hr2 = document.getElementById('hr-'+id);
      if (hr2) {
        const fotos2 = {};
        for (let s=1;s<=6;s++){
          const img = document.querySelector('#slot-'+id+'-'+s+' img');
          if (img) fotos2[s] = img.src;
        }
        const cur2 = parseInt(document.getElementById('card-'+id)?.dataset.fotoHome||1);
        hr2.innerHTML = buildHomeRow(id, fotos2, cur2);
      }
      toast('Foto eliminada','suc');
    } else toast('No se pudo eliminar','err');
  });
}

// ── EDITS INLINE ─────────────────────────────────────────────
function saveField(id, field, value) {
  const fd = new FormData(); fd.append(field, value);
  return fetch('admin.php?action=editar&id='+id, {method:'POST',body:fd}).then(r=>r.json());
}
function editName(id, el) {
  const inp = Object.assign(document.createElement('input'),{className:'cname-inp',value:el.textContent});
  const save = () => saveField(id,'nombre',inp.value.trim()).then(d=>{ if(d.ok){el.textContent=inp.value.trim();} inp.replaceWith(el); toast(d.ok?'Nombre guardado':'Error guardando','suc'); });
  inp.onkeydown = e => { if(e.key==='Enter')save(); if(e.key==='Escape')inp.replaceWith(el); };
  inp.onblur = save;
  el.replaceWith(inp); inp.focus();
}
function editPrecio(id, cur, el) {
  const inp = Object.assign(document.createElement('input'),{className:'cprice-inp',value:cur});
  const save = () => {
    const v = parseInt(inp.value.replace(/[^0-9]/g,''));
    if (!v||v<100) { toast('Precio inválido','err'); inp.replaceWith(el); return; }
    saveField(id,'precio',v).then(d=>{ if(d.ok) el.textContent=fmt(v); inp.replaceWith(el); });
  };
  inp.onkeydown = e => { if(e.key==='Enter')save(); if(e.key==='Escape')inp.replaceWith(el); };
  inp.onblur = save;
  el.replaceWith(inp); inp.focus(); inp.select();
}
function editStk(id, cur, el) {
  const inp = Object.assign(document.createElement('input'),{className:'cstk-inp',value:cur});
  const save = () => {
    const v = parseInt(inp.value)||0;
    saveField(id,'stk',v).then(d=>{ if(d.ok) el.textContent=v+' uds'; inp.replaceWith(el); });
  };
  inp.onkeydown = e => { if(e.key==='Enter')save(); if(e.key==='Escape')inp.replaceWith(el); };
  inp.onblur = save;
  el.replaceWith(inp); inp.focus(); inp.select();
}
function editDesc(id, el) {
  const ta = Object.assign(document.createElement('textarea'),{className:'cdesc-inp',value:el.classList.contains('empty')?'':el.textContent});
  ta.placeholder='Descripción del producto...';
  const save = () => {
    const v = ta.value.trim();
    saveField(id,'desc',v).then(d=>{ if(d.ok){ el.textContent=v||'Sin descripción — clic para agregar'; el.classList.toggle('empty',!v); } ta.replaceWith(el); toast(d.ok?'Descripción guardada':'Error','suc'); });
  };
  ta.onkeydown = e => { if(e.key==='Escape')ta.replaceWith(el); if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();save();} };
  ta.onblur = save;
  el.replaceWith(ta); ta.focus();
}

// ── CAMBIAR CATEGORÍA INLINE ─────────────────────────────────
function editarCategoria(id, btn) {
  const cats = CATS.map(c => c.label);
  const actual = btn.textContent.trim().replace('📁 ','').replace('\uD83D\uDCC1 ','').trim();
  const sel = document.createElement('select');
  sel.style.cssText = 'font-family:"Poppins";font-size:9px;font-weight:600;color:var(--vi);border:1.5px solid var(--vi);border-radius:6px;padding:3px 6px;outline:none;background:#fff;cursor:pointer;width:100%';
  sel.innerHTML = cats.map(c => `<option value="${c}" ${c===actual?'selected':''}>${c}</option>`).join('');
  const save = () => {
    const val = sel.value;
    if (val === actual) { sel.replaceWith(btn); return; }
    saveField(id, 'categoria', val).then(d => {
      if (d.ok) {
        btn.textContent = '📁 ' + val;
        sel.replaceWith(btn);
        const card = document.getElementById('card-'+id);
        if (card) {
          card.style.transition = 'opacity .3s';
          card.style.opacity = '0';
          setTimeout(() => {
            card.remove();
            updateGlobalStats(-1, card.dataset.estado==='ok' ? -1 : 0);
          }, 320);
        }
        toast('Movido a ' + val + ' — recarga para verlo allí', 'suc');
      } else toast('Error guardando','err');
    });
  };
  sel.onchange = save;
  sel.onblur = () => { if (sel.parentNode) sel.replaceWith(btn); };
  btn.replaceWith(sel);
  sel.focus();
}

// ── SUBGRUPO INLINE ──────────────────────────────────────────
function editarSg(id, el, catLabel) {
  const sgs = SG_MAP[catLabel] || [];
  const existing = [...new Set(
    [...document.querySelectorAll('.pcard')].map(c=>c.dataset.sg).filter(Boolean)
  )];
  const opciones = [...new Set([...sgs,...existing])].sort();

  const sel = document.createElement('select');
  sel.style.cssText = 'font-family:"Poppins";font-size:10px;color:var(--fu);font-weight:600;border:1.5px solid var(--fu);border-radius:6px;padding:2px 6px;outline:none;background:#fff;cursor:pointer';
  sel.innerHTML = '<option value="">— Sin subgrupo —</option>'
    + opciones.map(s=>`<option value="${s}" ${s===(el.textContent.trim())?'selected':''}>${s}</option>`).join('')
    + '<option value="__custom__">+ Otro...</option>';

  const save = () => {
    let val = sel.value;
    if (val === '__custom__') {
      val = prompt('Nombre del subgrupo:', '') || '';
    }
    saveField(id,'subgrupo',val).then(d=>{
      if(d.ok){
        el.innerHTML = val || '<span style="opacity:.35;font-style:italic;text-transform:none;font-weight:400">Sin subgrupo</span>';
        // Update data-sg on card
        document.getElementById('card-'+id)?.setAttribute('data-sg', val);
        sel.replaceWith(el);
        toast('Subgrupo actualizado','suc');
      }
    });
  };
  sel.onchange = save;
  sel.onblur = () => { if(sel.parentNode) sel.replaceWith(el); };
  el.replaceWith(sel);
  sel.focus();
}

// ── FOTO HOME ─────────────────────────────────────────────────
async function setFotoHome(id, slot) {
  const card = document.getElementById('card-'+id);
  const fd = new FormData();
  fd.append('foto_home', slot);
  const d = await fetch('admin.php?action=editar&id='+id,{method:'POST',body:fd}).then(r=>r.json());
  if (d.ok) {
    if (card) card.dataset.fotoHome = slot;
    // Reconstruir la fila home-row
    const hr = document.getElementById('hr-'+id);
    if (hr) {
      const fotos = {};
      for (let s=1;s<=6;s++){
        const img = document.querySelector('#slot-'+id+'-'+s+' img');
        if (img) fotos[s] = img.src;
      }
      hr.innerHTML = buildHomeRow(id, fotos, slot);
    }
    toast('Foto home: slot '+slot,'suc');
  } else toast('Error guardando','err');
}
function buildHomeRow(id, fotos, fotoHome) {
  const slots = [1,2,3,4,5,6].filter(s => fotos[s]);
  if (!slots.length) return '<span class="home-row-empty">Sin fotos aún</span>';
  const cur = fotoHome || 1;
  const pills = slots.map(s =>
    '<button class="home-pill'+(s===cur?' act':'')+'" onclick="setFotoHome('+id+','+s+')" title="Mostrar foto '+s+' en el home">Foto '+s+(s===1?' ★':''  )+'</button>'
  ).join('');
  return '<span class="home-row-lbl">Home</span>'+pills;
}

// ── TOGGLES ──────────────────────────────────────────────────
function toggleEnHome(id, btn) {
  const cur = btn.classList.contains('on');
  saveField(id,'en_home',cur?'0':'1').then(d=>{
    if(d.ok){
      btn.classList.toggle('on',!cur);
      btn.textContent = cur ? 'No home' : 'HOME';
      toast(cur?'Quitado del home':'Agregado al home','suc');
    }
  });
}
function savePosHome(id, val) {
  const v = Math.max(0, parseInt(val)||0);
  document.getElementById('pos-'+id).value = v;
  saveField(id,'pos_home',v).then(d=>{
    if(d.ok) toast('Orden guardado: '+v,'suc');
    else toast('Error guardando orden','err');
  });
}
function toggleDest(id, btn) {
  const cur = btn.classList.contains('on');
  saveField(id,'destacado',cur?'0':'1').then(d=>{
    if(d.ok){ btn.classList.toggle('on',!cur); btn.textContent=cur?'☆':'★';
      const chip=document.getElementById('destChip');
      if(chip){ let n=parseInt(chip.textContent)||0; chip.textContent=(cur?n-1:n+1)+' destacados'; }
      toast(cur?'Quitado de destacados':'Marcado como destacado','suc'); }
  });
}
function toggleActivo(id, btn) {
  const cur = btn.classList.contains('on');
  saveField(id,'activo',cur?'0':'1').then(d=>{
    if(d.ok){ btn.classList.toggle('on',!cur); btn.textContent=cur?'OFF':'ON';
      const card=document.getElementById('card-'+id);
      card?.classList.toggle('inactive',cur);
      toast(cur?'Producto desactivado':'Producto activado','warn'); }
  });
}
function eliminar(id) {
  if (!confirm('Quitar este producto del catálogo? (se puede recuperar activándolo)')) return;
  fetch('admin.php?action=eliminar&id='+id).then(r=>r.json()).then(d=>{
    if(d.ok){ document.getElementById('card-'+id)?.remove(); updateGlobalStats(-1,0); toast('Producto quitado','warn'); }
  });
}

// ── GLOBAL STATS ─────────────────────────────────────────────
function updateGlobalStats(deltaTotal, deltaCon) {
  gTotal += deltaTotal; gCon += deltaCon;
  const sin = gTotal - gCon;
  const pct = gTotal ? Math.round(gCon/gTotal*100) : 0;
  document.getElementById('gTotal').textContent   = gTotal;
  document.getElementById('gCon').textContent     = gCon;
  document.getElementById('gSin').textContent     = sin;
  document.getElementById('gFaltan').textContent  = sin;
  document.getElementById('gPct').textContent     = pct;
  document.getElementById('gBar').style.width     = pct+'%';
}

// ── TOAST ────────────────────────────────────────────────────
function toast(msg, tipo='') {
  const el = document.getElementById('toast');
  el.textContent=msg; el.className='toast show '+tipo;
  clearTimeout(el._t); el._t=setTimeout(()=>el.className='toast',2800);
}

// ── ATAJOS ───────────────────────────────────────────────────
document.addEventListener('keydown', e => {
  if ((e.ctrlKey||e.metaKey) && e.key==='f') { e.preventDefault(); document.getElementById('si').focus(); }
});

loadCategorias();

// ── HOME CONFIG PANEL ─────────────────────────────────────────
const ALL_CATS = ['Termos','Audífonos y Diademas','Relojes','Combos','Parlantes','Cargadores y Cables','Aros de Luz y Tripodes','Micrófonos','Power Bank','Tecnología','Humidificadores','Soporte Celular','Cases y Estuches','Vidrios Templados','Pulseras y Straps','Otros'];
let hcpData = [];

async function hcpInit() {
  try {
    const d = await fetch('admin.php?action=home_get').then(r=>r.json());
    hcpData = d.secciones || [];
    if(hcpData.length===0){
      hcpData = ['Termos','Audífonos y Diademas','Relojes','Combos'].map(cat=>({cat,max:8}));
    }
  } catch(e){}
  hcpRender();
}
function hcpRender() {
  const list = document.getElementById('hcpList');
  if(!list) return;
  list.innerHTML = '';
  hcpData.forEach((item,i)=>{
    const div = document.createElement('div');
    div.className='hcp-item';
    div.draggable=true;
    div.dataset.i=i;
    div.innerHTML=`<span class="hcp-drag" style="cursor:grab;color:#888;font-size:15px;flex-shrink:0">&#8942;&#8942;</span>
      <span style="flex:1;font-size:.8rem;font-weight:500">${item.cat}</span>
      <div style="display:flex;align-items:center;gap:6px;flex-shrink:0">
        <label style="font-size:.65rem;color:#888;white-space:nowrap">Mostrar</label>
        <input class="hcp-max" type="number" min="1" max="20" value="${item.max}"
          style="width:44px;padding:4px 6px;border:1.5px solid #EAE6F8;border-radius:6px;font-size:.75rem;text-align:center"
          onchange="hcpData[${i}].max=parseInt(this.value)||8">
      </div>
      <button onclick="hcpDel(${i})"
        style="width:22px;height:22px;border-radius:50%;background:rgba(220,38,38,.1);border:none;color:#dc2626;cursor:pointer;font-size:13px;flex-shrink:0">&#215;</button>`;
    div.addEventListener('dragstart',e=>{e.dataTransfer.setData('text/plain',i);div.classList.add('dragging-src')});
    div.addEventListener('dragend',()=>div.classList.remove('dragging-src'));
    div.addEventListener('dragover',e=>{e.preventDefault();div.classList.add('drag-over')});
    div.addEventListener('dragleave',()=>div.classList.remove('drag-over'));
    div.addEventListener('drop',e=>{
      e.preventDefault();div.classList.remove('drag-over');
      const from=parseInt(e.dataTransfer.getData('text/plain'));
      const to=parseInt(div.dataset.i);
      if(from===to) return;
      const moved=hcpData.splice(from,1)[0];
      hcpData.splice(to,0,moved);
      hcpRender();
    });
    list.appendChild(div);
  });
}
function hcpDel(i){ hcpData.splice(i,1); hcpRender(); }
function hcpAddRow(){
  const disponibles = ALL_CATS.filter(c => !hcpData.find(d=>d.cat===c));
  if(!disponibles.length){ toast('Todas las categorías ya están','warn'); return; }
  const wrap = document.getElementById('hcpList');
  // Si ya hay un select pendiente, no duplicar
  if(document.getElementById('hcp-add-sel')) return;
  const row = document.createElement('div');
  row.style.cssText='display:flex;align-items:center;gap:8px;padding:8px 0';
  row.id='hcp-add-sel';
  row.innerHTML='<select id="hcp-sel-val" style="flex:1;padding:7px 10px;border:1.5px solid var(--fu);border-radius:8px;font-family:Poppins;font-size:.78rem;color:var(--vi);outline:none">'
    +'<option value="">— Elegir categoría —</option>'
    +disponibles.map(c=>`<option value="${c}">${c}</option>`).join('')
    +'</select>'
    +'<button onclick="hcpConfirmAdd()" style="padding:7px 14px;border-radius:8px;background:var(--grad);border:none;color:#fff;font-family:Poppins;font-size:.75rem;font-weight:600;cursor:pointer">Agregar</button>'
    +'<button onclick="hcpCancelAdd()" style="padding:7px 10px;border-radius:8px;border:1px solid var(--bd);background:#fff;font-family:Poppins;font-size:.75rem;cursor:pointer;color:var(--mu)">Cancelar</button>';
  wrap.appendChild(row);
  document.getElementById('hcp-sel-val').focus();
}
function hcpCancelAdd(){ document.getElementById('hcp-add-sel')?.remove(); }
function hcpConfirmAdd(){
  const sel = document.getElementById('hcp-sel-val');
  const t = sel?.value;
  if(!t){ toast('Elige una categoría','err'); return; }
  if(hcpData.find(d=>d.cat===t)){ toast('Ya está en la lista','err'); return; }
  hcpData.push({cat:t,max:8});
  document.getElementById('hcp-add-sel')?.remove();
  hcpRender();
}
async function hcpSave(){
  const btn=document.querySelector('.hcp-save');
  btn.disabled=true; btn.textContent='Guardando...';
  try{
    const d=await fetch('admin.php?action=home_secciones',{
      method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify(hcpData)
    }).then(r=>r.json());
    document.getElementById('hcpStatus').textContent = d.ok ? 'Guardado — '+d.n+' secciones' : 'Error';
    if(d.ok) toast('Home actualizado','suc'); else toast('Error','err');
  }catch(e){ toast('Error de conexión','err'); }
  btn.disabled=false; btn.textContent='Guardar orden del home';
}
function toggleHcp(){
  const body=document.getElementById('hcpBody');
  const arrow=document.getElementById('hcpArrow');
  body.classList.toggle('open');
  arrow.innerHTML=body.classList.contains('open')?'&#9650;':'&#9660;';
}
hcpInit();

// ── AUDITORÍA INVENTARIO ─────────────────────────────────────
let audData = null;
const audFiltro = { foto: 'all', precio: 'all' };

async function abrirAuditoria() {
  document.getElementById('audBack').classList.add('show');
  document.body.style.overflow = 'hidden';
  if (!audData) await audCargar();
}
function cerrarAuditoria() {
  document.getElementById('audBack').classList.remove('show');
  document.body.style.overflow = '';
}
async function audCargar() {
  const body = document.getElementById('audBody');
  body.innerHTML = '<div class="aud-loading">Cargando auditoría...</div>';
  try {
    const d = await fetch('admin.php?action=auditoria').then(r=>r.json());
    if (!d.ok) throw new Error();
    audData = d;
    audPintarStats();
    audPintarTabla();
  } catch {
    body.innerHTML = '<div class="aud-msg" style="color:var(--err)">No se pudo cargar la auditoría</div>';
  }
}
function audPintarStats() {
  const c = audData.cnt;
  const total = c.total || 1;
  const pctF = Math.round((c.foto.v / total) * 100);
  const pctP = Math.round((c.precio.v / total) * 100);
  document.getElementById('audStats').innerHTML = `
    <div class="aud-stat-card">
      <h4>Foto · ${c.total} productos · ${pctF}% OK</h4>
      <div class="aud-pills">
        <div class="aud-pill v">${c.foto.v}<span class="lab">2+ fotos</span></div>
        <div class="aud-pill a">${c.foto.a}<span class="lab">solo 1</span></div>
        <div class="aud-pill r">${c.foto.r}<span class="lab">sin foto</span></div>
      </div>
    </div>
    <div class="aud-stat-card">
      <h4>Precio · ${c.total} productos · ${pctP}% OK</h4>
      <div class="aud-pills">
        <div class="aud-pill v">${c.precio.v}<span class="lab">&ge; $${audData.precio_min.toLocaleString('es-CO')}</span></div>
        <div class="aud-pill a">${c.precio.a}<span class="lab">sospechoso</span></div>
        <div class="aud-pill r">${c.precio.r}<span class="lab">sin precio</span></div>
      </div>
    </div>
  `;
}
function audPintarTabla() {
  const q = (document.getElementById('audSearch').value || '').toLowerCase().trim();
  const rows = audData.rows.filter(r => {
    if (audFiltro.foto   !== 'all' && r.sem_foto   !== audFiltro.foto)   return false;
    if (audFiltro.precio !== 'all' && r.sem_precio !== audFiltro.precio) return false;
    if (q && !(r.nombre.toLowerCase().includes(q)
            || r.categoria.toLowerCase().includes(q)
            || String(r.id) === q)) return false;
    return true;
  });
  const body = document.getElementById('audBody');
  if (!rows.length) {
    body.innerHTML = '<div class="aud-msg">Sin resultados con esos filtros</div>';
    return;
  }
  const labFoto = { v:'2+ fotos', a:'solo 1', r:'sin foto' };
  const labPre  = { v:'OK', a:'sospechoso', r:'sin precio' };
  body.innerHTML = `<table class="aud-table">
    <thead><tr>
      <th></th><th>Producto</th><th>Categoría</th><th>Precio</th><th>Stock</th><th>Foto</th><th>Precio</th>
    </tr></thead>
    <tbody>
      ${rows.map(r => `<tr>
        <td>${r.foto_url ? `<img class="aud-thumb" src="${r.foto_url}" alt="">` : '<div class="aud-thumb-empty">!</div>'}</td>
        <td><div class="aud-name">${r.nombre}</div><span class="aud-id">#${r.id}</span>${r.subgrupo?` <span style="font-size:9px;color:var(--mu)">· ${r.subgrupo}</span>`:''}</td>
        <td><span class="aud-cat">${r.categoria || '—'}</span></td>
        <td><span class="aud-precio ${r.precio<=0?'zero':''}">${r.precio>0 ? '$'+r.precio.toLocaleString('es-CO') : 'sin precio'}</span></td>
        <td><span class="aud-stk">${r.stk} uds</span></td>
        <td><span class="aud-sem"><span class="aud-dot ${r.sem_foto}"></span>${labFoto[r.sem_foto]} <span style="font-size:9px;color:var(--mu);margin-left:3px">${r.n_fotos}/6</span></span></td>
        <td><span class="aud-sem"><span class="aud-dot ${r.sem_precio}"></span>${labPre[r.sem_precio]}</span></td>
      </tr>`).join('')}
    </tbody>
  </table>`;
}
function audSetFiltro(btn) {
  const tipo = btn.dataset.tipo, val = btn.dataset.val;
  audFiltro[tipo] = val;
  btn.parentElement.querySelectorAll('.aud-filt-btn').forEach(b => b.classList.remove('act'));
  btn.classList.add('act');
  audPintarTabla();
}
function audFiltrar() {
  if (audData) audPintarTabla();
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && document.getElementById('audBack').classList.contains('show')) cerrarAuditoria();
});
</script>
<?php endif ?>
</body>
</html>
