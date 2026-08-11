<?php

namespace App\Form\Wishlist;

use App\Dto\WishlistCreationData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WishlistFamilyType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la liste',
                'attr' => [
                    'placeholder' => 'Liste de naissance',
                ],
            ])

            ->add('parentsNames', TextType::class, [
                'label' => 'Nom(s) des parents',
                'attr' => [
                    'placeholder' => 'Léa & Thomas',
                ],
            ])

            ->add('message', TextareaType::class, [
                'label' => 'Un petit mot pour vos proches',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'maxlength' => 1500,
                    'placeholder' => 'Merci de partager cette belle aventure avec nous...',
                ],
            ])

        ;
    }


    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => WishlistCreationData::class,

            /*
             * Uniquement les contraintes
             * du groupe family.
             */
            'validation_groups' => ['family'],
        ]);
    }
}
