<?php
// src/Repository/SubscriptionRepository.php
namespace App\Repository;

use App\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SubscriptionRepository extends ServiceEntityRepository  // ✅ Héritage officiel
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    // Ajoute tes méthodes : findPending(), etc.
    public function findPending(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->setParameter('status', \App\Enum\StatusEnum::PENDING)
            ->getQuery()->getResult();
    }
}
