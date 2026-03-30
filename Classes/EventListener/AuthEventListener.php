<?php

namespace Ud\UdTotpauth\EventListener;

use TYPO3\CMS\FrontendLogin\Event\LoginConfirmedEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Site\SiteFinder;

use Ud\UdTotpauth\Service\TotpService;
use Ud\UdTotpauth\Service\EmailAuthService;

final class AuthEventListener
{    
    public function __construct(
        private readonly SiteFinder $siteFinder,
    ) {}
    
    public function __invoke(LoginConfirmedEvent $event): void
    {            
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
        $validtime = $settings['emailWaitTime'];
        
        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];

        // redirectPid aus Gruppendatensatz des Users auslesen
        $frontendUser = $request->getAttribute('frontend.user');

        $groupIds = explode(',', $frontendUser->user['usergroup']);
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
                $pageArguments = $request->getAttribute('routing');
                $pageId = $pageArguments->getPageId();

                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('tt_content')
                ->createQueryBuilder();
                
                $row = $queryBuilder
                ->select('pi_flexform')
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, \Doctrine\DBAL\ParameterType::INTEGER)),
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
            $userId = $frontendUser->user['uid'];
                        
            $totpService = GeneralUtility::makeInstance(TotpService::class);
            $emailService = GeneralUtility::makeInstance(EmailAuthService::class);
            
            // Speichere die Original-Url in der PHP-Session
            session_start();
            if($redirectPid == 0 || $redirectPid == null) {
                // Fallback, wenn redirectpid nicht ausgelesen werden kann
                $_SESSION['original_url'] = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
            } else {
                $_SESSION['original_url'] = $this->buildPageUrl((int)$redirectPid);
            }

            // Check if TOTP is enabled for this user
            if ($totpService->isTotpEnabledForUser($userId)) {
                // Wenn eine Seiten-ID konfiguriert ist, leite weiter
                if ($verifyPageId > 0) {
                    $site = $this->siteFinder->getSiteByPageId($verifyPageId);
                    $url = (string)$site->getRouter()->generateUri(
                        $verifyPageId,
                        ['tx_udtotpauth_verification' => ['uid' => $userId]]
                    );
                    $frontendUser->logoff();
    
                    header('Location: ' . $url);
                    exit;
                }
            } elseif($totpmandatory && $frontendUser->user['tx_udtotpauth_disable2fa'] == 0) {
                // TOTP nicht eingerichtet, E-Mail-Bestätigung starten            
                $token = $emailService->generateEmailToken($userId, $validtime);
                
                $emailSent = $emailService->sendVerificationEmail(
                    $frontendUser->user,
                    $token,
                    $validtime,
                    $emailVerifyPageId,
                    $emailVerifySender,
                    $emailVerifyName,
                    $applicationName,
                    $request
                    );
    
                if ($emailSent) {
                    // Zur Wartenseite umleiten
                    if (!empty($emailWaitPageId)) {
                        $site = $this->siteFinder->getSiteByPageId($emailWaitPageId);
                        $url = (string)$site->getRouter()->generateUri(
                            $emailWaitPageId,
                            ['tx_udtotpauth_verification' => ['uid' => $userId]]
                        );
    
                        $frontendUser->logoff();
    
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
    
    private function buildPageUrl(int $pageId): string
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
            return (string)$site->getRouter()->generateUri($pageId);
        } catch (\Exception $e) {
            return '';
        }
    }
    
}