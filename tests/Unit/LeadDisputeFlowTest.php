<?php

declare(strict_types=1);

namespace App\Casing\Tests\Unit;

use App\Casing\DTO\Claim\CaseLeadDisputeClaimDTO;
use App\Casing\Form\Claim\CaseLeadDisputeClaimType;
use App\Casing\Resolver\Relating\CaseLeadSubjectResolver;
use App\Casing\Service\CaseCatalogService;
use App\Casing\Value\CaseLeadSubject;
use App\Cataloging\Entity\Catalog\CatalogCatalogEntity;
use App\Cataloging\Entity\Catalog\CatalogCategoryEntity;
use App\Cataloging\ServiceInterface\CatalogCatalogTreeReadServiceInterface;
use App\Cataloging\ServiceInterface\CatalogCategoryLookupServiceInterface;
use App\Relating\Entity\RelationLeadEntity;
use App\Relating\Service\RelationVendorLeadReadServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;

final class LeadDisputeFlowTest extends TestCase
{
    public function testLeadResolverUsesOnlyVendorScopedRelatingResults(): void
    {
        $lead = new RelationLeadEntity('lead-1');
        $owner = new class($lead) implements RelationVendorLeadReadServiceInterface {
            public function __construct(private readonly RelationLeadEntity $lead)
            {
            }

            public function leadsForVendor(string $vendorReference): array
            {
                return 'actor-1' === $vendorReference ? [$this->lead] : [];
            }
        };
        $resolver = new CaseLeadSubjectResolver($owner);

        self::assertCount(1, $resolver->listForActor('actor-1'));
        self::assertSame([], $resolver->listForActor('actor-2'));
        self::assertNotNull($resolver->resolve('actor-1', 'lead-1'));
        self::assertNull($resolver->resolve('actor-2', 'lead-1'));
    }

    public function testFormAcceptsOnlyProvidedLeadAndCatalogReasonChoices(): void
    {
        $subject = new CaseLeadSubject('lead-1', 'converted', 70);
        $types = [
            ['code' => 'invalid', 'label' => 'Invalid'],
            ['code' => 'duplicate', 'label' => 'Duplicate'],
        ];
        $data = new CaseLeadDisputeClaimDTO();
        $form = Forms::createFormFactory()->create(CaseLeadDisputeClaimType::class, $data, ['subjects' => [$subject], 'types' => $types]);
        $form->submit([
            'subject' => hash('sha256', $subject->leadReference),
            'typeCode' => 'invalid',
            'description' => 'The lead does not match the requested service.',
        ]);

        self::assertTrue($form->isValid());
        self::assertSame($subject, $data->subject);
        self::assertSame('invalid', $data->typeCode);

        $tampered = new CaseLeadDisputeClaimDTO();
        $tamperedForm = Forms::createFormFactory()->create(CaseLeadDisputeClaimType::class, $tampered, ['subjects' => [$subject], 'types' => $types]);
        $tamperedForm->submit([
            'subject' => hash('sha256', 'other-lead'),
            'typeCode' => 'not-published',
            'description' => 'Tampered request.',
        ]);

        self::assertFalse($tamperedForm->isValid());
        self::assertNull($tampered->subject);
    }

    public function testLeadDisputeTypesComeFromPublishedCategoryMetadata(): void
    {
        $catalog = new CatalogCatalogEntity('leads', 'Leads', 'lead-discovery');
        $category = new CatalogCategoryEntity($catalog, 'Dispute', 'dispute', 'leads.dispute', 1);
        $category->setPublished(true);
        $category->setWorkflowState('published');
        $category->setMetadata([
            'schema' => 'catalog-category-types@1',
            'types' => [
                ['code' => 'invalid', 'label' => 'Invalid'],
                ['code' => 'duplicate', 'label' => 'Duplicate'],
            ],
        ]);

        $trees = $this->createStub(CatalogCatalogTreeReadServiceInterface::class);
        $lookup = $this->createStub(CatalogCategoryLookupServiceInterface::class);
        $lookup->method('publishedByCatalogAndPath')->willReturnCallback(
            static fn (string $catalogCode, string $path): ?CatalogCategoryEntity => 'leads' === $catalogCode && 'leads.dispute' === $path ? $category : null,
        );
        $catalogs = new CaseCatalogService($trees, $lookup);

        self::assertSame([
            ['code' => 'invalid', 'label' => 'Invalid'],
            ['code' => 'duplicate', 'label' => 'Duplicate'],
        ], $catalogs->publishedTypes('leads', 'leads.dispute'));
        self::assertTrue($catalogs->isPublishedType('leads', 'leads.dispute', 'invalid'));
        self::assertFalse($catalogs->isPublishedType('leads', 'leads.dispute', 'missing'));
    }
}
