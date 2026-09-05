<?php

declare(strict_types=1);

namespace App\Casing\FormInterface\Contribution;

use Symfony\Component\Form\FormTypeInterface;

interface CaseFormContributionInterface
{
    public function key(): string;

    /** @return class-string<FormTypeInterface> */
    public function formType(): string;

    /** @return class-string<object> */
    public function dataClass(): string;

    public function createData(): object;

    /** @return array<string, mixed> */
    public function normalize(object $data): array;
}
