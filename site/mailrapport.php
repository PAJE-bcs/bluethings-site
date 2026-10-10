<?php
// Weekrapport van bluethings.be, in de huisstijl van de site.
// Wordt elke zaterdag aangeroepen door een geplande GitHub Action.
// Verstuurt hoogstens een mail per periode.

require_once __DIR__ . '/teller.php';

const NAAR      = 'pascal.jaques@bluecatservices.be';
const VAN       = 'rapport@bluethings.be';
const SLEUTEL   = 'bt41-rapport-9sKd3pWq';
const SITE_NAAM = 'Blue Things';
const SITE_URL  = 'bluethings.be';
const LOGO      = 'https://bluethings.be/logo-mail.png';
const NAVY      = '#0a1a4f';
const BLAUW     = '#1b46c2';
const TINT      = '#f1f4ff';
const LUCHT     = '#cfe0f5';
const PAPIER    = '#f7f8fc';
const LIJN      = '#e6e8f1';
const GRIJS     = '#6b7a9e';
const INKT      = '#2c3550';

header('Content-Type: text/plain; charset=utf-8');

$forceer = hash_equals(SLEUTEL, (string) ($_GET['sleutel'] ?? ''));

// Diagnose: waar komt de data terecht en wat staat er in.
if ($forceer && isset($_GET['waar'])) {
    $root = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    echo 'DOCUMENT_ROOT  ' . $root . PHP_EOL;
    echo 'script         ' . __DIR__ . PHP_EOL;
    echo 'ouder van root ' . dirname($root) . ' | schrijfbaar ' .
         (is_writable(dirname($root)) ? 'ja' : 'nee') . PHP_EOL . PHP_EOL;
    foreach ([dirname($root) . '/bt-stats', $root . '/bt-stats',
              dirname(__DIR__) . '/bt-stats', __DIR__ . '/bt-stats'] as $m) {
        echo str_pad($m, 46) . ' dir ' . (is_dir($m) ? 'ja ' : 'nee') .
             ' schrijfbaar ' . (is_writable($m) ? 'ja' : 'nee') . PHP_EOL;
    }
    $g = tel_map();
    echo PHP_EOL . 'gekozen        ' . var_export($g, true) . PHP_EOL;
    $f = $g ? glob($g . '/*') : [];
    foreach ($f ?: [] as $x) {
        echo '  ' . str_pad(basename($x), 36) . filesize($x) . ' bytes  ' .
             gmdate('Y-m-d H:i', filemtime($x)) . PHP_EOL;
    }
    exit;
}

$periode = ($forceer && isset($_GET['nu'])) ? tel_periode() : tel_periode(1);

$map = tel_map();
if ($map === null) { http_response_code(500); exit('geen datamap'); }

$slot = $map . '/verstuurd-' . TEL_SITE . '-' . $periode . '.txt';
$af = strtotime(tel_periode_einde($periode) . ' 23:59:59');
if (!$forceer && is_file($slot) && filemtime($slot) > $af) { exit('al verstuurd voor ' . $periode); }

$pad  = $map . '/' . TEL_SITE . '-' . $periode . '.json';
$data = is_file($pad) ? json_decode((string) file_get_contents($pad), true) : null;
if (!is_array($data)) $data = ['p' => [], 'k' => []];

