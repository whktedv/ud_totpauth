<?php

namespace Ud\UdTotpauth\EventListener;

use TYPO3\CMS\FrontendLogin\Event\LoginConfirmedEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Ud\UdTotpauth\Service\TotpService;
use Ud\UdTotpauth\Service\EmailAuthService;
use TYPO3\CMS\FrontendLogin\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use Psr\Http\Message\ServerRequestInterface;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Service\FlexFormService;

final class AuthEventListener
{    
    public function __construct(
        private readonly UriBuilder $uriBuilder,
        ) {}
        
    
    public function __invoke(LoginConfirmedEvent $event): void
    {
        //\TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($redirectPid);
        //die;
            
        $configurationManager = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Configuration\ConfigurationManager::class);
        $settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'UdTotpauth', // Dein Extension-Key
            'totpsetup'
        );
        
        $verifyPageId = $settings['verifyPageId'] ?? 0;
        $emailVerifyPageId = $settings['emailVerifyPageId'];
        $emailWaitPageId = $settings['emailWaitPageId'];
        $emailVerifySender = $settings['emailVerifySender'];
        $emailVerifyName = $settings['emailVerifyName'];
        $applicationName = $settings['applicationName'];
        $totpmandatory = $settings['mandatory'];
        
        
        // redirectPid aus Gruppendatensatz des Users auslesen
        $frontendUser = $GLOBALS['TSFE']->fe_user->user;
        
        $groupIds = explode(',', $frontendUser['usergroup']);
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('fe_groups');
        $queryBuilder = $connection->createQueryBuilder();
        $groups = $queryBuilder
        ->select('uid', 'felogin_redirectPid')
        ->from('fe_groups')
        ->where(
            $queryBuilder->expr()->LIKE('uid', $groupIds[0])
            )
            ->executeQuery()
            ->fetchAllAssociative();
            $redirectPid = (int)$groups[0]['felogin_redirectPid'];
            // Wenn im Gruppendatensatz keine redirectPid gespeichert ist, dann aus dem felogin-Datensatz auslesen
            if($redirectPid == 0){
                $pageId = (int)($GLOBALS['TSFE']->id);
                
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('tt_content')
                ->createQueryBuilder();
                
                $row = $queryBuilder
                ->select('pi_flexform')
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT)),
                    $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('felogin_login')),
                    )
                    ->setMaxResults(1)
                    ->executeQuery()
                    ->fetchAssociative();
                    
                    $redirectPid = null;
                    if (!empty($row['pi_flexform'])) {
                        $flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
                        $settings = $flexFormService->convertFlexFormContentToArray($row['pi_flexform']);
                        if (!empty($settings['settings']['redirectPageLogin'])) {
                            $redirectPid = (int)$settings['settings']['redirectPageLogin'];
                        }
                    }                    
            }
            

        if($verifyPageId != 0) {
            $userId = $frontendUser['uid'];
                        
            $totpService = GeneralUtility::makeInstance(TotpService::class);
            $emailService = GeneralUtility::makeInstance(EmailAuthService::class);
            
            /** @var ServerRequestInterface $request */
            $request = $GLOBALS['TYPO3_REQUEST'];
            $extbaseRequest = new Request(
                $request->withAttribute('extbase', new ExtbaseRequestParameters())
                );
            
            // Speichere die Original-Url in der PHP-Session
            session_start();
            if($redirectPid == 0 || $redirectPid == null) {
                // Fallback, wenn redirectpid nicht ausgelesen werden kann
                $_SESSION['original_url'] = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
            } else {
     
                $this->uriBuilder->setRequest($extbaseRequest);
                $url = $this->uriBuilder
                ->reset()
                ->setTargetPageUid($redirectPid)
                ->setCreateAbsoluteUri(true)
                ->buildFrontendUri();
                $_SESSION['original_url'] = $url;
            }
            
            // Check if TOTP is enabled for this user
            if ($totpService->isTotpEnabledForUser($userId)) {
                // Wenn eine Seiten-ID konfiguriert ist, leite weiter
                if ($verifyPageId > 0) {
                    $url = $this->getTypoLinkUrl($verifyPageId, $userId);
    
                    /** @var FrontendUserAuthentication $feUser */
                    $feUser = $GLOBALS['TSFE']->fe_user;
                    $feUser->logoff();
    
                    header('Location: ' . $url);
                    exit;
                }
            } elseif($totpmandatory) {
                // TOTP nicht eingerichtet, E-Mail-Bestätigung starten            
                $token = $emailService->generateEmailToken($userId);
                
                $emailSent = $emailService->sendVerificationEmail(
                    $GLOBALS['TSFE']->fe_user->user,
                    $token,
                    $emailVerifyPageId,
                    $emailVerifySender,
                    $emailVerifyName,
                    $applicationName,
                    $request
                    );
                
                if ($emailSent) {
                    // Zur Wartenseite umleiten
                    if (!empty($emailWaitPageId)) {
                        $url = $this->getTypoLinkUrl($emailWaitPageId, $userId);
    
                        /** @var FrontendUserAuthentication $feUser */
                        $feUser = $GLOBALS['TSFE']->fe_user;
                        $feUser->logoff();
    
                        header('Location: ' . $url);
                        exit;
                    }
                } else {
                    // Fehler beim E-Mail-Versand
                    // In einem echten Szenario würdest du hier einen Fallback
                    // oder eine Fehlermeldung implementieren
                }            
            } else {
                // 2FA ist nicht obligatorisch, hier also nichts tun
            }
        }       
    }
    
    /**
     * Get a URL from a page ID using typolink
     *
     * @param int $pageId
     * @return string
     */
    protected function getTypoLinkUrl(int $pageId, int $userId): string
    {
        $contentObject = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
        return $contentObject->typoLink_URL([
            'parameter' => $pageId,
            'additionalParams' => '&tx_udtotpauth_verification[uid]=' . $userId,
            'forceAbsoluteUrl' => true
        ]);
    }
        
}