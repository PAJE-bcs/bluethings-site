<?php
// Telpunt voor de browser: tel.php?s=p|k&n=<naam>
require_once __DIR__ . '/teller.php';

tel_noteer((string) ($_GET['s'] ?? ''), (string) ($_GET['n'] ?? ''));

header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
