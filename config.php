<?php
return [
    //  - server: whois server hostname (null to use system whois fallback)
    //  - checked: whether to check this TLD by default
    //  - enabled: whether this TLD appears in the UI
    //  - collapsed: whether this TLD is shown in collapsed UI
    'tlds' => [
        'com' => ['server' => 'whois.verisign-grs.com', 'checked' => true, 'enabled' => true, 'collapsed' => true],
        'net' => ['server' => 'whois.verisign-grs.com', 'checked' => true, 'enabled' => true, 'collapsed' => false],
        'org' => ['server' => 'whois.pir.org', 'checked' => true, 'enabled' => true, 'collapsed' => false],
        'io'  => ['server' => 'whois.nic.io', 'checked' => false, 'enabled' => true, 'collapsed' => false],
        'dev' => ['server' => 'whois.nic.google', 'checked' => false, 'enabled' => false, 'collapsed' => false],
        'app' => ['server' => 'whois.nic.google', 'checked' => false, 'enabled' => false, 'collapsed' => false],
        'ai'  => ['server' => 'whois.nic.ai', 'checked' => false, 'enabled' => true, 'collapsed' => false],
        'co'  => ['server' => 'whois.nic.co', 'checked' => false, 'enabled' => true, 'collapsed' => false],
        'me'  => ['server' => 'whois.nic.me', 'checked' => false, 'enabled' => true, 'collapsed' => false],
        'tech'=> ['server' => 'whois.nic.tech', 'checked' => false, 'enabled' => true, 'collapsed' => false],
        'xyz' => ['server' => 'whois.nic.xyz', 'checked' => false, 'enabled' => true, 'collapsed' => false],
        'online' => ['server' => 'whois.nic.online', 'checked' => false, 'enabled' => true, 'collapsed' => false],
        'site' => ['server' => 'whois.nic.site', 'checked' => false, 'enabled' => true, 'collapsed' => false],
        'shop' => ['server' => 'whois.nic.shop', 'checked' => false, 'enabled' => true, 'collapsed' => false],
        'ma'  => ['server' => 'whois.nic.ma', 'checked' => false, 'enabled' => false, 'collapsed' => false],
        'to'  => ['server' => 'whois.nic.to', 'checked' => false, 'enabled' => true, 'collapsed' => false],
    ],

    // Timeout (seconds) for network whois lookups.
    'check_timeout' => 3,
];
