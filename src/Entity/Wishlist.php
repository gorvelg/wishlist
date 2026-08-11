<?php

namespace App\Entity;

use App\Repository\WishlistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
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

    /**
     * @var Collection<int, Product>
     */
    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'wishlist')]
    private Collection $products;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $babyName = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $dueDate = null;

    #[ORM\Column(length: 255)]
    private ?string $parentsNames = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();

        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->accessToken = bin2hex(random_bytes(32));
        $this->wishlistOwners = new ArrayCollection();
        $this->products = new ArrayCollection();
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

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setWishlist($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            // set the owning side to null (unless already changed)
            if ($product->getWishlist() === $this) {
                $product->setWishlist(null);
            }
        }

        return $this;
    }

    public function getBabyName(): ?string
    {
        return $this->babyName;
    }

    public function setBabyName(?string $babyName): static
    {
        $this->babyName = $babyName;

        return $this;
    }

    public function getDueDate(): ?\DateTime
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTime $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getParentsNames(): ?string
    {
        return $this->parentsNames;
    }

    public function setParentsNames(string $parentsNames): static
    {
        $this->parentsNames = $parentsNames;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }
}
