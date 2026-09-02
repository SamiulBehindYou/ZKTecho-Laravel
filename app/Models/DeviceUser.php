<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'location_id',
        'admin_id',
    ];

    protected function casts(): array
    {
        return [
            'location_id' => 'integer',
            'admin_id' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Attendance for a user is only pushed to the dashboard once both the
     * location and the admin it belongs to have been filled in, since the
     * dashboard cannot file a punch without them.
     */
    public function isPushable(): bool
    {
        return $this->location_id !== null && $this->admin_id !== null;
    }

    /** Users that may be pushed: both location_id and admin_id set. */
    public function scopePushable(Builder $query): Builder
    {
        return $query->whereNotNull('location_id')->whereNotNull('admin_id');
    }

    /** Users still waiting on a location_id and/or admin_id. */
    public function scopeIncomplete(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('location_id')->orWhereNull('admin_id');
        });
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
