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
            ->columns([
                TextColumn::make('stock.name')
                    ->label('Stock Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('stock.code')
                    ->label('Stock Code')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('threshold_type')
                    ->label('Alert Type')
                    ->formatStateUsing(fn ($state) => $state === 'rise' ? 'Price Rise' : 'Price Drop')
                    ->badge()
                    ->color(fn ($state) => $state === 'rise' ? 'success' : 'danger'),

                TextColumn::make('threshold_value')
                    ->label('Threshold Price')
                    ->sortable()
                    ->numeric(decimalPlaces: 2)
                    ->money('CNY'),

                TextColumn::make('stock.dayPrices.0.close_price')
                    ->label('Current Price')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 2) : '-')
                    ->money('CNY'),

                TextColumn::make('last_notified_at')
                    ->label('Last Notified')
                    ->dateTime()
                    ->since(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with([
                'stock' => fn ($q) => $q->with([
                    'dayPrices' => fn ($q) => $q->latest('date')->limit(1),
                ]),
            ]))
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
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
