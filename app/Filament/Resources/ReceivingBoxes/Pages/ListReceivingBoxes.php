<?php

namespace App\Filament\Resources\ReceivingBoxes\Pages;

use App\Filament\Resources\ReceivingBoxes\ReceivingBoxResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReceivingBoxes extends ListRecords
{
    protected static string $resource = ReceivingBoxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
