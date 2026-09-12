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

        if (
            $product->getWishlist()?->getAccessToken()
            !== $token
        ) {
            throw $this->createNotFoundException();
        }

        if (!$product->isCollaborative()) {
            throw $this->createAccessDeniedException(
                'Cette discussion n’est pas disponible.'
            );
        }

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

        $content = trim(
            (string) $request->request->get('content')
        );


        if ($content === '') {

            return $this->messageError(
                $request,
                $product,
                $token,
                'Votre message ne peut pas être vide.'
            );
        }

        if (mb_strlen($content) > 1000) {

            return $this->messageError(
                $request,
                $product,
                $token,
                'Votre message ne peut pas dépasser 1000 caractères.'
            );
        }

        $message = new ProductMessage();

        $message
            ->setUser($user)
            ->setContent($content);

        $product->addMessage($message);

        $em->persist($message);

        $participation->setDiscussionReadAt(
            new \DateTimeImmutable()
        );


        $em->flush();

        foreach ($product->getProductUsers() as $productUser) {

            $participant = $productUser->getUser();

            if ($participant === null) {
                continue;
            }

            $topic = sprintf(
                'https://nidou.app/users/%d/discussions',
                $participant->getId()
            );


            $unreadMessageCount =
                $product->getUnreadMessageCountFor(
                    $participant
                );


            /*
             * ===============================================
             * TURBO STREAM PERSONNALISÉ
             * ===============================================
             */

            $stream = $this->renderView(
                'product_message/broadcast.stream.html.twig',
                [
                    'message' => $message,
                    'product' => $product,
                    'viewerUserId' => $participant->getId(),
                    'unreadMessageCount' => $unreadMessageCount,
                ]
            );


            $hub->publish(
                new Update(
                    $topic,
                    $stream,
                    true
                )
            );
        }


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


        return $this->redirectToRoute(
            'app_wishlist',
            [
                'token' => $token,
                'discussion' => $product->getId(),
            ],
            Response::HTTP_SEE_OTHER
        );
    }

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


        $participation->setDiscussionReadAt(
            new \DateTimeImmutable()
        );


        $em->flush();


        return new JsonResponse([
            'success' => true,
        ]);
    }

    private function messageError(
        Request $request,
        Product $product,
        string $token,
        string $error,
    ): Response {

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
