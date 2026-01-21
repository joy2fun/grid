<?php

namespace App\Filament\Resources\PriceAlerts\Pages;

use App\Filament\Resources\PriceAlerts\PriceAlertResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePriceAlerts extends ManageRecords
{
    protected static string $resource = PriceAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
