<?php 
namespace Ud\UdTotpauth\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

class TotpSecretRepository extends Repository
{
    /**
     * Find an active TOTP secret by frontend user uid
     *
     * @param int $feUserId
     * @return object|null
     */
    public function findActiveByFeUserId(int $feUserId)
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('feUser', $feUserId),
                $query->equals('isActive', true)
            )
        );
        $query->setLimit(1);
        
        return $query->execute()->getFirst();
    }
}