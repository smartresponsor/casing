<?php

declare(strict_types=1);

namespace App\Casing\Form;

use App\Casing\Dto\ProductReturnClaimData;
use App\Casing\Value\PurchasedProductSubject;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductReturnClaimType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', ChoiceType::class, [
                'label' => 'Purchased product',
                'choices' => $options['subjects'],
                'choice_label' => static fn (PurchasedProductSubject $subject): string => sprintf('%s · %s · %s %s', $subject->orderNumber, $subject->itemReference, $subject->unitPrice, $subject->currency),
                'choice_value' => static fn (?PurchasedProductSubject $subject): string => null === $subject ? '' : hash('sha256', $subject->orderReference."\0".$subject->itemReference),
                'placeholder' => 'Select a purchased product',
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
            'data_class' => ProductReturnClaimData::class,
            'subjects' => [],
        ]);
        $resolver->setAllowedTypes('subjects', 'array');
    }
}
