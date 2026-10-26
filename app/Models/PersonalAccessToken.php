<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     PersonalAccessToken.php
 * Ubicación:   app/Models/PersonalAccessToken.php
 * Descripción: Token de Sanctum apuntando a la tabla con el prefijo
 *              institucional (acs_personal_access_tokens). Se registra en
 *              AppServiceProvider::boot() con
 *              Sanctum::usePersonalAccessTokenModel(self::class).
 * ============================================================================
 */

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $table = 'acs_personal_access_tokens';
}
