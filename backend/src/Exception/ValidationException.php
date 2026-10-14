<?php

namespace App\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ValidationException extends \RuntimeException
{
    public function __construct(private readonly ConstraintViolationListInterface $violations)
    {
        parent::__construct('Les données envoyées sont invalides.');
    }

    /** @return list<array{field: string, message: string}> */
    public function getViolations(): array
    {
        $violations = [];

        foreach ($this->violations as $violation) {
            $violations[] = [
                'field' => $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $violations;
    }
}
