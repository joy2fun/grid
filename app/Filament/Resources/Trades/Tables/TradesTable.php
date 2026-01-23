<?php

namespace App\Filament\Resources\Trades\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TradesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('stock.name')
                    ->label('Stock')
                    ->sortable(),
                TextColumn::make('side')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'buy' => 'success',
                        'sell' => 'danger',
                    })
                    ->searchable(),
                TextColumn::make('price')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('quantity')
                    ->numeric(0)
                    ->sortable(),
                TextColumn::make('executed_at')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->iconButton()->iconSize('sm'),
            ])
            ->defaultSort('id', 'desc');
    }
}
