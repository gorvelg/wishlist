<?php

namespace App\Entity;

use App\Repository\WishlistOwnerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WishlistOwnerRepository::class)]
class WishlistOwner
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'wishlistOwners')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'wishlistOwners')]
    private ?Wishlist $wishlist = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getWishlist(): ?Wishlist
    {
        return $this->wishlist;
    }

    public function setWishlist(?Wishlist $wishlist): static
    {
        $this->wishlist = $wishlist;

        return $this;
    }
}
