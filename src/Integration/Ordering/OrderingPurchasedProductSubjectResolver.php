<?php

declare(strict_types=1);

namespace App\Casing\Integration\Ordering;

use App\Casing\Contract\PurchasedProductSubjectResolverInterface;
use App\Casing\Value\PurchasedProductSubject;
use App\Ordering\Entity\Order\OrderEntity;
use App\Ordering\Entity\Order\OrderItemEntity;
use App\Ordering\ReadModel\Repository\OrderReadRepository;

final readonly class OrderingPurchasedProductSubjectResolver implements PurchasedProductSubjectResolverInterface
{
    public function __construct(private OrderReadRepository $orders)
    {
    }

    public function listForActor(string $actorId): array
    {
        $actorId = trim($actorId);
        if ('' === $actorId) {
            return [];
        }

        $subjects = [];
        foreach ($this->orders->findByCustomerId($actorId) as $order) {
            if (!$order instanceof OrderEntity) {
                continue;
            }
            foreach ($order->getItems() as $item) {
                if ($item instanceof OrderItemEntity) {
                    $subjects[] = $this->subject($order, $item);
                }
            }
        }

        return $subjects;
    }

    public function listForActorOrder(string $actorId, string $orderReference): array
    {
        $actorId = trim($actorId);
        $orderReference = trim($orderReference);
        if ('' === $actorId || '' === $orderReference) {
            return [];
        }

        $subjects = [];
        foreach ($this->orders->findByCustomerId($actorId) as $order) {
            if (!$order instanceof OrderEntity || !$this->matchesOrderReference($order, $orderReference)) {
                continue;
            }
            foreach ($order->getItems() as $item) {
                if ($item instanceof OrderItemEntity) {
                    $subjects[] = $this->subject($order, $item);
                }
            }
            break;
        }

        return $subjects;
    }

    public function resolve(string $actorId, string $orderReference, string $itemReference): ?PurchasedProductSubject
    {
        $actorId = trim($actorId);
        $orderReference = trim($orderReference);
        $itemReference = trim($itemReference);
        if ('' === $actorId || '' === $orderReference || '' === $itemReference) {
            return null;
        }

        foreach ($this->orders->findByCustomerId($actorId) as $order) {
            if (!$order instanceof OrderEntity || !$this->matchesOrderReference($order, $orderReference)) {
                continue;
            }

            foreach ($order->getItems() as $item) {
                if ($item instanceof OrderItemEntity && $itemReference === $item->getSku()) {
                    return $this->subject($order, $item);
                }
            }
        }

        return null;
    }

    private function subject(OrderEntity $order, OrderItemEntity $item): PurchasedProductSubject
    {
        return new PurchasedProductSubject(
            orderReference: $order->getSlug(),
            orderNumber: $order->getNumber(),
            itemReference: $item->getSku(),
            quantity: $item->getQuantity(),
            currency: $item->getCurrency(),
            unitPrice: $item->getUnitPrice(),
            orderStatus: $order->getStatus(),
        );
    }

    private function matchesOrderReference(OrderEntity $order, string $reference): bool
    {
        if ($reference === $order->getSlug() || $reference === $order->getNumber()) {
            return true;
        }

        $id = $order->getId();

        return null !== $id && $reference === (string) $id;
    }
}
