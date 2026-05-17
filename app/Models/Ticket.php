<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'device_id',
        'title',
        'description',
        'status',
        'priority',
        'category',
    ];

    protected function casts(): array
    {
        return [
            'id'        => 'integer',
            'user_id'   => 'integer',
            'device_id' => 'integer',
            'status'    => TicketStatus::class,
            'priority'  => TicketPriority::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
