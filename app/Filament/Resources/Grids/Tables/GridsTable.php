<?php

namespace App\Filament\Resources\Grids\Tables;

use Filament\Actions\EditAction;
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
                TextColumn::make('initial_amount')
                    ->label(__('app.grid.initial_amount'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('grid_interval')
                    ->label(__('app.grid.grid_interval'))
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
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }
}
