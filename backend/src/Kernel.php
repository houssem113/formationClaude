<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        // Dates en UTC quel que soit le php.ini : sans changement d'heure, les durées
        // (TTL du refresh token, expiration des tokens de confirmation) restent exactes
        date_default_timezone_set('UTC');

        parent::boot();
    }
}
