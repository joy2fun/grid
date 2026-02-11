<?php

namespace App\Filament\Resources\Trades\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TradesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('stock.name')
                    ->label(__('app.trade.stock'))
                    ->sortable()
                    ->action(function ($record, $livewire) {
                        $livewire->tableFilters['stock_id']['value'] = $record->stock_id;
                    }),
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
                    ->label(function ($record): string {
                        $type = $record?->type ?? 'buy';

                        return match ($type) {
                            'dividend' => __('app.trade.shares_held'),
                            'stock_dividend' => __('app.trade.base_shares'),
                            default => __('app.trade.quantity'),
                        };
                    })
                    ->numeric(0)
                    ->sortable(),
                TextColumn::make('split_ratio')
                    ->label(__('app.trade.split_ratio'))
                    ->numeric(4)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('executed_at')
                    ->label(__('app.trade.executed_at'))
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('app.trade.created_at'))
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('app.trade.updated_at'))
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('stock_id')
                    ->relationship('stock', 'name', fn (Builder $query) => $query->where('type', 'etf'))
                    ->searchable()
                    ->preload()
                    ->label(__('app.trade.stock')),
                SelectFilter::make('type')
                    ->options([
                        'buy' => __('app.trade.type_buy'),
                        'sell' => __('app.trade.type_sell'),
                        'dividend' => __('app.trade.type_dividend'),
                        'stock_dividend' => __('app.trade.type_stock_dividend'),
                        'stock_split' => __('app.trade.type_stock_split'),
                    ])
                    ->label(__('app.trade.type')),
                Filter::make('executed_at')
                    ->schema([
                        DatePicker::make('executed_from')->label(__('app.trade.from')),
                        DatePicker::make('executed_until')->label(__('app.trade.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['executed_from'],
                                fn (Builder $query, $date) => $query->whereDate('executed_at', '>=', $date),
                            )
                            ->when(
                                $data['executed_until'],
                                fn (Builder $query, $date) => $query->whereDate('executed_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->iconButton()->iconSize('sm'),
            ])
            ->defaultSort('executed_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
