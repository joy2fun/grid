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

    public ?string $timeRange = '3y';

    #[\Livewire\Attributes\On('updateChart')]
    public function updateChart(string $timeRange): void
    {
        $this->timeRange = $timeRange;
        $this->updateOptions();
    }

    protected function getOptions(): array
    {
        // Get all index stocks
        $indexStocks = Stock::query()
            ->where('type', 'index')
            ->get();

        if ($indexStocks->isEmpty()) {
            return [];
        }

        // Calculate the date cutoff based on time range
        $startDate = $this->getStartDateFromTimeRange();

        $series = [];
        $yAxisConfig = [];
        $allDates = collect();

        // For each index stock, get historical prices within the time range
        foreach ($indexStocks as $stock) {
            $query = DayPrice::query()
                ->where('stock_id', $stock->id)
                ->orderBy('date', 'asc');

            if ($startDate) {
                $query->where('date', '>=', $startDate);
            }

            $prices = $query->get();

            if ($prices->isEmpty()) {
                continue;
            }

            // Downsample data if too many points (target ~300 points max) to improve rendering performance
            if ($prices->count() > 300) {
                // Determine interval to pick every Nth record
                // e.g. 1500 records / 300 target = 5 (keep every 5th record)
                $interval = (int) ceil($prices->count() / 300);
                $prices = $prices->nth($interval);
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
                'floating' => true,
            ];
        }

        // Get unique sorted dates for categories
        $categories = $allDates->unique()->sort()->values()->toArray();

        return [
            'chart' => [
                'type' => 'line',
                'height' => 350,
                'animations' => [
                    'enabled' => false,
                ],
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
            'grid' => [
                'padding' => [
                    'left' => 0,
                    'right' => 0,
                ],
            ],
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

    protected function getStartDateFromTimeRange(): ?\Carbon\Carbon
    {
        return match ($this->timeRange) {
            '3m' => now()->subMonths(3),
            '6m' => now()->subMonths(6),
            '12m', '1y' => now()->subYear(),
            '18m' => now()->subMonths(18),
            '2y' => now()->subYears(2),
            '3y' => now()->subYears(3),
            '4y' => now()->subYears(4),
            '5y' => now()->subYears(5),
            '6y' => now()->subYears(6),
            default => now()->subYears(3), // Default to 3 years
        };
    }
}
