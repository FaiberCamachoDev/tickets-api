<?php

namespace App\Services;

use App\Enums\DeviceStatus;
use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DeviceService
{
    public function list(): LengthAwarePaginator
    {
        return Device::with('assignments')->paginate(15);
    }

    public function assign(Device $device, User $assignedUser, array $data): DeviceAssignment
    {
        if ($device->status !== DeviceStatus::AVAILABLE) {
            throw new \DomainException(
                "El dispositivo '{$device->name}' no está disponible para asignación (estado actual: {$device->status->value})"
            );
        }

        return DB::transaction(function () use ($device, $assignedUser, $data) {
            $device->update(['status' => DeviceStatus::ASSIGNED]);

            $assignment = DeviceAssignment::create([
                'device_id'   => $device->id,
                'user_id'     => $assignedUser->id,
                'assigned_at' => now(),
                'notes'       => $data['notes'] ?? null,
            ]);

            ActivityLog::create([
                'user_id'       => $assignedUser->id,
                'action'        => 'device_assigned',
                'loggable_type' => Device::class,
                'loggable_id'   => $device->id,
                'description'   => "Dispositivo '{$device->name}' asignado a {$assignedUser->name}",
                'ip_address'    => request()->ip(),
                'user_agent'    => request()->userAgent(),
                'metadata'      => ['assignment_id' => $assignment->id],
            ]);

            return $assignment->load(['device', 'user']);
        });
    }
}
