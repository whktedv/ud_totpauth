<?php
namespace Ud\UdTotpauth\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Domain\Model\FrontendUser as BaseFrontendUser;

class FrontendUser extends BaseFrontendUser
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