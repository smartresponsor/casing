<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Contract\PurchasedProductSubjectResolverInterface;
use App\Casing\Entity\CaseDraftEntity;
use App\Casing\Integration\Ordering\OrderingPurchasedProductSubjectResolver;
use App\Casing\Service\ProductReturnIntakeService;
use App\Casing\Value\PurchasedProductSubject;
use App\Ordering\Entity\Order\OrderEntity;
use App\Ordering\Entity\Order\OrderItemEntity;
use App\Ordering\ReadModel\Repository\OrderReadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class ProductReturnIntakeTest extends TestCase
{
    public function testOrderingResolverDoesNotLeakAnotherCustomersOrder(): void
    {
        $order = new OrderEntity('ORD-TEST-1', 1999, 'USD', 'actor-1');
        $order->addItem(new OrderItemEntity('SKU-RETURN-1', 2, '19.99', 'USD'));

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturnCallback(
            static fn (array $criteria): array => ['customerId' => 'actor-1'] === $criteria ? [$order] : [],
        );
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $resolver = new OrderingPurchasedProductSubjectResolver(new OrderReadRepository($entityManager));

        self::assertCount(1, $resolver->listForActor('actor-1'));
        self::assertSame([], $resolver->listForActor('actor-2'));
        self::assertCount(1, $resolver->listForActorOrder('actor-1', 'ORD-TEST-1'));
        self::assertSame([], $resolver->listForActorOrder('actor-2', 'ORD-TEST-1'));
        self::assertNotNull($resolver->resolve('actor-1', 'ORD-TEST-1', 'SKU-RETURN-1'));
        self::assertNull($resolver->resolve('actor-2', 'ORD-TEST-1', 'SKU-RETURN-1'));
        self::assertNull($resolver->resolve('actor-1', 'ORD-TEST-1', 'SKU-OTHER'));
    }

    public function testVerifiedSubjectAndCustomerClaimRemainSeparate(): void
    {
        $resolver = new class implements PurchasedProductSubjectResolverInterface {
            public function listForActor(string $actorId): array
            {
                $subject = $this->resolve($actorId, 'ORD-TEST-1', 'SKU-RETURN-1');

                return null === $subject ? [] : [$subject];
            }

            public function listForActorOrder(string $actorId, string $orderReference): array
            {
                return array_values(array_filter($this->listForActor($actorId), static fn (PurchasedProductSubject $subject): bool => $subject->orderReference === $orderReference || $subject->orderNumber === $orderReference));
            }

            public function resolve(string $actorId, string $orderReference, string $itemReference): ?PurchasedProductSubject
            {
                if ('actor-1' !== $actorId) {
                    return null;
                }

                return new PurchasedProductSubject(
                    orderReference: 'order-slug-1',
                    orderNumber: 'ORD-TEST-1',
                    itemReference: 'SKU-RETURN-1',
                    quantity: 2,
                    currency: 'USD',
                    unitPrice: '19.99',
                    orderStatus: 'delivered',
                );
            }
        };
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('persist');
        $entityManager->expects(self::exactly(2))->method('flush');
        $service = new ProductReturnIntakeService($resolver, $entityManager);
        $draft = new CaseDraftEntity('actor-1', 'products');

        $service->associatePurchasedProduct($draft, 'ORD-TEST-1', 'SKU-RETURN-1');
        $service->recordCustomerClaim($draft, 'damaged', 'Arrived damaged.', 1);

        self::assertSame([
            ['component' => 'ordering', 'type' => 'order', 'id' => 'order-slug-1'],
            ['component' => 'ordering', 'type' => 'order-item', 'id' => 'SKU-RETURN-1'],
        ], $draft->getSubjectReferences());
        self::assertSame('ORD-TEST-1', $draft->getContributionData()['ordering.return_subject']['orderNumber']);
        self::assertSame([
            'catalogCode' => 'products',
            'categoryPath' => 'products.return',
            'typeCode' => 'damaged',
        ], $draft->getContributionData()['cataloging.support_type']);
        self::assertSame(['reason' => 'Arrived damaged.', 'quantity' => 1], $draft->getSuppliedFacts()['return']);
        self::assertSame('review', $draft->getCurrentStep());
    }

    public function testProductReturnFormRejectsUnknownCatalogType(): void
    {
        $subject = new PurchasedProductSubject('order-1', 'ORD-1', 'SKU-1', 1, 'USD', '25.00', 'delivered');
        $data = new \App\Casing\Dto\ProductReturnClaimData();
        $form = \Symfony\Component\Form\Forms::createFormFactory()->create(\App\Casing\Form\ProductReturnClaimType::class, $data, [
            'subjects' => [$subject],
            'types' => [['code' => 'damaged', 'label' => 'Damaged']],
        ]);
        $form->submit([
            'subject' => hash('sha256', $subject->orderReference."\0".$subject->itemReference),
            'typeCode' => 'invented',
            'reason' => 'Tampered return type.',
            'quantity' => 1,
        ]);

        self::assertFalse($form->isValid());
    }
}
