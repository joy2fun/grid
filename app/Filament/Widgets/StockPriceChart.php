<?php

namespace App\Filament\Widgets;

use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class StockPriceChart extends ApexChartWidget
{
    /**
     * Chart Id
     */
    protected static ?string $chartId = 'stockPriceChart';

    protected static ?string $heading = 'Stock Price History';

    public ?int $stockId = null;

    protected function getOptions(): array
    {
        if (! $this->stockId) {
            return [];
        }

        $data = \App\Models\DayPrice::query()
            ->where('stock_id', $this->stockId)
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
        ];
    }
}
