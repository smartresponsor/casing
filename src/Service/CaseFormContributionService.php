<?php

declare(strict_types=1);

namespace App\Casing\Service;

use App\Casing\Contract\CaseFormContributionRegistry;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class CaseFormContributionService
{
    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly CaseFormContributionRegistry $registry,
    ) {
    }

    public function create(string $key): FormInterface
    {
        $provider = $this->registry->get($key);

        return $this->forms->create($provider->formType(), $provider->createData());
    }

    /** @param array<string, mixed> $payload */
    public function submit(string $key, array $payload): FormInterface
    {
        $form = $this->create($key);
        $form->submit($payload);

        return $form;
    }

    /** @return array<string, mixed> */
    public function normalized(string $key, FormInterface $form): array
    {
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new \DomainException('Case form contribution must be submitted and valid before normalization.');
        }

        $data = $form->getData();
        if (!is_object($data)) {
            throw new \LogicException('Case form contribution did not produce owner DTO data.');
        }

        return $this->registry->get($key)->normalize($data);
    }
}
