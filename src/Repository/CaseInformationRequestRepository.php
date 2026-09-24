<?php

declare(strict_types=1);

namespace App\Casing\Repository;

use App\Casing\Entity\CaseEntity;
use App\Casing\Entity\CaseInformationRequestEntity;
use App\Casing\RepositoryInterface\CaseInformationRequestRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CaseInformationRequestEntity> */
final class CaseInformationRequestRepository extends ServiceEntityRepository implements CaseInformationRequestRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CaseInformationRequestEntity::class);
    }

    public function findOpenForCase(CaseEntity $case): ?CaseInformationRequestEntity
    {
        $request = $this->createQueryBuilder('informationRequest')
            ->andWhere('informationRequest.case = :case')
            ->andWhere('informationRequest.answeredAt IS NULL')
            ->setParameter('case', $case)
            ->orderBy('informationRequest.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $request instanceof CaseInformationRequestEntity ? $request : null;
    }

    public function save(CaseInformationRequestEntity $request, bool $flush = true): void
    {
        $this->getEntityManager()->persist($request);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
