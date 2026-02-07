<?php

namespace App\Filament\Resources\Grids\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GridForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('stock_id')
                    ->relationship('stock', 'name', modifyQueryUsing: fn ($query) => $query->where('type', '!=', 'index'))
                    ->label(__('app.grid.stock'))
                    ->required(),
                TextInput::make('name')
                    ->label(__('app.grid.name'))
                    ->required(),
                TextInput::make('initial_amount')
                    ->label(__('app.grid.initial_amount'))
                    ->required()
                    ->numeric(),
                TextInput::make('grid_interval')
                    ->label(__('app.grid.grid_interval'))
                    ->required()
                    ->numeric(),
            ]);
    }
}
