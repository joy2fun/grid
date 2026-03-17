<?php

namespace App\Services;

use App\Models\Holding;
use App\Models\Trade;
use App\Utilities\Helper;
use Illuminate\Support\Facades\Cache;

class PortfolioService
{
    /**
     * Calculate XIRR for all trades across all stocks.
     * Uses 5-minute cache for performance.
     */
    public static function calculateOverallXirr(): ?float
    {
        return Cache::remember('portfolio_overall_xirr', 300, function () {
            return self::calculateOverallXirrInternal();
        });
    }

    /**
     * Clear the XIRR cache when trades are modified.
     */
    public static function clearXirrCache(): void
    {
        Cache::forget('portfolio_overall_xirr');
    }

    /**
     * Internal calculation without caching.
     */
    private static function calculateOverallXirrInternal(): ?float
    {
        // Get all trades with stock info for current prices
        $trades = Trade::query()
            ->with(['stock' => function ($query) {
                $query->select('id', 'current_price');
            }])
            ->orderBy('executed_at')
            ->get();

        if ($trades->isEmpty()) {
            return null;
        }

        $cashFlows = [];
        $dates = [];

        foreach ($trades as $trade) {
            $date = $trade->executed_at->toDateString();

            switch ($trade->type) {
                case 'buy':
                    $cost = (float) $trade->quantity * (float) $trade->price;
                    $cashFlows[] = -$cost;
                    $dates[] = $date;

                    break;

                case 'sell':
                    $proceeds = (float) $trade->quantity * (float) $trade->price;
                    $cashFlows[] = $proceeds;
                    $dates[] = $date;

                    break;

                case 'dividend':
                    $dividendAmount = (float) $trade->quantity * (float) $trade->price;
                    if ($dividendAmount > 0) {
                        $cashFlows[] = $dividendAmount;
                        $dates[] = $date;
                    }

                    break;

                case 'stock_split':
                case 'stock_dividend':
                    // These don't directly affect cash flows for XIRR
                    break;
            }
        }

        // Add current market value of all holdings as final cash flow
        $holdings = Holding::query()
            ->with(['stock' => function ($query) {
                $query->select('id', 'current_price');
            }])
            ->get();

        $totalHoldingValue = 0;
        foreach ($holdings as $holding) {
            if ($holding->quantity > 0 && $holding->stock?->current_price) {
                $totalHoldingValue += (float) $holding->quantity * (float) $holding->stock->current_price;
            }
        }

        if ($totalHoldingValue > 0) {
            $cashFlows[] = $totalHoldingValue;
            $dates[] = now()->toDateString();
        }

        return Helper::calculateXIRR($cashFlows, $dates);
    }
}
