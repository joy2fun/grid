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
        $useWeeklyAggregation = $limit >= 300;

        $query = \App\Models\DayPrice::query()
            ->where('stock_id', $this->stockId)
            ->orderBy('date', 'desc')
            ->limit($limit);

        if ($useWeeklyAggregation) {
            // Select only needed columns for aggregation
            $query->select(['date', 'open_price', 'high_price', 'low_price', 'close_price']);
        }

        $prices = $query->get()->reverse()->values();

        if ($useWeeklyAggregation) {
            $chartData = $this->aggregateToWeeklyCandles($prices);
        } else {
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
        }

        $categories = $chartData->pluck('x')->toArray();

        // Get the date range of our price data to filter trades
        $firstPriceDate = $prices->first()?->date;
        $lastPriceDate = $prices->last()?->date;

        // Build a map of week keys to candle dates for trade marker alignment
        // Also track the start/end dates of each week from the actual data
        $weekToDateMap = [];
        $weekDateRanges = [];

        if ($useWeeklyAggregation) {
            // Re-group the prices to get proper week date ranges
            $pricesByWeek = $prices->groupBy(function ($dayPrice) {
                return $dayPrice->date->format('o').'-W'.$dayPrice->date->format('W');
            });

            foreach ($pricesByWeek as $weekKey => $weekPrices) {
                $sorted = $weekPrices->sortBy('date');
                $firstDay = $sorted->first()->date;
                $lastDay = $sorted->last()->date;

                $weekDateRanges[$weekKey] = [
                    'start' => $firstDay,
                    'end' => $lastDay,
                    'candle_date' => $lastDay->toDateString(),
                ];
                $weekToDateMap[$weekKey] = $lastDay->toDateString();
            }
        }

        $trades = \App\Models\Trade::query()
            ->where('stock_id', $this->stockId)
            ->when($firstPriceDate && $lastPriceDate, function ($query) use ($firstPriceDate, $lastPriceDate) {
                // Only get trades within our price data date range
                return $query->whereBetween('executed_at', [
                    $firstPriceDate->startOfDay(),
                    $lastPriceDate->endOfDay(),
                ]);
            })
            ->orderBy('executed_at')
            ->get();

        $pointAnnotations = $trades->map(function ($trade) use ($useWeeklyAggregation, $weekDateRanges) {
            // When using weekly candles, snap trade date to the matching week's candle date
            if ($useWeeklyAggregation) {
                $tradeDate = $trade->executed_at;
                $dateStr = null;

                // Find which week this trade belongs to by checking date ranges
                foreach ($weekDateRanges as $weekKey => $range) {
                    if ($tradeDate->between($range['start'], $range['end'])) {
                        $dateStr = $range['candle_date'];
                        break;
                    }
                }

                // Fallback to exact week key match if range check fails
                if ($dateStr === null) {
                    $tradeWeekKey = $tradeDate->format('o').'-W'.$tradeDate->format('W');
                    $dateStr = $weekDateRanges[$tradeWeekKey]['candle_date'] ?? $tradeDate->toDateString();
                }
            } else {
                $dateStr = $trade->executed_at->toDateString();
            }
            $color = match ($trade->type) {
                'buy' => '#00E396',
                'sell' => '#FF4560',
                'dividend' => '#008FFB',
                'stock_dividend' => '#FEB019',
                'stock_split' => '#775DD0',
                default => '#999999',
            };

            // Label background color with 50% opacity
            $labelBgColor = match ($trade->type) {
                'buy' => 'rgba(0, 227, 150, 0.7)',
                'sell' => 'rgba(255, 69, 96, 0.7)',
                'dividend' => 'rgba(0, 143, 251, 0.7)',
                'stock_dividend' => 'rgba(254, 176, 25, 0.7)',
                'stock_split' => 'rgba(119, 93, 208, 0.7)',
                default => 'rgba(153, 153, 153, 0.7)',
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
                    'borderColor' => $labelBgColor,
                    'style' => [
                        'color' => '#fff',
                        'background' => $labelBgColor,
                        'fontSize' => '10px',
                        'fontWeight' => 'bold',
                    ],
                    'text' => $typeLabel, //.' '.number_format($trade->price, 3),
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
                    // 'show' => false,
                    'rotate' => 0,
                    'style' => [
                        'fontSize' => '9px',
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

    /**
     * Aggregate daily prices into weekly candlesticks for better performance
     * with large datasets while preserving data integrity.
     *
     * @param  \Illuminate\Support\Collection  $prices
     * @return \Illuminate\Support\Collection
     */
    private function aggregateToWeeklyCandles($prices)
    {
        return $prices->groupBy(function ($dayPrice) {
            // Group by ISO year-week (e.g., "2024-W03")
            // Using 'o' for ISO week year to handle year boundaries correctly
            return $dayPrice->date->format('o').'-W'.$dayPrice->date->format('W');
        })->map(function ($weekPrices) {
            // Sort by date to ensure correct order
            $sorted = $weekPrices->sortBy('date')->values();

            $firstDay = $sorted->first();
            $lastDay = $sorted->last();

            return [
                'x' => $lastDay->date->toDateString(),
                'y' => [
                    (float) $firstDay->open_price,
                    (float) $sorted->max('high_price'),
                    (float) $sorted->min('low_price'),
                    (float) $lastDay->close_price,
                ],
            ];
        })->values();
    }

    /**
     * Get the ISO week key for a given date.
     * Uses ISO week year ('o') to handle year boundaries correctly.
     */
    private function getWeekKey(\Carbon\Carbon $date): string
    {
        return $date->format('o').'-W'.$date->format('W');
    }
}
