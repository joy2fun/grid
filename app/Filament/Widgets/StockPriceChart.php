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

    protected ?string $pollingInterval = null;

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

        $trades = \App\Models\Trade::query()
            ->where('stock_id', $this->stockId)
            ->orderBy('executed_at')
            ->get()
            ->map(function ($trade) {
                $color = $trade->side === 'buy' ? '#00E396' : '#FF4560'; // Green for buy, Red for sell
                $dayStart = $trade->executed_at->copy()->startOfDay()->timestamp * 1000; // Start of day in ms
                return [
                    'x' => $dayStart,
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
