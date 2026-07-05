<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /** Verification method used at the device (ZKTeco "state"). */
    public const STATES = [
        0 => 'Password',
        1 => 'Fingerprint',
        2 => 'Card',
        255 => 'Manual',
    ];

    /** State value used for entries added by hand in this app. */
    public const STATE_MANUAL = 255;

    /** Punch type (ZKTeco "type"). */
    public const TYPES = [
        0 => 'Check-in',
        1 => 'Check-out',
        2 => 'Break-out',
        3 => 'Break-in',
        4 => 'Overtime-in',
        5 => 'Overtime-out',
    ];

    protected $fillable = [
        'device_id',
        'uid',
        'userid',
        'state',
        'type',
        'punched_at',
        'pushed_at',
    ];

    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'pushed_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function getStateNameAttribute(): string
    {
        return self::STATES[(int) $this->state] ?? 'Unknown ('.$this->state.')';
    }

    public function getTypeNameAttribute(): string
    {
        return self::TYPES[(int) $this->type] ?? 'Unknown ('.$this->type.')';
    }
}
