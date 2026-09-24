<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Entity\CaseDraftEntity;
use App\Casing\RepositoryInterface\CaseDraftRepositoryInterface;
use App\Casing\Resolver\Ordering\CasePurchasedProductSubjectResolver;
use App\Casing\ResolverInterface\Ordering\CasePurchasedProductSubjectResolverInterface;
use App\Casing\Service\CaseProductReturnIntakeService;
use App\Casing\Value\CasePurchasedProductSubject;
use App\Ordering\Entity\Order\OrderEntity;
use App\Ordering\Entity\Order\OrderItemEntity;
use App\Ordering\ReadModel\Repository\OrderReadRepository;
use App\Ordering\ReadModel\Service\CustomerOrderReadService;
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

        $resolver = new CasePurchasedProductSubjectResolver(new CustomerOrderReadService(new OrderReadRepository($entityManager)));

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
        $resolver = new class implements CasePurchasedProductSubjectResolverInterface {
            public function listForActor(string $actorId): array
            {
                $subject = $this->resolve($actorId, 'ORD-TEST-1', 'SKU-RETURN-1');

                return null === $subject ? [] : [$subject];
            }

            public function listForActorOrder(string $actorId, string $orderReference): array
            {
                return array_values(array_filter($this->listForActor($actorId), static fn (CasePurchasedProductSubject $subject): bool => $subject->orderReference === $orderReference || $subject->orderNumber === $orderReference));
            }

            public function resolve(string $actorId, string $orderReference, string $itemReference): ?CasePurchasedProductSubject
            {
                if ('actor-1' !== $actorId) {
                    return null;
                }

                return new CasePurchasedProductSubject(
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
        $drafts = $this->createMock(CaseDraftRepositoryInterface::class);
        $drafts->expects(self::exactly(2))->method('save');
        $service = new CaseProductReturnIntakeService($resolver, $drafts);
        $draft = new CaseDraftEntity('actor-1', 'products');

        $service->associatePurchasedProduct($draft, 'ORD-TEST-1', 'SKU-RETURN-1');
        $service->recordCustomerClaim($draft, 'damaged', 'Arrived damaged.', 1);

        self::assertSame([
            ['component' => 'ordering', 'type' => 'order', 'id' => 'order-slug-1'],
            ['component' => 'ordering', 'type' => 'order-item', 'id' => 'SKU-RETURN-1'],
        ], $draft->getSubjectReferences());
        self::assertSame('ORD-TEST-1', $draft->getContributionData()['ordering.return_subject']['orderNumber']);
        self::assertSame([
            'catalogCode' => 'retailing',
            'categoryPath' => 'retailing.product',
            'supportKind' => 'return',
            'typeCode' => 'damaged',
        ], $draft->getContributionData()['cataloging.support_type']);
        self::assertSame(['reason' => 'Arrived damaged.', 'quantity' => 1], $draft->getSuppliedFacts()['return']);
        self::assertSame('review', $draft->getCurrentStep());
    }

    public function testProductReturnFormRejectsUnknownCatalogType(): void
    {
        $subject = new CasePurchasedProductSubject('order-1', 'ORD-1', 'SKU-1', 1, 'USD', '25.00', 'delivered');
        $data = new \App\Casing\DTO\Claim\CaseProductReturnClaimDTO();
        $form = \Symfony\Component\Form\Forms::createFormFactory()->create(\App\Casing\Form\Claim\CaseProductReturnClaimType::class, $data, [
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

    public function testAssociationFailureDoesNotMutateOrPersistDraft(): void
    {
        $resolver = $this->createMock(CasePurchasedProductSubjectResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with('actor-1', 'ORD-MISSING', 'SKU-MISSING')
            ->willReturn(null);

        $drafts = $this->createMock(CaseDraftRepositoryInterface::class);
        $drafts->expects(self::never())->method('save');

        $draft = new CaseDraftEntity('actor-1', 'products');
        $draft->setSubjectReferences([['component' => 'relating', 'type' => 'lead', 'id' => 'lead-1']]);

        try {
            (new CaseProductReturnIntakeService($resolver, $drafts))
                ->associatePurchasedProduct($draft, 'ORD-MISSING', 'SKU-MISSING');
            self::fail('Unowned or missing purchased products must be rejected.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('could not associate', $exception->getMessage());
        }

        self::assertSame(
            [['component' => 'relating', 'type' => 'lead', 'id' => 'lead-1']],
            $draft->getSubjectReferences(),
        );
    }

    public function testAssociationReplacesOldOrderingReferencesAndPreservesOtherComponents(): void
    {
        $subject = new CasePurchasedProductSubject(
            orderReference: 'order-new',
            orderNumber: 'ORD-NEW',
            itemReference: 'SKU-NEW',
            quantity: 1,
            currency: 'USD',
            unitPrice: '10.00',
            orderStatus: 'delivered',
        );

        $resolver = $this->createMock(CasePurchasedProductSubjectResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->willReturn($subject);

        $drafts = $this->createMock(CaseDraftRepositoryInterface::class);
        $drafts->expects(self::once())->method('save');

        $draft = new CaseDraftEntity('actor-1', 'products');
        $draft->setSubjectReferences([
            ['component' => 'ordering', 'type' => 'order', 'id' => 'order-old'],
            ['component' => 'ordering', 'type' => 'order-item', 'id' => 'SKU-OLD'],
            ['component' => 'relating', 'type' => 'lead', 'id' => 'lead-1'],
        ]);

        (new CaseProductReturnIntakeService($resolver, $drafts))
            ->associatePurchasedProduct($draft, 'ORD-NEW', 'SKU-NEW');

        self::assertSame([
            ['component' => 'relating', 'type' => 'lead', 'id' => 'lead-1'],
            ['component' => 'ordering', 'type' => 'order', 'id' => 'order-new'],
            ['component' => 'ordering', 'type' => 'order-item', 'id' => 'SKU-NEW'],
        ], $draft->getSubjectReferences());
        self::assertSame('details', $draft->getCurrentStep());
    }

    public function testCustomerClaimRejectsBlankTypeWithoutPersisting(): void
    {
        $drafts = $this->createMock(CaseDraftRepositoryInterface::class);
        $drafts->expects(self::never())->method('save');

        $service = new CaseProductReturnIntakeService(
            $this->createStub(CasePurchasedProductSubjectResolverInterface::class),
            $drafts,
        );

        $this->expectException(\InvalidArgumentException::class);
        $service->recordCustomerClaim(new CaseDraftEntity('actor-1', 'products'), '   ', 'Reason');
    }

    public function testCustomerClaimRejectsBlankReasonWithoutPersisting(): void
    {
        $drafts = $this->createMock(CaseDraftRepositoryInterface::class);
        $drafts->expects(self::never())->method('save');

        $service = new CaseProductReturnIntakeService(
            $this->createStub(CasePurchasedProductSubjectResolverInterface::class),
            $drafts,
        );

        $this->expectException(\InvalidArgumentException::class);
        $service->recordCustomerClaim(new CaseDraftEntity('actor-1', 'products'), 'damaged', '   ');
    }

    public function testCustomerClaimRejectsNonPositiveQuantityWithoutPersisting(): void
    {
        $drafts = $this->createMock(CaseDraftRepositoryInterface::class);
        $drafts->expects(self::never())->method('save');

        $service = new CaseProductReturnIntakeService(
            $this->createStub(CasePurchasedProductSubjectResolverInterface::class),
            $drafts,
        );

        $this->expectException(\InvalidArgumentException::class);
        $service->recordCustomerClaim(new CaseDraftEntity('actor-1', 'products'), 'damaged', 'Reason', 0);
    }
}
