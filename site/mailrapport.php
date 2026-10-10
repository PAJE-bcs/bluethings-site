<?php
// Weekrapport van bluethings.be, in de huisstijl van de site.
// Wordt elke zaterdag aangeroepen door een geplande GitHub Action.
// Vaste pixelbreedtes: Outlook rendert met de Word-engine en rekt
// procenttabellen op tot buiten hun cel.

require_once __DIR__ . '/teller.php';

const NAAR      = 'pascal.jaques@bluecatservices.be';
const VAN       = 'rapport@bluethings.be';
const SLEUTEL   = 'bt41-rapport-9sKd3pWq';
const SITE_NAAM = 'Blue Things';
const SITE_URL  = 'bluethings.be';
const LOGO      = 'https://bluethings.be/logo-mail.png';
const NAVY      = '#0a1a4f';
const ACCENT    = '#1b46c2';
const TINT      = '#f1f4ff';
const LUCHT     = '#cfe0f5';
const PAPIER    = '#f7f8fc';
const LIJN      = '#e6e8f1';
const GRIJS     = '#6b7a9e';
const INKT      = '#2c3550';

header('Content-Type: text/plain; charset=utf-8');

$forceer = hash_equals(SLEUTEL, (string) ($_GET['sleutel'] ?? ''));

if ($forceer && isset($_GET['waar'])) {
    $root = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    echo 'DOCUMENT_ROOT  ' . $root . PHP_EOL;
    echo 'script         ' . __DIR__ . PHP_EOL;
    echo 'ouder van root ' . dirname($root) . ' | schrijfbaar ' .
         (is_writable(dirname($root)) ? 'ja' : 'nee') . PHP_EOL . PHP_EOL;
    $g = tel_map();
    echo 'gekozen        ' . var_export($g, true) . PHP_EOL;
    foreach (($g ? glob($g . '/*') : []) ?: [] as $x) {
        echo '  ' . str_pad(basename($x), 36) . filesize($x) . ' bytes  ' .
             gmdate('Y-m-d H:i', filemtime($x)) . PHP_EOL;
    }
    exit;
}

