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

        // Calculate total cash flow
        $totalBuy = 0;
        $totalSell = 0;

        foreach ($trades as $trade) {
            $amount = $trade->price * $trade->quantity;
            if ($trade->side === 'buy') {
                $totalBuy += $amount;
            } else {
                $totalSell += $amount;
            }
        }

        $subHeadingText = 'Out: ¥'.number_format($totalBuy, 2).' | In: ¥'.number_format($totalSell, 2);

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
            // Make buy values negative for outflow and round to integer
            $buyData[] = round(-1 * $data['buy']);
            $sellData[] = round($data['sell']);
        }

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 350,
                'stacked' => true,
                'toolbar' => [
                    'show' => false,
                ],
            ],
            'title' => [
                'text' => $subHeadingText,
                'align' => 'left',
                'margin' => 10,
                'style' => [
                    'fontSize' => '12px',
                    'fontWeight' => 'normal',
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
                'labels' => [
                    'show' => false,
                ],
                'title' => [
                    'text' => '',
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
                'show' => false,
            ],
            'tooltip' => [
                'enabled' => true,
            ],
        ];
    }
}
