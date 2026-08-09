<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Wishlist;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WishlistController extends AbstractController
{
    #[Route('/wishlist/{token}', name: 'app_home')]
    public function index(
        string $token,
        EntityManagerInterface $em
    ): Response {

        $wishlist = $em->getRepository(Wishlist::class)->findOneBy(['accessToken' => $token]);
        if (!$wishlist) {
            throw $this->createNotFoundException();
        }

        return $this->render('wishlist/index.html.twig', [
            'wishlist' => $wishlist,
            'products' => $wishlist->getProducts()
        ]);
    }
}
