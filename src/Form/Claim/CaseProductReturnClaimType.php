<?php

declare(strict_types=1);

namespace App\Casing\Form\Claim;

use App\Casing\DTO\Claim\CaseProductReturnClaimDTO;
use App\Casing\Value\CasePurchasedProductSubject;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CaseProductReturnClaimType extends AbstractType
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
                'label' => 'Purchased product',
                'choices' => $options['subjects'],
                'choice_label' => static fn (CasePurchasedProductSubject $subject): string => sprintf('%s · %s · %s %s', $subject->orderNumber, $subject->itemReference, $subject->unitPrice, $subject->currency),
                'choice_value' => static fn (?CasePurchasedProductSubject $subject): string => null === $subject ? '' : hash('sha256', $subject->orderReference."\0".$subject->itemReference),
                'placeholder' => 'Select a purchased product',
            ])
            ->add('typeCode', ChoiceType::class, [
                'label' => 'Return type',
                'choices' => $typeChoices,
                'placeholder' => 'Select a return type',
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Why do you want to return it?',
                'attr' => ['rows' => 5],
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantity',
                'required' => false,
                'empty_data' => null,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CaseProductReturnClaimDTO::class,
            'subjects' => [],
            'types' => [],
        ]);
        $resolver->setAllowedTypes('subjects', 'array');
        $resolver->setAllowedTypes('types', 'array');
    }
}
