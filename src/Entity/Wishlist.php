<?php

namespace App\Entity;

use App\Repository\WishlistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WishlistRepository::class)]
class Wishlist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 64, unique: true)]
    private ?string $accessToken = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, WishlistOwner>
     */
    #[ORM\OneToMany(targetEntity: WishlistOwner::class, mappedBy: 'wishlist')]
    private Collection $wishlistOwners;

    public function __construct()
    {
        $now = new \DateTimeImmutable();

        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->wishlistOwners = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, WishlistOwner>
     */
    public function getWishlistOwners(): Collection
    {
        return $this->wishlistOwners;
    }

    public function addWishlistOwner(WishlistOwner $wishlistOwner): static
    {
        if (!$this->wishlistOwners->contains($wishlistOwner)) {
            $this->wishlistOwners->add($wishlistOwner);
            $wishlistOwner->setWishlist($this);
        }

        return $this;
    }

    public function removeWishlistOwner(WishlistOwner $wishlistOwner): static
    {
        if ($this->wishlistOwners->removeElement($wishlistOwner)) {
            // set the owning side to null (unless already changed)
            if ($wishlistOwner->getWishlist() === $this) {
                $wishlistOwner->setWishlist(null);
            }
        }

        return $this;
    }
}
