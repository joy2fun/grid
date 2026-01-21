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
                    ->label('Code')
                    ->required()
                    ->maxLength(255),

                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('peak_value')
                    ->label('Peak Value')
                    ->numeric()
                    ->step(0.0001),

                Select::make('type')
                    ->label('Type')
                    ->required()
                    ->options([
                        'etf' => 'ETF',
                        'index' => 'Index',
                    ]),
            ]);
    }
}
