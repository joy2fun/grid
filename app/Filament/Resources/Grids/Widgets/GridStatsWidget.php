<?php

namespace App\Filament\Resources\Grids\Widgets;

use App\Models\Grid;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class GridStatsWidget extends Widget
{
    protected string $view = 'filament.resources.grids.widgets.grid-stats-widget';

    public ?Model $record = null;

    public int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        if (! $this->record instanceof Grid) {
            return [];
        }

        return [
            'metrics' => $this->record->getMetrics(),
        ];
    }
}
