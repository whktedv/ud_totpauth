<?php
defined('TYPO3') or die();

// Feld zur TCA hinzufügen
$tempColumns = [
    'tx_udtotpauth_disable2fa' => [
        'exclude' => true,
        'label' => 'LLL:EXT:ud_totpauth/Resources/Private/Language/locallang.xlf:fe_users.udtotpauth_disable2fa',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'items' => [
                [
                    0 => '',
                    1 => '',
                ]
            ],
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_users', $tempColumns);

// Feld zu einer Palette oder direkt zu showitem hinzufügen
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'fe_users',
    'tx_udtotpauth_disable2fa',
    '',
    'after:disable'
    );