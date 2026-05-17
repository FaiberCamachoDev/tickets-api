<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Device\AssignDeviceRequest;
use App\Http\Resources\DeviceAssignmentResource;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Models\User;
use App\Services\DeviceService;
use Illuminate\Http\JsonResponse;

class DeviceController extends ApiController
{
    public function __construct(private readonly DeviceService $deviceService) {}

    public function index(): JsonResponse
    {
        $devices = $this->deviceService->list();

        return $this->paginated($devices, 'Dispositivos obtenidos', fn ($items) => DeviceResource::collection($items)->resolve());
    }

    public function assign(AssignDeviceRequest $request): JsonResponse
    {
        $device       = Device::findOrFail($request->device_id);
        $assignedUser = User::findOrFail($request->user_id);

        try {
            $assignment = $this->deviceService->assign($device, $assignedUser, $request->validated());
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        return $this->success(
            new DeviceAssignmentResource($assignment),
            'Dispositivo asignado correctamente',
            201
        );
    }
}
