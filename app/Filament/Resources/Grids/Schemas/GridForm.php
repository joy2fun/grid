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
                    ->relationship('stock', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('initial_amount')
                    ->required()
                    ->numeric(),
                TextInput::make('grid_interval')
                    ->required()
                    ->numeric(),
            ]);
    }
}
