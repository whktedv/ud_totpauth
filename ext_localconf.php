<?php
defined('TYPO3') or die();

(function () {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'UdTotpauth',
        'TotpSetup',
        [
            \Ud\UdTotpauth\Controller\AuthenticationController::class => 'showQrCode, storeSecret, verifyTotp, alreadyactive, emailVerificationRequired, verifyEmail, switchtomail'
        ],
        [
            \Ud\UdTotpauth\Controller\AuthenticationController::class => 'showQrCode, storeSecret, verifyTotp, alreadyactive, emailVerificationRequired, verifyEmail, switchtomail'
        ]
    );
    
})();