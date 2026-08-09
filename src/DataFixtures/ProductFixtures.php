<?php

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\Wishlist;
use App\Enum\ProductCategory;
use App\Enum\ProductStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ProductFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $wishlist1 = new Wishlist();
        $wishlist1->setName('Wishlist de Guillaume et Léa');

        $wishlist2 = new Wishlist();
        $wishlist2->setName('Wishlist de Becky');

        $manager->persist($wishlist1);
        $manager->persist($wishlist2);

        $manager->flush();

        $products = [
            [
                'name' => 'Camis',
                'url' => 'https://www.camis.fr/',
                'price' => 50.99,
                'image' => 'camis.jpg',
                'category' => ProductCategory::AWAKENING,
                'status' => ProductStatus::AVAILABLE,
                'wishlist' => $wishlist1,
            ],
            [
                'name' => 'Biberon',
                'url' => 'https://www.biberon.fr/',
                'price' => 50.99,
                'image' => 'biberon.jpg',
                'category' => ProductCategory::MEAL,
                'status' => ProductStatus::AVAILABLE,
                'wishlist' => $wishlist1,
            ],
            [
                'name' => 'Poussette',
                'url' => 'https://www.poussette.fr/',
                'price' => 250.99,
                'image' => 'poussette.jpg',
                'category' => ProductCategory::TRAVEL,
                'status' => ProductStatus::AVAILABLE,
                'wishlist' => $wishlist1,
            ],
            [
                'name' => 'Couches',
                'url' => 'https://www.couches.fr/',
                'price' => 30.99,
                'image' => 'couches.jpg',
                'category' => ProductCategory::HYGIENE,
                'status' => ProductStatus::AVAILABLE,
                'wishlist' => $wishlist2,
            ]

        ];

        foreach ($products as $productData) {
            $product = new Product();
            $product->setName($productData['name']);
            $product->setUrl($productData['url']);
            $product->setPrice($productData['price']);
            $product->setImage($productData['image']);
            $product->setCategory($productData['category']);
            $product->setStatus($productData['status']);
            $product->setWishlist($productData['wishlist']);
            $manager->persist($product);
        }

        $manager->flush();
    }
}
