<?php

namespace DomainCheck\Src;

class WhoisService
{
    private array $whoisServers;
    private int $timeout;

    public function __construct(array $whoisServers = [], int $timeout = 3)
    {
        $this->whoisServers = $whoisServers;
        $this->timeout = $timeout;
    }

    /**
     * Check availability of a single name for a tld.
     * Returns ['available' => bool, 'raw' => string]
     */
    public function check(string $name, string $tld): array
    {
        $name = $this->sanitizeName($name);
        $tld = strtolower(preg_replace('/[^a-z0-9]/i', '', $tld));

        if ($name === '') {
            return ['available' => false, 'raw' => 'invalid name'];
        }

        // If user accidentally passed a full domain in $name (e.g. example.com), strip known tld
        if (strpos($name, '.') !== false) {
            $parts = explode('.', $name);
            $last = strtolower(array_pop($parts));
            if (isset($this->whoisServers[$last])) {
                $name = implode('.', $parts);
            }
        }

        $domain = $name . '.' . $tld;

        // Prefer network whois
        $server = $this->whoisServers[$tld] ?? null;
        $result = null;

        if ($server) {
            $result = $this->queryWhoisServer($server, $domain);
        }

        // Fallback: try PHP's shell whois if allowed and available
        if ($result === null) {
            $result = $this->runSystemWhois($domain);
        }

        if ($result === null) {
            return ['available' => false, 'raw' => 'no response'];
        }

        // Debug: log raw whois responses for .ma to help diagnosis
        if ($tld === 'ma') {
            try {
                $logDir = __DIR__ . '/../../var';
                if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
                $logFile = $logDir . '/whois-ma.log';
                $entry = sprintf("%s\t%s\t%s\n----\n%s\n----\n", date('c'), $domain, ($server ?? 'system-whois'), substr($result ?? '', 0, 4000));
                @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
            } catch (\Throwable $e) {
                // ignore logging errors
            }
        }

        $available = $this->parseAvailability($result, $tld);

        return ['available' => $available, 'raw' => $result];
    }

    private function sanitizeName(string $name): string
    {
        $name = trim($name);
        // strip scheme or path if user pasted a URL
        $name = preg_replace('#^https?://#i', '', $name);
        $name = preg_replace('#/.*$#', '', $name);
        // keep only domain label characters
        $name = preg_replace('/[^a-z0-9\-]/i', '', $name);
        return strtolower($name);
    }

    private function queryWhoisServer(string $server, string $domain): ?string
    {
        $port = 43;
        $errno = 0;
        $errstr = '';

        $fp = @fsockopen($server, $port, $errno, $errstr, $this->timeout);
        if (!$fp) {
            return null;
        }

        stream_set_timeout($fp, $this->timeout);
        fwrite($fp, $domain . "\r\n");

        $response = '';
        while (!feof($fp)) {
            $response .= fgets($fp, 128);
        }

        fclose($fp);
        return $response;
    }

    private function runSystemWhois(string $domain): ?string
    {
        // Avoid shell injection by allowing only safe characters
        if (!preg_match('/^[a-z0-9\-\.]+$/i', $domain)) {
            return null;
        }

        $cmd = sprintf('whois %s 2>&1', escapeshellarg($domain));
        $descriptorspec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = @proc_open($cmd, $descriptorspec, $pipes);
        if (!is_resource($proc)) {
            return null;
        }

        $start = time();
        $output = '';
        while (($line = fgets($pipes[1])) !== false) {
            $output .= $line;
            if ((time() - $start) > $this->timeout) {
                // timeout: terminate
                proc_terminate($proc);
                break;
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        return $output;
    }

    private function parseAvailability(string $raw, string $tld): bool
    {
        $raw = strtolower($raw);
        // Normalize whitespace
        $norm = preg_replace('/\s+/', ' ', $raw);

        // Common phrases that indicate the domain is available
        $availableMarkers = [
            'no match for',
            'not found',
            'no entries found',
            'no data found',
            'domain not found',
            'not registered',
            'is available',
            'status: available',
            'this domain is available',
            'no whois server',
            'no match',
            'not found in database',
        ];

        // Phrases that strongly indicate the domain exists / is taken
        $takenMarkers = [
            'domain name:',
            'registrar:',
            'registry expiry date',
            'creation date:',
            'updated date:',
            'nameserver',
            'status: active',
        ];

        foreach ($availableMarkers as $m) {
            if (strpos($norm, $m) !== false) return true;
        }

        foreach ($takenMarkers as $m) {
            if (strpos($norm, $m) !== false) return false;
        }

        // Special-case: some registries return the word "available" inside a longer
        // explanatory line while also including domain metadata — be conservative
        // and only treat plain "available" as positive when no domain metadata exists.
        if (strpos($norm, 'available') !== false && strpos($norm, 'domain name:') === false && strpos($norm, 'registrar:') === false) {
            return true;
        }

        // TLD-specific heuristics (localized responses)
        $tldSpecific = [
            'ma' => [
                // French/English phrases common in .ma whois responses
                'available' => [
                    "n'existe pas",
                    "n'est pas enreg",
                    "n'est pas enregistré",
                    'non trouvé',
                    'non enregistre',
                    'not found',
                    'no match',
                    'not found in database',
                    'aucun enregistrement',
                ],
                'taken' => [
                    'date d\'enregistrement',
                    'nom de domaine',
                    'registrar',
                    'nameserver',
                    'serveur whois',
                ]
            ],
            'dev' => [
                // Google registry often uses minimal whois; look for explicit taken markers
                'available' => ['not found', 'no match', 'is available'],
                'taken' => ['domain name:', 'registrar:', 'status: active']
            ],
            'co' => [
                'available' => ['no match', 'not found', 'no entries found'],
                'taken' => ['domain name:', 'registrar:', 'registrar url', 'nameserver']
            ],
            'to' => [
                'available' => ['no match', 'not found', 'available'],
                'taken' => ['domain:', 'registrar:', 'nameserver']
            ],
        ];

        if (isset($tldSpecific[$tld])) {
            $spec = $tldSpecific[$tld];
            foreach ($spec['available'] as $m) {
                if (strpos($norm, strtolower($m)) !== false) return true;
            }
            foreach ($spec['taken'] as $m) {
                if (strpos($norm, strtolower($m)) !== false) return false;
            }
        }

        // Fallback conservative: assume taken unless evidence of availability
        return false;
    }
}
