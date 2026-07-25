<?php

namespace App\Filament\Resources\OrganizationalUnits\Pages;

use App\Filament\Resources\OrganizationalUnits\OrganizationalUnitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrganizationalUnits extends ListRecords
{
    protected static string $resource = OrganizationalUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
