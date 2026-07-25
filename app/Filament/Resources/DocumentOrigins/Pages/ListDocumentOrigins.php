<?php

namespace App\Filament\Resources\DocumentOrigins\Pages;

use App\Filament\Resources\DocumentOrigins\DocumentOriginResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocumentOrigins extends ListRecords
{
    protected static string $resource = DocumentOriginResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
