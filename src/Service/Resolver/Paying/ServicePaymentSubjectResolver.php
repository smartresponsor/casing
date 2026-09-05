<?php

declare(strict_types=1);

namespace App\Casing\Service\Resolver\Paying;

use App\Casing\ServiceInterface\Resolver\ServicePaymentSubjectResolverInterface;
use App\Casing\Value\ServicePaymentSubject;
use App\Ordering\Entity\Order\OrderEntity;
use App\Ordering\ReadModel\Repository\OrderReadRepository;
use App\Paying\Entity\PaymentEntity;
use App\Paying\RepositoryInterface\PaymentRepositoryInterface;

final readonly class ServicePaymentSubjectResolver implements ServicePaymentSubjectResolverInterface
{
    public function __construct(
        private OrderReadRepository $orders,
        private PaymentRepositoryInterface $payments,
    ) {
    }

    public function listForActor(string $actorId): array
    {
        $actorId = trim($actorId);
        if ('' === $actorId) {
            return [];
        }

        $subjects = [];
        $seen = [];
        foreach ($this->orders->findByCustomerId($actorId) as $order) {
            if (!$order instanceof OrderEntity) {
                continue;
            }

            $payment = $this->paymentForOrder($order);
            if (!$payment instanceof PaymentEntity || isset($seen[$payment->slug()])) {
                continue;
            }
            $seen[$payment->slug()] = true;
            $subjects[] = $this->subject($order, $payment);
        }

        return $subjects;
    }

    public function resolve(string $actorId, string $paymentReference): ?ServicePaymentSubject
    {
        $paymentReference = trim($paymentReference);
        if ('' === $paymentReference) {
            return null;
        }

        foreach ($this->listForActor($actorId) as $subject) {
            if ($subject->paymentReference === $paymentReference) {
                return $subject;
            }
        }

        return null;
    }

    private function paymentForOrder(OrderEntity $order): ?PaymentEntity
    {
        $references = [$order->getSlug(), $order->getNumber()];
        $id = $order->getId();
        if (null !== $id) {
            $references[] = (string) $id;
        }

        foreach (array_unique($references) as $reference) {
            $payment = $this->payments->findByOrderId($reference);
            if ($payment instanceof PaymentEntity) {
                return $payment;
            }
        }

        return null;
    }

    private function subject(OrderEntity $order, PaymentEntity $payment): ServicePaymentSubject
    {
        return new ServicePaymentSubject(
            paymentReference: $payment->slug(),
            orderReference: $order->getSlug(),
            orderNumber: $order->getNumber(),
            status: $payment->status()->value,
            amount: $payment->amount(),
            currency: $payment->currency(),
            providerReference: $payment->providerRef(),
        );
    }
}
