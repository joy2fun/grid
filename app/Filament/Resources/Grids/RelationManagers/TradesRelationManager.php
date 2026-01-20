<?php

namespace App\Filament\Resources\Grids\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TradesRelationManager extends RelationManager
{
    protected static string $relationship = 'trades';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('stock_id')
                    ->default(fn (RelationManager $livewire): int => $livewire->getOwnerRecord()->stock_id),
                Select::make('side')
                    ->options([
                        'buy' => 'Buy',
                        'sell' => 'Sell',
                    ])
                    ->required(),
                TextInput::make('price')
                    ->required()
                    ->prefix('¥'),
                TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                DateTimePicker::make('executed_at')
                    ->required()
                    ->default(now()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->paginated(false)
            ->columns([
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
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('executed_at', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ]);
    }
}
