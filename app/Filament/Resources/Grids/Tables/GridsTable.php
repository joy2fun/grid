<?php

namespace App\Filament\Resources\Grids\Tables;

use App\Models\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GridsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('stock.name')
                    ->label(__('app.grid.stock'))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('app.grid.name'))
                    ->searchable(),
                TextColumn::make('last_trade_at_formatted')
                    ->label(__('app.grid.last_trade'))
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('last_trade_at', $direction)),
                TextColumn::make('price_change_percentage')
                    ->label(__('app.grid.price_change'))
                    ->getStateUsing(fn (Grid $record) => $record->price_change_percentage)
                    ->badge()
                    ->color(fn ($state) => $state === null ? 'gray' : ($state >= 0 ? 'success' : 'danger'))
                    ->formatStateUsing(fn ($state) => $state === null ? '-' : number_format($state, 2).'%'),
                TextColumn::make('xirr')
                    ->label(__('app.grid.xirr'))
                    ->getStateUsing(fn (Grid $record) => $record->xirr !== null ? $record->xirr * 100 : null)
                    ->badge()
                    ->color(fn ($state) => $state === null ? 'gray' : ($state >= 0 ? 'success' : 'danger'))
                    ->formatStateUsing(fn ($state) => $state === null ? '-' : number_format($state, 2).'%')
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('xirr', $direction)),
                TextColumn::make('grid_interval')
                    ->label(__('app.grid.grid_interval'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('initial_amount')
                    ->label(__('app.grid.initial_amount'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['stock']))
            ->filters([
                //
            ])
            ->paginated(false)
            ->defaultSort('id', 'desc');
    }
}
