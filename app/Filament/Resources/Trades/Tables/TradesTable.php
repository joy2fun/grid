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
                TextColumn::make('side')
                    ->label(__('app.trade.side'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'buy' ? __('app.trade.side_buy') : __('app.trade.side_sell'))
                    ->color(fn (string $state): string => match ($state) {
                        'buy' => 'success',
                        'sell' => 'danger',
                    })
                    ->searchable(),
                TextColumn::make('price')
                    ->label(__('app.trade.price'))
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label(__('app.trade.quantity'))
                    ->numeric(0)
                    ->sortable(),
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
