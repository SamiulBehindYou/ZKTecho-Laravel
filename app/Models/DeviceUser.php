<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceUser extends Model
{
    public const ROLE_USER = 0;
    public const ROLE_ADMIN = 14;

    protected $fillable = [
        'device_id',
        'uid',
        'userid',
        'name',
        'role',
        'cardno',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function getRoleNameAttribute(): string
    {
        return match ((int) $this->role) {
            self::ROLE_USER => 'User',
            self::ROLE_ADMIN => 'Admin',
            default => 'Level '.$this->role,
        };
    }
}
