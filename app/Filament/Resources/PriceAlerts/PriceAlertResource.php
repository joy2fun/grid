<?php

namespace App\Filament\Resources\PriceAlerts;

use App\Filament\Resources\PriceAlerts\Pages\ManagePriceAlerts;
use App\Filament\Resources\PriceAlerts\Schemas\PriceAlertForm;
use App\Filament\Resources\PriceAlerts\Tables\PriceAlertsTable;
use App\Models\PriceAlert;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PriceAlertResource extends Resource
{
    protected static ?string $model = PriceAlert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    public static function getNavigationLabel(): string
    {
        return __('app.nav.price_alerts');
    }

    public static function getModelLabel(): string
    {
        return __('app.price_alert.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.nav.price_alerts');
    }

    public static function form(Schema $schema): Schema
    {
        return PriceAlertForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceAlertsTable::configure($table);
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
            'index' => ManagePriceAlerts::route('/'),
        ];
    }
}
