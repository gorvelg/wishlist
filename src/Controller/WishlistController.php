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
        $wishlist = $em
            ->getRepository(Wishlist::class)
            ->findOneBy([
                'accessToken' => $token,
            ]);

        if (!$wishlist) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();

        /*
         * Vérifie si l'utilisateur connecté
         * est propriétaire de la wishlist.
         */
        $isOwner = $user !== null
            && $wishlist->getWishlistOwners()->exists(
                fn ($key, $wishlistOwner) =>
                    $wishlistOwner->getUser() === $user
            );

        /*
         * Le formulaire d'ajout de produit
         * n'est disponible que pour les propriétaires.
         */
        $form = null;

        if ($isOwner) {
            $product = new Product();

            $product->setWishlist($wishlist);

            $form = $this->createForm(
                ProductType::class,
                $product
            );

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $image = $form
                    ->get('image')
                    ->getData();

                if ($image) {
                    $originalFilename = pathinfo(
                        $image->getClientOriginalName(),
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
                        . $image->guessExtension();

                    try {
                        $image->move(
                            'uploads',
                            $newFilename
                        );

                        $product->setImage(
                            $newFilename
                        );
                    } catch (FileException $e) {
                        $this->addFlash(
                            'error',
                            'Une erreur est survenue lors de l\'upload du fichier.'
                        );
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
            ]
        );
    }

    /*
     * ============================================================
     * CLAIM / ANNULER UN CLAIM
     * ============================================================
     *
     * Premier clic :
     * available -> buying
     * création ProductUser
     *
     * Deuxième clic :
     * suppression ProductUser
     *
     * Si plus aucun participant :
     * buying -> available
     */
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
        /*
         * L'utilisateur doit être connecté.
         */
        $this->denyAccessUnlessGranted(
            'IS_AUTHENTICATED_FULLY'
        );

        $user = $this->getUser();

        /*
         * Sécurité :
         * vérifie que le produit appartient bien
         * à la wishlist correspondant au token.
         */
        if (
            $product->getWishlist()?->getAccessToken()
            !== $token
        ) {
            throw $this->createNotFoundException();
        }

        /*
         * Vérification du CSRF.
         */
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

        /*
         * Un produit PURCHASED est verrouillé.
         *
         * On fait cette vérification AVANT de chercher
         * le ProductUser pour empêcher également
         * un participant d'annuler après achat.
         */
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

        /*
         * Cherche si l'utilisateur participe déjà.
         *
         * IMPORTANT :
         * c'est "user" et non "User".
         */
        $existingProductUser = $em
            ->getRepository(ProductUser::class)
            ->findOneBy([
                'product' => $product,
                'user' => $user,
            ]);

        /*
         * ========================================================
         * L'utilisateur participe déjà :
         * on annule sa participation.
         * ========================================================
         */
        if ($existingProductUser !== null) {
            /*
             * Retire également l'objet de la collection Product
             * afin que getProductUsers() soit immédiatement à jour.
             */
            $product->removeProductUser(
                $existingProductUser
            );

            $em->remove(
                $existingProductUser
            );

            /*
             * Si c'était le dernier participant,
             * le produit redevient disponible.
             */
            if (
                $product
                    ->getProductUsers()
                    ->isEmpty()
            ) {
                $product->setStatus(
                    ProductStatus::AVAILABLE
                );
            } else {
                /*
                 * Il reste d'autres participants.
                 */
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

        /*
         * ========================================================
         * L'utilisateur ne participe pas encore :
         * création du ProductUser.
         * ========================================================
         */
        $productUser = new ProductUser();

        $productUser
            ->setProduct($product)
            ->setUser($user);

        /*
         * Dès qu'au moins une personne participe,
         * le cadeau passe en BUYING.
         */
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

    /*
     * ============================================================
     * MARQUER LE PRODUIT COMME ACHETÉ
     * ============================================================
     *
     * Disponible uniquement si :
     *
     * 1. utilisateur connecté
     * 2. produit appartenant à la wishlist
     * 3. produit non déjà purchased
     * 4. utilisateur présent dans product_user
     *
     * Donc :
     *
     * USER doit avoir CLAIM avant de pouvoir confirmer l'achat.
     */
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
        /*
         * Connexion obligatoire.
         */
        $this->denyAccessUnlessGranted(
            'IS_AUTHENTICATED_FULLY'
        );

        $user = $this->getUser();

        /*
         * Vérifie que le produit correspond bien
         * à la wishlist visible.
         */
        if (
            $product->getWishlist()?->getAccessToken()
            !== $token
        ) {
            throw $this->createNotFoundException();
        }

        /*
         * Protection CSRF.
         */
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

        /*
         * Le produit est déjà acheté.
         */
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

        /*
         * ========================================================
         * LA VÉRIFICATION IMPORTANTE
         * ========================================================
         *
         * Existe-t-il une ligne :
         *
         * product_id = ce produit
         * user_id    = utilisateur connecté
         *
         * dans product_user ?
         */
        $productUser = $em
            ->getRepository(ProductUser::class)
            ->findOneBy([
                'product' => $product,
                'user' => $user,
            ]);

        /*
         * Aucun ProductUser :
         *
         * l'utilisateur n'a jamais claim ce cadeau.
         *
         * Il n'a donc PAS le droit de le passer
         * en PURCHASED.
         */
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

        /*
         * Normalement, si ProductUser existe,
         * le produit doit être BUYING.
         *
         * Cette vérification protège contre
         * un éventuel état incohérent.
         */
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

        /*
         * ========================================================
         * Tout est OK :
         *
         * BUYING -> PURCHASED
         * ========================================================
         */
        $product->setStatus(
            ProductStatus::PURCHASED
        );

        /*
         * IMPORTANT :
         *
         * On NE SUPPRIME PAS les ProductUser.
         *
         * Ils permettent de conserver la liste
         * des personnes ayant participé au cadeau.
         */
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

        /*
         * Le produit doit appartenir à la wishlist.
         */
        if (
            $product->getWishlist()?->getAccessToken()
            !== $token
        ) {
            throw $this->createNotFoundException();
        }

        /*
         * Vérification CSRF.
         */
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

        /*
         * On ne peut annuler que si le produit
         * est actuellement PURCHASED.
         */
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

        /*
         * Pas participant = pas le droit d'annuler.
         */
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
}
