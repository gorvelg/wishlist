<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductUser;
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
    #[Route('/wishlist/{token}', name: 'app_wishlist')]
    public function index(
        string $token,
        Request $request,
        SluggerInterface $slugger,
        EntityManagerInterface $em
    ): Response {
        $wishlist = $em->getRepository(Wishlist::class)
            ->findOneBy(['accessToken' => $token]);

        if (!$wishlist) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();

        $isOwner = $user !== null
            && $wishlist->getWishlistOwners()->exists(
                fn ($key, $wishlistOwner) => $wishlistOwner->getUser() === $user
            );

        $form = null;

        if ($isOwner) {
            $product = new Product();
            $product->setWishlist($wishlist);

            $form = $this->createForm(ProductType::class, $product);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $image = $form->get('image')->getData();

                if ($image) {
                    $originalFilename = pathinfo(
                        $image->getClientOriginalName(),
                        PATHINFO_FILENAME
                    );

                    $safeFilename = $slugger->slug($originalFilename);

                    $newFilename = $safeFilename
                        . '-'
                        . uniqid()
                        . '.'
                        . $image->guessExtension();

                    try {
                        $image->move('uploads', $newFilename);
                        $product->setImage($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash(
                            'error',
                            'Une erreur est survenue lors de l\'upload du fichier'
                        );
                    }
                }

                $em->persist($product);
                $em->flush();

                return $this->redirectToRoute('app_wishlist', [
                    'token' => $wishlist->getAccessToken(),
                ]);
            }
        }

        $products = $wishlist->getProducts();

        $countProducts = count($products);

        $giftedProducts = 0;

        foreach ($products as $product) {
            if ($product->getStatus() === ProductStatus::PURCHASED) {
                $giftedProducts++;
            }
        }

        $remainingProducts = $countProducts - $giftedProducts;

        return $this->render('wishlist/index.html.twig', [
            'wishlist' => $wishlist,
            'products' => $products,
            'countProducts' => $countProducts,
            'giftedProducts' => $giftedProducts,
            'remainingProducts' => $remainingProducts,
            'form' => $form,
            'isOwner' => $isOwner,
        ]);
    }

    #[Route(
        '/wishlist/{token}/product/{id}/purchase',
        name: 'app_product_purchase',
        methods: ['POST']
    )]
    public function purchase(
        string $token,
        Product $product,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();

        if ($product->getWishlist()?->getAccessToken() !== $token) {
            throw $this->createNotFoundException();
        }

        $existingProductUser = $em
            ->getRepository(ProductUser::class)
            ->findOneBy([
                'product' => $product,
                'User' => $user,
            ]);

        if ($existingProductUser !== null) {
            $product->removeProductUser($existingProductUser);

            $em->remove($existingProductUser);

            if ($product->getProductUsers()->isEmpty()) {
                $product->setStatus(ProductStatus::AVAILABLE);
            } else {
                $product->setStatus(ProductStatus::BUYING);
            }

            $em->flush();

            $this->addFlash(
                'success',
                'Votre réservation a été annulée.'
            );

            return $this->redirectToRoute('app_wishlist', [
                'token' => $token,
            ]);
        }

        // Si le produit est définitivement acheté
        if ($product->getStatus() === ProductStatus::PURCHASED) {
            $this->addFlash(
                'error',
                'Ce produit a déjà été offert.'
            );

            return $this->redirectToRoute('app_wishlist', [
                'token' => $token,
            ]);
        }

        $productUser = new ProductUser();

        $productUser
            ->setProduct($product)
            ->setUser($user);

        $product->setStatus(ProductStatus::BUYING);

        $em->persist($productUser);
        $em->flush();

        $this->addFlash(
            'success',
            'Le cadeau a bien été réservé.'
        );

        return $this->redirectToRoute('app_wishlist', [
            'token' => $token,
        ]);
    }
}
