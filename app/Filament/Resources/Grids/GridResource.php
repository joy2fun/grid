<?php

namespace App\Filament\Resources\Grids;

use App\Filament\Resources\Grids\Pages\CreateGrid;
use App\Filament\Resources\Grids\Pages\EditGrid;
use App\Filament\Resources\Grids\Pages\ListGrids;
use App\Filament\Resources\Grids\RelationManagers\TradesRelationManager;
use App\Filament\Resources\Grids\Schemas\GridForm;
use App\Filament\Resources\Grids\Tables\GridsTable;
use App\Models\Grid;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GridResource extends Resource
{
    protected static ?string $model = Grid::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return GridForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GridsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TradesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGrids::route('/'),
            'create' => CreateGrid::route('/create'),
            'edit' => EditGrid::route('/{record}/edit'),
        ];
    }
}
