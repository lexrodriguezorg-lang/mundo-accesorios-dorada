<?php
// ══════════════════════════════════════════════════════════════
//  SEED DE AFILIADAS DE PRUEBA — eliminar después de usar
//  Requiere login previo como admin (comparte sesión con admin.php).
//  Uso:
//    1. Entrá primero a /admin.php con tu usuario admin
//    2. Luego abrí /seed_afiliadas.php
//    3. El script crea 3 afiliadas de demo si no existen
//    4. Eliminá ESTE archivo después para no poder repoblar
// ══════════════════════════════════════════════════════════════

require_once __DIR__ . '/afiliados_lib.php';
session_start(); // usa PHPSESSID del admin

if (empty($_SESSION['ok']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo "⛔ Solo admin logueado en /admin.php puede correr este seed.\n";
    echo "Entrá a /admin.php primero y recargá esta página.\n";
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');

$SEED = [
    [
        'user' => 'maria', 'nombre' => 'María López (demo)', 'codigo' => 'MARIA2026',
        'clave' => 'maria123', 'tipo' => 'porcentaje', 'valor' => 10,
        'wa' => '573001111111',
        'mensaje' => "Hola 🌸 soy {afil_nombre} de Mundo Accesorios Dorada.\nVi que te interesa {producto_nombre} — {producto_precio}.\n{producto_link}\n\n¿Te ayudo con la compra? 💖",
        'variante_a' => "Linda, ¡me encanta que te guste {producto_nombre}! 🌸\nPrecio especial: {producto_precio}\nAcá tenés el link: {producto_link}\n\nSoy {afil_nombre}, te acompaño.",
    ],
    [
        'user' => 'camila', 'nombre' => 'Camila Ríos (demo)', 'codigo' => 'CAMI2026',
        'clave' => 'camila123', 'tipo' => 'porcentaje', 'valor' => 15,
        'wa' => '573002222222',
        'mensaje' => "¡Hola! Soy {afil_nombre} de Mundo Accesorios ✨\n{producto_nombre}\n{producto_precio}\n\nMira: {producto_link}\n\nComisión especial contigo, avísame si te interesa.",
    ],
    [
        'user' => 'laura', 'nombre' => 'Laura Duque (demo)', 'codigo' => 'LAURA777',
        'clave' => 'laura123', 'tipo' => 'fijo', 'valor' => 8000,
        'wa' => '573003333333',
        'mensaje' => "Hola 💕 Te hablo de parte de Mundo Accesorios.\n\n{producto_nombre} — solo {producto_precio}\n\nEste es el link con descuento: {producto_link}\n\nCualquier duda te ayudo ✨\n— {afil_nombre}",
    ],
];

$data = afil_read();
$creadas = 0; $ya = 0;

foreach ($SEED as $s) {
    if (afil_idx_user($data, $s['user']) >= 0) { $ya++; echo "• @{$s['user']} ya existe — saltada\n"; continue; }
    $data['afiliadas'][] = [
        'user'              => $s['user'],
        'clave_hash'        => password_hash($s['clave'], PASSWORD_DEFAULT),
        'nombre'            => $s['nombre'],
        'codigo'            => $s['codigo'],
        'tipo_comision'     => $s['tipo'],
        'valor_comision'    => $s['valor'],
        'producto_comision' => null,
        'activa'            => true,
        'creada'            => date('Y-m-d'),
        'mensaje_agente'    => $s['mensaje'],
        'variante_a'        => $s['variante_a'] ?? '',
        'variante_b'        => '',
        'wa_telefono'       => $s['wa'],
        'clics_total'       => 0,
        'clics_recientes'   => [],
        'ventas'            => [],
    ];
    $creadas++;
    echo "✓ @{$s['user']} — clave: {$s['clave']} — código: {$s['codigo']} — {$s['tipo']} {$s['valor']}\n";
}

if ($creadas > 0) afil_write($data);

echo "\n── Resumen ──────────────────────\n";
echo "Creadas: $creadas\n";
echo "Ya existían: $ya\n";
echo "Total en sistema: " . count($data['afiliadas']) . "\n";
echo "\n⚠️  Eliminá este archivo (seed_afiliadas.php) después de correrlo.\n";
echo "\nLinks de prueba (copiá y abrí en incógnito):\n";
foreach ($SEED as $s) {
    echo "  https://mundoaccesoriosdorada.com/?ref={$s['codigo']}\n";
}
echo "\nLogin afiliadas:  /afiliados/login.php\n";
