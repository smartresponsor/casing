<?php

declare(strict_types=1);

namespace App\Casing\Form\Claim;

use App\Casing\DTO\Claim\CaseLeadDisputeClaimDTO;
use App\Casing\Value\CaseLeadSubject;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CaseLeadDisputeClaimType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $typeChoices = [];
        foreach ($options['types'] as $type) {
            if (is_array($type) && isset($type['label'], $type['code'])) {
                $typeChoices[(string) $type['label']] = (string) $type['code'];
            }
        }

        $builder
            ->add('subject', ChoiceType::class, [
                'label' => 'Lead',
                'choices' => $options['subjects'],
                'choice_label' => static fn (CaseLeadSubject $subject): string => sprintf('%s · %s · score %d', $subject->leadReference, $subject->status, $subject->score),
                'choice_value' => static fn (?CaseLeadSubject $subject): string => null === $subject ? '' : hash('sha256', $subject->leadReference),
                'placeholder' => 'Select a lead associated with your account',
            ])
            ->add('typeCode', ChoiceType::class, [
                'label' => 'Dispute reason',
                'choices' => $typeChoices,
                'placeholder' => 'Select a reason',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Describe the issue',
                'attr' => ['rows' => 6],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CaseLeadDisputeClaimDTO::class, 'subjects' => [], 'types' => []]);
        $resolver->setAllowedTypes('subjects', 'array');
        $resolver->setAllowedTypes('types', 'array');
    }
}
