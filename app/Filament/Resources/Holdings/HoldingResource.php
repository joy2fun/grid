<?php

namespace App\Filament\Resources\Holdings;

use App\Filament\Resources\Holdings\Pages\ManageHoldings;
use App\Models\Holding;
use BackedEnum;
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
