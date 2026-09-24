<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Controller\CaseContextualSupportController;
use App\Casing\ResolverInterface\Ordering\CasePurchasedProductSubjectResolverInterface;
use App\Casing\ResolverInterface\Paying\CaseServicePaymentSubjectResolverInterface;
use App\Casing\Service\CaseActorAccessService;
use App\Casing\Value\CasePurchasedProductSubject;
use App\Casing\Value\CaseServicePaymentSubject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class CaseContextualSupportControllerCoverageTest extends TestCase
{
    public function testOrdersMergeProductAndPaymentContextsByOrderReference(): void
    {
        $products = $this->createMock(CasePurchasedProductSubjectResolverInterface::class);
        $products->expects(self::once())->method('listForActor')->with('actor-1')->willReturn([
            $this->product('order-1', 'ORD-1', 'SKU-1'),
            $this->product('order-1', 'ORD-1', 'SKU-2'),
        ]);

        $payments = $this->createMock(CaseServicePaymentSubjectResolverInterface::class);
        $payments->expects(self::once())->method('listForActor')->with('actor-1')->willReturn([
            $this->payment('payment-1', 'order-1', 'ORD-1'),
            $this->payment('payment-2', 'order-2', 'ORD-2'),
        ]);

        $payload = (new CaseContextualSupportController($this->actors(), $products, $payments))
            ->orders($this->request());

        self::assertSame('Order help', $payload['meta']['title']);
        self::assertSame([
            [
                'id' => 'order-1',
                'context' => 'Order',
                'request' => 'ORD-1',
                'description' => 'Status: delivered',
                'href' => '/support/order/order-1',
                'availableItems' => 3,
            ],
            [
                'id' => 'order-2',
                'context' => 'Order',
                'request' => 'ORD-2',
                'description' => 'Status: captured',
                'href' => '/support/order/order-2',
                'availableItems' => 1,
            ],
        ], $payload['data']['rows']);
    }

    public function testOrderContextBuildsProductAndPaymentActions(): void
    {
        $products = $this->createMock(CasePurchasedProductSubjectResolverInterface::class);
        $products->expects(self::once())->method('listForActorOrder')->with('actor-1', 'order 1')->willReturn([
            $this->product('order 1', 'ORD-1', 'SKU/1'),
        ]);

        $payments = $this->createMock(CaseServicePaymentSubjectResolverInterface::class);
        $payments->expects(self::once())->method('listForActor')->with('actor-1')->willReturn([
            $this->payment('payment/1', 'order 1', 'ORD-1'),
        ]);

        $payload = (new CaseContextualSupportController($this->actors(), $products, $payments))
            ->order($this->request(), 'order 1');

        self::assertSame('Order support', $payload['meta']['title']);
        self::assertSame([
            [
                'label' => 'Return SKU/1',
                'href' => '/support/product/return/order/order%201/item/SKU%2F1',
                'kind' => 'product-return',
            ],
            [
                'label' => 'Dispute service payment',
                'href' => '/support/service/dispute/payment/payment%2F1',
                'kind' => 'service-dispute',
            ],
        ], $payload['data']['actions']);
    }

    public function testOrderContextRejectsUnownedOrder(): void
    {
        $products = $this->createStub(CasePurchasedProductSubjectResolverInterface::class);
        $products->method('listForActorOrder')->willReturn([]);
        $payments = $this->createStub(CaseServicePaymentSubjectResolverInterface::class);
        $payments->method('listForActor')->willReturn([]);

        $this->expectException(AccessDeniedHttpException::class);
        (new CaseContextualSupportController($this->actors(), $products, $payments))
            ->order($this->request(), 'missing');
    }

    public function testServiceContextMatchesOrderNumberAndBuildsPaymentAction(): void
    {
        $products = $this->createStub(CasePurchasedProductSubjectResolverInterface::class);
        $payments = $this->createMock(CaseServicePaymentSubjectResolverInterface::class);
        $payments->expects(self::once())->method('listForActor')->with('actor-1')->willReturn([
            $this->payment('payment-2', 'order-2', 'ORD-2'),
        ]);

        $payload = (new CaseContextualSupportController($this->actors(), $products, $payments))
            ->service($this->request(), 'ORD-2');

        self::assertSame('Service support', $payload['meta']['title']);
        self::assertSame([
            [
                'label' => 'Dispute service payment',
                'href' => '/support/service/dispute/payment/payment-2',
                'kind' => 'service-dispute',
            ],
        ], $payload['data']['actions']);
    }

    public function testServiceContextRejectsOrderWithoutPayment(): void
    {
        $products = $this->createStub(CasePurchasedProductSubjectResolverInterface::class);
        $payments = $this->createStub(CaseServicePaymentSubjectResolverInterface::class);
        $payments->method('listForActor')->willReturn([]);

        $this->expectException(AccessDeniedHttpException::class);
        (new CaseContextualSupportController($this->actors(), $products, $payments))
            ->service($this->request(), 'missing');
    }

    private function actors(): CaseActorAccessService
    {
        return new CaseActorAccessService($this->createStub(Security::class));
    }

    private function request(): Request
    {
        $request = new Request();
        $request->attributes->set('casing_actor_id', 'actor-1');

        return $request;
    }

    private function product(string $orderReference, string $orderNumber, string $itemReference): CasePurchasedProductSubject
    {
        return new CasePurchasedProductSubject(
            $orderReference,
            $orderNumber,
            $itemReference,
            1,
            'USD',
            '19.99',
            'delivered',
        );
    }

    private function payment(string $paymentReference, string $orderReference, string $orderNumber): CaseServicePaymentSubject
    {
        return new CaseServicePaymentSubject(
            $paymentReference,
            $orderReference,
            $orderNumber,
            'captured',
            '19.99',
            'USD',
            null,
        );
    }
}
