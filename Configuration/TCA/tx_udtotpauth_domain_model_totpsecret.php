<?php
return [
    'ctrl' => [
        'title' => 'LLL:EXT:ud_totpauth/Resources/Private/Language/locallang.xlf:tx_udtotpauth_domain_model_totpsecret',
        'label' => 'fe_user',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'fe_user',
        'iconfile' => 'EXT:ud_totpauth/Resources/Public/Icons/tx_udtotpauth_domain_model_totpsecret.svg'
    ],
    'types' => [
        '1' => ['showitem' => 'fe_user, secret, is_active, last_used_at, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access, hidden'],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'default' => 0
            ]
        ],
        'fe_user' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ud_totpauth/Resources/Private/Language/locallang.xlf:tx_udtotpauth_domain_model_totpsecret.fe_user',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_users',
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],
        'secret' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ud_totpauth/Resources/Private/Language/locallang.xlf:tx_udtotpauth_domain_model_totpsecret.secret',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,required'
            ],
        ],
        'is_active' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ud_totpauth/Resources/Private/Language/locallang.xlf:tx_udtotpauth_domain_model_totpsecret.is_active',
            'config' => [
                'type' => 'check',
                'default' => 0
            ]
        ],
        'last_used_at' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ud_totpauth/Resources/Private/Language/locallang.xlf:tx_udtotpauth_domain_model_totpsecret.last_used_at',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
                'default' => 0
            ]
        ],
    ],
];