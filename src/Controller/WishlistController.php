<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductUser;
use App\Entity\Wishlist;
use App\Entity\WishlistOwner;
use App\Enum\ProductStatus;
use App\Form\ProductType;
use App\Service\ProductImporter;
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
        EntityManagerInterface $em,
        ProductImporter $productImporter,
    ): Response {
        $wishlist = $em
            ->getRepository(Wishlist::class)
            ->findOneBy([
                'accessToken' => $token,
            ]);

        if (!$wishlist) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();

        $isOwner = $user !== null
            && $wishlist->getWishlistOwners()->exists(
                fn ($key, $wishlistOwner) =>
                    $wishlistOwner->getUser() === $user
            );

        $form = null;
        $openProductModal = false;

        if ($isOwner) {
            $product = new Product();
            $product->setWishlist($wishlist);

            $isImport = $request->isMethod('POST')
                && $request->request->has('import_product');

            if ($isImport) {
                $openProductModal = true;

                if (
                    !$this->isCsrfTokenValid(
                        'import-product-' . $wishlist->getAccessToken(),
                        (string) $request->request->get('_token')
                    )
                ) {
                    throw $this->createAccessDeniedException(
                        'Jeton CSRF invalide.'
                    );
                }

                $url = trim(
                    (string) $request->request->get('product_url')
                );

                try {
                    $data = $productImporter->extract($url);

                    $product->setUrl($data['url']);

                    if ($data['name'] !== null) {
                        $product->setName($data['name']);
                    }

                    if ($data['price'] !== null) {
                        $product->setPrice($data['price']);
                    }

                    if ($data['image'] !== null) {
                        $product->setImage($data['image']);
                    }

                    $this->addFlash(
                        'success',
                        'Les informations du produit ont été récupérées.'
                    );
                } catch (\Throwable $e) {
                    $product->setUrl($url !== '' ? $url : null);

                    $this->addFlash(
                        'error',
                        'Impossible de récupérer automatiquement ce produit.'
                    );
                }
            }

            $form = $this->createForm(
                ProductType::class,
                $product
            );

            if (!$isImport) {
                $form->handleRequest($request);
            }

            if (
                $form->isSubmitted()
                && $form->isValid()
            ) {
                $imageFile = $form
                    ->get('imageFile')
                    ->getData();

                if ($imageFile) {
                    $originalFilename = pathinfo(
                        $imageFile->getClientOriginalName(),
                        PATHINFO_FILENAME
                    );

                    $safeFilename = $slugger->slug(
                        $originalFilename
                    );

                    $newFilename =
                        $safeFilename
                        . '-'
                        . uniqid()
                        . '.'
                        . $imageFile->guessExtension();

                    try {
                        $imageFile->move(
                            'uploads',
                            $newFilename
                        );

                        $product->setImage(
                            $newFilename
                        );
                    } catch (FileException $e) {
                        $this->addFlash(
                            'error',
                            "Une erreur est survenue lors de l'upload du fichier."
                        );

                        $openProductModal = true;
                    }
                }

                $em->persist($product);
                $em->flush();

                return $this->redirectToRoute(
                    'app_wishlist',
                    [
                        'token' => $wishlist->getAccessToken(),
                    ]
                );
            }

            if ($form->isSubmitted() && !$form->isValid()) {
                $openProductModal = true;
            }


        }

        /*
         * Statistiques
         */
        $products = $wishlist->getProducts();

        $countProducts = count($products);

        $giftedProducts = 0;

        foreach ($products as $product) {
            if (
                $product->getStatus()
                === ProductStatus::PURCHASED
            ) {
                $giftedProducts++;
            }
        }

        $remainingProducts =
            $countProducts - $giftedProducts;

        return $this->render(
            'wishlist/index.html.twig',
            [
                'wishlist' => $wishlist,
                'products' => $products,
                'countProducts' => $countProducts,
                'giftedProducts' => $giftedProducts,
                'remainingProducts' => $remainingProducts,
                'form' => $form,
                'isOwner' => $isOwner,
                'openProductModal' => $openProductModal,
            ]
        );
    }

    #[Route(
        '/wishlist/{token}/product/{id}/purchase',
        name: 'app_product_purchase',
        methods: ['POST']
    )]
    public function purchase(
        string $token,
        Product $product,
        Request $request,
        EntityManagerInterface $em,
    ): Response {

        $this->denyAccessUnlessGranted(
            'IS_AUTHENTICATED_FULLY'
        );

        $user = $this->getUser();

        if (
            $product->getWishlist()?->getAccessToken()
            !== $token
        ) {
            throw $this->createNotFoundException();
        }

        if (
            !$this->isCsrfTokenValid(
                'purchase-' . $product->getId(),
                (string) $request->request->get('_token')
            )
        ) {
            throw $this->createAccessDeniedException(
                'Jeton CSRF invalide.'
            );
        }

        if (
            $product->getStatus()
            === ProductStatus::PURCHASED
        ) {
            $this->addFlash(
                'error',
                'Ce produit a déjà été offert.'
            );

            return $this->redirectToRoute(
                'app_wishlist',
                [
                    'token' => $token,
                ]
            );
        }

        $existingProductUser = $em
            ->getRepository(ProductUser::class)
            ->findOneBy([
                'product' => $product,
                'user' => $user,
            ]);

        if ($existingProductUser !== null) {

            $product->removeProductUser(
                $existingProductUser
            );

            $em->remove(
                $existingProductUser
            );

            if (
                $product
                    ->getProductUsers()
                    ->isEmpty()
            ) {
                $product->setStatus(
                    ProductStatus::AVAILABLE
                );
            } else {
                $product->setStatus(
                    ProductStatus::BUYING
                );
            }

            $em->flush();

            $this->addFlash(
                'success',
                'Votre participation a été annulée.'
            );

            return $this->redirectToRoute(
                'app_wishlist',
                [
                    'token' => $token,
                ]
            );
        }

        $productUser = new ProductUser();

        $productUser
            ->setProduct($product)
            ->setUser($user);


        $product->setStatus(
            ProductStatus::BUYING
        );

        $em->persist(
            $productUser
        );

        $em->flush();

        $this->addFlash(
            'success',
            'Vous participez maintenant à ce cadeau.'
        );

        return $this->redirectToRoute(
            'app_wishlist',
            [
                'token' => $token,
            ]
        );
    }

    #[Route(
        '/wishlist/{token}/product/{id}/purchased',
        name: 'app_product_mark_purchased',
        methods: ['POST']
    )]
    public function markPurchased(
        string $token,
        Product $product,
        Request $request,
        EntityManagerInterface $em,
    ): Response {

        $this->denyAccessUnlessGranted(
            'IS_AUTHENTICATED_FULLY'
        );

        $user = $this->getUser();

        if (
            $product->getWishlist()?->getAccessToken()
            !== $token
        ) {
            throw $this->createNotFoundException();
        }

        if (
            !$this->isCsrfTokenValid(
                'product-purchased-' . $product->getId(),
                (string) $request->request->get('_token')
            )
        ) {
            throw $this->createAccessDeniedException(
                'Jeton CSRF invalide.'
            );
        }

        if (
            $product->getStatus()
            === ProductStatus::PURCHASED
        ) {
            $this->addFlash(
                'info',
                'Ce cadeau a déjà été marqué comme acheté.'
            );

            return $this->redirectToRoute(
                'app_wishlist',
                [
                    'token' => $token,
                ]
            );
        }


        $productUser = $em
            ->getRepository(ProductUser::class)
            ->findOneBy([
                'product' => $product,
                'user' => $user,
            ]);

        if ($productUser === null) {
            $this->addFlash(
                'error',
                'Vous devez participer à ce cadeau avant de pouvoir le marquer comme acheté.'
            );

            return $this->redirectToRoute(
                'app_wishlist',
                [
                    'token' => $token,
                ]
            );
        }

        if (
            $product->getStatus()
            !== ProductStatus::BUYING
        ) {
            $this->addFlash(
                'error',
                'Ce produit ne peut pas être marqué comme acheté.'
            );

            return $this->redirectToRoute(
                'app_wishlist',
                [
                    'token' => $token,
                ]
            );
        }

        $product->setStatus(
            ProductStatus::PURCHASED
        );

        $em->flush();

        $this->addFlash(
            'success',
            'Le cadeau a été marqué comme acheté.'
        );

        return $this->redirectToRoute(
            'app_wishlist',
            [
                'token' => $token,
            ]
        );
    }

    #[Route(
        '/wishlist/{token}/product/{id}/cancel-purchased',
        name: 'app_product_cancel_purchased',
        methods: ['POST']
    )]
    public function cancelPurchased(
        string $token,
        Product $product,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted(
            'IS_AUTHENTICATED_FULLY'
        );

        $user = $this->getUser();

        if (
            $product->getWishlist()?->getAccessToken()
            !== $token
        ) {
            throw $this->createNotFoundException();
        }

        if (
            !$this->isCsrfTokenValid(
                'cancel-purchased-' . $product->getId(),
                (string) $request->request->get('_token')
            )
        ) {
            throw $this->createAccessDeniedException(
                'Jeton CSRF invalide.'
            );
        }

        if (
            $product->getStatus()
            !== ProductStatus::PURCHASED
        ) {
            $this->addFlash(
                'error',
                'Ce cadeau n\'est pas marqué comme acheté.'
            );

            return $this->redirectToRoute(
                'app_wishlist',
                [
                    'token' => $token,
                ]
            );
        }

        $productUser = $em
            ->getRepository(ProductUser::class)
            ->findOneBy([
                'product' => $product,
                'user' => $user,
            ]);

        if ($productUser === null) {
            $this->addFlash(
                'error',
                'Vous ne pouvez pas modifier ce cadeau.'
            );

            return $this->redirectToRoute(
                'app_wishlist',
                [
                    'token' => $token,
                ]
            );
        }

        $product->setStatus(
            ProductStatus::BUYING
        );

        $em->flush();

        $this->addFlash(
            'success',
            'Le cadeau a été remis en cours d\'achat.'
        );

        return $this->redirectToRoute(
            'app_wishlist',
            [
                'token' => $token,
            ]
        );
    }


    #[Route('/product/{id}/delete', name: 'app_product_delete', methods: ['POST'])]
    public function delete(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $wishlist = $product->getWishlist();

        // 1. Vérification du propriétaire

        $isOwner = $wishlist->getWishlistOwners()->exists(
            fn (int $key, WishlistOwner $owner) =>
                $owner->getUser() === $this->getUser()
        );

        if (!$isOwner) {
            throw $this->createAccessDeniedException(
                'Vous ne pouvez pas supprimer ce produit.'
            );
        }

        // 2. Vérification CSRF
        if (!$this->isCsrfTokenValid(
            'delete_product_' . $product->getId(),
            $request->getPayload()->getString('_token')
        )) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        // 3. Suppression
        $entityManager->remove($product);
        $entityManager->flush();

        $this->addFlash('success', 'Produit supprimé.');

        return $this->redirectToRoute('app_wishlist', [
            'token' => $wishlist->getAccessToken(),
        ]);
    }
}
