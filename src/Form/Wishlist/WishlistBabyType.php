<?php

namespace App\Form\Wishlist;

use App\Dto\WishlistCreationData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WishlistBabyType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('dueDate', DateType::class, [
                'label' => 'Date prévue d’arrivée',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('babyName', TextType::class, [
                'label' => 'Prénom du bébé',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Facultatif',
                ],
                'help' => 'Vous pourrez l’ajouter ou le modifier plus tard.',
            ])
        ;
    }


    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => WishlistCreationData::class,
            'validation_groups' => ['baby'],
        ]);
    }
}
