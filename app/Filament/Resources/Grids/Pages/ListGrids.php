<?php

namespace App\Filament\Resources\Grids\Pages;

use App\Filament\Resources\Grids\GridResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ListGrids extends ManageRecords
{
    protected static string $resource = GridResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
