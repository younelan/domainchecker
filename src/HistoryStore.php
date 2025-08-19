<?php

namespace DomainCheck\Src;

class HistoryStore
{
    private const SESSION_KEY = 'domaincheck_history';
    private int $max = 50;

    public function __construct(int $max = 50)
    {
        $this->max = $max;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }

    public function save(array $entry): void
    {
        // keep only essential fields: query, timestamp, available (sample)
        $entry = [
            'query' => substr((string)($entry['query'] ?? ''), 0, 200),
            'when' => time(),
            'available' => $entry['available'] ?? [],
        ];

        array_unshift($_SESSION[self::SESSION_KEY], $entry);
        $_SESSION[self::SESSION_KEY] = array_slice($_SESSION[self::SESSION_KEY], 0, $this->max);
    }

    public function all(): array
    {
        return $_SESSION[self::SESSION_KEY] ?? [];
    }
}
