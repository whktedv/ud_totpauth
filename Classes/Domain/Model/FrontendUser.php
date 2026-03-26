<?php
namespace Ud\UdTotpauth\Domain\Model;

class FrontendUser extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    protected bool $tx_udtotpauth_disable2fa = false;
    
    public function getDisableTwoFactorAuthentication(): bool
    {
        return $this->tx_udtotpauth_disable2fa;
    }
    
    public function setDisableTwoFactorAuthentication(bool $twoFactorAuthentication): void
    {
        $this->tx_udtotpauth_disable2fa = $twoFactorAuthentication;
    }
    
    public function isDisableTwoFactorAuthentication(): bool
    {
        return $this->tx_udtotpauth_disable2fa;
    }
}