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
                    ->label(__('app.price_alert.stock_name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('threshold_type')
                    ->label(__('app.price_alert.alert_type'))
                    ->formatStateUsing(fn ($state) => $state === 'rise' ? __('app.price_alert.price_rise') : __('app.price_alert.price_drop'))
                    ->badge()
                    ->color(fn ($state) => $state === 'rise' ? 'success' : 'danger'),

                TextColumn::make('threshold_value')
                    ->label(__('app.price_alert.threshold'))
                    ->sortable()
                    ->numeric(decimalPlaces: 2),

                TextColumn::make('stock.current_price')
                    ->label(__('app.price_alert.current_price'))
                    ->numeric(decimalPlaces: 2)
                    ->default('-'),

                TextColumn::make('last_notified_at')
                    ->label(__('app.price_alert.last_notified'))
                    ->dateTime()
                    ->since(),

                IconColumn::make('is_active')
                    ->label(__('app.price_alert.active'))
                    ->boolean(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('stock'))
            ->filters([
                SelectFilter::make('threshold_type')
                    ->label(__('app.price_alert.alert_type'))
                    ->options([
                        'rise' => __('app.price_alert.price_rise'),
                        'drop' => __('app.price_alert.price_drop'),
                    ]),
                SelectFilter::make('is_active')
                    ->label(__('app.price_alert.active'))
                    ->options([
                        '1' => __('app.common.active'),
                        '0' => __('app.common.inactive'),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
