<?php
return [
    // Merged TLD configuration. Each key is a TLD and the value contains:
    //  - server: whois server hostname (null to use system whois fallback)
    //  - checked: whether to check this TLD by default
    //  - enabled: whether this TLD appears in the UI (set false to hide)
    'tlds' => [
        'com' => ['server' => 'whois.verisign-grs.com', 'checked' => true, 'enabled' => true],
        'net' => ['server' => 'whois.verisign-grs.com', 'checked' => true, 'enabled' => true],
        'org' => ['server' => 'whois.pir.org', 'checked' => true, 'enabled' => true],
        'io'  => ['server' => 'whois.nic.io', 'checked' => false, 'enabled' => true],
        'dev' => ['server' => 'whois.nic.google', 'checked' => false, 'enabled' => false],
        'app' => ['server' => 'whois.nic.google', 'checked' => false, 'enabled' => false],
        'ai'  => ['server' => 'whois.nic.ai', 'checked' => false, 'enabled' => true],
        'co'  => ['server' => 'whois.nic.co', 'checked' => false, 'enabled' => true],
        'me'  => ['server' => 'whois.nic.me', 'checked' => false, 'enabled' => true],
        'tech'=> ['server' => 'whois.nic.tech', 'checked' => false, 'enabled' => true],
        'xyz' => ['server' => 'whois.nic.xyz', 'checked' => false, 'enabled' => true],
        'online' => ['server' => 'whois.nic.online', 'checked' => false, 'enabled' => true],
        'site' => ['server' => 'whois.nic.site', 'checked' => false, 'enabled' => true],
        'shop' => ['server' => 'whois.nic.shop', 'checked' => false, 'enabled' => true],
        'ma'  => ['server' => 'whois.nic.ma', 'checked' => false, 'enabled' => false],
        'to'  => ['server' => 'whois.nic.to', 'checked' => false, 'enabled' => true],
    ],

    // Timeout (seconds) for network whois lookups.
    'check_timeout' => 3,
];
