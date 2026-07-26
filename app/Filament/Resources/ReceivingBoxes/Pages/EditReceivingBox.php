<?php

namespace App\Filament\Resources\ReceivingBoxes\Pages;

use App\Filament\Resources\ReceivingBoxes\ReceivingBoxResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditReceivingBox extends EditRecord
{
    protected static string $resource = ReceivingBoxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
