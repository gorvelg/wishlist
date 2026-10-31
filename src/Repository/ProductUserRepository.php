<?php

namespace App\Repository;

use App\Entity\ProductUser;
use App\Enum\ProductStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductUser>
 */
class ProductUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductUser::class);
    }

    public function findReservationsToRemind(
        \DateTimeImmutable $now
    ): array {
        return $this
            ->createQueryBuilder('productUser')
            ->join(
                'productUser.product',
                'product'
            )
            ->andWhere(
                'product.status = :status'
            )
            ->andWhere(
                'productUser.reservationReminderAt <= :now'
            )
            ->andWhere(
                'productUser.isReminded = :isReminded'
            )
            ->setParameter(
                'status',
                ProductStatus::RESERVED
            )
            ->setParameter(
                'now',
                $now
            )
            ->setParameter(
                'isReminded',
                false
            )
            ->getQuery()
            ->getResult();
    }

}
