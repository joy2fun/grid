<?php

namespace App\Filament\Resources\Stocks\Pages;

use App\Filament\Resources\Stocks\StockResource;
use App\Models\Stock;
use App\Utilities\Helper;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class BacktestStock extends Page implements HasForms
{
    use InteractsWithRecord;
    use InteractsWithForms;

    protected static string $resource = StockResource::class;

    protected string $view = 'filament.resources.stocks.pages.backtest-stock';

    public ?array $data = [];
    public $results = null;

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->form->fill([
            'start_date' => Carbon::now()->subYear(),
            'end_date' => Carbon::now(),
            'interval' => 5,
            'trade_amount' => 10000,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Backtest Parameters')
                    ->columns(4)
                    ->schema([
                        TextInput::make('trade_amount')
                            ->numeric()
                            ->required()
                            ->default(10000)
                            ->prefix('$'),
                        TextInput::make('interval')
                            ->numeric()
                            ->required()
                            ->default(5)
                            ->step(0.1)
                            ->suffix('%')
                            ->label('Grid Interval (%)'),
                        DatePicker::make('start_date')
                            ->required(),
                        DatePicker::make('end_date')
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function runBacktest()
    {
        $data = $this->form->getState();
        $startDate = $data['start_date'];
        $endDate = $data['end_date'];
        $intervalPercent = (float) $data['interval']; // Now a percentage (e.g., 5 for 5%)
        $tradeAmount = (float) $data['trade_amount'];

        $prices = $this->record->dayPrices()
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        if ($prices->isEmpty()) {
            Notification::make()->title('No data found for this period.')->warning()->send();
            return;
        }

        // Logic
        $cash = 0;
        $shares = 0;
        $trades = [];
        $cashFlows = []; // For XIRR: [amount, date]
        $dates = []; // For XIRR
        $maxCashOccupied = 0; // Track the most negative cash position

        $referencePrice = $prices->first()->close_price;
        $firstDate = $prices->first()->date;

        // Place initial buy order at the reference price
        $rawShares = $tradeAmount / $referencePrice;
        $initialShares = round($rawShares / 100) * 100;

        if ($initialShares > 0) {
            $cost = $initialShares * $referencePrice;
            $cash -= $cost;
            $shares += $initialShares;
            $maxCashOccupied = $cash;

            $trades[] = [
                'type' => 'buy',
                'price' => $referencePrice,
                'shares' => $initialShares,
                'date' => $firstDate->toDateString(),
            ];

            $cashFlows[] = -$cost;
            $dates[] = $firstDate->toDateString();
        }

        $lastTradePrice = $referencePrice;

        // Skip the first price since we already used it for initial buy
        $pricesForLoop = $prices->skip(1);

        foreach ($pricesForLoop as $dayPrice) {
            $currentPrice = $dayPrice->close_price;
            $date = $dayPrice->date;

            // Calculate percentage change from last trade price
            $percentChange = (($currentPrice - $lastTradePrice) / $lastTradePrice) * 100;

            // BUY condition: price dropped by interval percentage
            if ($percentChange <= -$intervalPercent) {
                // Determine shares to buy closest to fixed amount
                // shares must be multiple of 100
                $rawShares = $tradeAmount / $currentPrice;
                $buyShares = round($rawShares / 100) * 100;

                if ($buyShares > 0) {
                    $cost = $buyShares * $currentPrice;
                    $cash -= $cost;

                    // Track maximum cash occupied (most negative)
                    if ($cash < $maxCashOccupied) {
                        $maxCashOccupied = $cash;
                    }

                    $shares += $buyShares;
                    $lastTradePrice = $currentPrice; // Update grid reference

                    $trades[] = [
                        'type' => 'buy',
                        'price' => $currentPrice,
                        'shares' => $buyShares,
                        'date' => $date->toDateString(),
                    ];

                    $cashFlows[] = -$cost;
                    $dates[] = $date->toDateString();
                }
            }
            // SELL condition: price rose by interval percentage
            elseif ($percentChange >= $intervalPercent) {
                if ($shares > 0) {
                     $rawShares = $tradeAmount / $currentPrice;
                     $sellShares = round($rawShares / 100) * 100;
                     $sellShares = min($sellShares, $shares);

                     if ($sellShares > 0) {
                        $revenue = $sellShares * $currentPrice;
                        $cash += $revenue;
                        $shares -= $sellShares;
                        $lastTradePrice = $currentPrice;

                        $trades[] = [
                            'type' => 'sell',
                            'price' => $currentPrice,
                            'shares' => $sellShares,
                            'date' => $date->toDateString(),
                        ];

                        $cashFlows[] = $revenue;
                        $dates[] = $date->toDateString();
                     }
                }
            }
        }

        // Final holding value
        $finalPrice = $prices->last()->close_price;
        $holdingValue = $shares * $finalPrice;

        // Add final "liquidation" for XIRR calculation
        if ($holdingValue > 0) {
            $cashFlows[] = $holdingValue;
            $dates[] = $prices->last()->date->toDateString();
        }

        // Calculate XIRR
        $xirr = Helper::calculateXIRR($cashFlows, $dates);

        // Prepare cash flow details for debugging
        $cashFlowDetails = [];
        for ($i = 0; $i < count($cashFlows); $i++) {
            $cashFlowDetails[] = [
                'date' => $dates[$i],
                'amount' => $cashFlows[$i],
                'type' => $cashFlows[$i] < 0 ? 'Outflow (Buy)' : 'Inflow (Sell/Final)',
            ];
        }

        $this->results = [
            'xirr' => $xirr,
            'total_profit' => $cash + $holdingValue,
            'net_cash' => $cash,
            'holding_value' => $holdingValue,
            'max_cash_occupied' => abs($maxCashOccupied), // Show as positive number
            'trades_count' => count($trades),
            'final_shares' => $shares,
            'final_price' => $finalPrice,
            'trades' => $trades,
            'cash_flows' => $cashFlowDetails,
            'chart_data' => $prices->map(fn($p) => [
                'x' => $p->date->toDateString(),
                'y' => [(float)$p->open_price, (float)$p->high_price, (float)$p->low_price, (float)$p->close_price]
            ])->toArray(),
            'annotations' => collect($trades)->map(fn($trade) => [
                'x' => (new \DateTime($trade['date']))->getTimestamp() * 1000,
                'borderColor' => $trade['type'] === 'buy' ? '#00E396' : '#FF4560',
                'label' => [
                    'borderColor' => $trade['type'] === 'buy' ? '#00E396' : '#FF4560',
                    'style' => [
                        'color' => '#fff',
                        'background' => $trade['type'] === 'buy' ? '#00E396' : '#FF4560',
                        'fontSize' => '10px',
                        'fontWeight' => 'bold',
                    ],
                    'text' => str($trade['type'])->substr(0, 1)->upper() . ' ' . number_format($trade['price'], 3)
                ]
            ])->values()->toArray(),
        ];
    }
}
