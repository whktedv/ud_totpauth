<?php 
namespace Ud\UdTotpauth\Controller;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Context\Context;

use Ud\UdTotpauth\Domain\Model\TotpSecret;
use Ud\UdTotpauth\Domain\Repository\TotpSecretRepository;
use Ud\UdTotpauth\Service\TotpService;
use Ud\UdTotpauth\Service\EmailAuthService;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Http\ForwardResponse;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class AuthenticationController extends ActionController
{
    /**
     * @var TotpSecretRepository
     */
    protected $totpSecretRepository;

    /**
     * @var TotpService
     */
    protected $totpService;

    /**
     * @var EmailAuthService
     */
    protected $emailAuthService;
    
    /**
     * @param TotpSecretRepository $totpSecretRepository
     * @param TotpService $totpService
     * @param EmailAuthService
     */
    public function __construct(
        TotpSecretRepository $totpSecretRepository,
        TotpService $totpService,
        EmailAuthService $emailAuthService
    ) {
        $this->totpSecretRepository = $totpSecretRepository;
        $this->totpService = $totpService;
        $this->emailAuthService = $emailAuthService;
    }

    /**
     * Show QR code action
     */
    public function showQrCodeAction(): ResponseInterface
    {
        if ($this->settings['action'] == 'verifyTotp') {
            return (new ForwardResponse('verifyTotp'))->withControllerName('Authentication');
        } elseif ($this->settings['action'] == 'emailVerificationRequired') {
            return (new ForwardResponse('emailVerificationRequired'))->withControllerName('Authentication');
        } else {
            $feUser = $GLOBALS['TSFE']->fe_user->user;
            
            if($feUser != NULL) {
                
                $totpSecret = $this->totpSecretRepository->findActiveByFeUserId($feUser['uid']);
                
                if ($totpSecret != null) {
                    // 2FA schon aktiv, über alreadyactive-Seite kann auf 2FA per E-Mail umgestellt werden
                    return (new ForwardResponse('alreadyactive'))->withControllerName('Authentication')->withArguments(['userId' => $feUser['uid']]); 
                }
                
                // Generate a new TOTP secret
                $secret = $this->totpService->generateSecret();
                
                // Get QR code URL
                $qrCodeUrl = $this->totpService->getQrCodeUrl(
                    $secret,
                    $feUser['username'],
                    $this->settings['applicationName']
                    );
                
                $this->view->assign('secret', $secret);
                $this->view->assign('qrCodeUrl', $qrCodeUrl);
                return $this->htmlResponse();
            } else {
                return $this->redirectToUri('/');
            }
        }
    }

    /**
     * Store the TOTP secret
     *
     * @param string $secret
     * @param string $verificationCode
     * @return void
     */
    public function storeSecretAction(string $secret, string $verificationCode): ResponseInterface
    {
        $feUser = $GLOBALS['TSFE']->fe_user->user;
        
        // Verify the TOTP code first
        if (!$this->totpService->verifyCode($secret, $verificationCode)) {
            $this->addFlashMessage('Der Verifizierungscode ist ungültig. Bitte erneut versuchen.', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
            return $this->redirect('showQrCode');
        }
        
        // First check if user already has a TOTP secret
        $existingSecret = $this->totpSecretRepository->findActiveByFeUserId($feUser['uid']);
        
        if ($existingSecret !== null) {
            // Update existing secret
            $existingSecret->setSecret($secret);
            $existingSecret->setLastUsedAt(new \DateTime());
            $this->totpSecretRepository->update($existingSecret);
        } else {
            // Create new TOTP secret
            $totpSecret = new TotpSecret();
            $totpSecret->setFeUser($feUser['uid']);
            $totpSecret->setSecret($secret);
            $totpSecret->setIsActive(true);
            $totpSecret->setLastUsedAt(new \DateTime());
            $this->totpSecretRepository->add($totpSecret);
        }
        
        $this->addFlashMessage('Zwei-Faktor-Authentifizierung aktiviert.');
        
        session_start();
        $loginurl = $_SESSION['original_url'] ?? $this->uriBuilder->reset()
        ->setTargetPageUid($this->settings['loginPageId'])
        ->build();

        return $this->redirectToUri($loginurl);
    }

    /**
     * Verify TOTP code
     *
     * @param string $totpCode
     * @return void
     */
    public function verifyTotpAction(string $totpCode = ''): ResponseInterface
    {
        if($this->request->hasArgument('userId')) {
            $userId = $this->request->getArgument('userId');
        } else {
            $userId = $this->request->getQueryParams()['tx_udtotpauth_verification']['uid'] ?? 0;
        }
        
        // If no code provided, show the verification form
        if (empty($totpCode)) {
            $this->view->assign('userId', $userId);
            return $this->htmlResponse();
        } else {
                 
            $totpSecret = $this->totpSecretRepository->findActiveByFeUserId($userId);
            
            if ($totpSecret === null) {
                $this->addFlashMessage('Kein aktiver TOTP-Account für diesen Benutzer vorhanden, bitte erneut einloggen.', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
                return $this->redirect('verifyTotp');
            }
            
            // Verify the code
            if ($this->totpService->verifyCode($totpSecret->getSecret(), $totpCode)) {
                // Code is valid, update last used time
                $totpSecret->setLastUsedAt(new \DateTime());
                $this->totpSecretRepository->update($totpSecret);
                
                $this->logUserIn($userId);
                
                session_start();
                $loginurl = $_SESSION['original_url'] ?? $this->uriBuilder->reset()
                ->setTargetPageUid($this->settings['loginPageId'])
                ->build();
                
                return $this->redirectToUri($loginurl);
            } else {
                $this->addFlashMessage('Ungültiger Verifizierungscode. Bitte erneut versuchen.', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
                return $this->redirect('verifyTotp');
            }
        }
    }
    
    /**
     * Alreadyactive action
     */
    public function alreadyactiveAction(): ResponseInterface
    {     
        $change2fa = $this->request->hasArgument('change') ? $this->request->getArgument('change') : 0;
        $userId = $this->request->getArgument('userId');
        $existingSecret = NULL;
        
        if($change2fa == 1) {
            $existingSecret = $this->totpSecretRepository->findActiveByFeUserId($userId);
            
            if($existingSecret != NULL) {
                $this->totpSecretRepository->remove($existingSecret);
                $this->addFlashMessage('Beim nächsten Login erfolgt die Zwei-Faktor-Authentifizierung per E-Mail.', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::WARNING);
            }            
        } else {
            $this->addFlashMessage('Zwei-Faktor-Authentifizierung ist schon per Code eingerichtet.', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::WARNING);
        }
                
        $this->view->assign('isexistingsecret', $existingSecret != NULL);
        $this->view->assign('userId', $userId);
        return $this->htmlResponse();
    }
    
    /**
     * Zeigt eine E-Mail-Bestätigungsseite an
     *
     * @return void
     */
    public function emailVerificationRequiredAction(): ResponseInterface
    {
        return $this->htmlResponse();
    }
    
    /**
     * Verarbeitet die E-Mail-Bestätigung
     *
     * @param string $token
     * @param int $user
     * @return void
     */
    public function verifyEmailAction(string $token = '', int $user = 0): ResponseInterface
    {   
        // Sicherheitsprüfung: Benutzer-ID aus Session mit Parameter abgleichen
        $currentUserId = (int)($GLOBALS['TSFE']->fe_user->user['uid'] ?? 0);       
        $valid = true;
        
        if ($user === 0 || ($currentUserId > 0 && $currentUserId !== $user)) {
            $this->addFlashMessage(
                'Fehler: Ungültige Anfrage. Bitte melden Sie sich erneut an.',
                '',
                \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR
                );
            
            $valid = false;
        }

        // E-Mail-Token validieren
        $isValid = $this->emailAuthService->validateToken($user, $token);
        
        if (!$isValid) {
            $this->addFlashMessage(
                'Fehler: Der Bestätigungslink ist ungültig oder abgelaufen. Bitte melden Sie sich erneut an.',
                '',
                \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR
                );

            // Session löschen und zur Login-Seite umleiten
            $GLOBALS['TSFE']->fe_user->logoff();
            $valid = false;
        }
        
        if($valid) {
            $this->logUserIn($user);
            
            session_start();
            $loginurl = $_SESSION['original_url'] ?? $this->uriBuilder->reset()
            ->setTargetPageUid($this->settings['loginPageId'])
            ->build();
        }
        
        $this->view->assign('valid', $valid);
        $this->view->assign('redirecturl', $loginurl ?? '0');
        return $this->htmlResponse();
    }
    
    
    /**
     * Switchtomail action
     */
    public function switchtomailAction(): ResponseInterface
    {
        $userId = $this->request->getArgument('userId');
        
        $existingSecret = $this->totpSecretRepository->findActiveByFeUserId($userId);
        $this->totpSecretRepository->remove($existingSecret);
        
        $uriBuilder = $this->uriBuilder;
        $uri = $uriBuilder->setTargetPageUid($this->settings['loginPageId'])->build();
        return $this->redirectToUri($uri, 0, 303);
    }
    
    protected function logUserIn(int $userId): void
    {
        // Benutzerdaten aus der Datenbank abrufen
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('fe_users');           
        $user = $queryBuilder
            ->select('*')
            ->from('fe_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(intval($userId), \PDO::PARAM_INT))
                )
                ->execute()
                ->fetchAssociative();

        /** @var \TYPO3\CMS\FrontendLogin\Authentication\FrontendUserAuthentication $frontendUser */
        $frontendUser = $GLOBALS['TSFE']->fe_user;
        
        // Enforce session so we get a FE cookie. Otherwise autologin might not work :
        $frontendUser->storeSessionData();
        $frontendUser->setAndSaveSessionData('login', true);
        // Login successfull: create user session
        $sessionRecord = $frontendUser->createUserSession($user);
        $frontendUser->user = $user;
        // The login session is started.
        $frontendUser->loginSessionStarted = true;
        $frontendUser->createUserAspect();
        $aspect = GeneralUtility::makeInstance(UserAspect::class, $frontendUser);
        $context = GeneralUtility::makeInstance(Context::class);
        $context->setAspect('frontend.user', $aspect);
    }

}