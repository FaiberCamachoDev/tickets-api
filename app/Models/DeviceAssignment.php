<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeviceAssignment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'device_id',
        'user_id',
        'assigned_at',
        'returned_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at'  => 'datetime',
            'returned_at'  => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
