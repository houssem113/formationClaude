<?php

namespace App\Http;

use App\Dto\JsonRequestDto;
use App\Exception\ApiException;
use App\Exception\ValidationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Lit le corps JSON d'une requête, le convertit en DTO et le valide.
 */
final class JsonBodyParser
{
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    /**
     * @template T of JsonRequestDto
     *
     * @param class-string<T> $dtoClass
     *
     * @return T
     *
     * @throws ApiException        INVALID_JSON si le corps n'est pas un objet JSON
     * @throws ValidationException si le DTO est invalide
     */
    public function parse(Request $request, string $dtoClass): JsonRequestDto
    {
        try {
            $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ApiException::invalidJson();
        }

        if (!\is_array($data) || ([] !== $data && array_is_list($data))) {
            throw ApiException::invalidJson();
        }

        $dto = $dtoClass::fromArray($data);

        $violations = $this->validator->validate($dto);
        if (\count($violations) > 0) {
            throw new ValidationException($violations);
        }

        return $dto;
    }
}