function nl_datum(string $ymd): string {
    $m = ['', 'januari', 'februari', 'maart', 'april', 'mei', 'juni',
          'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
    return $d ? ((int) $d->format('j') . ' ' . $m[(int) $d->format('n')]) : $ymd;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function mooi(string $n): string {
    if (strpos($n, 'item/') === 0) $n = substr($n, 5);
    $vast = [
        'home'      => 'Startpagina',
        'mailadres' => 'Mailadres aangeklikt',
        'bestellen' => 'Bestellen',
        'maatwerk'  => 'Maatwerk',
    ];
    if (isset($vast[$n])) return $vast[$n];
    return ucfirst(str_replace('-', ' ', $n));
}

// Een staafje dat de verhouding toont binnen het blok.
function balk(int $deel, int $totaal): string {
    $pct = $totaal > 0 ? max(2, (int) round($deel * 100 / $totaal)) : 0;
    $o  = '<table width="80" cellpadding="0" cellspacing="0" role="presentation"';
    $o .= ' style="width:80px;border-collapse:collapse"><tr>';
    $o .= '<td bgcolor="' . BLAUW . '" style="width:' . $pct . '%;height:4px;';
    $o .= 'background:' . BLAUW . ';font-size:0;line-height:4px">&nbsp;</td>';
    if ($pct < 100) {
        $o .= '<td bgcolor="' . LIJN . '" style="width:' . (100 - $pct) . '%;height:4px;';
        $o .= 'background:' . LIJN . ';font-size:0;line-height:4px">&nbsp;</td>';
    }
    return $o . '</tr></table>';
}

function blok_html(string $titel, array $rijen): string {
    $tot = array_sum(array_map('intval', $rijen));
    arsort($rijen);
    $o  = '<tr><td style="padding:30px 36px 0 36px">';
    $o .= '<table width="100%" cellpadding="0" cellspacing="0" role="presentation">';
    $o .= '<tr><td style="font:bold 11px/1.2 Arial,Helvetica,sans-serif;letter-spacing:2px;';
    $o .= 'text-transform:uppercase;color:' . BLAUW . ';padding-bottom:12px">' . h($titel) . '</td>';
    $o .= '<td align="right" style="font:bold 11px/1.2 Arial,Helvetica,sans-serif;letter-spacing:1px;';
    $o .= 'color:' . GRIJS . ';padding-bottom:12px">' . $tot . ' totaal</td></tr>';
    foreach ($rijen as $n => $c) {
        $c = (int) $c;
        $o .= '<tr><td style="font:400 15px/1.5 Georgia,\'Times New Roman\',serif;color:' . INKT . ';';
        $o .= 'border-top:1px solid ' . LIJN . ';padding:9px 10px 9px 0">' . h(mooi((string) $n)) . '</td>';
        $o .= '<td align="right" style="border-top:1px solid ' . LIJN . ';padding:9px 0;white-space:nowrap">';
        $o .= '<table cellpadding="0" cellspacing="0" role="presentation" align="right"><tr>';
        $o .= '<td style="padding-right:12px">' . balk($c, $tot) . '</td>';
        $o .= '<td style="font:bold 15px/1.5 Georgia,\'Times New Roman\',serif;color:' . NAVY . '">' . $c . '</td>';
        $o .= '</tr></table></td></tr>';
    }
    if (!$rijen) {
        $o .= '<tr><td colspan="2" style="font:italic 15px/1.5 Georgia,\'Times New Roman\',serif;color:' . GRIJS;
        $o .= ';border-top:1px solid ' . LIJN . ';padding:9px 0">niets geregistreerd deze week</td></tr>';
    }
    return $o . '</table></td></tr>';
}

function tegel(int $getal, string $label): string {
    $o  = '<td width="50%" bgcolor="' . TINT . '" style="background:' . TINT . ';border:1px solid ' . LIJN;
    $o .= ';padding:20px 10px" align="center">';
    $o .= '<div style="font:bold 40px/1 Georgia,\'Times New Roman\',serif;color:' . NAVY . '">' . $getal . '</div>';
    $o .= '<div style="font:bold 10px/1.2 Arial,Helvetica,sans-serif;letter-spacing:2px;';
    $o .= 'text-transform:uppercase;color:' . BLAUW . ';padding-top:8px">' . h($label) . '</div></td>';
    return $o;
}

function blok_tekst(string $titel, array $rijen): string {
    $t = array_sum(array_map('intval', $rijen));
    arsort($rijen);
    $o = str_pad($titel, 34) . str_pad((string) $t, 6, ' ', STR_PAD_LEFT) . PHP_EOL;
    foreach ($rijen as $n => $c) {
        $o .= '  ' . str_pad(mooi((string) $n), 32) . str_pad((string) (int) $c, 6, ' ', STR_PAD_LEFT) . PHP_EOL;
    }
    return $o . PHP_EOL;
}

$bezoeken = is_array($data['p'] ?? null) ? $data['p'] : [];
$alles    = is_array($data['k'] ?? null) ? $data['k'] : [];

$items = []; $overig = [];
foreach ($alles as $n => $c) {
    if (strpos((string) $n, 'item/') === 0) $items[(string) $n] = (int) $c;
    else $overig[(string) $n] = (int) $c;
}
arsort($items);
$topItems = array_slice($items, 0, 12, true);

$totBezoek = array_sum(array_map('intval', $bezoeken));
$totKlik   = array_sum(array_map('intval', $alles));

$vanaf = nl_datum($periode);
$tot   = nl_datum(tel_periode_einde($periode));
$jaar  = substr($periode, 0, 4);

$serif = 'Georgia,\'Times New Roman\',serif';
$sans  = 'Arial,Helvetica,sans-serif';

$html  = '<!doctype html><html lang="nl"><head><meta charset="utf-8">';
$html .= '<meta name="viewport" content="width=device-width,initial-scale=1">';
$html .= '<title>Weekrapport ' . h(SITE_NAAM) . '</title></head>';
$html .= '<body style="margin:0;padding:0;background:' . PAPIER . '">';

// Voorvertoning in de inbox, onzichtbaar in de mail zelf.
$html .= '<div style="display:none;max-height:0;overflow:hidden;opacity:0">';
$html .= $totBezoek . ' bezoeken en ' . $totKlik . ' kliks, ' . h($vanaf) . ' t/m ' . h($tot) . '.</div>';

$html .= '<table width="100%" cellpadding="0" cellspacing="0" role="presentation"';
$html .= ' bgcolor="' . PAPIER . '" style="background:' . PAPIER . ';padding:30px 12px">';
$html .= '<tr><td align="center">';
$html .= '<table width="600" cellpadding="0" cellspacing="0" role="presentation"';
$html .= ' bgcolor="#ffffff" style="max-width:600px;background:#ffffff;border:1px solid ' . LIJN . '">';

// Logo
$html .= '<tr><td align="center" style="padding:34px 36px 0 36px">';
$html .= '<img src="' . LOGO . '" width="190" alt="Blue Things" ';
$html .= 'style="display:block;width:190px;max-width:190px;height:auto;border:0"></td></tr>';

// Titel
$html .= '<tr><td align="center" style="padding:22px 36px 0 36px">';
$html .= '<div style="font:bold 11px/1.2 ' . $sans . ';letter-spacing:3px;color:' . BLAUW . '">WEEKRAPPORT</div>';
$html .= '<div style="font:400 17px/1.6 ' . $serif . ';color:' . GRIJS . ';padding-top:10px">';
$html .= 'zaterdag ' . h($vanaf) . ' &ndash; vrijdag ' . h($tot) . ' ' . h($jaar) . '</div>';
$html .= '</td></tr>';
$html .= '<tr><td align="center" style="padding:20px 36px 0 36px">';
$html .= '<table cellpadding="0" cellspacing="0" role="presentation"><tr>';
$html .= '<td bgcolor="' . BLAUW . '" style="width:46px;height:3px;background:' . BLAUW;
$html .= ';font-size:0;line-height:3px">&nbsp;</td></tr></table></td></tr>';

// Kerncijfers
$html .= '<tr><td style="padding:28px 36px 0 36px">';
$html .= '<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>';
$html .= tegel($totBezoek, 'Bezoeken');
$html .= '<td width="14" style="width:14px;font-size:0;line-height:0">&nbsp;</td>';
$html .= tegel($totKlik, 'Kliks');
$html .= '</tr></table></td></tr>';

$html .= blok_html('Bezoeken per pagina', $bezoeken);
$html .= blok_html('Kliks op items', $topItems);
if ($overig) { $html .= blok_html('Overige kliks', $overig); }

// Knop
$html .= '<tr><td align="center" style="padding:34px 36px 0 36px">';
$html .= '<table cellpadding="0" cellspacing="0" role="presentation"><tr>';
$html .= '<td bgcolor="' . BLAUW . '" style="background:' . BLAUW . '">';
$html .= '<a href="https://' . SITE_URL . '/index.html" style="display:inline-block;padding:13px 30px;';
$html .= 'font:bold 11px/1 ' . $sans . ';letter-spacing:2px;color:#ffffff;text-decoration:none">';
$html .= 'OPEN DE WEBSITE</a></td></tr></table></td></tr>';

// Voet
$html .= '<tr><td style="padding:30px 36px 32px 36px">';
$html .= '<div style="border-top:1px solid ' . LIJN . ';padding-top:16px;font:400 12px/1.7 ' . $sans;
$html .= ';color:' . GRIJS . '">Bezoeken zijn paginaweergaven, geen unieke personen.<br>';
$html .= 'Er worden geen cookies gebruikt en geen IP-adressen bewaard.</div></td></tr>';

$html .= '<tr><td bgcolor="' . NAVY . '" align="center" style="background:' . NAVY . ';padding:16px 36px">';
$html .= '<div style="font:400 11px/1.4 ' . $sans . ';letter-spacing:2px;color:' . LUCHT . '">';
$html .= h(strtoupper(SITE_URL)) . '</div></td></tr>';

$html .= '</table></td></tr></table></body></html>';

$tekst  = 'Weekrapport ' . SITE_NAAM . PHP_EOL . SITE_URL . PHP_EOL;
$tekst .= str_repeat('=', 44) . PHP_EOL;
$tekst .= 'Periode: zaterdag ' . $vanaf . ' t/m vrijdag ' . $tot . ' ' . $jaar . PHP_EOL . PHP_EOL;
$tekst .= blok_tekst('BEZOEKEN', $bezoeken);
$tekst .= blok_tekst('KLIKS OP ITEMS', $topItems);
if ($overig) { $tekst .= blok_tekst('OVERIGE KLIKS', $overig); }
$tekst .= 'Bezoeken zijn paginaweergaven, geen unieke personen.' . PHP_EOL;
$tekst .= 'Er worden geen cookies gebruikt en geen IP-adressen bewaard.' . PHP_EOL;

$grens = 'bt' . bin2hex(random_bytes(12));
$nr = "\r\n";

$body  = '--' . $grens . $nr;
$body .= 'Content-Type: text/plain; charset=UTF-8' . $nr;
$body .= 'Content-Transfer-Encoding: 8bit' . $nr . $nr;
$body .= $tekst . $nr;
$body .= '--' . $grens . $nr;
$body .= 'Content-Type: text/html; charset=UTF-8' . $nr;
$body .= 'Content-Transfer-Encoding: 8bit' . $nr . $nr;
$body .= $html . $nr;
$body .= '--' . $grens . '--' . $nr;

$kop  = 'From: Weekrapport Blue Things <' . VAN . '>' . $nr;
$kop .= 'Reply-To: ' . NAAR . $nr;
$kop .= 'MIME-Version: 1.0' . $nr;
$kop .= 'Content-Type: multipart/alternative; boundary=' . "\"" . $grens . "\"";

$onderwerp = 'Weekrapport Blue Things ' . $periode;
$ok = @mail(NAAR, $onderwerp, $body, $kop, '-f' . VAN);

if ($ok && !isset($_GET['nu'])) { @file_put_contents($slot, gmdate('c')); }

echo ($ok ? 'verstuurd' : 'mail() gaf false') . ' voor ' . $periode . PHP_EOL;
if ($forceer) { echo PHP_EOL . $tekst; }
