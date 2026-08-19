<?php

declare(strict_types=1);

namespace App\Casing\Repository;

use App\Casing\Entity\CaseEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CaseEntity> */
final class CaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CaseEntity::class);
    }

    public function findActorCase(string $caseReference, string $actorId): ?CaseEntity
    {
        $case = $this->findOneBy([
            'caseReference' => $caseReference,
            'actorId' => $actorId,
        ]);

        return $case instanceof CaseEntity ? $case : null;
    }

    public function save(CaseEntity $case, bool $flush = true): void
    {
        $this->getEntityManager()->persist($case);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
