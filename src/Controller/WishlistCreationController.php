<?php

namespace App\Controller;

use App\Dto\WishlistCreationData;
use App\Entity\User;
use App\Entity\Wishlist;
use App\Entity\WishlistOwner;
use App\Enum\WishlistCreationStep;
use App\Form\Wishlist\WishlistBabyType;
use App\Form\Wishlist\WishlistConfirmType;
use App\Form\Wishlist\WishlistFamilyType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WishlistCreationController extends AbstractController
{
    private const SESSION_KEY = 'wishlist_creation';


    #[Route(
        '/wishlist/create/{step}',
        name: 'app_wishlist_create',
        requirements: [
            'step' => 'family|baby|confirm',
        ]
    )]
    public function create(
        WishlistCreationStep $step,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /*
         * Il faut être connecté pour créer une wishlist.
         */
        $this->denyAccessUnlessGranted(
            'IS_AUTHENTICATED_FULLY'
        );

        $session = $request->getSession();


        /*
         * ===============================================
         * RECONSTRUIRE LE DTO
         * ===============================================
         */

        $data = WishlistCreationData::fromArray(
            $session->get(
                self::SESSION_KEY,
                []
            )
        );


        /*
         * ===============================================
         * EMPÊCHER DE SAUTER DES ÉTAPES
         * ===============================================
         */

        if (
            $step === WishlistCreationStep::BABY
            && !$data->hasFamilyData()
        ) {
            return $this->redirectToStep(
                WishlistCreationStep::FAMILY
            );
        }


        if (
            $step === WishlistCreationStep::CONFIRM
        ) {
            if (!$data->hasFamilyData()) {
                return $this->redirectToStep(
                    WishlistCreationStep::FAMILY
                );
            }

            if (!$data->hasBabyData()) {
                return $this->redirectToStep(
                    WishlistCreationStep::BABY
                );
            }
        }


        /*
         * ===============================================
         * CHOISIR LE FORMULAIRE
         * ===============================================
         */

        $form = match ($step) {
            WishlistCreationStep::FAMILY =>
            $this->createForm(
                WishlistFamilyType::class,
                $data
            ),

            WishlistCreationStep::BABY =>
            $this->createForm(
                WishlistBabyType::class,
                $data
            ),

            WishlistCreationStep::CONFIRM =>
            $this->createForm(
                WishlistConfirmType::class,
                $data
            ),
        };

        $form->handleRequest($request);


        /*
         * ===============================================
         * FORMULAIRE VALIDÉ
         * ===============================================
         */

        if (
            $form->isSubmitted()
            && $form->isValid()
        ) {
            /*
             * -------------------------------------------
             * ÉTAPE 1
             * -------------------------------------------
             */
            if (
                $step === WishlistCreationStep::FAMILY
            ) {
                $session->set(
                    self::SESSION_KEY,
                    $data->toArray()
                );

                return $this->redirectToStep(
                    WishlistCreationStep::BABY
                );
            }


            /*
             * -------------------------------------------
             * ÉTAPE 2
             * -------------------------------------------
             */
            if (
                $step === WishlistCreationStep::BABY
            ) {
                $session->set(
                    self::SESSION_KEY,
                    $data->toArray()
                );

                return $this->redirectToStep(
                    WishlistCreationStep::CONFIRM
                );
            }


            /*
             * -------------------------------------------
             * ÉTAPE 3
             *
             * Ici seulement on crée la vraie Wishlist.
             * -------------------------------------------
             */

            if (
                $step === WishlistCreationStep::CONFIRM
            ) {
                /** @var User $user */
                $user = $this->getUser();


                /*
                 * Création de la wishlist.
                 */
                $wishlist = new Wishlist();

                $wishlist
                    ->setName($data->name)
                    ->setParentsNames(
                        $data->parentsNames
                    )
                    ->setBabyName(
                        $data->babyName ?: null
                    )
                    ->setDueDate(
                        $data->dueDate
                    )
                    ->setMessage(
                        $data->visitorMessage ?: null
                    );


                $wishlist->setAccessToken(
                    bin2hex(random_bytes(32))
                );


                /*
                 * Création du propriétaire.
                 *
                 * Ton WishlistOwner actuel possède bien
                 * setUser() et setWishlist().
                 */
                $wishlistOwner =
                    new WishlistOwner();

                $wishlistOwner
                    ->setUser($user)
                    ->setWishlist($wishlist);


                $em->persist($wishlist);
                $em->persist($wishlistOwner);

                $em->flush();


                /*
                 * Le wizard est terminé :
                 * on nettoie la session.
                 */
                $session->remove(
                    self::SESSION_KEY
                );


                $this->addFlash(
                    'success',
                    'Votre liste a bien été créée.'
                );


                return $this->redirectToRoute(
                    'app_wishlist',
                    [
                        'token' =>
                            $wishlist->getAccessToken(),
                    ]
                );
            }
        }

        return $this->render(
            'wishlist/create/' . $step->value . '.html.twig',
            [
                'form' => $form,
                'data' => $data,
                'step' => $step,
            ]
        );
    }


    /*
     * Petit helper pour éviter de répéter
     * redirectToRoute partout.
     */
    private function redirectToStep(
        WishlistCreationStep $step
    ): Response {
        return $this->redirectToRoute(
            'app_wishlist_create',
            [
                'step' => $step->value,
            ]
        );
    }
}
