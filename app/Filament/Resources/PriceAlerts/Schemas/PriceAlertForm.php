<?php

namespace App\Filament\Resources\PriceAlerts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PriceAlertForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('stock_id')
                    ->label(__('app.price_alert.stock'))
                    ->relationship('stock', 'name')
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->code})")
                    ->required()
                    ->preload(),

                Select::make('threshold_type')
                    ->label(__('app.price_alert.alert_type'))
                    ->options([
                        'rise' => __('app.price_alert.threshold_type_rise'),
                        'drop' => __('app.price_alert.threshold_type_drop'),
                    ])
                    ->required(),

                TextInput::make('threshold_value')
                    ->label(__('app.price_alert.threshold_price'))
                    ->numeric()
                    ->step(0.01)
                    ->required()
                    ->helperText(__('app.price_alert.threshold_helper')),

                Toggle::make('is_active')
                    ->label(__('app.price_alert.active'))
                    ->default(true),
            ]);
    }
}
