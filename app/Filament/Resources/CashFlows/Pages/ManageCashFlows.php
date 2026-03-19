<?php

namespace App\Filament\Resources\CashFlows\Pages;

use App\Filament\Resources\CashFlows\CashFlowResource;
use App\Models\CashFlow;
use App\Utilities\Helper;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ManageCashFlows extends ManageRecords
{
    protected static string $resource = CashFlowResource::class;

    private array $cachedXirrData = [];

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
                            $message .= ' ' . __('app.cash_flow.skipped_count', ['count' => $skippedCount]);
                        }

                        Notification::make()
                            ->title($importedCount > 0 ? __('app.notifications.import_completed') : __('app.notifications.import_failed'))
                            ->body($message)
                            ->when($importedCount > 0, fn($notification) => $notification->success())
                            ->when($importedCount === 0, fn($notification) => $notification->warning())
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
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('app.common.close'))
                ->schema([
                    TextInput::make('portfolio_value')
                        ->label(__('app.cash_flow.portfolio_value'))
                        ->required()
                        ->numeric()
                        ->step(10000)
                        ->prefix('¥')
                        ->helperText(__('app.cash_flow.portfolio_value_helper'))
                        ->live(debounce: 500),

                    Section::make()
                        ->visible(fn(Get $get) => filled($get('portfolio_value')))
                        ->compact()
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    TextEntry::make('total_cost')
                                        ->label(__('app.cash_flow.total_cost'))
                                        ->state(fn(Get $get) => $this->getXirrResult($get('portfolio_value'), 'total_cost')),
                                    TextEntry::make('total_value')
                                        ->label(__('app.cash_flow.total_value'))
                                        ->state(fn(Get $get) => $this->getXirrResult($get('portfolio_value'), 'total_value')),
                                    TextEntry::make('profit_loss')
                                        ->label(__('app.cash_flow.profit_loss'))
                                        ->color('danger')
                                        ->state(fn(Get $get) => $this->getXirrResult($get('portfolio_value'), 'profit_loss')),
                                    TextEntry::make('xirr')
                                        ->label('XIRR')
                                        ->color('danger')
                                        ->state(fn(Get $get) => $this->getXirrResult($get('portfolio_value'), 'xirr')),
                                ]),
                        ]),
                ]),
        ];
    }

    protected function getXirrResult(mixed $portfolioValue, string $type): HtmlString|string
    {
        $data = $this->calculateXirrData($portfolioValue);

        if (! $data) {
            return '-';
        }

        if (isset($data['error'])) {
            return $type === 'total_cost' ? new HtmlString('<span class="text-danger-600">' . $data['error'] . '</span>') : '-';
        }

        return match ($type) {
            'total_cost' => '¥' . number_format($data['total_cost'], 2),
            'total_value' => '¥' . number_format($data['total_value'], 2),
            'profit_loss' => new HtmlString("<span class='" . ($data['profit_loss'] >= 0 ? 'text-success-600' : 'text-danger-600') . " font-bold'>¥" . number_format($data['profit_loss'], 2) . '</span>'),
            'xirr' => new HtmlString("<span class='" . ($data['xirr'] !== null && $data['xirr'] >= 0 ? 'text-success-600' : 'text-danger-600') . " font-bold'>" . ($data['xirr'] !== null ? number_format($data['xirr'] * 100, 2) . '%' : __('app.cash_flow.xirr_failed')) . '</span>'),
            default => '-',
        };
    }

    protected function calculateXirrData(mixed $portfolioValue): ?array
    {
        $portfolioValue = (float) $portfolioValue;
        if (! $portfolioValue) {
            return null;
        }

        $cacheKey = (string) $portfolioValue;
        if (isset($this->cachedXirrData[$cacheKey])) {
            return $this->cachedXirrData[$cacheKey];
        }

        $cashFlows = CashFlow::orderBy('date')->get();

        if ($cashFlows->isEmpty()) {
            return $this->cachedXirrData[$cacheKey] = ['error' => __('app.cash_flow.no_cash_flows')];
        }

        $amounts = $cashFlows->pluck('amount')->map(fn($v) => (float) $v)->toArray();
        $dates = $cashFlows->pluck('date')->map(fn($d) => $d->format('Y-m-d'))->toArray();

        $totalCost = abs(array_sum(array_filter($amounts, fn($v) => $v < 0)));
        $totalInflow = array_sum(array_filter($amounts, fn($v) => $v > 0));
        $totalValue = $totalInflow + $portfolioValue;
        $profitLoss = $totalValue - $totalCost;

        // Append portfolio value as the final positive cash-in on today's date
        $calcAmounts = $amounts;
        $calcDates = $dates;
        $calcAmounts[] = $portfolioValue;
        $calcDates[] = now()->format('Y-m-d');

        $xirr = Helper::calculateXIRR($calcAmounts, $calcDates);

        return $this->cachedXirrData[$cacheKey] = [
            'total_cost' => $totalCost,
            'total_value' => $totalValue,
            'profit_loss' => $profitLoss,
            'xirr' => $xirr,
        ];
    }
}
