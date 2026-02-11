<?php

namespace App\Filament\Widgets;

use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class StockPriceChart extends ApexChartWidget
{
    /**
     * Chart Id
     */
    protected static ?string $chartId = 'stockPriceChart';

    protected static ?string $heading = null;

    protected ?string $pollingInterval = null;

    public ?int $stockId = null;

    public ?string $filter = '120';

    protected function getFilters(): ?array
    {
        return [
            '100' => '100 Days',
            '200' => '200 Days',
            '300' => '300 Days',
            '600' => '600 Days',
            '900' => '900 Days',
            '2000' => '2000 Days',
        ];
    }

    protected function getOptions(): array
    {
        if (! $this->stockId) {
            return [];
        }

        $limit = (int) $this->filter;

        $prices = \App\Models\DayPrice::query()
            ->where('stock_id', $this->stockId)
            ->orderBy('date', 'desc')
            ->limit($limit)
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

        $pointAnnotations = $trades->map(function ($trade) {
            $dateStr = $trade->executed_at->toDateString();
            $color = match ($trade->type) {
                'buy' => '#00E396',
                'sell' => '#FF4560',
                'dividend' => '#008FFB',
                'stock_dividend' => '#FEB019',
                'stock_split' => '#775DD0',
                default => '#999999',
            };

            $typeLabel = match ($trade->type) {
                'buy' => 'B',
                'sell' => 'S',
                'dividend' => 'D',
                'stock_dividend' => 'G',
                'stock_split' => 'P',
                default => 'T',
            };

            // Only show annotations for buy/sell trades on price chart
            if (! in_array($trade->type, ['buy', 'sell'])) {
                return null;
            }

            return [
                'x' => $dateStr,
                'y' => (float) $trade->price,
                'marker' => [
                    'size' => 2,
                    'fillColor' => $color,
                    'strokeColor' => '#fff',
                    'strokeWidth' => 1,
                    'shape' => 'circle',
                ],
                'label' => [
                    'borderColor' => $color,
                    'style' => [
                        'color' => '#fff',
                        'background' => $color,
                        'fontSize' => '10px',
                        'fontWeight' => 'bold',
                    ],
                    'text' => $typeLabel.' '.number_format($trade->price, 3),
                    'offsetY' => -10,
                ],
            ];
        })->filter()->values()->toArray();

        return [
            'chart' => [
                'type' => 'candlestick',
                'height' => 350,
                'animations' => [
                    'enabled' => false,
                ],
                'toolbar' => [
                    'show' => true,
                ],
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
                        'fontSize' => '11px',
                    ],
                ],
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
