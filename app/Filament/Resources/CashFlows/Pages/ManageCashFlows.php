<?php

namespace App\Filament\Resources\CashFlows\Pages;

use App\Filament\Resources\CashFlows\CashFlowResource;
use App\Models\CashFlow;
use App\Utilities\Helper;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ManageCashFlows extends ManageRecords
{
    protected static string $resource = CashFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('app.common.new'))
                ->icon('heroicon-o-plus'),
            Action::make('importCsv')
                ->label(__('app.cash_flow.import_csv'))
                ->icon('heroicon-o-document-arrow-up')
                ->color('gray')
                ->modalHeading(__('app.cash_flow.import_csv'))
                ->schema([
                    FileUpload::make('csv_file')
                        ->label(__('app.cash_flow.csv_file'))
                        ->acceptedFileTypes(['text/csv', 'text/plain', '.csv'])
                        ->disk('local')
                        ->directory('cash-flow-imports')
                        ->maxSize(5120)
                        ->required()
                        ->helperText(__('app.cash_flow.csv_helper')),
                ])
                ->action(function (array $data) {
                    $filePaths = $data['csv_file'];

                    if (is_array($filePaths)) {
                        $filePath = $filePaths[0] ?? null;
                    } else {
                        $filePath = $filePaths;
                    }

                    if (! $filePath) {
                        Notification::make()
                            ->title(__('app.notifications.no_file'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $fullPath = Storage::disk('local')->path($filePath);

                    if (! file_exists($fullPath)) {
                        Notification::make()
                            ->title(__('app.notifications.file_not_found'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $importedCount = 0;
                    $skippedCount = 0;
                    $errors = [];

                    DB::beginTransaction();

                    try {
                        $handle = fopen($fullPath, 'r');
                        $header = fgetcsv($handle);

                        if (! $header) {
                            throw new \RuntimeException('CSV file is empty or invalid.');
                        }

                        // Normalize header names to lowercase
                        $header = array_map('strtolower', array_map('trim', $header));
                        $dateIndex = array_search('date', $header);
                        $amountIndex = array_search('amount', $header);
                        $notesIndex = array_search('notes', $header);

                        if ($dateIndex === false || $amountIndex === false) {
                            throw new \RuntimeException('CSV must contain "date" and "amount" columns.');
                        }

                        $rowNumber = 1;
                        while (($row = fgetcsv($handle)) !== false) {
                            $rowNumber++;

                            $date = trim($row[$dateIndex] ?? '');
                            $amount = trim($row[$amountIndex] ?? '');

                            if (! $date || ! is_numeric($amount)) {
                                $errors[] = "Row #{$rowNumber}: Invalid date or amount.";
                                $skippedCount++;

                                continue;
                            }

                            try {
                                $parsedDate = \Carbon\Carbon::parse($date)->format('Y-m-d');
                            } catch (\Exception $e) {
                                $errors[] = "Row #{$rowNumber}: Cannot parse date '{$date}'.";
                                $skippedCount++;

                                continue;
                            }

                            $notes = $notesIndex !== false ? trim($row[$notesIndex] ?? '') : null;

                            CashFlow::create([
                                'date' => $parsedDate,
                                'amount' => (float) $amount,
                                'notes' => $notes ?: null,
                            ]);

                            $importedCount++;
                        }

                        fclose($handle);
                        DB::commit();

                        Storage::disk('local')->delete($filePath);

                        $message = __('app.cash_flow.imported_count', ['count' => $importedCount]);
                        if ($skippedCount > 0) {
                            $message .= ' '.__('app.cash_flow.skipped_count', ['count' => $skippedCount]);
                        }

                        Notification::make()
                            ->title($importedCount > 0 ? __('app.notifications.import_completed') : __('app.notifications.import_failed'))
                            ->body($message)
                            ->when($importedCount > 0, fn ($notification) => $notification->success())
                            ->when($importedCount === 0, fn ($notification) => $notification->warning())
                            ->send();
                    } catch (\Exception $e) {
                        DB::rollBack();

                        if (isset($handle) && is_resource($handle)) {
                            fclose($handle);
                        }

                        Storage::disk('local')->delete($filePath);

                        Notification::make()
                            ->title(__('app.notifications.import_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('calculateXirr')
                ->label(__('app.cash_flow.calculate_xirr'))
                ->icon('heroicon-o-calculator')
                ->color('primary')
                ->modalHeading(__('app.cash_flow.calculate_xirr'))
                ->schema([
                    TextInput::make('portfolio_value')
                        ->label(__('app.cash_flow.portfolio_value'))
                        ->required()
                        ->numeric()
                        ->step(0.01)
                        ->prefix('¥')
                        ->helperText(__('app.cash_flow.portfolio_value_helper')),
                ])
                ->action(function (array $data) {
                    $portfolioValue = (float) $data['portfolio_value'];

                    $cashFlows = CashFlow::orderBy('date')->get();

                    if ($cashFlows->isEmpty()) {
                        Notification::make()
                            ->title(__('app.cash_flow.no_cash_flows'))
                            ->warning()
                            ->send();

                        return;
                    }

                    $amounts = $cashFlows->pluck('amount')->map(fn ($v) => (float) $v)->toArray();
                    $dates = $cashFlows->pluck('date')->map(fn ($d) => $d->format('Y-m-d'))->toArray();

                    // Append portfolio value as the final positive cash-in on today's date
                    $amounts[] = $portfolioValue;
                    $dates[] = now()->format('Y-m-d');

                    $xirr = Helper::calculateXIRR($amounts, $dates);

                    if ($xirr === null) {
                        Notification::make()
                            ->title(__('app.cash_flow.xirr_failed'))
                            ->body(__('app.cash_flow.xirr_failed_body'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $xirrPercentage = number_format($xirr * 100, 2);

                    Notification::make()
                        ->title(__('app.cash_flow.xirr_result'))
                        ->body("XIRR: {$xirrPercentage}%")
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
