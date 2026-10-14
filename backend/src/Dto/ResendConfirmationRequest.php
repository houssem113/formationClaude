<?php

namespace App\Dto;

use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

final class ResendConfirmationRequest implements JsonRequestDto
{
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'Cette adresse email n\'est pas valide.')]
    public ?string $email = null;

    public static function fromArray(array $data): static
    {
        $dto = new self();
        $dto->email = \is_string($data['email'] ?? null) ? User::normalizeEmail($data['email']) : null;

        return $dto;
    }
}
