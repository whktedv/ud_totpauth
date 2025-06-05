<?php 
namespace Ud\UdTotpauth\Hooks;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use Ud\UdTotpauth\Service\TotpService;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

class LoginFormHook
{
    /**
     * @var TotpService
     */
    protected $totpService;
    
    /**
     * @param TotpService $totpService
     */
    public function __construct(TotpService $totpService)
    {
        $this->totpService = $totpService;
    }
    
    /**
     * Hook executed after a successful login
     *
     * @param array $params
     * @param object $pObj
     * @return void
     */
    public function afterLogin(array $params, $pObj)
    {
        // Check if user is logged in
        if (!isset($GLOBALS['TSFE']->fe_user->user['uid'])) {
            return;
        }
        
        $userId = $GLOBALS['TSFE']->fe_user->user['uid'];
        
        // Store the original request URL for later redirect
        $originalUrl = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
        $GLOBALS['TSFE']->fe_user->setKey('ses', 'original_request_url', $originalUrl);
        $GLOBALS['TSFE']->fe_user->storeSessionData();
        
       
        // Check if TOTP is enabled for this user
        if ($this->totpService->isTotpEnabledForUser($userId)) {
            // Redirect to TOTP verification page
            $verifyPage = $this->getVerifyPageId();
            if (!empty($verifyPage)) {
                $url = $this->getTypoLinkUrl($verifyPage);
                header('Location: ' . $url);
                exit;
            }
        } else {
            // TOTP nicht eingerichtet, E-Mail-Bestätigung starten
            $emailService = GeneralUtility::makeInstance(\Ud\UdTotpAuth\Service\EmailAuthService::class);
            $token = $emailService->generateEmailToken($userId);
            
            $emailSent = $emailService->sendVerificationEmail(
                $GLOBALS['TSFE']->fe_user->user,
                $token,
                $this->getEmailVerifyPageId()
                );
            
            if ($emailSent) {
                // Zur Wartenseite umleiten
                $waitPage = $this->getEmailWaitPageId();
                if (!empty($waitPage)) {
                    $url = $this->getTypoLinkUrl($waitPage);
                    header('Location: ' . $url);
                    exit;
                }
            } else {
                // Fehler beim E-Mail-Versand
                // In einem echten Szenario würdest du hier einen Fallback
                // oder eine Fehlermeldung implementieren
            }
        }
    }
    
    /**
     * Gibt die ID der TOTP-Verifizierungsseite zurück
     *
     * @return int
     */
    protected function getVerifyPageId(): int
    {
        $settings = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_udtotpauth_totpsetup.']['settings.'] ?? [];
        return (int)($settings['verifyPageId'] ?? $GLOBALS['TYPO3_CONF_VARS']['FE']['totp_auth_verify_page'] ?? 0);
    }
    
    /**
     * Gibt die ID der E-Mail-Wartenseite zurück
     *
     * @return int
     */
    protected function getEmailWaitPageId(): int
    {
        $settings = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_udtotpauth_totpsetup.']['settings.'] ?? [];
        return (int)($settings['emailWaitPageId'] ?? $GLOBALS['TYPO3_CONF_VARS']['FE']['totp_auth_email_wait_page'] ?? 0);
    }
    
    /**
     * Gibt die ID der E-Mail-Verifizierungsseite zurück
     *
     * @return int
     */
    protected function getEmailVerifyPageId(): int
    {
        $settings = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_udtotpauth_totpsetup.']['settings.'] ?? [];
        return (int)($settings['emailVerifyPageId'] ?? $GLOBALS['TYPO3_CONF_VARS']['FE']['totp_auth_email_verify_page'] ?? 0);
    }
    
    
    /**
     * Get a URL from a page ID using typolink
     *
     * @param int $pageId
     * @return string
     */
    protected function getTypoLinkUrl(int $pageId): string
    {
        $contentObject = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
        return $contentObject->typoLink_URL([
            'parameter' => $pageId,
            'forceAbsoluteUrl' => true
        ]);
    }
}