<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'serial_number',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type'   => DeviceType::class,
            'status' => DeviceStatus::class,
        ];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeviceAssignment::class);
    }
}
