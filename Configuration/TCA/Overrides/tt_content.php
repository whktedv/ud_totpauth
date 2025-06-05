<?php
defined('TYPO3') or die();

// Register plugin
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'UdTotpauth',
    'TotpSetup',
    'TOTP Two-Factor Authentication',
    'EXT:ud_totpauth/Resources/Public/Icons/Extension.svg'
);

// Flexform für Plugin-Konfiguration (optional)
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['udtotpauth_totpsetup'] = 'pi_flexform';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
    'udtotpauth_totpsetup',
    'FILE:EXT:ud_totpauth/Configuration/FlexForms/TotpSetup.xml'
);
