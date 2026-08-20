<?php

declare(strict_types=1);

namespace App\Casing\Form;

use App\Casing\Dto\ServiceDisputeClaimData;
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
        $builder
            ->add('subject', ChoiceType::class, [
                'label' => 'Payment',
                'choices' => $options['subjects'],
                'choice_label' => static fn (ServicePaymentSubject $subject): string => sprintf('%s · %s %s · %s', $subject->orderNumber, $subject->amount, $subject->currency, $subject->status),
                'choice_value' => static fn (?ServicePaymentSubject $subject): string => null === $subject ? '' : hash('sha256', $subject->paymentReference),
                'placeholder' => 'Select a payment from your orders',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Describe the dispute',
                'attr' => ['rows' => 6],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ServiceDisputeClaimData::class, 'subjects' => []]);
        $resolver->setAllowedTypes('subjects', 'array');
    }
}
