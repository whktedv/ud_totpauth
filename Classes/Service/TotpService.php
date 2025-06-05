<?php 
namespace Ud\UdTotpauth\Service;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Ud\UdTotpauth\Domain\Repository\TotpSecretRepository;

class TotpService
{
    protected $secretLength = 16;
    protected $timeStep = 30;
    protected $digits = 6;
    
    /**
     * @var TotpSecretRepository
     */
    protected $totpSecretRepository;
    
    /**
     * @param TotpSecretRepository $totpSecretRepository
     */
    public function __construct(TotpSecretRepository $totpSecretRepository)
    {
        $this->totpSecretRepository = $totpSecretRepository;
    }
    
    public function generateSecret(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        $random = random_bytes($this->secretLength);
        for ($i = 0; $i < $this->secretLength; $i++) {
            $secret .= $chars[ord($random[$i]) & 31];
        }
        return $secret;
    }
    
    public function getQrCodeUrl(string $secret, string $username, string $issuer = 'TYPO3 Website'): string
    {
        $issuer = rawurlencode($issuer);
        $username = rawurlencode($username);
        return 'otpauth://totp/' . $issuer . ':' . $username . '?secret=' . $secret . '&issuer=' . $issuer;
    }
    
    public function verifyCode(string $secret, string $code): bool
    {
        // Aktuelle Zeit in 30-Sekunden-Intervallen
        $timestamp = floor(time() / $this->timeStep);
        
        // Prüfe den aktuellen Code sowie die Codes für den vorherigen und nächsten Zeitraum
        for ($i = -1; $i <= 1; $i++) {
            $calculatedCode = $this->generateCode($secret, $timestamp + $i);
            if ($this->timingSafeEquals($calculatedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    protected function generateCode(string $secret, int $timestamp): string
    {
        $secret = $this->base32Decode($secret);
        
        // Konvertiere Timestamp zu einem binären String mit Padding
        $timestamp = pack('N*', 0, $timestamp);
        
        // Generiere HMAC-SHA1 des Timestamps mit dem Secret als Schlüssel
        $hash = hash_hmac('sha1', $timestamp, $secret, true);
        
        // Nehme den letzten Nibble des Hashes als Offset
        $offset = ord($hash[19]) & 0x0F;
        
        // Extrahiere 4 Bytes ab dem Offset
        $value = unpack('N', substr($hash, $offset, 4))[1];
        
        // Generiere nur Digits-Anzahl an Stellen (Standard: 6)
        $value = $value & 0x7FFFFFFF;
        $modulo = pow(10, $this->digits);
        
        return str_pad($value % $modulo, $this->digits, '0', STR_PAD_LEFT);
    }
    
    protected function base32Decode(string $secret): string
    {
        $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $n = 0;
        $j = 0;
        $binary = '';
        
        for ($i = 0; $i < strlen($secret); $i++) {
            $n = $n << 5;
            $n = $n + strpos($base32Chars, $secret[$i]);
            $j += 5;
            
            if ($j >= 8) {
                $j -= 8;
                $binary .= chr(($n & (0xFF << $j)) >> $j);
            }
        }
        
        return $binary;
    }
    
    protected function timingSafeEquals(string $a, string $b): bool
    {
        if (function_exists('hash_equals')) {
            return hash_equals($a, $b);
        }
        
        if (strlen($a) !== strlen($b)) {
            return false;
        }
        
        $result = 0;
        for ($i = 0; $i < strlen($a); $i++) {
            $result |= ord($a[$i]) ^ ord($b[$i]);
        }
        
        return $result === 0;
    }
    
    /**
     * Check if a user has TOTP enabled
     *
     * @param int $feUserId
     * @return bool
     */
    public function isTotpEnabledForUser(int $feUserId): bool
    {
        $secret = $this->totpSecretRepository->findActiveByFeUserId($feUserId);        
        return $secret !== null;
    }
}