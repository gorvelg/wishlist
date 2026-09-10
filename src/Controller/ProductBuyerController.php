<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductUser;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductBuyerController extends AbstractController
{
    #[Route(
        '/wishlist/{token}/product/{id}/buyer',
        name: 'app_product_buyer',
        methods: ['POST']
    )]
    public function becomeBuyer(
        string $token,
        Product $product,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }


        /*
         * ===============================================
         * VÉRIFIER LA WISHLIST
         * ===============================================
         */

        if ($product->getWishlist()?->getAccessToken() !== $token) {
            throw $this->createNotFoundException();
        }


        /*
         * ===============================================
         * UNIQUEMENT CADEAU COLLABORATIF
         * ===============================================
         */

        if (!$product->isCollaborative()) {
            throw $this->createAccessDeniedException(
                'Ce cadeau n’est pas collaboratif.'
            );
        }


        /*
         * ===============================================
         * PRODUIT NON ACHETÉ
         * ===============================================
         */

        if ($product->getStatus()->value === 'purchased') {
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
         * CSRF
         * ===============================================
         */

        if (!$this->isCsrfTokenValid(
            'buyer-' . $product->getId(),
            (string) $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Jeton CSRF invalide.'
            );
        }


        /*
         * ===============================================
         * L'UTILISATEUR DOIT PARTICIPER
         * ===============================================
         */

        $participation = $em
            ->getRepository(ProductUser::class)
            ->findOneBy([
                'product' => $product,
                'user' => $user,
            ]);

        if ($participation === null) {
            throw $this->createAccessDeniedException(
                'Vous devez participer à ce cadeau pour vous charger de son achat.'
            );
        }


        /*
         * ===============================================
         * UN ACHETEUR EXISTE DÉJÀ
         * ===============================================
         */

        if (
            $product->getBuyer() !== null
            && $product->getBuyer()?->getId() !== $user->getId()
        ) {
            $this->addFlash(
                'error',
                'Un autre participant se charge déjà de cet achat.'
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
         * DEVENIR ACHETEUR
         * ===============================================
         */

        $product->setBuyer($user);

        $em->flush();

        $this->addFlash(
            'success',
            'Vous vous chargez maintenant de l’achat de ce cadeau.'
        );

        return $this->redirectToRoute(
            'app_wishlist',
            [
                'token' => $token,
            ]
        );
    }


    #[Route(
        '/wishlist/{token}/product/{id}/buyer/cancel',
        name: 'app_product_buyer_cancel',
        methods: ['POST']
    )]
    public function cancelBuyer(
        string $token,
        Product $product,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }


        /*
         * ===============================================
         * VÉRIFIER LA WISHLIST
         * ===============================================
         */

        if ($product->getWishlist()?->getAccessToken() !== $token) {
            throw $this->createNotFoundException();
        }


        /*
         * ===============================================
         * CSRF
         * ===============================================
         */

        if (!$this->isCsrfTokenValid(
            'cancel-buyer-' . $product->getId(),
            (string) $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Jeton CSRF invalide.'
            );
        }


        /*
         * ===============================================
         * SEUL L'ACHETEUR PEUT ANNULER
         * ===============================================
         */

        if ($product->getBuyer()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException(
                'Vous n’êtes pas chargé de cet achat.'
            );
        }


        /*
         * ===============================================
         * RETIRER L'ACHETEUR
         * ===============================================
         */

        $product->setBuyer(null);

        $em->flush();

        $this->addFlash(
            'success',
            'Vous ne vous chargez plus de l’achat de ce cadeau.'
        );

        return $this->redirectToRoute(
            'app_wishlist',
            [
                'token' => $token,
            ]
        );
    }
}
