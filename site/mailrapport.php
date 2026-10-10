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
const NAVY      = '#0a1a4f';
const BLAUW     = '#1b46c2';
const LUCHT     = '#cfe0f5';
const PAPIER    = '#f7f8fc';
const LIJN      = '#e6e8f1';
const GRIJS     = '#6b7a9e';

header('Content-Type: text/plain; charset=utf-8');

$forceer = hash_equals(SLEUTEL, (string) ($_GET['sleutel'] ?? ''));
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
    return ucfirst(str_replace('-', ' ', $n));
}

function blok_html(string $titel, array $rijen): string {
    $tot = array_sum(array_map('intval', $rijen));
    arsort($rijen);
    $o  = '<tr><td style="padding:26px 34px 0 34px">';
    $o .= '<table width="100%" cellpadding="0" cellspacing="0" role="presentation">';
    $o .= '<tr><td style="font:600 11px/1.2 Arial,sans-serif;letter-spacing:.18em;text-transform:uppercase;color:' . BLAUW . ';padding-bottom:10px">' . h($titel) . '</td>';
    $o .= '<td align="right" style="font:700 26px/1 Georgia,serif;color:' . NAVY . ';padding-bottom:10px">' . $tot . '</td></tr>';
    foreach ($rijen as $n => $c) {
        $o .= '<tr><td style="font:400 15px/1.5 Georgia,serif;color:#2c3550;border-top:1px solid ' . LIJN . ';padding:7px 0">' . h(mooi((string) $n)) . '</td>';
        $o .= '<td align="right" style="font:400 15px/1.5 Georgia,serif;color:#2c3550;border-top:1px solid ' . LIJN . ';padding:7px 0">' . (int) $c . '</td></tr>';
    }
    if (!$rijen) {
        $o .= '<tr><td colspan="2" style="font:italic 15px/1.5 Georgia,serif;color:' . GRIJS . ';border-top:1px solid ' . LIJN . ';padding:7px 0">niets geregistreerd</td></tr>';
    }
    return $o . '</table></td></tr>';
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

$vanaf = nl_datum($periode);
$tot   = nl_datum(tel_periode_einde($periode));
$jaar  = substr($periode, 0, 4);

$st_body = 'margin:0;padding:0;background:' . PAPIER;
$st_wrap = 'background:' . PAPIER . ';padding:28px 12px';
$st_card = 'max-width:600px;background:#ffffff;border:1px solid ' . LIJN;
$st_kop  = 'background:' . NAVY . ';padding:22px 34px';
$st_nm   = 'font:600 19px/1.2 Georgia,serif;color:#ffffff;letter-spacing:.12em';
$st_site = 'font:400 12px/1.2 Arial,sans-serif;color:' . LUCHT . ';letter-spacing:.16em';
$st_h1   = 'font:700 34px/1.1 Georgia,serif;color:' . NAVY;
$st_sub  = 'font:400 15px/1.6 Georgia,serif;color:' . GRIJS . ';padding-top:8px';
$st_rule = 'height:3px;width:46px;background:' . BLAUW . ';margin-top:16px';
$st_foot = 'border-top:1px solid ' . LIJN . ';padding-top:16px;font:400 12px/1.6 Arial,sans-serif;color:' . GRIJS;

$html  = '<!doctype html><html lang="nl"><head><meta charset="utf-8">';
$html .= '<meta name="viewport" content="width=device-width,initial-scale=1">';
$html .= '<title>Weekrapport</title></head><body style="' . $st_body . '">';
$html .= '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="' . $st_wrap . '">';
$html .= '<tr><td align="center">';
$html .= '<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="' . $st_card . '">';
$html .= '<tr><td style="' . $st_kop . '">';
$html .= '<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>';
$html .= '<td style="' . $st_nm . '">BLUE THINGS</td>';
$html .= '<td align="right" style="' . $st_site . '">' . h(SITE_URL) . '</td>';
$html .= '</tr></table></td></tr>';
$html .= '<tr><td style="padding:30px 34px 0 34px">';
$html .= '<div style="' . $st_h1 . '">Weekrapport</div>';
$html .= '<div style="' . $st_sub . '">zaterdag ' . h($vanaf);
$html .= ' t/m vrijdag ' . h($tot) . ' ' . h($jaar) . '</div>';
$html .= '<div style="' . $st_rule . '"></div></td></tr>';

$html .= blok_html('Bezoeken', $bezoeken);
$html .= blok_html('Kliks op items', $topItems);
if ($overig) { $html .= blok_html('Overige kliks', $overig); }

$html .= '<tr><td style="padding:28px 34px 30px 34px"><div style="' . $st_foot . '">';
$html .= 'Bezoeken zijn paginaweergaven, geen unieke personen.<br>';
$html .= 'Er worden geen cookies gebruikt en geen IP-adressen bewaard.</div></td></tr>';
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
