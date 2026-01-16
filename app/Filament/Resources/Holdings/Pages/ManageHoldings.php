<?php

namespace App\Filament\Resources\Holdings\Pages;

use App\Filament\Resources\Holdings\HoldingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHoldings extends ManageRecords
{
    protected static string $resource = HoldingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
