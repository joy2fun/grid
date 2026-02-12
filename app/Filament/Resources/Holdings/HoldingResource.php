<?php

namespace App\Filament\Resources\Holdings;

use App\Filament\Resources\Holdings\Pages\ManageHoldings;
use App\Filament\Resources\Trades\TradeResource;
use App\Models\Holding;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HoldingResource extends Resource
{
    protected static ?string $model = Holding::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    public static function getNavigationLabel(): string
    {
        return __('app.nav.holdings');
    }

    public static function getModelLabel(): string
    {
        return __('app.holding.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.nav.holdings');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('stock_id')
                    ->relationship('stock', 'name')
                    ->label(__('app.holding.stock'))
                    ->required(),
                TextInput::make('initial_quantity')
                    ->label(__('app.holding.initial_quantity'))
                    ->numeric()
                    ->required(),
                TextInput::make('initial_cost')
                    ->label(__('app.holding.initial_cost'))
                    ->numeric()
                    ->required(),
                TextInput::make('quantity')
                    ->label(__('app.holding.current_quantity'))
                    ->numeric()
                    ->disabled(),
                TextInput::make('average_cost')
                    ->label(__('app.holding.average_cost'))
                    ->numeric()
                    ->disabled(),
                TextInput::make('total_cost')
                    ->label(__('app.holding.total_cost'))
                    ->numeric()
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('stock.name')
                    ->label(__('app.holding.stock'))
                    ->description(fn (Holding $record): string => $record->stock->code)
                    ->sortable()
                    ->searchable(['name', 'code']),
                TextColumn::make('stock.xirr')
                    ->label(__('app.stock.xirr'))
                    ->getStateUsing(fn (Holding $record) => ($record->stock->xirr !== null) ? $record->stock->xirr * 100 : null)
                    ->badge()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => is_numeric($state) ? number_format($state, 2).'%' : $state)
                    ->default('-')
                    ->action(
                        Action::make('exportXirrCashFlow')
                            ->label(__('app.actions.export_xirr_cashflow'))
                            ->icon('heroicon-o-arrow-down-tray')
                            ->hidden(fn (Holding $record) => $record->stock->type !== 'etf')
                            ->action(function (Holding $record) {
                                $stock = $record->stock;
                                $cashFlows = [];
                                $trades = $stock->trades->sortBy('executed_at');

                                foreach ($trades as $trade) {
                                    $amount = (float) $trade->quantity * (float) $trade->price;
                                    $cashFlow = match ($trade->type) {
                                        'buy' => [
                                            'date' => $trade->executed_at->toDateString(),
                                            'amount' => -$amount,
                                            'description' => 'Buy '.$trade->quantity.' @ '.$trade->price,
                                        ],
                                        'sell' => [
                                            'date' => $trade->executed_at->toDateString(),
                                            'amount' => $amount,
                                            'description' => 'Sell '.$trade->quantity.' @ '.$trade->price,
                                        ],
                                        'dividend' => [
                                            'date' => $trade->executed_at->toDateString(),
                                            'amount' => $amount,
                                            'description' => 'Dividend '.$trade->quantity.' shares @ '.$trade->price.'/share',
                                        ],
                                        'stock_dividend' => [
                                            'date' => $trade->executed_at->toDateString(),
                                            'amount' => 0,
                                            'description' => 'Stock Dividend base='.$trade->quantity.' ratio='.($trade->split_ratio ?? $trade->price),
                                        ],
                                        'stock_split' => [
                                            'date' => $trade->executed_at->toDateString(),
                                            'amount' => 0,
                                            'description' => 'Stock Split ratio='.($trade->split_ratio ?? $trade->price),
                                        ],
                                        default => [
                                            'date' => $trade->executed_at->toDateString(),
                                            'amount' => 0,
                                            'description' => $trade->type.' '.$trade->quantity.' @ '.$trade->price,
                                        ],
                                    };
                                    $cashFlows[] = $cashFlow;
                                }

                                if ($record->quantity > 0 && $stock->current_price) {
                                    $holdingValue = (float) $record->quantity * (float) $stock->current_price;
                                    $cashFlows[] = [
                                        'date' => now()->toDateString(),
                                        'amount' => $holdingValue,
                                        'description' => 'Current holding value: '.$record->quantity.' shares @ '.$stock->current_price,
                                    ];
                                }

                                $csvContent = "Date,Amount,Description\n";
                                foreach ($cashFlows as $flow) {
                                    $csvContent .= "{$flow['date']},{$flow['amount']},\"{$flow['description']}\"\n";
                                }

                                $filename = 'xirr_cashflow_'.$stock->code.'_'.now()->format('Ymd_His').'.csv';

                                return response()->streamDownload(function () use ($csvContent) {
                                    echo $csvContent;
                                }, $filename, [
                                    'Content-Type' => 'text/csv',
                                ]);
                            })
                    ),
                TextColumn::make('stock.last_trade_at_formatted')
                    ->label(__('app.stock.last_trade'))
                    ->url(
                        fn (Holding $record): ?string => $record->stock->type === 'etf'
                            ? TradeResource::getUrl('index', [
                                'filters' => [
                                    'stock_id' => [
                                        'value' => $record->stock->id,
                                    ],
                                ],
                            ])
                            : null
                    ),
                TextColumn::make('quantity')
                    ->label(__('app.holding.current_qty'))
                    ->numeric(0)
                    ->sortable(),
                TextColumn::make('average_cost')
                    ->label(__('app.holding.avg_cost'))
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('total_cost')
                    ->label(__('app.holding.total_cost'))
                    ->numeric(0)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('initial_quantity')
                    ->label(__('app.holding.initial_quantity'))
                    ->numeric(0)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('initial_cost')
                    ->label(__('app.holding.initial_cost'))
                    ->numeric(3)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated(false)
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHoldings::route('/'),
        ];
    }
}
