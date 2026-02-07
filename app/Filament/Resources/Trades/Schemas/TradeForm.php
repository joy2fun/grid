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
                    ->relationship('grid', 'name')
                    ->label(__('app.trade.grid')),
                Select::make('stock_id')
                    ->relationship('stock', 'name', modifyQueryUsing: fn ($query) => $query->where('type', '!=', 'index'))
                    ->label(__('app.trade.stock'))
                    ->required(),
                Select::make('side')
                    ->label(__('app.trade.side'))
                    ->options([
                        'buy' => __('app.trade.side_buy'),
                        'sell' => __('app.trade.side_sell'),
                    ])
                    ->required(),
                TextInput::make('price')
                    ->label(__('app.trade.price'))
                    ->required()
                    ->prefix('¥'),
                TextInput::make('quantity')
                    ->label(__('app.trade.quantity'))
                    ->required()
                    ->numeric(),
                DateTimePicker::make('executed_at')
                    ->label(__('app.trade.executed_at'))
                    ->required(),
            ]);
    }
}
