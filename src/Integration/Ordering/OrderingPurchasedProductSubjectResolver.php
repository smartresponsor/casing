<?php

declare(strict_types=1);

namespace App\Casing\Integration\Ordering;

use App\Casing\Contract\PurchasedProductSubjectResolverInterface;
use App\Casing\Value\PurchasedProductSubject;
use App\Ordering\ReadModel\ServiceInterface\CustomerOrderReadServiceInterface;
use App\Ordering\ReadModel\View\CustomerOrderDetail;
use App\Ordering\ReadModel\View\CustomerOrderItemSummary;

final readonly class OrderingPurchasedProductSubjectResolver implements PurchasedProductSubjectResolverInterface
{
    public function __construct(private CustomerOrderReadServiceInterface $orders)
    {
    }

    public function listForActor(string $actorId): array
    {
        $actorId = trim($actorId);
        if ('' === $actorId) {
            return [];
        }

        $subjects = [];
        foreach ($this->orders->listDetailsForCustomer($actorId) as $order) {
            foreach ($order->items as $item) {
                $subjects[] = $this->subject($order, $item);
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

        $order = $this->orders->findDetailForCustomer($actorId, $orderReference);
        if (!$order instanceof CustomerOrderDetail) {
            return [];
        }

        return array_map(
            fn (CustomerOrderItemSummary $item): PurchasedProductSubject => $this->subject($order, $item),
            $order->items,
        );
    }

    public function resolve(string $actorId, string $orderReference, string $itemReference): ?PurchasedProductSubject
    {
        $actorId = trim($actorId);
        $orderReference = trim($orderReference);
        $itemReference = trim($itemReference);
        if ('' === $actorId || '' === $orderReference || '' === $itemReference) {
            return null;
        }

        $order = $this->orders->findDetailForCustomer($actorId, $orderReference);
        if (!$order instanceof CustomerOrderDetail) {
            return null;
        }

        foreach ($order->items as $item) {
            if ($itemReference === $item->reference) {
                return $this->subject($order, $item);
            }
        }

        return null;
    }

    private function subject(CustomerOrderDetail $order, CustomerOrderItemSummary $item): PurchasedProductSubject
    {
        return new PurchasedProductSubject(
            orderReference: $order->reference,
            orderNumber: $order->number,
            itemReference: $item->reference,
            quantity: $item->quantity,
            currency: $item->currency,
            unitPrice: $item->unitPrice,
            orderStatus: $order->status,
        );
    }
}
