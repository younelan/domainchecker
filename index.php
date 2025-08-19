<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/WhoisService.php';
require_once __DIR__ . '/src/HistoryStore.php';

use DomainCheck\Src\WhoisService;
use DomainCheck\Src\HistoryStore;

$config = require __DIR__ . '/config.php';

// Simple router
$action = $_GET['action'] ?? null;
header('Content-Type: application/json; charset=utf-8');

$whoisServers = [];

// Backwards compatibility: accept new 'tlds' mapping or older 'whois_servers' + 'known_tlds'.
if (!empty($config['tlds']) && is_array($config['tlds'])) {
  foreach ($config['tlds'] as $tld => $meta) {
    // only expose enabled TLDs in the UI; server may be null to rely on system whois
    if (is_array($meta) && array_key_exists('server', $meta)) {
      $whoisServers[$tld] = $meta['server'];
    } elseif (is_string($meta)) {
      $whoisServers[$tld] = $meta;
    }
  }
} else {
  $whoisServers = $config['whois_servers'] ?? [];
}

$whois = new WhoisService($whoisServers, $config['check_timeout'] ?? 3);
$history = new HistoryStore();

if ($action === 'check' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $name = $_GET['name'] ?? '';
    $tld = $_GET['tld'] ?? '';

    $res = $whois->check($name, $tld);
    echo json_encode(['ok' => true, 'name' => $name, 'tld' => $tld, 'result' => $res]);
    exit;
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid json']);
        exit;
    }

    // Basic same-origin check: require a Referer from same host if available
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer && parse_url($referer, PHP_URL_HOST) !== ($_SERVER['HTTP_HOST'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }

    $history->save($body);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'history' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['ok' => true, 'history' => $history->all()]);
    exit;
}

if ($action === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $history->clear();
  echo json_encode(['ok' => true]);
  exit;
}

// Default: serve a minimal HTML page when no action specified
header('Content-Type: text/html; charset=utf-8');

// Normalize known TLDs configuration into a consistent array of entries:
// ['name' => ..., 'checked' => bool, 'server' => string|null]
$known = [];
if (!empty($config['tlds']) && is_array($config['tlds'])) {
  foreach ($config['tlds'] as $t => $meta) {
    // Skip entries explicitly disabled
    $enabled = true;
    if (is_array($meta) && array_key_exists('enabled', $meta)) {
      $enabled = (bool)$meta['enabled'];
    }
    if (!$enabled) continue;

    $checked = false;
    $server = null;
    if (is_array($meta)) {
      $checked = !empty($meta['checked']);
      $server = $meta['server'] ?? null;
    } elseif (is_string($meta)) {
      // legacy shorthand: server string
      $server = $meta;
    }
    $known[] = ['name' => $t, 'checked' => $checked, 'server' => $server];
  }
} else {
  // Legacy support: known_tlds may be a numeric or associative array.
  $rawKnown = $config['known_tlds'] ?? ['com','net','org'];
  if (array_values($rawKnown) === $rawKnown) {
    foreach ($rawKnown as $t) {
      $known[] = ['name' => $t, 'checked' => in_array($t, ['com','net','org']), 'server' => ($config['whois_servers'][$t] ?? null)];
    }
  } else {
    foreach ($rawKnown as $t => $meta) {
      $checked = is_array($meta) && !empty($meta['checked']);
      $known[] = ['name' => $t, 'checked' => $checked, 'server' => ($config['whois_servers'][$t] ?? null)];
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>TLD Explorer — Minimal</title>
<link rel="stylesheet" href="resources/style.css">
</head>
<body>
<div class="container">
  <header>
    <div class="header-row">
      <div class="header-left">
  <h1>🔎 TLD Explorer</h1>
        <p class="muted">Quick TLD availability checks — parallel, session history.</p>
      </div>
      <div>
  <button id="themeToggle" class="theme-toggle" aria-pressed="false" aria-label="Toggle theme"></button>
      </div>
    </div>
  </header>
  <main>
    <form id="searchForm">
      <label for="q">Name</label>
      <div class="search-row">
  <input id="q" name="q" type="text" placeholder="example" required>
  <button type="submit" class="check-btn"><span class="icon">🔍</span><span class="label">Check</span></button>
      </div>

      <div style="margin:12px 0">
        <button type="button" id="toggleTlds" class="theme-toggle">Select all</button>
      </div>

      <div class="tlds">
        <?php foreach ($known as $entry): $t = $entry['name']; $isChecked = !empty($entry['checked']); ?>
          <label class="chip"><input type="checkbox" name="tlds[]" value="<?=htmlspecialchars($t)?>" <?= $isChecked ? 'checked' : '' ?>> <?=htmlspecialchars($t)?></label>
        <?php endforeach; ?>
      </div>
    </form>

    <section id="results" aria-live="polite"></section>
  </main>

  <aside id="history">
    <div class="history-header"><h2>History</h2><button id="clearHistory" class="clear-btn">Clear</button></div>
  </aside>
</div>

<script src="resources/app.js"></script>
</body>
</html>

<script>
  // Known TLDs for client-side normalization
  window.KNOWN_TLDS = <?= json_encode(array_map(function($e){return $e['name'];}, $known), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP) ?>;
  // Additional TLD metadata (server, checked) available to client scripts.
  window.TLD_META = <?= json_encode(array_reduce($known, function($carry, $e){ $carry[$e['name']] = ['server' => $e['server'] ?? null, 'checked' => !empty($e['checked'])]; return $carry; }, []), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP) ?>;
</script>
