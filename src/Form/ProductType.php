<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Enum\ProductCategory;
use App\Enum\ProductStatus;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'ex: Poussette',
                    'class' => 'w-full rounded-xl border border-[rgba(45,45,45,0.15)] px-4 py-2.5 text-[#2D2D2D] text-sm focus:outline-none focus:ring-2 focus:ring-[#4ECDC4]/60 transition'

                ],
                'label_attr' => [
                    'class' => 'text-sm font-medium text-[#6B6B6B] mb-1 block'
                ],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Prix',
                'attr' => [
                    'placeholder' => 'ex: 10,99',
                    'class' => 'w-full rounded-xl border border-[rgba(45,45,45,0.15)] px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#4ECDC4]/60 bg-white appearance-none cursor-pointer'
                ],
                'label_attr' => [
                    'class' => 'text-sm font-medium text-[#6B6B6B] mb-1 block'
                ]
            ])
            ->add('image', FileType::class, [
                'label' => 'Image',
                'attr' => [
                    'accept' => 'image/*',
                    'multiple' => false,
                    'class' => 'w-full rounded-xl border border-[rgba(45,45,45,0.15)] px-4 py-2.5 text-[#2D2D2D] text-sm focus:outline-none focus:ring-2 focus:ring-[#4ECDC4]/60 transition'
                ],
                'label_attr' => [
                    'class' => 'text-sm font-medium text-[#6B6B6B] mb-1 block'
                ]
            ])
            ->add('url', TextType::class, [
                'label' => 'Lien',
                'attr' => [
                    'class' => 'w-full rounded-xl border border-[rgba(45,45,45,0.15)] px-4 py-2.5 text-[#2D2D2D] text-sm focus:outline-none focus:ring-2 focus:ring-[#4ECDC4]/60 transition',
                    'placeholder' => 'https://...'
                ],
                'label_attr' => [
                    'class' => 'text-sm font-medium text-[#6B6B6B] mb-1 block'
                ]
            ])
            ->add('category', EnumType::class, [
                'label' => 'Categorie',
                'class' => ProductCategory::class,
                'choice_label' => fn (ProductCategory $category) => $category->toFrench(),
                'attr' => [
                    'class' => 'w-full rounded-xl border border-[rgba(45,45,45,0.15)] px-4 py-2.5 text-[#2D2D2D] text-sm capitalize focus:outline-none focus:ring-2 focus:ring-[#4ECDC4]/60 transition',
                ],
                'label_attr' => [
                    'class' => 'text-sm font-medium text-[#6B6B6B] mb-1 block'
                ]
            ])
            ->add('status', EnumType::class, [
                'label' => 'Statut',
                'class' => ProductStatus::class,
                'choice_label' => fn (ProductStatus $status) => $status->toFrench(),
                'attr' => [
                    'class' => 'w-full rounded-xl border border-[rgba(45,45,45,0.15)] px-4 py-2.5 text-[#2D2D2D] capitalize text-sm focus:outline-none focus:ring-2 focus:ring-[#4ECDC4]/60 transition'
                ],
                'label_attr' => [
                    'class' => 'text-sm font-medium text-[#6B6B6B] mb-1 block'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Note',
                'attr' => [
                    'class' => 'w-full rounded-xl border border-[rgba(45,45,45,0.15)] px-4 py-2.5 text-[#2D2D2D] text-sm focus:outline-none focus:ring-2 focus:ring-[#4ECDC4]/60 transition',
                    'placeholder' => 'Préférence de couleur, marque, taille...'
                ],
                'label_attr' => [
                    'class' => 'text-sm font-medium text-[#6B6B6B] mb-1 block'
                ],
                'required' => false
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
            'attr' => [
                'class' => 'flex flex-col gap-4'
            ]
        ]);
    }
}
