<?php

namespace App\Form;

use App\Entity\Product;
use App\Enum\ProductCategory;
use App\Enum\ProductStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputClass = 'w-full rounded-xl border border-[rgba(45,45,45,0.15)] px-4 py-2.5 text-[#2D2D2D] text-sm focus:outline-none focus:ring-2 focus:ring-[#4ECDC4]/60 transition';
        $labelClass = 'text-sm font-medium text-[#6B6B6B] mb-1 block';

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'ex: Poussette',
                    'class' => $inputClass,
                ],
                'label_attr' => [
                    'class' => $labelClass,
                ],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Prix',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'placeholder' => 'ex: 10,99',
                    'step' => '0.01',
                    'min' => '0',
                    'class' => $inputClass,
                ],
                'label_attr' => [
                    'class' => $labelClass,
                ],
            ])
            /*
             * "image" reste mappé sur Product::$image.
             * Il contient soit une URL distante importée, soit le nom
             * d'un fichier déjà uploadé. Il n'a pas besoin d'être visible.
             */
            ->add('image', HiddenType::class, [
                'required' => false,
            ])
            /*
             * "imageFile" sert uniquement à l'upload manuel.
             * mapped=false évite d'essayer de mettre un UploadedFile
             * dans Product::$image qui est une string.
             */
            ->add('imageFile', FileType::class, [
                'label' => 'Image',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => 'image/*',
                    'class' => $inputClass,
                ],
                'label_attr' => [
                    'class' => $labelClass,
                ],
            ])
            ->add('url', UrlType::class, [
                'label' => 'Lien',
                'required' => false,
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => 'https://...',
                ],
                'label_attr' => [
                    'class' => $labelClass,
                ],
            ])
            ->add('category', EnumType::class, [
                'label' => 'Catégorie',
                'class' => ProductCategory::class,
                'choice_label' => fn (ProductCategory $category) => $category->toFrench(),
                'attr' => [
                    'class' => $inputClass . ' capitalize',
                ],
                'label_attr' => [
                    'class' => $labelClass,
                ],
            ])
            ->add('status', EnumType::class, [
                'label' => 'Statut',
                'class' => ProductStatus::class,
                'choice_label' => fn (ProductStatus $status) => $status->toFrench(),
                'attr' => [
                    'class' => $inputClass . ' capitalize',
                ],
                'label_attr' => [
                    'class' => $labelClass,
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Note',
                'required' => false,
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => 'Préférence de couleur, marque, taille...',
                ],
                'label_attr' => [
                    'class' => $labelClass,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
            'attr' => [
                'class' => 'flex flex-col gap-4',
            ],
        ]);
    }
}
