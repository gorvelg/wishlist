<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private ?string $firstname = null;

    #[ORM\Column]
    private ?string $lastname = null;


    /**
     * @var Collection<int, ProductUser>
     */
    #[ORM\OneToMany(targetEntity: ProductUser::class, mappedBy: 'User')]
    private Collection $productUsers;

    /**
     * @var Collection<int, WishlistOwner>
     */
    #[ORM\OneToMany(targetEntity: WishlistOwner::class, mappedBy: 'user')]
    private Collection $wishlist;

    /**
     * @var Collection<int, WishlistOwner>
     */
    #[ORM\OneToMany(targetEntity: WishlistOwner::class, mappedBy: 'user')]
    private Collection $wishlistOwners;

    public function __construct()
    {
        $this->productUsers = new ArrayCollection();
        $this->wishlist = new ArrayCollection();
        $this->wishlistOwners = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): void
    {
        $this->firstname = $firstname;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): void
    {
        $this->lastname = $lastname;
    }

    public function getInitials(): string
    {
        $firstNameInitial = $this->firstname
            ? mb_strtoupper(mb_substr($this->firstname, 0, 1))
            : '';

        $lastNameInitial = $this->lastname
            ? mb_strtoupper(mb_substr($this->lastname, 0, 1))
            : '';

        return $firstNameInitial . $lastNameInitial;
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
            $productUser->setUser($this);
        }

        return $this;
    }

    public function removeProductUser(ProductUser $productUser): static
    {
        if ($this->productUsers->removeElement($productUser)) {
            // set the owning side to null (unless already changed)
            if ($productUser->getUser() === $this) {
                $productUser->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, WishlistOwner>
     */
    public function getWishlist(): Collection
    {
        return $this->wishlist;
    }

    public function addWishlist(WishlistOwner $wishlist): static
    {
        if (!$this->wishlist->contains($wishlist)) {
            $this->wishlist->add($wishlist);
            $wishlist->setUser($this);
        }

        return $this;
    }

    public function removeWishlist(WishlistOwner $wishlist): static
    {
        if ($this->wishlist->removeElement($wishlist)) {
            // set the owning side to null (unless already changed)
            if ($wishlist->getUser() === $this) {
                $wishlist->setUser(null);
            }
        }

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
            $wishlistOwner->setUser($this);
        }

        return $this;
    }

    public function removeWishlistOwner(WishlistOwner $wishlistOwner): static
    {
        if ($this->wishlistOwners->removeElement($wishlistOwner)) {
            // set the owning side to null (unless already changed)
            if ($wishlistOwner->getUser() === $this) {
                $wishlistOwner->setUser(null);
            }
        }

        return $this;
    }
}
