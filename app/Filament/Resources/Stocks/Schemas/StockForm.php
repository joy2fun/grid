<?php

namespace App\Filament\Resources\Stocks\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label(__('app.stock.code'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('name')
                    ->label(__('app.stock.name'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('peak_value')
                    ->label(__('app.stock.peak_value'))
                    ->numeric()
                    ->step(0.0001),

                Select::make('type')
                    ->label(__('app.stock.type'))
                    ->required()
                    ->options([
                        'etf' => __('app.stock.type_etf'),
                        'index' => __('app.stock.type_index'),
                    ]),
            ]);
    }
}
