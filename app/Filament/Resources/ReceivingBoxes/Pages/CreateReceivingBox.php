<?php

namespace App\Filament\Resources\ReceivingBoxes\Pages;

use App\Filament\Resources\ReceivingBoxes\ReceivingBoxResource;
use App\Models\User;
use App\Services\ReceivingBoxService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateReceivingBox extends CreateRecord
{
    protected static string $resource = ReceivingBoxResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = Filament::auth()->user();

        return app(ReceivingBoxService::class)->create($actor, $data, [
            'ip_address' => request()->ip(),
            'device_info' => request()->userAgent(),
        ]);
    }
}
