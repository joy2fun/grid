<?php

namespace App\Filament\Widgets;

use App\Models\DayPrice;
use App\Models\Stock;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class IndexStockPricesChart extends ApexChartWidget
{
    /**
     * Chart Id
     */
    protected static ?string $chartId = 'indexStockPricesChart';

    protected static ?string $heading = 'Index Stock Price History';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getOptions(): array
    {
        // Get all index stocks
        $indexStocks = Stock::query()
            ->where('type', 'index')
            ->get();

        if ($indexStocks->isEmpty()) {
            return [];
        }

        $series = [];
        $yAxisConfig = [];
        $allDates = collect();

        // For each index stock, get all historical prices
        foreach ($indexStocks as $stock) {
            $prices = DayPrice::query()
                ->where('stock_id', $stock->id)
                ->orderBy('date', 'desc')
                ->get()
                ->reverse() // Ensure chronological order for chart
                ->values();

            if ($prices->isEmpty()) {
                continue;
            }

            // Collect all unique dates for x-axis
            $allDates = $allDates->merge($prices->pluck('date')->map(fn ($date) => $date->toDateString()));

            // Prepare series data for this stock
            $seriesData = $prices->map(function ($dayPrice) {
                return [
                    'x' => $dayPrice->date->toDateString(),
                    'y' => (float) $dayPrice->close_price,
                ];
            })->toArray();

            $series[] = [
                'name' => $stock->name,
                'data' => $seriesData,
            ];

            // Configure a separate y-axis for each stock (hidden but functional)
            // This ensures each stock has its own scale, preventing flat lines
            $yAxisConfig[] = [
                'seriesName' => $stock->name,
                'labels' => [
                    'show' => false,
                ],
                'axisBorder' => [
                    'show' => false,
                ],
                'axisTicks' => [
                    'show' => false,
                ],
            ];
        }

        // Get unique sorted dates for categories
        $categories = $allDates->unique()->sort()->values()->toArray();

        return [
            'chart' => [
                'type' => 'line',
                'height' => 350,
                'toolbar' => [
                    'show' => true,
                ],
            ],
            'series' => $series,
            'xaxis' => [
                'type' => 'category',
                'categories' => $categories,
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
            'yaxis' => $yAxisConfig,
            'stroke' => [
                'curve' => 'smooth',
                'width' => 2,
            ],
            'dataLabels' => [
                'enabled' => false,
            ],
            'legend' => [
                'show' => true,
                'position' => 'top',
            ],
            'tooltip' => [
                'shared' => true,
                'intersect' => false,
            ],
        ];
    }
}
