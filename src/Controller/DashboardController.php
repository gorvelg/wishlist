<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        EntityManagerInterface $em,
    ): Response
    {
        $user = $this->getUser();

        if (!$user){
            return $this->redirectToRoute('app_login');
        }
        $wishlists = $user
            ->getWishlistOwners()
            ->map(
                fn ($wishlistOwner) =>
                $wishlistOwner->getWishlist()
            );



        return $this->render('dashboard/index.html.twig', [
            'wishlists' => $wishlists,
        ]);
    }
}
