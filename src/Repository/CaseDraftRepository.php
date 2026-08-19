<?php

declare(strict_types=1);

namespace App\Casing\Repository;

use App\Casing\Entity\CaseDraftEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CaseDraftEntity> */
final class CaseDraftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CaseDraftEntity::class);
    }

    public function findActorDraft(string $draftReference, string $actorId): ?CaseDraftEntity
    {
        $draft = $this->findOneBy([
            'draftReference' => $draftReference,
            'actorId' => $actorId,
        ]);

        return $draft instanceof CaseDraftEntity ? $draft : null;
    }

    public function save(CaseDraftEntity $draft, bool $flush = true): void
    {
        $this->getEntityManager()->persist($draft);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CaseDraftEntity $draft, bool $flush = true): void
    {
        $this->getEntityManager()->remove($draft);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
