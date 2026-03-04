<?php

namespace App\Filament\Resources\Grids\Pages;

use App\Filament\Resources\Grids\GridResource;
use Filament\Resources\Pages\EditRecord;

class EditGrid extends EditRecord
{
    protected static string $resource = GridResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\Grids\Widgets\GridStatsWidget::class,
            \App\Filament\Resources\Grids\Widgets\GridTradesChart::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
        ];
    }
}
