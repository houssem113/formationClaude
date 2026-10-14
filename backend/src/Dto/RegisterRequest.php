<?php

namespace App\Dto;

use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

final class RegisterRequest implements JsonRequestDto
{
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'Cette adresse email n\'est pas valide.')]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.')]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.')]
    #[Assert\Regex(pattern: '/^(?=.*\p{L})(?=.*\d)/su', message: 'Le mot de passe doit contenir au moins une lettre et un chiffre.')]
    public ?string $password = null;

    public static function fromArray(array $data): static
    {
        $dto = new self();
        $dto->email = \is_string($data['email'] ?? null) ? User::normalizeEmail($data['email']) : null;
        $dto->password = \is_string($data['password'] ?? null) ? $data['password'] : null;

        return $dto;
    }
}
