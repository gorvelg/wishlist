<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Wishlist;
use App\Enum\ProductStatus;
use App\Form\ProductType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class WishlistController extends AbstractController
{
    #[Route('/wishlist/{token}', name: 'app_home')]
    public function index(
        string $token,
        Request $request,
        SluggerInterface $slugger,
        EntityManagerInterface $em
    ): Response {

        $wishlist = $em->getRepository(Wishlist::class)->findOneBy(['accessToken' => $token]);
        if (!$wishlist) {
            throw $this->createNotFoundException();
        }

        // Création d'un nouveau produit

        $product = new Product();
        $product->setWishlist($wishlist);

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // upload de de l'image

            $image = $form->get('image')->getData();

            if ($image) {
                $originalFilename = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$image->guessExtension();

                try {
                    $image->move('uploads', $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error','Une erreur est survenue lors de l\'upload du fichier');
                }
                $product->setImage($newFilename);
            }


            $em->persist($product);
            $em->flush();
            return $this->redirectToRoute('app_home', [
                'token' => $wishlist->getAccessToken(),
            ]);
        }

        // tous les produits
        $products = $wishlist->getProducts();
        $countProducts = count($products);

        // produits achetés
        $giftedProducts = 0;
        foreach ($products as $product) {
            if ($product->getStatus() === ProductStatus::PURCHASED ){
                $giftedProducts++;
            }
        }

        // produits restants
        $remainingProducts = $countProducts - $giftedProducts;



        return $this->render('wishlist/index.html.twig', [
            'wishlist' => $wishlist,
            'products' => $products,
            'countProducts' => $countProducts,
            'giftedProducts' => $giftedProducts,
            'remainingProducts' => $remainingProducts,
            'form' => $form,
        ]);
    }
}
