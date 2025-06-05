<?php
// ext_emconf.php
$EM_CONF[$_EXTKEY] = [
    'title' => 'TOTP Authentication',
    'description' => 'Two-factor authentication with TOTP for frontend users - created with help from Claude AI 3.7 Sonnet',
    'category' => 'fe',
    'author' => 'UD',
    'author_email' => 'edv@whkt.de',
    'state' => 'stable',
    'version' => '1.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-12.4.99',
            'extbase' => '12.4.0-12.4.99',
            'fluid' => '12.4.0-12.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];