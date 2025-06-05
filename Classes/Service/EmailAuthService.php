<?php 
namespace Ud\UdTotpauth\Service;

use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class EmailAuthService
{
    /**
     * @var ConnectionPool
     */
    protected $connectionPool;

    /**
     * @param ConnectionPool $connectionPool
     */
    public function __construct(ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    /**
     * Generiert einen eindeutigen Token und speichert ihn in der Datenbank
     *
     * @param int $userId
     * @param int $validityInMinutes
     * @return string Der generierte Token
     */
    public function generateEmailToken(int $userId, int $validityInMinutes = 15): string
    {
        // Token generieren (32 Zeichen)
        $token = GeneralUtility::makeInstance(Random::class)->generateRandomHexString(32);
        
        // Gültigkeitszeitraum berechnen
        $validUntil = time() + ($validityInMinutes * 60);
        
        // Prüfen, ob bereits ein Token existiert
        $connection = $this->connectionPool->getConnectionForTable('tx_udtotpauth_domain_model_emailtoken');
        $existingToken = $connection->select(
            ['uid'],
            'tx_udtotpauth_domain_model_emailtoken',
            ['fe_user' => $userId]
        )->fetchOne();
        
        // Daten für DB
        $tokenData = [
            'token' => $token,
            'valid_until' => $validUntil,
            'tstamp' => time()
        ];
        
        if ($existingToken) {
            // Token aktualisieren
            $connection->update(
                'tx_udtotpauth_domain_model_emailtoken',
                $tokenData,
                ['fe_user' => $userId]
            );
        } else {
            // Neuen Token erstellen
            $tokenData['fe_user'] = $userId;
            $tokenData['crdate'] = time();
            $tokenData['pid'] = 0; // Standard-PID, ggf. anpassen
            
            $connection->insert(
                'tx_udtotpauth_domain_model_emailtoken',
                $tokenData
            );
        }
        
        return $token;
    }

    /**
     * Überprüft, ob ein Token gültig ist
     *
     * @param int $userId
     * @param string $token
     * @return bool
     */
    public function validateToken(int $userId, string $token): bool
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_udtotpauth_domain_model_emailtoken');
        
        $tokenRecord = $connection->select(
            ['token', 'valid_until'],
            'tx_udtotpauth_domain_model_emailtoken',
            [
                'fe_user' => $userId,
                'token' => $token
            ]
        )->fetchAssociative();
        
        if (!$tokenRecord) {
            return false;
        }
        
        // Prüfen, ob der Token noch gültig ist
        if ((int)$tokenRecord['valid_until'] < time()) {
            return false;
        }
        
        // Token löschen nach erfolgreicher Validierung
        $connection->delete(
            'tx_udtotpauth_domain_model_emailtoken',
            ['fe_user' => $userId]
        );
        
        return true;
    }

    /**
     * Sendet eine E-Mail mit dem Bestätigungslink an den Benutzer
     *
     * @param array $user Benutzerinformationen
     * @param string $token Der generierte Token
     * @param int $pageUid ID der Bestätigungsseite
     * @return bool
     */
    public function sendVerificationEmail(array $user, string $token, int $pageUid, string $emailVerifySender, string $emailVerifyName, string $applicationName, $extbaseRequest): bool
    {       
        if (empty($user['email'])) {
            return false;
        }
        
        // Bestätigungs-URL generieren
        $verifyUrl = $this->generateVerificationUrl($token, $user['uid'], $pageUid);

        // E-Mail vorbereiten
        /** @var MailMessage $mail */
        $mail = GeneralUtility::makeInstance(MailMessage::class);
        $mail->setFrom(
            $emailVerifySender,
            $emailVerifyName
        );
        
        $mail->setTo([$user['email'] => $user['username']]);
        $mail->setSubject(sprintf('%s Login-Bestätigung', $applicationName));
        
        $variables = array(
            'firstname' => $user['first_name'],
            'lastname' => $user['last_name'],
            'company' => $user['company'],
            'applicationName' => $applicationName,
            'verificationurl' => $verifyUrl
        );
        
        $relativePath = 'EXT:ud_totpauth/Resources/Private/Templates/';
        $absolutePath = GeneralUtility::getFileAbsFileName($relativePath);
        $templatePathAndFilename = $absolutePath . 'Mail/Mailtoverify.html';
        
        $emailview = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(StandaloneView::class);
        $emailview->setRequest($extbaseRequest);        
        $emailview->setTemplatePathAndFilename($templatePathAndFilename);
        $emailview->assignMultiple($variables);
        $emailBody = $emailview->render();
        
        $mail->html($emailBody);

        // E-Mail senden        
        return $mail->send() > 0;        
    }

    /**
     * Generiert die Bestätigungs-URL
     *
     * @param string $token
     * @param int $userId
     * @param int $pageUid
     * @return string
     */
    protected function generateVerificationUrl(string $token, int $userId, int $pageUid): string
    {
        // ContentObjectRenderer für typoLink_URL verwenden
        $contentObject = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
        
        // Parameter für die URL vorbereiten
        $arguments = [
            'tx_udtotpauth_totpsetup' => [
                'action' => 'verifyEmail',
                'token' => $token,
                'user' => $userId
            ]
        ];
        
        // URL generieren mit typoLink_URL
        return $contentObject->typoLink_URL([
            'parameter' => $pageUid,
            'additionalParams' => '&' . GeneralUtility::implodeArrayForUrl('', $arguments),
            'forceAbsoluteUrl' => true
        ]);
    }
}