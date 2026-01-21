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
                    ->label('Stock')
                    ->relationship('stock', 'name')
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->code})")
                    ->required(),

                Select::make('threshold_type')
                    ->label('Alert Type')
                    ->options([
                        'rise' => 'Price Rise (Alert when price >= threshold)',
                        'drop' => 'Price Drop (Alert when price <= threshold)',
                    ])
                    ->required(),

                TextInput::make('threshold_value')
                    ->label('Threshold Price')
                    ->numeric()
                    ->step(0.01)
                    ->required()
                    ->helperText('Enter the price threshold that will trigger the alert.'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
