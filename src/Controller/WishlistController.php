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
        /*
         * ===============================================
         * RÉCUPÉRATION DE LA WISHLIST
         * ===============================================
         */

        $wishlist = $em
            ->getRepository(Wishlist::class)
            ->findOneBy([
                'accessToken' => $token,
            ]);

        if (!$wishlist) {
            throw $this->createNotFoundException();
        }


        /*
         * ===============================================
         * VÉRIFICATION DU PROPRIÉTAIRE
         * ===============================================
         */

        $user = $this->getUser();

        $isOwner = $user !== null
            && $wishlist->getWishlistOwners()->exists(
                fn ($key, $wishlistOwner) =>
                    $wishlistOwner->getUser() === $user
            );


        /*
         * ===============================================
         * VARIABLES DE LA MODALE PRODUIT
         * ===============================================
         */

        $form = null;
        $openProductModal = false;
        $editingProduct = false;


        if ($isOwner) {

            /*
             * ===============================================
             * CRÉATION OU MODIFICATION ?
             * ===============================================
             *
             * Exemple :
             *
             * /wishlist/xxx
             * => création
             *
             * /wishlist/xxx?edit=42
             * => modification du produit 42
             */

            $editProductId = $request->query->getInt('edit');


            if ($editProductId > 0) {

                /*
                 * On récupère le produit existant.
                 */
                $product = $em
                    ->getRepository(Product::class)
                    ->find($editProductId);


                /*
                 * Sécurité :
                 *
                 * le produit doit exister ET appartenir
                 * à cette wishlist.
                 */
                if (
                    !$product
                    || $product->getWishlist() !== $wishlist
                ) {
                    throw $this->createNotFoundException();
                }


                /*
                 * Comme on modifie un produit,
                 * on ouvre automatiquement la modale.
                 */
                $editingProduct = true;
                $openProductModal = true;

            } else {

                /*
                 * Sinon on crée simplement un nouveau produit.
                 */
                $product = new Product();

                $product->setWishlist($wishlist);
            }


            /*
             * ===============================================
             * IMPORT D'UN PRODUIT DEPUIS UNE URL
             * ===============================================
             */

            $isImport = $request->isMethod('POST')
                && $request->request->has('import_product');


            if ($isImport) {

                $openProductModal = true;


                /*
                 * Vérification CSRF.
                 */
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


                    $product->setUrl(
                        $data['url']
                    );


                    if ($data['name'] !== null) {
                        $product->setName(
                            $data['name']
                        );
                    }


                    if ($data['price'] !== null) {
                        $product->setPrice(
                            $data['price']
                        );
                    }


                    if ($data['image'] !== null) {
                        $product->setImage(
                            $data['image']
                        );
                    }


                    $this->addFlash(
                        'success',
                        'Les informations du produit ont été récupérées.'
                    );

                } catch (\Throwable $e) {

                    $product->setUrl(
                        $url !== ''
                            ? $url
                            : null
                    );


                    $this->addFlash(
                        'error',
                        'Impossible de récupérer automatiquement ce produit.'
                    );
                }
            }


            /*
             * ===============================================
             * FORMULAIRE
             * ===============================================
             *
             * IMPORTANT :
             *
             * Ici ProductType ne sait pas s'il crée
             * ou modifie un produit.
             *
             * Symfony regarde simplement l'objet $product.
             *
             * Produit vide   => création
             * Produit rempli => modification
             */

            $form = $this->createForm(
                ProductType::class,
                $product
            );


            /*
             * Pendant l'import, on ne veut pas traiter
             * ProductType comme s'il avait été soumis.
             */
            if (!$isImport) {
                $form->handleRequest(
                    $request
                );
            }


            /*
             * ===============================================
             * ENREGISTREMENT
             * ===============================================
             */

            if (
                $form->isSubmitted()
                && $form->isValid()
            ) {

                /*
                 * Upload éventuel d'une nouvelle image.
                 */
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


                /*
                 * ===========================================
                 * NOUVEAU PRODUIT
                 * ===========================================
                 *
                 * Un produit qui n'a pas encore d'ID
                 * n'existe pas encore dans la base.
                 */

                if ($product->getId() === null) {

                    $em->persist(
                        $product
                    );

                } else {

                    /*
                     * =======================================
                     * PRODUIT EXISTANT
                     * =======================================
                     *
                     * Doctrine connaît déjà le produit.
                     * Pas besoin de persist().
                     */

                    $product->setUpdatedAt(
                        new \DateTimeImmutable()
                    );
                }


                $em->flush();


                /*
                 * Message différent suivant le cas.
                 */
                $this->addFlash(
                    'success',
                    $editingProduct
                        ? 'Le cadeau a bien été modifié.'
                        : 'Le cadeau a bien été ajouté.'
                );


                /*
                 * On revient à l'URL sans ?edit=42.
                 *
                 * La modale sera donc refermée.
                 */
                return $this->redirectToRoute(
                    'app_wishlist',
                    [
                        'token' =>
                            $wishlist->getAccessToken(),
                    ]
                );
            }


            /*
             * Si le formulaire contient une erreur,
             * on laisse la modale ouverte.
             */
            if (
                $form->isSubmitted()
                && !$form->isValid()
            ) {
                $openProductModal = true;
            }
        }


        /*
         * ===============================================
         * STATISTIQUES
         * ===============================================
         */

        $products = $wishlist->getProducts();

        $countProducts = count(
            $products
        );

        $giftedProducts = 0;


        foreach ($products as $wishlistProduct) {

            if (
                $wishlistProduct->getStatus()
                === ProductStatus::PURCHASED
            ) {
                $giftedProducts++;
            }
        }


        $remainingProducts =
            $countProducts
            - $giftedProducts;


        $openDiscussionId =
            $request->query->getInt('discussion');

        /*
         * ===============================================
         * AFFICHAGE
         * ===============================================
         */

        return $this->render(
            'wishlist/index.html.twig',
            [
                'wishlist' => $wishlist,
                'products' => $products,

                'countProducts' =>
                    $countProducts,

                'giftedProducts' =>
                    $giftedProducts,

                'remainingProducts' =>
                    $remainingProducts,

                'form' => $form,

                'isOwner' =>
                    $isOwner,

                'openProductModal' =>
                    $openProductModal,


                'editingProduct' =>
                    $editingProduct,

                'openDiscussionId' =>
                    $openDiscussionId,
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


        /*
         * ===============================================
         * VÉRIFICATION DU PRODUIT
         * ===============================================
         */

        if (
            $product->getWishlist()?->getAccessToken()
            !== $token
        ) {
            throw $this->createNotFoundException();
        }


        /*
         * ===============================================
         * CSRF
         * ===============================================
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
         * ===============================================
         * PRODUIT DÉJÀ ACHETÉ
         * ===============================================
         */

        if (
            $product->getStatus()
            === ProductStatus::PURCHASED
        ) {
            $this->addFlash(
                'error',
                'Ce cadeau a déjà été acheté.'
            );

            return $this->redirectToRoute(
                'app_wishlist',
                [
                    'token' => $token,
                ]
            );
        }


        /*
         * ===============================================
         * RÉCUPÉRATION DU MONTANT
         * ===============================================
         */

        $rawAmount = trim(
            (string) $request->request->get('amount')
        );

        /*
         * Permet aussi :
         *
         * 50,00
         *
         * au lieu de seulement :
         *
         * 50.00
         */
        $rawAmount = str_replace(
            ',',
            '.',
            $rawAmount
        );


        if (
            !is_numeric($rawAmount)
            || (float) $rawAmount <= 0
        ) {
            $this->addFlash(
                'error',
                'Le montant de la participation est invalide.'
            );

            return $this->redirectToRoute(
                'app_wishlist',
                [
                    'token' => $token,
                ]
            );
        }


        $amount = round(
            (float) $rawAmount,
            2
        );


        /*
         * ===============================================
         * PARTICIPATION EXISTANTE ?
         * ===============================================
         */

        $productUser = $em
            ->getRepository(ProductUser::class)
            ->findOneBy([
                'product' => $product,
                'user' => $user,
            ]);


        /*
         * ===============================================
         * CALCUL DU MONTANT DISPONIBLE
         * ===============================================
         *
         * Important lorsqu'on MODIFIE une participation.
         *
         * Exemple :
         *
         * cadeau = 500 €
         *
         * moi = 50 €
         * autres = 200 €
         *
         * je dois pouvoir modifier mes 50 €
         * jusqu'à 300 € maximum.
         */

        $otherContributions = 0.0;

        foreach ($product->getProductUsers() as $participation) {

            if (
                $productUser !== null
                && $participation->getId()
                === $productUser->getId()
            ) {
                continue;
            }

            $otherContributions +=
                (float) ($participation->getAmount() ?? 0);
        }


        $maxAmount = max(
            0,
            (float) $product->getPrice()
            - $otherContributions
        );


        /*
         * ===============================================
         * EMPÊCHER DE DÉPASSER LE PRIX
         * ===============================================
         */

        if ($amount > $maxAmount) {

            $this->addFlash(
                'error',
                sprintf(
                    'Vous pouvez participer au maximum à hauteur de %.2f €.',
                    $maxAmount
                )
            );

            return $this->redirectToRoute(
                'app_wishlist',
                [
                    'token' => $token,
                ]
            );
        }


        /*
         * ===============================================
         * CRÉATION
         * ===============================================
         */

        if ($productUser === null) {

            $productUser = new ProductUser();

            $productUser
                ->setProduct($product)
                ->setUser($user);

            $em->persist(
                $productUser
            );
        }


        /*
         * ===============================================
         * MONTANT
         * ===============================================
         */

        $productUser->setAmount(
            number_format(
                $amount,
                2,
                '.',
                ''
            )
        );

        /*
         * Dès qu'une première participation
         * avec un montant est enregistrée,
         * le cadeau devient collaboratif.
         */
        $product->setCollaborative(true);

        $product->setStatus(
            ProductStatus::BUYING
        );

        $em->flush();


        $this->addFlash(
            'success',
            'Votre participation a bien été enregistrée.'
        );


        return $this->redirectToRoute(
            'app_wishlist',
            [
                'token' => $token,
            ]
        );
    }

    #[Route(
        '/wishlist/{token}/product/{id}/participation/cancel',
        name: 'app_product_cancel_participation',
        methods: ['POST']
    )]
    public function cancelParticipation(
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
         * ===============================================
         * VÉRIFICATION DU PRODUIT
         * ===============================================
         */

        if (
            $product->getWishlist()?->getAccessToken()
            !== $token
        ) {
            throw $this->createNotFoundException();
        }


        /*
         * ===============================================
         * CSRF
         * ===============================================
         */

        if (
            !$this->isCsrfTokenValid(
                'cancel-participation-' . $product->getId(),
                (string) $request->request->get('_token')
            )
        ) {
            throw $this->createAccessDeniedException(
                'Jeton CSRF invalide.'
            );
        }


        /*
         * ===============================================
         * PARTICIPATION
         * ===============================================
         */

        $productUser = $em
            ->getRepository(ProductUser::class)
            ->findOneBy([
                'product' => $product,
                'user' => $user,
            ]);


        if ($productUser === null) {

            $this->addFlash(
                'error',
                'Vous ne participez pas à ce cadeau.'
            );

            return $this->redirectToRoute(
                'app_wishlist',
                [
                    'token' => $token,
                ]
            );
        }


        /*
         * ===============================================
         * SI L'UTILISATEUR ÉTAIT CHARGÉ DE L'ACHAT
         * ===============================================
         *
         * Un buyer doit obligatoirement être participant.
         *
         * Donc s'il annule sa participation,
         * il ne peut plus rester buyer.
         */

        if (
            $product->getBuyer()?->getId()
            === $user->getId()
        ) {
            $product->setBuyer(null);
        }


        /*
         * ===============================================
         * SUPPRESSION DE LA PARTICIPATION
         * ===============================================
         */

        $product->removeProductUser(
            $productUser
        );

        $em->remove(
            $productUser
        );


        /*
         * ===============================================
         * PLUS AUCUN PARTICIPANT
         * ===============================================
         *
         * Le cadeau revient complètement à son état initial.
         *
         * Il redevient :
         *
         * AVAILABLE
         * non collaboratif
         * sans buyer
         */

        if (
            $product
                ->getProductUsers()
                ->isEmpty()
        ) {
            $product->setStatus(
                ProductStatus::AVAILABLE
            );

            $product->setCollaborative(
                false
            );

            $product->setBuyer(
                null
            );
        }


        /*
         * ===============================================
         * ENREGISTREMENT
         * ===============================================
         */

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


        /*
         * ===============================================
         * VÉRIFICATION DU PRODUIT
         * ===============================================
         */

        if (
            $product->getWishlist()?->getAccessToken()
            !== $token
        ) {
            throw $this->createNotFoundException();
        }


        /*
         * ===============================================
         * CSRF
         * ===============================================
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
         * ===============================================
         * DÉJÀ ACHETÉ
         * ===============================================
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
         * ===============================================
         * LE PRODUIT DOIT ÊTRE EN COURS D'ACHAT
         * ===============================================
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
         * ===============================================
         * CADEAU COLLABORATIF
         * ===============================================
         *
         * Seule la personne désignée comme buyer
         * peut déclarer le cadeau acheté.
         */

        if ($product->isCollaborative()) {

            if ($product->getBuyer() === null) {

                $this->addFlash(
                    'error',
                    'Aucun participant ne s’est encore chargé de l’achat de ce cadeau.'
                );

                return $this->redirectToRoute(
                    'app_wishlist',
                    [
                        'token' => $token,
                    ]
                );
            }


            if (
                $product->getBuyer()?->getId()
                !== $user->getId()
            ) {

                $this->addFlash(
                    'error',
                    'Seule la personne chargée de l’achat peut marquer ce cadeau comme acheté.'
                );

                return $this->redirectToRoute(
                    'app_wishlist',
                    [
                        'token' => $token,
                    ]
                );
            }

        } else {

            /*
             * ===============================================
             * CADEAU OFFERT SEUL
             * ===============================================
             *
             * Il n'y a pas de buyer.
             *
             * La personne ayant réservé le cadeau
             * est automatiquement celle qui l'achète.
             */

            $productUser = $em
                ->getRepository(ProductUser::class)
                ->findOneBy([
                    'product' => $product,
                    'user' => $user,
                ]);


            if ($productUser === null) {

                $this->addFlash(
                    'error',
                    'Vous devez avoir choisi d’offrir ce cadeau avant de pouvoir le marquer comme acheté.'
                );

                return $this->redirectToRoute(
                    'app_wishlist',
                    [
                        'token' => $token,
                    ]
                );
            }
        }


        /*
         * ===============================================
         * MARQUER COMME ACHETÉ
         * ===============================================
         */

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


        /*
         * ===============================================
         * VÉRIFICATION DU PRODUIT
         * ===============================================
         */

        if (
            $product->getWishlist()?->getAccessToken()
            !== $token
        ) {
            throw $this->createNotFoundException();
        }


        /*
         * ===============================================
         * CSRF
         * ===============================================
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
         * ===============================================
         * LE CADEAU DOIT ÊTRE ACHETÉ
         * ===============================================
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


        /*
         * ===============================================
         * CADEAU COLLABORATIF
         * ===============================================
         *
         * Seul le buyer peut annuler l'achat.
         */

        if ($product->isCollaborative()) {

            if (
                $product->getBuyer() === null
                || $product->getBuyer()?->getId() !== $user->getId()
            ) {
                $this->addFlash(
                    'error',
                    'Seule la personne chargée de l’achat peut annuler cet achat.'
                );

                return $this->redirectToRoute(
                    'app_wishlist',
                    [
                        'token' => $token,
                    ]
                );
            }

        } else {

            /*
             * ===============================================
             * CADEAU SOLO
             * ===============================================
             *
             * La personne qui offre doit être celle
             * ayant réservé le cadeau.
             */

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
        }


        /*
         * ===============================================
         * REMETTRE LE CADEAU EN COURS D'ACHAT
         * ===============================================
         */

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


    #[Route(
        '/wishlist/{token}/product/{id}/offer',
        name: 'app_product_offer',
        methods: ['POST']
    )]
    public function offer(
        string $token,
        Product $product,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();

        if ($product->getWishlist()?->getAccessToken() !== $token) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid(
            'offer-' . $product->getId(),
            (string) $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Jeton CSRF invalide.'
            );
        }

        if (
            $product->getStatus() === ProductStatus::PURCHASED
            || $product->isCollaborative()
            || !$product->getProductUsers()->isEmpty()
        ) {
            $this->addFlash(
                'error',
                'Ce cadeau ne peut plus être offert individuellement.'
            );

            return $this->redirectToRoute('app_wishlist', [
                'token' => $token,
            ]);
        }

        $productUser = new ProductUser();

        $productUser
            ->setProduct($product)
            ->setUser($user)
            ->setAmount($product->getPrice());

        $product->setStatus(
            ProductStatus::BUYING
        );

        $em->persist($productUser);
        $em->flush();

        $this->addFlash(
            'success',
            'Vous avez choisi d’offrir ce cadeau.'
        );

        return $this->redirectToRoute('app_wishlist', [
            'token' => $token,
        ]);
    }
}
