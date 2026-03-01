<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChartController extends Controller
{
    /**
     * Display the chart page with available stocks.
     */
    public function index(): View
    {
        $stocks = Stock::where('type', '!=', 'index')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        return view('chart', compact('stocks'));
    }

    /**
     * Get chart data for selected stocks and date range.
     */
    public function data(Request $request): array
    {
        $validated = $request->validate([
            'stock_ids' => 'required|array|min:1',
            'stock_ids.*' => 'integer|exists:stocks,id',
            'start_date' => 'required|date',
            'interval' => 'nullable|string|in:daily,2days,3days,auto',
        ]);

        $stockIds = $validated['stock_ids'];
        $startDate = $validated['start_date'];
        $interval = $validated['interval'] ?? 'auto';
        $initialCapital = 100000; // 初始市值 10万

        $stocks = Stock::whereIn('id', $stockIds)
            ->with(['dayPrices' => function ($query) use ($startDate) {
                $query->where('date', '>=', $startDate)
                    ->orderBy('date');
            }])
            ->get();

        $series = [];
        $dates = [];
        $sharesMap = [];

        foreach ($stocks as $stock) {
            $startDayPrice = $stock->dayPrices->first();

            if (! $startDayPrice) {
                continue;
            }

            $startPrice = (float) $startDayPrice->close_price;
            if ($startPrice <= 0) {
                continue;
            }

            $shares = $initialCapital / $startPrice;
            $sharesMap[$stock->id] = $shares;

            $data = [];
            foreach ($stock->dayPrices as $dayPrice) {
                $marketValue = $shares * (float) $dayPrice->close_price;
                $data[] = [
                    'date' => $dayPrice->date->format('Y-m-d'),
                    'value' => round($marketValue, 2),
                ];

                if (! in_array($dayPrice->date->format('Y-m-d'), $dates)) {
                    $dates[] = $dayPrice->date->format('Y-m-d');
                }
            }

            if (count($data) > 0) {
                $series[] = [
                    'id' => 'stock_'.$stock->id,
                    'name' => $stock->name,
                    'code' => $stock->code,
                    'color' => $this->generateColor($stock->id),
                    'data' => $data,
                    'shares' => round($shares, 4),
                    'startPrice' => $startPrice,
                ];
            }
        }

        sort($dates);

        // Auto-detect interval if needed
        if ($interval === 'auto') {
            $interval = $this->detectOptimalInterval($dates);
        }

        // Aggregate data based on interval
        if ($interval !== 'daily') {
            [$dates, $series] = $this->aggregateData($dates, $series, $interval);
        }

        // Normalize series data
        $normalizedSeries = [];
        foreach ($series as $s) {
            $dataMap = collect($s['data'])->keyBy('date');
            $normalizedData = [];

            foreach ($dates as $date) {
                if ($dataMap->has($date)) {
                    $normalizedData[] = $dataMap[$date]['value'];
                } else {
                    $lastValue = count($normalizedData) > 0 ? $normalizedData[count($normalizedData) - 1] : $initialCapital;
                    $normalizedData[] = $lastValue;
                }
            }

            $normalizedSeries[] = [
                'id' => $s['id'],
                'name' => $s['name'],
                'code' => $s['code'],
                'color' => $s['color'],
                'data' => $normalizedData,
                'shares' => $s['shares'],
                'startPrice' => $s['startPrice'],
            ];
        }

        return [
            'series' => $normalizedSeries,
            'dates' => $dates,
            'startDate' => $startDate,
            'initialCapital' => $initialCapital,
            'interval' => $interval,
            'totalPoints' => count($dates),
        ];
    }

    /**
     * Auto-detect optimal interval based on data span.
     */
    private function detectOptimalInterval(array $dates): string
    {
        $count = count($dates);

        if ($count <= 180) {
            return 'daily';        // <= 6 months: daily
        } elseif ($count <= 270) {
            return '2days';        // 6-9 months: every 2 days
        } else {
            return '3days';        // > 9 months: every 3 days
        }
    }

    /**
     * Aggregate data based on interval.
     */
    private function aggregateData(array $dates, array $series, string $interval): array
    {
        $intervalMap = [
            'daily' => 1,
            '2days' => 2,
            '3days' => 3,
        ];

        $step = $intervalMap[$interval] ?? 1;
        $aggregatedDates = [];
        $aggregatedSeries = [];

        // Sample dates at interval
        for ($i = 0; $i < count($dates); $i += $step) {
            $aggregatedDates[] = $dates[$i];
        }

        // Ensure last date is included
        if (end($aggregatedDates) !== end($dates)) {
            $aggregatedDates[] = end($dates);
        }

        // Aggregate series data
        foreach ($series as $s) {
            $dataMap = collect($s['data'])->keyBy('date');
            $newData = [];

            foreach ($aggregatedDates as $date) {
                if ($dataMap->has($date)) {
                    $newData[] = [
                        'date' => $date,
                        'value' => $dataMap[$date]['value'],
                    ];
                } else {
                    // Find nearest previous date
                    $nearestDate = $this->findNearestDate($date, $dates);
                    $value = $dataMap->has($nearestDate) ? $dataMap[$nearestDate]['value'] : $s['data'][0]['value'];
                    $newData[] = ['date' => $date, 'value' => $value];
                }
            }

            $aggregatedSeries[] = [
                'id' => $s['id'],
                'name' => $s['name'],
                'code' => $s['code'],
                'color' => $s['color'],
                'data' => $newData,
                'shares' => $s['shares'],
                'startPrice' => $s['startPrice'],
            ];
        }

        return [$aggregatedDates, $aggregatedSeries];
    }

    /**
     * Find nearest previous date.
     */
    private function findNearestDate(string $targetDate, array $dates): ?string
    {
        $target = strtotime($targetDate);
        $nearest = null;
        $minDiff = PHP_INT_MAX;

        foreach ($dates as $date) {
            $current = strtotime($date);
            $diff = $target - $current;
            if ($diff >= 0 && $diff < $minDiff) {
                $minDiff = $diff;
                $nearest = $date;
            }
        }

        return $nearest;
    }

    /**
     * Generate a consistent color for a stock.
     */
    private function generateColor(int $id): string
    {
        $colors = [
            '#00d4aa', '#6366f1', '#f59e0b', '#ef4444',
            '#22d3ee', '#ec4899', '#a855f7', '#84cc16',
            '#f97316', '#06b6d4', '#8b5cf6', '#f43f5e',
        ];

        return $colors[$id % count($colors)];
    }
}
