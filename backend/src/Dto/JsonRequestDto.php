<?php

namespace App\Dto;

interface JsonRequestDto
{
    /**
     * @param array<string, mixed> $data corps JSON décodé
     */
    public static function fromArray(array $data): static;
}
