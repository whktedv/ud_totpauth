<?php
namespace Ud\UdTotpauth\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class TotpSecret extends AbstractEntity
{
    /**
     * @var int
     */
    protected $feUser = 0;

    /**
     * @var string
     */
    protected $secret = '';

    /**
     * @var bool
     */
    protected $isActive = false;

    /**
     * @var \DateTime
     */
    protected $lastUsedAt = null;

    /**
     * @return int
     */
    public function getFeUser(): int
    {
        return $this->feUser;
    }

    /**
     * @param int $feUser
     */
    public function setFeUser(int $feUser): void
    {
        $this->feUser = $feUser;
    }

    /**
     * @return string
     */
    public function getSecret(): string
    {
        return $this->secret;
    }

    /**
     * @param string $secret
     */
    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }

    /**
     * @return bool
     */
    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @param bool $isActive
     */
    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    /**
     * @return \DateTime
     */
    public function getLastUsedAt(): ?\DateTime
    {
        return $this->lastUsedAt;
    }

    /**
     * @param \DateTime $lastUsedAt
     */
    public function setLastUsedAt(?\DateTime $lastUsedAt): void
    {
        $this->lastUsedAt = $lastUsedAt;
    }
}