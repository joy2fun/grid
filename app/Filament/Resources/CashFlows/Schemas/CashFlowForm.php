<?php

namespace App\Filament\Resources\CashFlows\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CashFlowForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->label(__('app.cash_flow.date'))
                    ->required(),
                TextInput::make('amount')
                    ->label(__('app.cash_flow.amount'))
                    ->required()
                    ->numeric()
                    ->step(0.01)
                    ->prefix('¥')
                    ->helperText(__('app.cash_flow.amount_helper')),
                Textarea::make('notes')
                    ->label(__('app.cash_flow.notes'))
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
