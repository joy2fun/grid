<?php

namespace App\Filament\Resources\PriceAlerts\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PriceAlertsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('stock.name')
                    ->label('Stock Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('threshold_type')
                    ->label('Alert Type')
                    ->formatStateUsing(fn ($state) => $state === 'rise' ? 'Price Rise' : 'Price Drop')
                    ->badge()
                    ->color(fn ($state) => $state === 'rise' ? 'success' : 'danger'),

                TextColumn::make('threshold_value')
                    ->label('Threshold')
                    ->sortable()
                    ->numeric(decimalPlaces: 2),

                TextColumn::make('stock.current_price')
                    ->label('Current Price')
                    ->numeric(decimalPlaces: 2)
                    ->default('-'),

                TextColumn::make('last_notified_at')
                    ->label('Last Notified')
                    ->dateTime()
                    ->since(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('stock'))
            ->filters([
                SelectFilter::make('threshold_type')
                    ->label('Alert Type')
                    ->options([
                        'rise' => 'Price Rise',
                        'drop' => 'Price Drop',
                    ]),
                SelectFilter::make('is_active')
                    ->label('Active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
