<?php

namespace App\Entity;

use App\Enum\ProductCategory;
use App\Enum\ProductStatus;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 30)]
    private ?ProductCategory $category = null;

    #[ORM\Column(length: 30)]
    private ?ProductStatus $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, ProductUser>
     */
    #[ORM\OneToMany(targetEntity: ProductUser::class, mappedBy: 'product')]
    private Collection $productUsers;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Wishlist $wishlist = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $collaborative = false;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductMessage::class, orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    #[Assert\Length(
        min: 2,
        max: 200,
        minMessage: 'Votre description doit avoir au minimum {{ limit }} caractères.',
        maxMessage: 'Votre description ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function __construct()
    {
        $this->productUsers = new ArrayCollection();
        $this->messages = new ArrayCollection();

        $now = new \DateTimeImmutable();

        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->status = ProductStatus::AVAILABLE;
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

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getCategory(): ?ProductCategory
    {
        return $this->category;
    }

    public function setCategory(ProductCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getStatus(): ?ProductStatus
    {
        return $this->status;
    }

    public function setStatus(ProductStatus $status): static
    {
        $this->status = $status;

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
     * @return Collection<int, ProductUser>
     */
    public function getProductUsers(): Collection
    {
        return $this->productUsers;
    }

    public function addProductUser(ProductUser $productUser): static
    {
        if (!$this->productUsers->contains($productUser)) {
            $this->productUsers->add($productUser);
            $productUser->setProduct($this);
        }

        return $this;
    }

    public function removeProductUser(ProductUser $productUser): static
    {
        if ($this->productUsers->removeElement($productUser)) {
            // set the owning side to null (unless already changed)
            if ($productUser->getProduct() === $this) {
                $productUser->setProduct(null);
            }
        }

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }
    public function getContributedAmount(): float
    {
        $total = 0.0;

        foreach ($this->productUsers as $productUser) {
            $total += (float) ($productUser->getAmount() ?? 0);
        }

        return round($total, 2);
    }

    public function getRemainingAmount(): float
    {
        return max(
            0,
            round(
                (float) $this->price - $this->getContributedAmount(),
                2
            )
        );
    }

    public function getContributionPercent(): float
    {
        $price = (float) $this->price;

        if ($price <= 0) {
            return 0;
        }

        return min(
            100,
            round(
                ($this->getContributedAmount() / $price) * 100,
                1
            )
        );
    }

    public function isCollaborative(): bool
    {
        return $this->collaborative;
    }

    public function setCollaborative(bool $collaborative): void
    {
        $this->collaborative = $collaborative;
    }

    /**
     * @return Collection<int, ProductMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(ProductMessage $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setProduct($this);
        }

        return $this;
    }

    public function removeMessage(ProductMessage $message): static
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getProduct() === $this) {
                $message->setProduct(null);
            }
        }

        return $this;
    }
}
