<?php

namespace App\Filament\Resources\Trades\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TradeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('grid_id')
                    ->relationship('grid', 'name'),
                Select::make('stock_id')
                    ->relationship('stock', 'name')
                    ->required(),
                Select::make('side')
                    ->options([
                        'buy' => 'Buy',
                        'sell' => 'Sell',
                    ])
                    ->required(),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                DateTimePicker::make('executed_at')
                    ->required(),
            ]);
    }
}
