<?php
// Gedeelde teller voor bluethings.be.
// Geen cookies, geen IP-adressen: enkel optellen per periode (za t/m vr)
// in een JSON-bestand buiten de webmap.

if (!defined('TEL_SITE')) define('TEL_SITE', 'bluethings');

function tel_afschermen(string $map): void {
    $ht = $map . '/.htaccess';
    if (is_file($ht)) return;
    @file_put_contents($ht,
        "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
        "<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
}

function tel_map(): ?string {
    static $gezocht = false;
    static $uit = null;
    if ($gezocht) return $uit;
    $gezocht = true;
    $root = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $kandidaten = [];
    if ($root !== '') {
        $kandidaten[] = dirname($root) . '/bt-stats';
        $kandidaten[] = $root . '/bt-stats';
    }
    $kandidaten[] = dirname(__DIR__) . '/bt-stats';
    $kandidaten[] = __DIR__ . '/bt-stats';
    foreach ($kandidaten as $m) {
        if (is_dir($m) && is_writable($m)) { $uit = $m; break; }
    }
    if ($uit === null) {
        foreach ($kandidaten as $m) {
            if (@mkdir($m, 0775, true) && is_writable($m)) { $uit = $m; break; }
        }
    }
    if ($uit !== null) tel_afschermen($uit);
    return $uit;
}

// Een periode loopt van zaterdag tot en met vrijdag.
function tel_periode(int $wekenTerug = 0): string {
    $nu = new DateTimeImmutable('now', new DateTimeZone('Europe/Brussels'));
    $terug = ((int) $nu->format('w') + 1) % 7;
    $terug += 7 * max(0, $wekenTerug);
    return $nu->modify('-' . $terug . ' days')->format('Y-m-d');
}

function tel_periode_einde(string $start): string {
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $start,
        new DateTimeZone('Europe/Brussels'));
    return $d ? $d->modify('+6 days')->format('Y-m-d') : $start;
}

function tel_schoon(string $s, int $max = 60): string {
    $s = preg_replace('~[^A-Za-z0-9/._-]~', '', $s);
    return substr((string) $s, 0, $max);
}

function tel_isbot(): bool {
    $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') return true;
    foreach (['bot', 'crawl', 'spider', 'slurp', 'preview', 'fetch',
              'monitor', 'curl', 'wget', 'python', 'headless', 'lighthouse'] as $w) {
        if (strpos($ua, $w) !== false) return true;
    }
    return false;
}

function tel_noteer(string $soort, string $naam): void {
    if (tel_isbot()) return;
    $soort = tel_schoon($soort, 1);
    $naam  = tel_schoon($naam, 60);
    if (!in_array($soort, ['p', 'k', 'd'], true) || $naam === '') return;
    $map = tel_map();
    if ($map === null) return;
    $periode = tel_periode();
    $pad = $map . '/' . TEL_SITE . '-' . $periode . '.json';
    $fh = @fopen($pad, 'c+');
    if ($fh === false) return;
    if (flock($fh, LOCK_EX)) {
        $ruw = stream_get_contents($fh);
        $data = $ruw !== '' ? json_decode($ruw, true) : null;
        if (!is_array($data)) $data = ['periode' => $periode, 'p' => [], 'k' => [], 'd' => []];
        if (!isset($data[$soort]) || !is_array($data[$soort])) $data[$soort] = [];
        $data[$soort][$naam] = (int) ($data[$soort][$naam] ?? 0) + 1;
        $data['laatst'] = gmdate('Y-m-d\TH:i:s\Z');
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
}
