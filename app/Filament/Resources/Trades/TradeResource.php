<?php

namespace App\Filament\Resources\Trades;

use App\Filament\Resources\Trades\Pages\ManageTrades;
use App\Filament\Resources\Trades\Schemas\TradeForm;
use App\Filament\Resources\Trades\Tables\TradesTable;
use App\Models\Trade;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TradeResource extends Resource
{
    protected static ?string $model = Trade::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    public static function getNavigationLabel(): string
    {
        return __('app.nav.trades');
    }

    public static function getModelLabel(): string
    {
        return __('app.trade.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.nav.trades');
    }

    public static function form(Schema $schema): Schema
    {
        return TradeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TradesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTrades::route('/'),
        ];
    }
}
