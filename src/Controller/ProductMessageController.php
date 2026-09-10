<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductMessage;
use App\Entity\ProductUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductMessageController extends AbstractController
{
    #[Route(
        '/wishlist/{token}/product/{id}/message',
        name: 'app_product_message',
        methods: ['POST']
    )]
    public function create(
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
         * VÉRIFIER LA WISHLIST
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
         * UNIQUEMENT CADEAU COLLABORATIF
         * ===============================================
         */

        if (!$product->isCollaborative()) {
            throw $this->createAccessDeniedException(
                'Cette discussion n’est pas disponible.'
            );
        }


        /*
         * ===============================================
         * VÉRIFIER QUE L'UTILISATEUR PARTICIPE
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
                'Vous devez participer à ce cadeau pour accéder à la discussion.'
            );
        }


        /*
         * ===============================================
         * CSRF
         * ===============================================
         */

        if (!$this->isCsrfTokenValid(
            'product-message-' . $product->getId(),
            (string) $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Jeton CSRF invalide.'
            );
        }


        /*
         * ===============================================
         * MESSAGE
         * ===============================================
         */

        $content = trim(
            (string) $request->request->get('content')
        );


        if ($content === '') {
            $this->addFlash(
                'error',
                'Votre message ne peut pas être vide.'
            );

            return $this->redirectToRoute(
                'app_wishlist',
                [
                    'token' => $token,
                    'discussion' => $product->getId(),
                ]
            );
        }


        if (mb_strlen($content) > 1000) {
            $this->addFlash(
                'error',
                'Votre message ne peut pas dépasser 1000 caractères.'
            );

            return $this->redirectToRoute(
                'app_wishlist',
                [
                    'token' => $token,
                    'discussion' => $product->getId(),
                ]
            );
        }


        $message = new ProductMessage();

        $message
            ->setProduct($product)
            ->setUser($user)
            ->setContent($content);


        $em->persist($message);
        $em->flush();


        /*
         * discussion=id permet de rouvrir
         * automatiquement la modale.
         */
        return $this->redirectToRoute(
            'app_wishlist',
            [
                'token' => $token,
                'discussion' => $product->getId(),
            ]
        );
    }
}
