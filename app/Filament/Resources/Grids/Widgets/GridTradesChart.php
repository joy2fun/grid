<?php

namespace App\Filament\Resources\Grids\Widgets;

use App\Models\Grid;
use Illuminate\Database\Eloquent\Model;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class GridTradesChart extends ApexChartWidget
{
    /**
     * Chart Id
     */
    protected static ?string $chartId = 'gridTradesChart';

    public function getHeading(): string
    {
        return __('app.widgets.stock_price_trades');
    }

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

        // Get earliest trade date
        $earliestTrade = $grid->trades()
            ->orderBy('executed_at')
            ->first();
        $earliestTradeDate = $earliestTrade?->executed_at;

        // Default to 120 days ago
        $defaultStartDate = now()->subDays(120);

        // Determine chart start date: use earliest trade date if it's earlier than 120 days ago
        $chartStartDate = $earliestTradeDate && $earliestTradeDate->lt($defaultStartDate)
            ? $earliestTradeDate
            : $defaultStartDate;

        $data = \App\Models\DayPrice::query()
            ->where('stock_id', $stockId)
            ->where('date', '>=', $chartStartDate->toDateString())
            ->orderBy('date', 'desc')
            ->get()
            ->reverse() // Order chronologically for the chart
            ->values();

        $chartData = $data->map(function ($dayPrice) {
            return [
                'x' => $dayPrice->date->format('Y-m-d'),
                'y' => [
                    (float) $dayPrice->open_price,
                    (float) $dayPrice->high_price,
                    (float) $dayPrice->low_price,
                    (float) $dayPrice->close_price,
                ],
            ];
        })->toArray();

        $categories = $data->map(fn ($dayPrice) => $dayPrice->date->format('m-d'))->values()->toArray();

        // Key data by date for easy lookup of close price
        $datesMap = $data->keyBy(fn ($item) => $item->date->toDateString());

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
            ->map(function ($trade) {
                $color = match ($trade->type) {
                    'buy' => '#00E396',
                    'sell' => '#FF4560',
                    'dividend' => '#008FFB',
                    'stock_dividend' => '#FEB019',
                    'stock_split' => '#775DD0',
                    default => '#999999',
                };

                $typeChar = match ($trade->type) {
                    'buy' => 'B',
                    'sell' => 'S',
                    'dividend' => 'D',
                    'stock_dividend' => 'G',
                    'stock_split' => 'P',
                    default => 'T',
                };

                $dateStr = $trade->executed_at instanceof \Carbon\Carbon
                    ? $trade->executed_at->toDateString()
                    : \Illuminate\Support\Carbon::parse($trade->executed_at)->toDateString();

                // Only show price markers for buy/sell trades
                if (! in_array($trade->type, ['buy', 'sell'])) {
                    return null;
                }

                // Use the ACTUAL TRADING PRICE for the marker position y-value
                $yPosition = (float) $trade->price;

                return [
                    'x' => $dateStr,
                    'y' => $yPosition,
                    'marker' => [
                        'size' => 2,
                        'fillColor' => $color,
                        'strokeColor' => '#fff',
                        'strokeWidth' => 1,
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
                        'text' => "{$typeChar} ".number_format($trade->price, 3),
                    ],
                ];
            })->filter()->values()->toArray();

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
                'animations' => [
                    'enabled' => false,
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
                    'rotate' => 0,
                    'rotateAlways' => false,
                    'hideOverlappingLabels' => false,
                    'trim' => false,
                    'style' => [
                        'fontSize' => '9px',
                    ],
                ],
                'axisBorder' => [
                    'show' => false,
                ],
                'axisTicks' => [
                    'show' => false,
                ],
                'crosshairs' => [
                    'show' => true,
                    'position' => 'back',
                    'stroke' => [
                        'color' => '#b6b6b6',
                        'width' => 1,
                        'dashArray' => 3,
                    ],
                ],
                'tooltip' => [
                    'enabled' => true,
                ],
            ],
            'yaxis' => [
                'decimalsInFloat' => 3,
                'tooltip' => [
                    'enabled' => true,
                ],
                'labels' => [
                    'show' => false,
                ],
                'axisBorder' => [
                    'show' => false,
                ],
                'axisTicks' => [
                    'show' => false,
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
