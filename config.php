<?php
return [
    // Minimal mapping of common TLDs to whois servers.
    'whois_servers' => [
        'com' => 'whois.verisign-grs.com',
        'net' => 'whois.verisign-grs.com',
        'org' => 'whois.pir.org',
        'io'  => 'whois.nic.io',
        'dev' => 'whois.nic.google',
        'app' => 'whois.nic.google',
        'ai'  => 'whois.nic.ai',
        'co'  => 'whois.nic.co',
        'me'  => 'whois.nic.me',
        'tech'=> 'whois.nic.tech',
        'xyz' => 'whois.nic.xyz',
        'online' => 'whois.nic.online',
        'site' => 'whois.nic.site',
        'shop' => 'whois.nic.shop',
    // Morocco (.ma) whois server — verify with your environment if needed
    'ma'  => 'whois.nic.ma',
    // Tonga (.to)
    'to'  => 'whois.nic.to',
    ],

    // Known TLDs presented in the UI by default.
    'known_tlds' => ['com','net','org','io','app','ai','me','tech','xyz','online','site','shop','to'],

    // Timeout (seconds) for network whois lookups.
    'check_timeout' => 3,
];
