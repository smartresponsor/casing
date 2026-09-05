<?php

declare(strict_types=1);

namespace App\Casing\Form\Claim;

use App\Casing\DTO\Claim\ServiceDisputeClaimDTO;
use App\Casing\Value\ServicePaymentSubject;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ServiceDisputeClaimType extends AbstractType
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
                'label' => 'Payment',
                'choices' => $options['subjects'],
                'choice_label' => static fn (ServicePaymentSubject $subject): string => sprintf('%s · %s %s · %s', $subject->orderNumber, $subject->amount, $subject->currency, $subject->status),
                'choice_value' => static fn (?ServicePaymentSubject $subject): string => null === $subject ? '' : hash('sha256', $subject->paymentReference),
                'placeholder' => 'Select a payment from your orders',
            ])
            ->add('typeCode', ChoiceType::class, [
                'label' => 'Dispute type',
                'choices' => $typeChoices,
                'placeholder' => 'Select a dispute type',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Describe the dispute',
                'attr' => ['rows' => 6],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ServiceDisputeClaimDTO::class, 'subjects' => [], 'types' => []]);
        $resolver->setAllowedTypes('subjects', 'array');
        $resolver->setAllowedTypes('types', 'array');
    }
}
