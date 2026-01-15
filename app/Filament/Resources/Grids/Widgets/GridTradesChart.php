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

    protected static ?int $sort = 1;

    protected string|int|array $columnSpan = 'full';

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
            ->reverse() // Order chronologically for the chart
            ->values();

        $chartData = $data->map(function ($dayPrice) {
            return [
                'x' => $dayPrice->date->toDateString(),
                'y' => [
                    (float) $dayPrice->open_price,
                    (float) $dayPrice->high_price,
                    (float) $dayPrice->low_price,
                    (float) $dayPrice->close_price,
                ],
            ];
        })->toArray();

        $categories = $data->map(fn($dayPrice) => $dayPrice->date->toDateString())->values()->toArray();
        
        // Key data by date for easy lookup of close price
        $datesMap = $data->keyBy(fn($item) => $item->date->toDateString());

        $pointAnnotations = $grid->trades()
            ->orderBy('executed_at')
            ->get()
            ->filter(function ($trade) use ($datesMap) {
                 // Only show trades that are within the chart's date range
                 $dateStr = $trade->executed_at instanceof \Carbon\Carbon 
                    ? $trade->executed_at->toDateString() 
                    : \Illuminate\Support\Carbon::parse($trade->executed_at)->toDateString();
                 return $datesMap->has($dateStr);
            })
            ->map(function ($trade) use ($datesMap) {
                $color = $trade->side === 'buy' ? '#00E396' : '#FF4560';
                $sideChar = str($trade->side)->substr(0, 1)->upper();
                
                $dateStr = $trade->executed_at instanceof \Carbon\Carbon 
                    ? $trade->executed_at->toDateString() 
                    : \Illuminate\Support\Carbon::parse($trade->executed_at)->toDateString();
                
                // Mirror Backtest Logic: Use the day's CLOSE price for the marker position y-value
                // But show the ACTUAL TRADING PRICE in the label
                $dayPrice = $datesMap->get($dateStr);
                $yPosition = $dayPrice ? (float)$dayPrice->close_price : (float)$trade->price;

                return [
                    'x' => $dateStr,
                    'y' => $yPosition,
                    'marker' => [
                        'size' => 6,
                        'fillColor' => $color,
                        'strokeColor' => '#fff',
                        'strokeWidth' => 2,
                        'shape' => 'circle',
                    ],
                    'label' => [
                        'borderColor' => $color,
                        'offsetY' => -10,
                        'style' => [
                            'color' => '#fff',
                            'background' => $color,
                            'fontSize' => '10px',
                            'fontWeight' => 'bold',
                        ],
                        'text' => "{$sideChar} " . number_format($trade->price, 3),
                    ],
                ];
            })->values()->toArray();

        return [
            'chart' => [
                'type' => 'candlestick',
                'height' => 450,
                'toolbar' => [
                    'show' => true,
                    'tools' => [
                        'zoom' => true,
                        'zoomin' => true,
                        'zoomout' => true,
                        'pan' => true,
                        'reset' => true,
                    ],
                ],
            ],
            'series' => [
                [
                    'name' => 'Price',
                    'data' => $chartData,
                ],
            ],
            'xaxis' => [
                'type' => 'category',
                'categories' => $categories,
                'tickAmount' => 10,
                'labels' => [
                    'rotate' => -45,
                    'rotateAlways' => false,
                    'hideOverlappingLabels' => true,
                    'trim' => true,
                    'style' => [
                        'fontSize' => '11px',
                    ],
                ],
            ],
            'yaxis' => [
                'decimalsInFloat' => 3, // Use ApexCharts native formatting instead of JS function
                'tooltip' => [
                    'enabled' => true,
                ],
            ],
            'tooltip' => [
                'x' => [
                    'show' => true,
                ],
            ],
            'annotations' => [
                'points' => $pointAnnotations,
            ],
        ];
    }
}
