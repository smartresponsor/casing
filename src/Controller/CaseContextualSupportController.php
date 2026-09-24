<?php

declare(strict_types=1);

namespace App\Casing\Controller;

use App\Casing\ResolverInterface\Ordering\CasePurchasedProductSubjectResolverInterface;
use App\Casing\ResolverInterface\Paying\CaseServicePaymentSubjectResolverInterface;
use App\Casing\Service\CaseActorAccessService;
use App\Casing\Value\CaseServicePaymentSubject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class CaseContextualSupportController
{
    public function __construct(
        private CaseActorAccessService $actors,
        private CasePurchasedProductSubjectResolverInterface $products,
        private CaseServicePaymentSubjectResolverInterface $payments,
    ) {
    }

    /** @return array<string, mixed> */
    #[Route('/support/order', name: 'casing_support_order_list', methods: ['GET'], defaults: ['_view_controlled' => true])]
    public function orders(Request $request): array
    {
        $actorId = $this->actors->requireActorId($request);
        $orders = [];

        foreach ($this->products->listForActor($actorId) as $product) {
            $key = $product->orderReference;
            $orders[$key] ??= [
                'reference' => $product->orderReference,
                'number' => $product->orderNumber,
                'status' => $product->orderStatus,
                'availableItems' => 0,
            ];
            ++$orders[$key]['availableItems'];
        }

        foreach ($this->payments->listForActor($actorId) as $payment) {
            $key = $payment->orderReference;
            $orders[$key] ??= [
                'reference' => $payment->orderReference,
                'number' => $payment->orderNumber,
                'status' => $payment->status,
                'availableItems' => 0,
            ];
            ++$orders[$key]['availableItems'];
        }

        $rows = array_map(static fn (array $order): array => [
            'id' => $order['reference'],
            'context' => 'Order',
            'request' => $order['number'],
            'description' => sprintf('Status: %s', $order['status']),
            'href' => '/support/order/'.rawurlencode((string) $order['reference']),
            'availableItems' => $order['availableItems'],
        ], array_values($orders));

        return [
            '_view' => ['surface' => 'support', 'operation' => 'index', 'intent' => 'order-context', 'format' => 'auto', 'component' => 'Casing'],
            'interface' => ['locations' => ['shell.main.content' => [[
                'type' => 'text',
                'label' => 'Order help',
                'description' => 'Choose one of your orders to see the support actions currently available for it.',
            ]]]],
            'data' => ['rows' => $rows],
            'meta' => ['title' => 'Order help'],
        ];
    }

    /** @return array<string, mixed> */
    #[Route('/support/order/{orderReference}', name: 'casing_support_order_context', methods: ['GET'], defaults: ['_view_controlled' => true])]
    public function order(Request $request, string $orderReference): array
    {
        $actorId = $this->actors->requireActorId($request);
        $products = $this->products->listForActorOrder($actorId, $orderReference);
        $payment = $this->paymentForOrder($actorId, $orderReference);
        if ([] === $products && !$payment instanceof CaseServicePaymentSubject) {
            throw new AccessDeniedHttpException('We could not associate this order with your account.');
        }

        $actions = [];
        foreach ($products as $product) {
            $actions[] = [
                'label' => sprintf('Return %s', $product->itemReference),
                'href' => sprintf('/support/product/return/order/%s/item/%s', rawurlencode($orderReference), rawurlencode($product->itemReference)),
                'kind' => 'product-return',
            ];
        }
        if ($payment instanceof CaseServicePaymentSubject) {
            $actions[] = [
                'label' => 'Dispute service payment',
                'href' => sprintf('/support/service/dispute/payment/%s', rawurlencode($payment->paymentReference)),
                'kind' => 'service-dispute',
            ];
        }

        return $this->payload('Order support', 'Choose an available help action for this order.', $actions);
    }

    /** @return array<string, mixed> */
    #[Route('/support/service/order/{orderReference}', name: 'casing_support_service_order_context', methods: ['GET'], defaults: ['_view_controlled' => true])]
    public function service(Request $request, string $orderReference): array
    {
        $actorId = $this->actors->requireActorId($request);
        $payment = $this->paymentForOrder($actorId, $orderReference);
        if (!$payment instanceof CaseServicePaymentSubject) {
            throw new AccessDeniedHttpException('We could not associate this service order with your account.');
        }

        return $this->payload('Service support', 'Get help with this service order.', [[
            'label' => 'Dispute service payment',
            'href' => sprintf('/support/service/dispute/payment/%s', rawurlencode($payment->paymentReference)),
            'kind' => 'service-dispute',
        ]]);
    }

    private function paymentForOrder(string $actorId, string $orderReference): ?CaseServicePaymentSubject
    {
        foreach ($this->payments->listForActor($actorId) as $payment) {
            if ($payment->orderReference === $orderReference || $payment->orderNumber === $orderReference) {
                return $payment;
            }
        }

        return null;
    }

    /** @param list<array{label: string, href: string, kind: string}> $actions
     * @return array<string, mixed>
     */
    private function payload(string $title, string $description, array $actions): array
    {
        return [
            '_view' => ['surface' => 'support', 'operation' => 'show', 'intent' => 'context', 'format' => 'auto', 'component' => 'Casing'],
            'interface' => ['locations' => ['shell.main.content' => [['type' => 'text', 'label' => $title, 'description' => $description]]]],
            'data' => ['actions' => $actions],
            'meta' => ['title' => $title],
        ];
    }
}
