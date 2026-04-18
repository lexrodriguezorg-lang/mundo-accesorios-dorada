<?php
// ══════════════════════════════════════════════════════════════
//  LIBRERÍA COMPARTIDA · AFILIADAS
//  Lectura/escritura de data/afiliados.json, tracking de clics,
//  atribución por cookie `ma_ref` de 30 días.
// ══════════════════════════════════════════════════════════════

if (!defined('AFIL_DATA_FILE')) {
    define('AFIL_DATA_FILE', __DIR__ . '/data/afiliados.json');
    define('AFIL_COOKIE',    'ma_ref');
    define('AFIL_COOKIE_TTL', 30 * 24 * 3600); // 30 días
    define('AFIL_CLICS_MAX',  200);            // guarda los últimos N por afiliada
}

function afil_read(): array {
    if (!file_exists(AFIL_DATA_FILE)) {
        return ['v' => 1, 'afiliadas' => []];
    }
    $d = json_decode(file_get_contents(AFIL_DATA_FILE), true);
    return is_array($d) ? $d : ['v' => 1, 'afiliadas' => []];
}

function afil_write(array $data): bool {
    if (!is_dir(dirname(AFIL_DATA_FILE))) {
        mkdir(dirname(AFIL_DATA_FILE), 0755, true);
    }
    return file_put_contents(
        AFIL_DATA_FILE,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    ) !== false;
}

function afil_find_user(string $user): ?array {
    $d = afil_read();
    foreach ($d['afiliadas'] as $a) if (($a['user'] ?? '') === $user) return $a;
    return null;
}

function afil_find_codigo(string $codigo): ?array {
    if ($codigo === '') return null;
    $codigo = strtoupper(trim($codigo));
    $d = afil_read();
    foreach ($d['afiliadas'] as $a) if (strtoupper($a['codigo'] ?? '') === $codigo) return $a;
    return null;
}

function afil_idx_user(array &$data, string $user): int {
    foreach ($data['afiliadas'] as $i => $a) if (($a['user'] ?? '') === $user) return $i;
    return -1;
}

function afil_idx_codigo(array &$data, string $codigo): int {
    $codigo = strtoupper(trim($codigo));
    foreach ($data['afiliadas'] as $i => $a) if (strtoupper($a['codigo'] ?? '') === $codigo) return $i;
    return -1;
}

function afil_normalize_codigo(string $s): string {
    $s = strtoupper(trim($s));
    $s = preg_replace('/[^A-Z0-9_-]/', '', $s);
    return substr($s, 0, 24);
}

function afil_gen_codigo(string $nombre): string {
    $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $nombre)) ?: 'AFIL';
    $base = substr($base, 0, 8);
    return $base . rand(100, 999);
}

function afil_default_plantilla(): string {
    return "Hola 🌸 soy {afil_nombre} de Mundo Accesorios Dorada.\n".
           "Vi que te interesa este producto:\n\n".
           "{producto_nombre} — {producto_precio}\n".
           "{producto_link}\n\n".
           "¿Te ayudo con la compra? Tengo descuentos para ti 💖";
}

function afil_cliente_ip(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (str_contains($ip, ',')) $ip = trim(explode(',', $ip)[0]);
    return $ip;
}

function afil_ip_hash(string $ip): string {
    return $ip ? substr(md5($ip . '|ma-salt-2026'), 0, 10) : '';
}

function afil_set_ref_cookie(string $codigo): void {
    if ($codigo === '') return;
    $codigo = afil_normalize_codigo($codigo);
    if ($codigo === '') return;
    if (!headers_sent()) {
        setcookie(AFIL_COOKIE, $codigo, [
            'expires'  => time() + AFIL_COOKIE_TTL,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
    $_COOKIE[AFIL_COOKIE] = $codigo;
}

function afil_get_ref(): string {
    return afil_normalize_codigo($_COOKIE[AFIL_COOKIE] ?? '');
}

function afil_track_click(string $codigo, string $url = ''): void {
    $codigo = afil_normalize_codigo($codigo);
    if ($codigo === '') return;
    $data = afil_read();
    $i = afil_idx_codigo($data, $codigo);
    if ($i < 0) return;
    $a = &$data['afiliadas'][$i];
    if (empty($a['activa'])) return;

    $a['clics_total'] = intval($a['clics_total'] ?? 0) + 1;
    $rec = $a['clics_recientes'] ?? [];
    $rec[] = [
        't'   => date('c'),
        'url' => substr($url ?: ($_SERVER['REQUEST_URI'] ?? '/'), 0, 240),
        'ip'  => afil_ip_hash(afil_cliente_ip()),
        'ref' => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 160),
    ];
    if (count($rec) > AFIL_CLICS_MAX) $rec = array_slice($rec, -AFIL_CLICS_MAX);
    $a['clics_recientes'] = $rec;
    afil_write($data);
}

// Procesa ?ref=CODIGO — setea cookie, dispara tracking una vez
function afil_handle_incoming_ref(): void {
    $ref = $_GET['ref'] ?? '';
    if ($ref === '') return;
    $ref = afil_normalize_codigo($ref);
    if ($ref === '') return;
    if (!afil_find_codigo($ref)) return;
    $before = afil_get_ref();
    afil_set_ref_cookie($ref);
    // Siempre registramos el click del link de referido
    afil_track_click($ref, $_SERVER['REQUEST_URI'] ?? '/');
}

// Render del mensaje del agente con reemplazos
function afil_render_agente(array $afil, array $ctx = []): string {
    $tpl = $afil['mensaje_agente'] ?? afil_default_plantilla();
    $site = 'https://mundoaccesoriosdorada.com';
    $link_base = $site . '/?ref=' . urlencode($afil['codigo'] ?? '');
    $link_prod = $site . '/producto.php?id=' . intval($ctx['producto_id'] ?? 0) . '&ref=' . urlencode($afil['codigo'] ?? '');
    $reemplazos = [
        '{afil_nombre}'     => $afil['nombre'] ?? $afil['user'] ?? '',
        '{afil_user}'       => $afil['user'] ?? '',
        '{afil_codigo}'     => $afil['codigo'] ?? '',
        '{link_home}'       => $link_base,
        '{link_catalogo}'   => $site . '/catalogo.php?ref=' . urlencode($afil['codigo'] ?? ''),
        '{producto_nombre}' => $ctx['producto_nombre'] ?? '[nombre del producto]',
        '{producto_precio}' => $ctx['producto_precio'] ?? '[precio]',
        '{producto_link}'   => isset($ctx['producto_id']) ? $link_prod : $link_base,
    ];
    return strtr($tpl, $reemplazos);
}

function afil_comision_calc(array $afil, int $monto_venta, ?int $prod_id = null): int {
    $tipo = $afil['tipo_comision'] ?? 'porcentaje';
    $val  = floatval($afil['valor_comision'] ?? 0);
    if ($tipo === 'fijo') return max(0, intval($val));
    if ($tipo === 'producto') {
        // Comisión en producto: no hay monto en dinero, se registra como 1 unidad
        return 0;
    }
    return max(0, intval(round($monto_venta * $val / 100)));
}
