<?php

namespace App\Form\Wishlist;

use App\Dto\WishlistCreationData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WishlistConfirmType extends AbstractType
{
    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => WishlistCreationData::class,

            /*
             * Les étapes précédentes ont déjà
             * été validées.
             */
            'validation_groups' => false,
        ]);
    }
}
