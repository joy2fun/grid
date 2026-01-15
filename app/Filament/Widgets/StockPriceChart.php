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

        $prices = \App\Models\DayPrice::query()
            ->where('stock_id', $this->stockId)
            ->orderBy('date', 'desc')
            ->limit(120)
            ->get()
            ->reverse() // Ensure chronological order for chart
            ->values();

        $chartData = $prices->map(function ($dayPrice) {
            return [
                'x' => $dayPrice->date->toDateString(),
                'y' => [
                    (float) $dayPrice->open_price,
                    (float) $dayPrice->high_price,
                    (float) $dayPrice->low_price,
                    (float) $dayPrice->close_price,
                ],
            ];
        });

        $categories = $chartData->pluck('x')->toArray();

        $trades = \App\Models\Trade::query()
            ->where('stock_id', $this->stockId)
            ->orderBy('executed_at')
            ->get();

        $pointAnnotations = $trades->map(function ($trade) use ($chartData, $categories) {
            $dateStr = $trade->executed_at->toDateString();
            $color = $trade->side === 'buy' ? '#00E396' : '#FF4560';

            return [
                'x' => $dateStr,
                'y' => (float) $trade->price,
                'marker' => [
                    'size' => 2,
                    'fillColor' => $color,
                    'strokeColor' => '#fff',
                    'strokeWidth' => 1,
                    'shape' => 'circle'
                ],
                'label' => [
                    'borderColor' => $color,
                    'style' => [
                        'color' => '#fff',
                        'background' => $color,
                        'fontSize' => '10px',
                        'fontWeight' => 'bold',
                    ],
                    'text' => ($trade->side === 'buy' ? 'B ' : 'S ') . number_format($trade->price, 3),
                    'offsetY' => -10
                ]
            ];
        })->values()->toArray();

        return [
            'chart' => [
                'type' => 'candlestick',
                'height' => 350,
                'toolbar' => [
                    'show' => true,
                ]
            ],
            'series' => [
                [
                    'name' => 'Price',
                    'data' => $chartData->toArray(),
                ],
            ],
            'xaxis' => [
                'type' => 'category',
                'categories' => $categories,
                'tickAmount' => 10,
                'labels' => [
                    'rotate' => -45,
                    'style' => [
                        'fontSize' => '11px'
                    ]
                ]
            ],
            'yaxis' => [
                'tooltip' => [
                    'enabled' => true,
                ],
            ],
            'annotations' => [
                'points' => $pointAnnotations,
            ],
        ];
    }
}
