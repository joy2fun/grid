<?php

namespace App\Filament\Resources\CashFlows\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CashFlowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label(__('app.cash_flow.date'))
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('app.cash_flow.amount'))
                    ->numeric(2)
                    ->sortable()
                    ->color(fn ($state): string => $state >= 0 ? 'success' : 'danger'),
                TextColumn::make('notes')
                    ->label(__('app.cash_flow.notes'))
                    ->limit(50)
                    ->searchable()
                    ->tooltip(fn ($record) => $record->notes),
                TextColumn::make('created_at')
                    ->label(__('app.cash_flow.created_at'))
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('date')
                    ->schema([
                        DatePicker::make('date_from')->label(__('app.cash_flow.from')),
                        DatePicker::make('date_until')->label(__('app.cash_flow.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date) => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (Builder $query, $date) => $query->whereDate('date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->iconButton()->iconSize('sm'),
            ])
            ->defaultSort('date', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
