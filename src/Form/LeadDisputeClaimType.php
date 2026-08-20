<?php

declare(strict_types=1);

namespace App\Casing\Form;

use App\Casing\Dto\LeadDisputeClaimData;
use App\Casing\Value\LeadSubject;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class LeadDisputeClaimType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $reasonChoices = [];
        foreach ($options['reasons'] as $reason) {
            if (is_array($reason) && isset($reason['title'], $reason['path'])) {
                $reasonChoices[(string) $reason['title']] = (string) $reason['path'];
            }
        }

        $builder
            ->add('subject', ChoiceType::class, [
                'label' => 'Lead',
                'choices' => $options['subjects'],
                'choice_label' => static fn (LeadSubject $subject): string => sprintf('%s · %s · score %d', $subject->leadReference, $subject->status, $subject->score),
                'choice_value' => static fn (?LeadSubject $subject): string => null === $subject ? '' : hash('sha256', $subject->leadReference),
                'placeholder' => 'Select a lead associated with your account',
            ])
            ->add('reasonPath', ChoiceType::class, [
                'label' => 'Dispute reason',
                'choices' => $reasonChoices,
                'placeholder' => 'Select a reason',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Describe the issue',
                'attr' => ['rows' => 6],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => LeadDisputeClaimData::class, 'subjects' => [], 'reasons' => []]);
        $resolver->setAllowedTypes('subjects', 'array');
        $resolver->setAllowedTypes('reasons', 'array');
    }
}
