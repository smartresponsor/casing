<?php

declare(strict_types=1);

namespace App\Casing\Repository;

use App\Casing\Entity\CaseOutboxMessageEntity;
use App\Casing\RepositoryInterface\CaseOutboxMessageRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CaseOutboxMessageEntity> */
final class CaseOutboxMessageRepository extends ServiceEntityRepository implements CaseOutboxMessageRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CaseOutboxMessageEntity::class);
    }

    /** @return list<CaseOutboxMessageEntity> */
    public function findDispatchable(int $limit, ?\DateTimeImmutable $now = null): array
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException('Outbox processing limit must be at least 1.');
        }

        $now ??= new \DateTimeImmutable();

        /** @var list<CaseOutboxMessageEntity> $messages */
        $messages = $this->createQueryBuilder('message')
            ->andWhere('message.dispatched = :dispatched')
            ->andWhere('(message.availableAt IS NULL OR message.availableAt <= :now)')
            ->setParameter('dispatched', false)
            ->setParameter('now', $now)
            ->orderBy('message.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $messages;
    }
}
