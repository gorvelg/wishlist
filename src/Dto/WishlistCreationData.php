<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class WishlistCreationData
{
    #[Assert\NotBlank(
        message: 'Donnez un nom à votre liste.',
        groups: ['family']
    )]
    #[Assert\Length(
        max: 255,
        groups: ['family']
    )]
    public ?string $name = null;


    #[Assert\NotBlank(
        message: 'Indiquez le nom des parents.',
        groups: ['family']
    )]
    #[Assert\Length(
        max: 255,
        groups: ['family']
    )]
    public ?string $parentsNames = null;


    /*
     * Facultatif :
     * les parents n'ont peut-être pas encore
     * choisi ou révélé le prénom.
     */
    #[Assert\Length(
        max: 255,
        groups: ['family']
    )]
    public ?string $babyName = null;


    #[Assert\NotNull(
        message: 'Indiquez la date prévue d’arrivée.',
        groups: ['baby']
    )]
    public ?\DateTimeImmutable $dueDate = null;


    #[Assert\Length(
        max: 1500,
        maxMessage: 'Votre message ne peut pas dépasser {{ limit }} caractères.',
        groups: ['baby']
    )]
    public ?string $message = null;


    /*
     * Transforme le DTO en tableau pour la session.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'parentsNames' => $this->parentsNames,
            'babyName' => $this->babyName,
            'dueDate' => $this->dueDate?->format('Y-m-d'),
            'message' => $this->message,
        ];
    }


    /*
     * Reconstruit le DTO depuis la session.
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();

        $dto->name = $data['name'] ?? null;
        $dto->parentsNames = $data['parentsNames'] ?? null;
        $dto->babyName = $data['babyName'] ?? null;
        $dto->visitorMessage = $data['visitorMessage'] ?? null;

        if (!empty($data['dueDate'])) {
            $dto->dueDate = new \DateTimeImmutable(
                $data['dueDate']
            );
        }

        return $dto;
    }


    public function hasFamilyData(): bool
    {
        return
            $this->name !== null
            && trim($this->name) !== ''
            && $this->parentsNames !== null
            && trim($this->parentsNames) !== '';
    }


    public function hasBabyData(): bool
    {
        return $this->dueDate !== null;
    }
}
