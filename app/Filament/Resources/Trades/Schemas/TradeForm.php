<?php

namespace App\Filament\Resources\Trades\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
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
                Select::make('type')
                    ->label(__('app.trade.type'))
                    ->options([
                        'buy' => __('app.trade.type_buy'),
                        'sell' => __('app.trade.type_sell'),
                        'dividend' => __('app.trade.type_dividend'),
                        'stock_dividend' => __('app.trade.type_stock_dividend'),
                        'stock_split' => __('app.trade.type_stock_split'),
                    ])
                    ->required()
                    ->live(),
                TextInput::make('price')
                    ->label(fn (Get $get) => match ($get('type')) {
                        'dividend' => __('app.trade.dividend_per_share'),
                        'stock_dividend', 'stock_split' => __('app.trade.ratio'),
                        default => __('app.trade.price'),
                    })
                    ->required()
                    ->prefix(fn (Get $get) => $get('type') === 'dividend' ? '¥' : null)
                    ->suffix(fn (Get $get) => match ($get('type')) {
                        'stock_dividend' => __('app.trade.stock_dividend_suffix'),
                        'stock_split' => __('app.trade.stock_split_suffix'),
                        default => null,
                    })
                    ->numeric()
                    ->step(0.0001),
                TextInput::make('quantity')
                    ->label(fn (Get $get) => match ($get('type')) {
                        'dividend' => __('app.trade.shares_held'),
                        'stock_dividend' => __('app.trade.base_shares'),
                        default => __('app.trade.quantity'),
                    })
                    ->required(fn (Get $get) => in_array($get('type'), ['buy', 'sell', 'dividend', 'stock_dividend']))
                    ->numeric()
                    ->integer(),
                TextInput::make('split_ratio')
                    ->label(__('app.trade.split_ratio'))
                    ->numeric()
                    ->step(0.0001)
                    ->visible(fn (Get $get) => in_array($get('type'), ['stock_split', 'stock_dividend']))
                    ->helperText(fn (Get $get) => match ($get('type')) {
                        'stock_dividend' => __('app.trade.stock_dividend_helper'),
                        'stock_split' => __('app.trade.stock_split_helper'),
                        default => null,
                    }),
                DateTimePicker::make('executed_at')
                    ->label(__('app.trade.executed_at'))
                    ->required(),
            ]);
    }
}
