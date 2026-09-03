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
        $wishlists = $user
            ->getWishlistOwners()
            ->map(
                fn ($wishlistOwner) =>
                $wishlistOwner->getWishlist()
            );

        dump($wishlists);

        return $this->render('dashboard/index.html.twig', [
            'wishlists' => $wishlists,
        ]);
    }
}
