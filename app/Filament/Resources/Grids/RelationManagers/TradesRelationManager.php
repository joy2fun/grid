<?php

namespace App\Filament\Resources\Grids\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TradesRelationManager extends RelationManager
{
    protected static string $relationship = 'trades';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('stock_id')
                    ->default(fn (RelationManager $livewire): int => $livewire->getOwnerRecord()->stock_id),
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
                    ->numeric(),
                TextInput::make('quantity')
                    ->label(fn (Get $get) => match ($get('type')) {
                        'dividend' => __('app.trade.shares_held'),
                        'stock_dividend' => __('app.trade.base_shares'),
                        default => __('app.trade.quantity'),
                    })
                    ->required(fn (Get $get) => in_array($get('type'), ['buy', 'sell', 'dividend', 'stock_dividend']))
                    ->numeric(),
                TextInput::make('split_ratio')
                    ->label(__('app.trade.split_ratio'))
                    ->numeric()
                    ->visible(fn (Get $get) => in_array($get('type'), ['stock_split', 'stock_dividend'])),
                DateTimePicker::make('executed_at')
                    ->label(__('app.trade.executed_at'))
                    ->required()
                    ->default(now()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->paginated(false)
            ->columns([
                TextColumn::make('type')
                    ->label(__('app.trade.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("app.trade.type_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'buy' => 'success',
                        'sell' => 'danger',
                        'dividend' => 'info',
                        'stock_dividend' => 'warning',
                        'stock_split' => 'gray',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('price')
                    ->label(function ($record): string {
                        $type = $record?->type ?? 'buy';

                        return match ($type) {
                            'dividend' => __('app.trade.dividend_per_share'),
                            'stock_dividend', 'stock_split' => __('app.trade.ratio'),
                            default => __('app.trade.price'),
                        };
                    })
                    ->numeric(4)
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label(__('app.trade.quantity'))
                    ->numeric(0)
                    ->sortable(),
                TextColumn::make('executed_at')
                    ->label(__('app.trade.executed_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('executed_at', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ]);
    }
}
