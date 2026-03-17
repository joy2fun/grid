<?php

namespace App\Filament\Resources\Holdings;

use App\Filament\Resources\Holdings\Pages\ManageHoldings;
use App\Filament\Resources\Trades\TradeResource;
use App\Models\Holding;
use App\Services\PortfolioService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HoldingResource extends Resource
{
    protected static ?string $model = Holding::class;

    protected static ?bool $canCreate = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

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
                    ->sortable()
                    ->summarize(
                        Summarizer::make('overall_xirr')
                            ->label(__('app.portfolio.overall_xirr'))
                            ->using(fn (): string => PortfolioService::calculateOverallXirr() ?? '-')
                            ->formatStateUsing(fn ($state): string => is_numeric($state) ? number_format($state * 100, 2).'%' : $state)
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
                TextColumn::make('stock.current_price')
                    ->label(__('app.stock.current_price'))
                    ->numeric(3)
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
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated(false)
            ->filters([
                //
            ])
            ->summaries(
                pageCondition: false,
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHoldings::route('/'),
        ];
    }
}
