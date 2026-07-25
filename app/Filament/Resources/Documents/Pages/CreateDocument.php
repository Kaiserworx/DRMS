<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\User;
use App\Services\DocumentRegistrationService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User) {
            throw new LogicException('An authenticated user is required.');
        }

        return app(DocumentRegistrationService::class)->register($user, $data);
    }
}
