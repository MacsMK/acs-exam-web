<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     UserDevice.php
 * Ubicación:   app/Models/UserDevice.php
 * Descripción: Modelo Eloquent de acs_user_devices (token FCM).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends Model
{
    protected $table = 'acs_user_devices';

    protected $fillable = [
        'user_id',
        'fcm_token',
        'platform',
        'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'last_active_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, UserDevice>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
