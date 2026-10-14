<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ConfirmEmailRequest implements JsonRequestDto
{
    #[Assert\NotBlank(message: 'Le token est obligatoire.')]
    public ?string $token = null;

    public static function fromArray(array $data): static
    {
        $dto = new self();
        $dto->token = \is_string($data['token'] ?? null) ? trim($data['token']) : null;

        return $dto;
    }
}
