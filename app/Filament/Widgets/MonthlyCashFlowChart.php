<?php

namespace App\Filament\Widgets;

use App\Models\Trade;
use Carbon\Carbon;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class MonthlyCashFlowChart extends ApexChartWidget
{
    protected static ?string $chartId = 'monthlyCashFlowChart';

    protected static ?string $heading = 'Monthly Cash Flow';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getOptions(): array
    {
        // Get trades from last 12 months
        $startDate = Carbon::now()->subMonths(12)->startOfMonth();

        $trades = Trade::query()
            ->where('executed_at', '>=', $startDate)
            ->orderBy('executed_at')
            ->get();

        // Group trades by month
        $monthlyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i)->format('Y-m');
            $monthlyData[$month] = [
                'buy' => 0,
                'sell' => 0,
            ];
        }

        foreach ($trades as $trade) {
            $month = $trade->executed_at->format('Y-m');
            if (isset($monthlyData[$month])) {
                $amount = $trade->price * $trade->quantity;
                $monthlyData[$month][$trade->side] += $amount;
            }
        }

        // Prepare chart data
        $categories = [];
        $buyData = [];
        $sellData = [];

        foreach ($monthlyData as $month => $data) {
            $categories[] = Carbon::createFromFormat('Y-m', $month)->format('M Y');
            // Make buy values negative for outflow
            $buyData[] = -1 * $data['buy'];
            $sellData[] = $data['sell'];
        }

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 300,
                'stacked' => true,
                'toolbar' => [
                    'show' => true,
                ],
            ],
            'series' => [
                [
                    'name' => 'Buy (Outflow)',
                    'data' => $buyData,
                ],
                [
                    'name' => 'Sell (Inflow)',
                    'data' => $sellData,
                ],
            ],
            'xaxis' => [
                'categories' => $categories,
                'labels' => [
                    'rotate' => -45,
                    'style' => [
                        'fontSize' => '11px',
                    ],
                ],
            ],
            'yaxis' => [
                'title' => [
                    'text' => 'Cash Flow (¥)',
                ],
            ],
            'plotOptions' => [
                'bar' => [
                    'borderRadius' => 4,
                ],
            ],
            'colors' => ['#FF4560', '#00E396'],
            'dataLabels' => [
                'enabled' => false,
            ],
            'legend' => [
                'position' => 'top',
                'horizontalAlign' => 'right',
            ],
            'tooltip' => [
                'enabled' => true,
            ],
        ];
    }
}
