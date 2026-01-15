<?php

namespace App\Filament\Resources\Grids\Widgets;

use App\Models\Grid;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Illuminate\Database\Eloquent\Model;

class GridTradesChart extends ApexChartWidget
{
    /**
     * Chart Id
     */
    protected static ?string $chartId = 'gridTradesChart';

    protected static ?string $heading = 'Stock Price & Trades';

    protected ?string $pollingInterval = null;

    public ?Model $record = null;

    protected function getOptions(): array
    {
        if (! $this->record) {
            return [];
        }

        /** @var Grid $grid */
        $grid = $this->record;
        $stockId = $grid->stock_id;

        $data = \App\Models\DayPrice::query()
            ->where('stock_id', $stockId)
            ->orderBy('date', 'desc')
            ->limit(120)
            ->get()
            ->map(function ($dayPrice) {
                return [
                    'x' => $dayPrice->date,
                    'y' => [
                        $dayPrice->open_price,
                        $dayPrice->high_price,
                        $dayPrice->low_price,
                        $dayPrice->close_price,
                    ],
                ];
            });

        $trades = $grid->trades()
            ->orderBy('executed_at')
            ->get()
            ->map(function ($trade) {
                $color = $trade->side === 'buy' ? '#00E396' : '#FF4560'; // Green for buy, Red for sell
                return [
                    'x' => $trade->executed_at->timestamp * 1000, // Unix timestamp in ms
                    'borderColor' => $color,
                    'label' => [
                        'borderColor' => $color,
                        'style' => [
                            'color' => '#fff',
                            'background' => $color,
                        ],
                        'text' => ucfirst($trade->side) . ': $' . number_format($trade->price, 2),
                    ],
                ];
            });

        return [
            'chart' => [
                'type' => 'candlestick',
                'height' => 350,
            ],
            'series' => [
                [
                    'name' => 'Price',
                    'data' => $data->toArray(),
                ],
            ],
            'xaxis' => [
                'type' => 'datetime',
            ],
            'yaxis' => [
                'tooltip' => [
                    'enabled' => true,
                ],
            ],
            'annotations' => [
                'xaxis' => $trades->toArray(),
            ],
        ];
    }
}
