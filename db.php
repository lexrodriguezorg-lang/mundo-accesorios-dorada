<?php
// ── Conexión compartida MySQL ─────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;dbname=u373685810_MundoAcces;charset=utf8mb4',
            'u373685810_mundoaccesorio',
            'MundoAcces2026',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}
