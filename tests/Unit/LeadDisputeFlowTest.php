<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\Dto\LeadDisputeClaimData;
use App\Casing\Form\LeadDisputeClaimType;
use App\Casing\Integration\Relating\LeadSubjectResolver;
use App\Casing\Service\CaseCatalogService;
use App\Casing\Value\LeadSubject;
use App\Cataloging\ServiceInterface\CatalogCatalogTreeReadServiceInterface;
use App\Cataloging\ServiceInterface\CatalogCategoryLookupServiceInterface;
use App\Entity\Lead;
use App\Service\VendorLeadReadServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;

final class LeadDisputeFlowTest extends TestCase
{
    public function testLeadResolverUsesOnlyVendorScopedRelatingResults(): void
    {
        $lead = new Lead('lead-1');
        $owner = new class($lead) implements VendorLeadReadServiceInterface {
            public function __construct(private readonly Lead $lead)
            {
            }

            public function leadsForVendor(string $vendorReference): array
            {
                return 'actor-1' === $vendorReference ? [$this->lead] : [];
            }
        };
        $resolver = new LeadSubjectResolver($owner);

        self::assertCount(1, $resolver->listForActor('actor-1'));
        self::assertSame([], $resolver->listForActor('actor-2'));
        self::assertNotNull($resolver->resolve('actor-1', 'lead-1'));
        self::assertNull($resolver->resolve('actor-2', 'lead-1'));
    }

    public function testFormAcceptsOnlyProvidedLeadAndCatalogReasonChoices(): void
    {
        $subject = new LeadSubject('lead-1', 'converted', 70);
        $reasons = [
            ['title' => 'Invalid', 'path' => 'leads.dispute.invalid', 'slug' => 'invalid'],
            ['title' => 'Duplicate', 'path' => 'leads.dispute.duplicate', 'slug' => 'duplicate'],
        ];
        $data = new LeadDisputeClaimData();
        $form = Forms::createFormFactory()->create(LeadDisputeClaimType::class, $data, ['subjects' => [$subject], 'reasons' => $reasons]);
        $form->submit([
            'subject' => hash('sha256', $subject->leadReference),
            'reasonPath' => 'leads.dispute.invalid',
            'description' => 'The lead does not match the requested service.',
        ]);

        self::assertTrue($form->isValid());
        self::assertSame($subject, $data->subject);
        self::assertSame('leads.dispute.invalid', $data->reasonPath);

        $tampered = new LeadDisputeClaimData();
        $tamperedForm = Forms::createFormFactory()->create(LeadDisputeClaimType::class, $tampered, ['subjects' => [$subject], 'reasons' => $reasons]);
        $tamperedForm->submit([
            'subject' => hash('sha256', 'other-lead'),
            'reasonPath' => 'leads.dispute.not-published',
            'description' => 'Tampered request.',
        ]);

        self::assertFalse($tamperedForm->isValid());
        self::assertNull($tampered->subject);
    }

    public function testLeadDisputeReasonsComeFromPublishedCatalogChildren(): void
    {
        $trees = new class implements CatalogCatalogTreeReadServiceInterface {
            public function byCode(string $catalogCode, string $tenant = 'default'): ?array
            {
                if ('leads' !== $catalogCode) {
                    return null;
                }

                return [
                    'catalog' => ['code' => 'leads', 'name' => 'Leads', 'purpose' => 'lead-discovery'],
                    'root' => [
                        'title' => 'Leads',
                        'slug' => 'leads',
                        'path' => 'leads',
                        'children' => [[
                            'title' => 'Dispute',
                            'slug' => 'dispute',
                            'path' => 'leads.dispute',
                            'children' => [
                                ['title' => 'Invalid', 'slug' => 'invalid', 'path' => 'leads.dispute.invalid', 'children' => []],
                                ['title' => 'Duplicate', 'slug' => 'duplicate', 'path' => 'leads.dispute.duplicate', 'children' => []],
                            ],
                        ]],
                    ],
                    'nodes' => [],
                ];
            }
        };
        $lookup = $this->createStub(CatalogCategoryLookupServiceInterface::class);
        $catalogs = new CaseCatalogService($trees, $lookup);

        self::assertSame([
            ['title' => 'Invalid', 'path' => 'leads.dispute.invalid', 'slug' => 'invalid'],
            ['title' => 'Duplicate', 'path' => 'leads.dispute.duplicate', 'slug' => 'duplicate'],
        ], $catalogs->publishedChildren('leads', 'leads.dispute'));
    }
}
