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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('stock_id')
                    ->relationship('stock', 'name')
                    ->required(),
                TextInput::make('initial_quantity')
                    ->label('Initial Quantity')
                    ->numeric()
                    ->required(),
                TextInput::make('initial_cost')
                    ->label('Initial Cost')
                    ->numeric()
                    ->required(),
                TextInput::make('quantity')
                    ->label('Current Quantity')
                    ->numeric()
                    ->disabled(),
                TextInput::make('average_cost')
                    ->label('Average Cost')
                    ->numeric()
                    ->disabled(),
                TextInput::make('total_cost')
                    ->label('Total Cost')
                    ->numeric()
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('stock.name')
                    ->label('Stock')
                    ->description(fn (Holding $record): string => $record->stock->code)
                    ->sortable()
                    ->searchable(['name', 'code']),
                TextColumn::make('quantity')
                    ->label('Current Qty')
                    ->numeric(0)
                    ->sortable(),
                TextColumn::make('average_cost')
                    ->label('Avg Cost')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->numeric(0)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('initial_quantity')
                    ->label('Initial Qty')
                    ->numeric(0)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('initial_cost')
                    ->label('Initial Cost')
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
