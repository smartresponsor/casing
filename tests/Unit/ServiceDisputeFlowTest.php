<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Dto\ServiceDisputeClaimData;
use App\Casing\Form\ServiceDisputeClaimType;
use App\Casing\Integration\Paying\ServicePaymentSubjectResolver;
use App\Ordering\Entity\Order\OrderEntity;
use App\Ordering\ReadModel\Repository\OrderReadRepository;
use App\Paying\Entity\PaymentEntity;
use App\Paying\RepositoryInterface\PaymentRepositoryInterface;
use App\Paying\ValueObject\PaymentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;
use Symfony\Component\Uid\Uuid;

final class ServiceDisputeFlowTest extends TestCase
{
    public function testPaymentListIsScopedThroughActorOwnedOrders(): void
    {
        $order = new OrderEntity('ORD-SERVICE-1', 5000, 'USD', 'actor-1');
        $orderRepository = $this->createStub(EntityRepository::class);
        $orderRepository->method('findBy')->willReturnCallback(
            static fn (array $criteria): array => ['customerId' => 'actor-1'] === $criteria ? [$order] : [],
        );
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($orderRepository);

        $payment = new PaymentEntity(Uuid::v7(), PaymentStatus::completed, '50.00', 'USD', 'ORD-SERVICE-1');
        $payments = $this->createStub(PaymentRepositoryInterface::class);
        $payments->method('findByOrderId')->willReturnCallback(
            static fn (string $orderId): ?PaymentEntity => 'ORD-SERVICE-1' === $orderId ? $payment : null,
        );

        $resolver = new ServicePaymentSubjectResolver(new OrderReadRepository($entityManager), $payments);

        self::assertCount(1, $resolver->listForActor('actor-1'));
        self::assertSame([], $resolver->listForActor('actor-2'));
        self::assertNotNull($resolver->resolve('actor-1', $payment->slug()));
        self::assertNull($resolver->resolve('actor-2', $payment->slug()));
    }

    public function testFormRejectsPaymentOutsideProvidedActorScopedChoices(): void
    {
        $subject = new \App\Casing\Value\ServicePaymentSubject('payment-1', 'order-1', 'ORD-1', 'completed', '50.00', 'USD', null);
        $data = new ServiceDisputeClaimData();
        $form = Forms::createFormFactory()->create(ServiceDisputeClaimType::class, $data, ['subjects' => [$subject]]);
        $form->submit([
            'subject' => hash('sha256', 'other-payment'),
            'description' => 'I dispute this charge.',
        ]);

        self::assertFalse($form->isValid());
        self::assertNull($data->subject);
    }
}
