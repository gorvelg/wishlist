<?php

namespace App\Entity;

use App\Repository\ProductUserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductUserRepository::class)]
#[ORM\UniqueConstraint(
    name: 'UNIQ_PRODUCT_USER',
    fields: ['product', 'user']
)]
class ProductUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'productUsers')]
    private ?Product $product = null;

    #[ORM\ManyToOne(inversedBy: 'productUsers')]
    private ?User $user = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 8,
        scale: 2,
        nullable: true
    )]
    private ?string $amount = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $discussionReadAt = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();

        $this->createdAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getDiscussionReadAt(): ?\DateTimeImmutable
    {
        return $this->discussionReadAt;
    }

    public function setDiscussionReadAt(?\DateTimeImmutable $discussionReadAt): void
    {
        $this->discussionReadAt = $discussionReadAt;
    }
}
