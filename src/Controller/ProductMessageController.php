<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductMessage;
use App\Entity\ProductUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

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
        HubInterface $hub,
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

        if (
            !$this->isCsrfTokenValid(
                'product-message-' . $product->getId(),
                (string) $request->request->get('_token')
            )
        ) {
            throw $this->createAccessDeniedException(
                'Jeton CSRF invalide.'
            );
        }


        /*
         * ===============================================
         * CONTENU DU MESSAGE
         * ===============================================
         */

        $content = trim(
            (string) $request->request->get('content')
        );


        /*
         * ===============================================
         * MESSAGE VIDE
         * ===============================================
         */

        if ($content === '') {

            return $this->messageError(
                $request,
                $product,
                $token,
                'Votre message ne peut pas être vide.'
            );
        }


        /*
         * ===============================================
         * MESSAGE TROP LONG
         * ===============================================
         */

        if (mb_strlen($content) > 1000) {

            return $this->messageError(
                $request,
                $product,
                $token,
                'Votre message ne peut pas dépasser 1000 caractères.'
            );
        }


        /*
         * ===============================================
         * CRÉATION DU MESSAGE
         * ===============================================
         */

        $message = new ProductMessage();

        $message
            ->setProduct($product)
            ->setUser($user)
            ->setContent($content);


        $em->persist(
            $message
        );


        /*
         * ===============================================
         * DISCUSSION CONSIDÉRÉE COMME LUE
         * ===============================================
         *
         * L'utilisateur est actuellement dans la
         * discussion puisqu'il vient d'écrire.
         */

        $participation->setDiscussionReadAt(
            new \DateTimeImmutable()
        );


        $em->flush();

        /*
 * ===============================================
 * TEMPS RÉEL MERCURE
 * ===============================================
 *
 * On diffuse le nouveau message à chaque
 * participant du cadeau collaboratif.
 *
 * Chaque utilisateur possède son propre topic
 * afin de générer correctement :
 *
 * - ses messages à droite
 * - les messages des autres à gauche
 */

        foreach ($product->getProductUsers() as $productUser) {

            $participant = $productUser->getUser();

            if ($participant === null) {
                continue;
            }


            /*
             * Topic privé de ce participant
             */

            $topic = sprintf(
                'https://nidou.app/users/%d/discussions',
                $participant->getId()
            );


            /*
             * Génération du Turbo Stream personnalisé
             */

            $stream = $this->renderView(
                'product_message/broadcast.stream.html.twig',
                [
                    'message' => $message,
                    'product' => $product,
                    'viewerUserId' => $participant->getId(),
                ]
            );


            /*
             * Diffusion privée
             */

            $hub->publish(
                new Update(
                    $topic,
                    $stream,
                    true
                )
            );
        }


        /*
         * ===============================================
         * TURBO
         * ===============================================
         *
         * Au lieu de recharger la wishlist,
         * on renvoie seulement les modifications DOM.
         */

        if (
            TurboBundle::STREAM_FORMAT
            === $request->getPreferredFormat()
        ) {
            $request->setRequestFormat(
                TurboBundle::STREAM_FORMAT
            );

            return $this->renderBlock(
                'product_message/create.stream.html.twig',
                'success_stream',
                [
                    'message' => $message,
                    'product' => $product,
                ]
            );
        }


        /*
         * ===============================================
         * FALLBACK SANS TURBO
         * ===============================================
         *
         * Si JavaScript/Turbo n'est pas disponible,
         * l'application continue quand même à fonctionner.
         */

        return $this->redirectToRoute(
            'app_wishlist',
            [
                'token' => $token,
                'discussion' => $product->getId(),
            ],
            Response::HTTP_SEE_OTHER
        );
    }


    /*
     * ==========================================================
     * MARQUER LA DISCUSSION COMME LUE
     * ==========================================================
     */

    #[Route(
        '/wishlist/{token}/product/{id}/discussion/read',
        name: 'app_product_discussion_read',
        methods: ['POST']
    )]
    public function read(
        string $token,
        Product $product,
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(
            'IS_AUTHENTICATED_FULLY'
        );

        $user = $this->getUser();


        /*
         * ===============================================
         * WISHLIST
         * ===============================================
         */

        if (
            $product->getWishlist()?->getAccessToken()
            !== $token
        ) {
            return new JsonResponse(
                [
                    'success' => false,
                ],
                404
            );
        }


        /*
         * ===============================================
         * CSRF
         * ===============================================
         */

        if (
            !$this->isCsrfTokenValid(
                'discussion-read-' . $product->getId(),
                (string) $request->request->get('_token')
            )
        ) {
            return new JsonResponse(
                [
                    'success' => false,
                ],
                403
            );
        }


        /*
         * ===============================================
         * PARTICIPATION
         * ===============================================
         */

        $participation = $em
            ->getRepository(ProductUser::class)
            ->findOneBy([
                'product' => $product,
                'user' => $user,
            ]);


        if ($participation === null) {

            return new JsonResponse(
                [
                    'success' => false,
                ],
                403
            );
        }


        /*
         * ===============================================
         * MARQUER COMME LU
         * ===============================================
         */

        $participation->setDiscussionReadAt(
            new \DateTimeImmutable()
        );


        $em->flush();


        return new JsonResponse([
            'success' => true,
        ]);
    }


    /*
     * ==========================================================
     * ERREUR DE MESSAGE
     * ==========================================================
     */

    private function messageError(
        Request $request,
        Product $product,
        string $token,
        string $error,
    ): Response {

        /*
         * ===============================================
         * ERREUR TURBO
         * ===============================================
         */

        if (
            TurboBundle::STREAM_FORMAT
            === $request->getPreferredFormat()
        ) {
            $request->setRequestFormat(
                TurboBundle::STREAM_FORMAT
            );

            return $this->renderBlock(
                'product_message/create.stream.html.twig',
                'error_stream',
                [
                    'product' => $product,
                    'error' => $error,
                ]
            );
        }


        /*
         * ===============================================
         * FALLBACK CLASSIQUE
         * ===============================================
         */

        $this->addFlash(
            'error',
            $error
        );


        return $this->redirectToRoute(
            'app_wishlist',
            [
                'token' => $token,
                'discussion' => $product->getId(),
            ],
            Response::HTTP_SEE_OTHER
        );
    }
}