// Teller op nul zetten voor de lopende periode.
if ($forceer && isset($_GET['nul'])) {
    $m = tel_map();
    if ($m === null) { http_response_code(500); exit('geen datamap'); }
    $w = tel_periode();
    file_put_contents($m . '/' . TEL_SITE . '-' . $w . '.json',
        json_encode(['p' => new stdClass(), 'k' => new stdClass()]));
    exit('teller op nul voor ' . $w . PHP_EOL);
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

function pad_r(string $s, int $n): string {
    $l = mb_strlen($s, 'UTF-8');
    return $s . str_repeat(' ', max(0, $n - $l));
}
function pad_l(string $s, int $n): string {
    $l = mb_strlen($s, 'UTF-8');
    return str_repeat(' ', max(0, $n - $l)) . $s;
}

// Vaste pixelbreedtes: Outlook rekt procenttabellen op tot buiten hun cel.
function balk(int $deel, int $totaal, string $kleur): string {
    $vol = $totaal > 0 ? max(3, (int) round($deel * 76 / $totaal)) : 0;
    $vol = min(76, $vol);
    $rest = 76 - $vol;
    $o  = '<table width="76" cellpadding="0" cellspacing="0" border="0" role="presentation"';
    $o .= ' style="width:76px;border-collapse:collapse;table-layout:fixed"><tr>';
    $o .= '<td width="' . $vol . '" bgcolor="' . $kleur . '" style="width:' . $vol . 'px;height:4px;';
    $o .= 'background:' . $kleur . ';font-size:0;line-height:4px">&nbsp;</td>';
    if ($rest > 0) {
        $o .= '<td width="' . $rest . '" bgcolor="' . LIJN . '" style="width:' . $rest . 'px;height:4px;';
        $o .= 'background:' . LIJN . ';font-size:0;line-height:4px">&nbsp;</td>';
    }
    return $o . '</tr></table>';
}

function rij_ruimte(int $px): string {
    return '<tr><td height="' . $px . '" style="height:' . $px . 'px;font-size:0;line-height:' .
           $px . 'px">&nbsp;</td></tr>';
}

function blok_html(string $titel, array $rijen, string $leeg = 'niets geregistreerd deze week'): string {
    $tot = array_sum(array_map('intval', $rijen));
    arsort($rijen);
    $serif = 'Georgia,\'Times New Roman\',serif';
    $sans  = 'Arial,Helvetica,sans-serif';
    $o  = '<tr><td style="padding:0 36px">';
    $o .= '<table width="528" cellpadding="0" cellspacing="0" border="0" role="presentation"';
    $o .= ' style="width:528px;border-collapse:collapse;table-layout:fixed">';
    $o .= '<tr><td style="font:bold 11px/1.2 ' . $sans . ';letter-spacing:2px;';
    $o .= 'text-transform:uppercase;color:' . ACCENT . ';padding-bottom:12px">' . h($titel) . '</td>';
    $o .= '<td width="124" colspan="2" align="right" style="width:124px;font:bold 11px/1.2 ' . $sans . ';';
    $o .= 'color:' . GRIJS . ';padding-bottom:12px">' . $tot . ' totaal</td></tr>';
    foreach ($rijen as $n => $c) {
        $c = (int) $c;
        $o .= '<tr><td style="font:400 15px/1.5 ' . $serif . ';color:' . INKT . ';';
        $o .= 'border-top:1px solid ' . LIJN . ';padding:9px 10px 9px 0">' . h(mooi((string) $n)) . '</td>';
        $o .= '<td width="76" align="right" style="width:76px;border-top:1px solid ' . LIJN . ';padding:9px 0">';
        $o .= balk($c, $tot, ACCENT) . '</td>';
        $o .= '<td width="48" align="right" style="width:48px;border-top:1px solid ' . LIJN . ';padding:9px 0 9px 12px;';
        $o .= 'font:bold 15px/1.5 ' . $serif . ';color:' . NAVY . '">' . $c . '</td></tr>';
    }
    if (!$rijen) {
        $o .= '<tr><td colspan="3" style="font:italic 15px/1.5 ' . $serif . ';color:' . GRIJS;
        $o .= ';border-top:1px solid ' . LIJN . ';padding:9px 0">' . h($leeg) . '</td></tr>';
    }
    return $o . '</table></td></tr>';
}

function tegel(int $getal, string $label, int $breedte): string {
    $sans = 'Arial,Helvetica,sans-serif';
    $o  = '<td width="' . $breedte . '" bgcolor="' . TINT . '" align="center"';
    $o .= ' style="width:' . $breedte . 'px;background:' . TINT . ';border:1px solid ' . LIJN;
    $o .= ';padding:20px 4px">';
    $o .= '<div style="font:bold 38px/1 Georgia,\'Times New Roman\',serif;color:' . NAVY . '">' . $getal . '</div>';
    $o .= '<div style="font:bold 10px/1.2 ' . $sans . ';letter-spacing:2px;';
    $o .= 'text-transform:uppercase;color:' . ACCENT . ';padding-top:8px">' . h($label) . '</div></td>';
    return $o;
}

function blok_tekst(string $titel, array $rijen): string {
    $t = array_sum(array_map('intval', $rijen));
    arsort($rijen);
    $o = pad_r($titel, 34) . pad_l((string) $t, 6) . PHP_EOL;
    foreach ($rijen as $n => $c) {
        $o .= '  ' . pad_r(mooi((string) $n), 32) . pad_l((string) (int) $c, 6) . PHP_EOL;
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
$html .= '<!--[if mso]><style>table{border-collapse:collapse}</style><![endif]-->';
$html .= '<title>Weekrapport ' . h(SITE_NAAM) . '</title></head>';
$html .= '<body style="margin:0;padding:0;background:' . PAPIER . '">';
$html .= '<div style="display:none;max-height:0;overflow:hidden;opacity:0">';
$html .= $totBezoek . ' bezoeken en ' . $totKlik . ' kliks, ' . h($vanaf) . ' t/m ' . h($tot) . '.</div>';

$html .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"';
$html .= ' bgcolor="' . PAPIER . '" style="background:' . PAPIER . ';border-collapse:collapse">';
$html .= '<tr><td align="center" style="padding:30px 10px">';
$html .= '<table width="600" cellpadding="0" cellspacing="0" border="0" role="presentation"';
$html .= ' bgcolor="#ffffff" style="width:600px;background:#ffffff;border:1px solid ' . LIJN;
$html .= ';border-collapse:collapse">';

$html .= '<tr><td align="center" style="padding:34px 36px 0 36px">';
$html .= '<img src="' . LOGO . '" width="190" height="181" alt="Blue Things" ';
$html .= 'style="display:block;width:190px;height:181px;border:0"></td></tr>';

$html .= '<tr><td align="center" style="padding:22px 36px 0 36px">';
$html .= '<div style="font:bold 11px/1.2 ' . $sans . ';letter-spacing:3px;color:' . ACCENT . '">WEEKRAPPORT</div>';
$html .= '<div style="font:400 17px/1.6 ' . $serif . ';color:' . GRIJS . ';padding-top:10px">';
$html .= 'zaterdag ' . h($vanaf) . ' &ndash; vrijdag ' . h($tot) . ' ' . h($jaar) . '</div></td></tr>';

$html .= '<tr><td align="center" style="padding:18px 36px 0 36px">';
$html .= '<table width="46" cellpadding="0" cellspacing="0" border="0" role="presentation"';
$html .= ' style="width:46px;border-collapse:collapse"><tr>';
$html .= '<td bgcolor="' . ACCENT . '" style="height:3px;background:' . ACCENT;
$html .= ';font-size:0;line-height:3px">&nbsp;</td></tr></table></td></tr>';

$html .= rij_ruimte(28);
$html .= '<tr><td style="padding:0 36px">';
$html .= '<table width="528" cellpadding="0" cellspacing="0" border="0" role="presentation"';
$html .= ' style="width:528px;border-collapse:collapse;table-layout:fixed"><tr>';
$html .= tegel($totBezoek, 'Bezoeken', 258);
$html .= '<td width="12" style="width:12px;font-size:0;line-height:0">&nbsp;</td>';
$html .= tegel($totKlik, 'Kliks', 258);
$html .= '</tr></table></td></tr>';

$html .= rij_ruimte(32);
$html .= blok_html('Bezoeken per pagina', $bezoeken);
$html .= rij_ruimte(30);
$html .= blok_html('Kliks op items', $topItems);
if ($overig) { $html .= rij_ruimte(30); $html .= blok_html('Overige kliks', $overig); }

$html .= rij_ruimte(34);
$html .= '<tr><td align="center" style="padding:0 36px">';
$html .= '<table cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse"><tr>';
$html .= '<td bgcolor="' . ACCENT . '" align="center" style="background:' . ACCENT . '">';
$html .= '<a href="https://' . SITE_URL . '/index.html" style="display:block;padding:14px 30px;';
$html .= 'font:bold 11px/1 ' . $sans . ';letter-spacing:2px;color:#ffffff;text-decoration:none">';
$html .= 'OPEN DE WEBSITE</a></td></tr></table></td></tr>';

$html .= rij_ruimte(30);
$html .= '<tr><td style="padding:0 36px">';
$html .= '<div style="border-top:1px solid ' . LIJN . ';padding-top:16px;font:400 12px/1.7 ' . $sans;
$html .= ';color:' . GRIJS . '">Bezoeken zijn paginaweergaven, geen unieke personen.<br>';
$html .= 'Er worden geen cookies gebruikt en geen IP-adressen bewaard.</div></td></tr>';
$html .= rij_ruimte(30);

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
