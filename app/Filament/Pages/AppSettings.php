<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Services\BarkService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AppSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.app-settings';

    public ?array $data = [];

    private ?BarkService $barkService = null;

    private function barkService(): BarkService
    {
        if ($this->barkService === null) {
            $this->barkService = app(BarkService::class);
        }

        return $this->barkService;
    }

    public function mount(): void
    {
        $this->form->fill([
            'notifications_enabled' => AppSetting::get('notifications_enabled', true),
            'bark_url' => AppSetting::get('bark_url'),
            'inactive_stocks_threshold' => AppSetting::get('inactive_stocks_threshold', 30),
            'price_change_threshold' => AppSetting::get('price_change_threshold', 5),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Application Settings')
                    ->description('Configure application preferences')
                    ->schema([
                        Toggle::make('notifications_enabled')
                            ->label('Enable Notifications')
                            ->default(true),
                        TextInput::make('bark_url')
                            ->label('Bark Notification URL')
                            ->placeholder('https://api.day.app/your-key/')
                            ->url()
                            ->helperText('Enter your Bark push notification endpoint URL')
                            ->afterContent(
                                Action::make('test_bark_notification')
                                    ->label('Test Notification')
                                    ->action(function () {
                                        $this->testBarkNotification();
                                    })
                            ),
                        TextInput::make('inactive_stocks_threshold')
                            ->label('Inactive Stocks Threshold (days)')
                            ->placeholder('30')
                            ->numeric()
                            ->minValue(1)
                            ->default(30)
                            ->helperText('Number of days after which a stock is considered inactive if not traded'),
                        TextInput::make('price_change_threshold')
                            ->label('Price Change Threshold (%)')
                            ->placeholder('5')
                            ->numeric()
                            ->minValue(0.1)
                            ->maxValue(100)
                            ->step(0.1)
                            ->default(5)
                            ->helperText('Percentage price change (rise or drop) compared to last traded price to trigger notifications'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function testBarkNotification(): void
    {
        $barkUrl = $this->form->getState()['bark_url'];

        if (empty($barkUrl)) {
            Notification::make()
                ->title('Error')
                ->body('Please enter a Bark URL first')
                ->danger()
                ->send();

            return;
        }

        $success = $this->barkService()->send(
            'Test Notification',
            'This is a test notification from Grid application',
            $barkUrl
        );

        if ($success) {
            Notification::make()
                ->title('Success')
                ->body('Test notification sent successfully!')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Error')
                ->body('Failed to send test notification. Please check your Bark URL.')
                ->danger()
                ->send();
        }
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Save app settings
        AppSetting::set('notifications_enabled', $data['notifications_enabled'] ?? false);
        AppSetting::set('bark_url', $data['bark_url'] ?? null);
        AppSetting::set('inactive_stocks_threshold', (int) ($data['inactive_stocks_threshold'] ?? 30));
        AppSetting::set('price_change_threshold', (float) ($data['price_change_threshold'] ?? 5));

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
