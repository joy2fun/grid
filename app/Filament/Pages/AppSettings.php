<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Services\BarkService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
            'locale' => AppSetting::get('locale', config('app.locale', 'en')),
            'notifications_enabled' => AppSetting::get('notifications_enabled', true),
            'bark_url' => AppSetting::get('bark_url'),
            'inactive_stocks_threshold' => AppSetting::get('inactive_stocks_threshold', 30),
            'price_change_threshold' => AppSetting::get('price_change_threshold', 5),
            'deepseek_api_key' => AppSetting::get('deepseek_api_key'),
            'baidu_ocr_token' => AppSetting::get('baidu_ocr_token'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('app.app_settings.title'))
                    ->description(__('app.app_settings.description'))
                    ->schema([
                        Select::make('locale')
                            ->label(__('app.app_settings.language'))
                            ->options([
                                'en' => 'English',
                                'zh_CN' => '简体中文',
                            ])
                            ->default(config('app.locale', 'en'))
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                app()->setLocale($state);
                            }),
                        Toggle::make('notifications_enabled')
                            ->label(__('app.app_settings.enable_notifications'))
                            ->default(true),
                        TextInput::make('bark_url')
                            ->label(__('app.app_settings.bark_url'))
                            ->placeholder(__('app.app_settings.bark_placeholder'))
                            ->url()
                            ->helperText(__('app.app_settings.bark_helper'))
                            ->afterContent(
                                Action::make('test_bark_notification')
                                    ->label(__('app.app_settings.test_notification'))
                                    ->action(function () {
                                        $this->testBarkNotification();
                                    })
                            ),
                        TextInput::make('inactive_stocks_threshold')
                            ->label(__('app.app_settings.inactive_threshold'))
                            ->placeholder('30')
                            ->numeric()
                            ->minValue(1)
                            ->default(30)
                            ->helperText(__('app.app_settings.inactive_helper')),
                        TextInput::make('price_change_threshold')
                            ->label(__('app.app_settings.price_change_threshold'))
                            ->placeholder('5')
                            ->numeric()
                            ->minValue(0.1)
                            ->maxValue(100)
                            ->step(0.1)
                            ->default(5)
                            ->helperText(__('app.app_settings.price_change_helper')),
                    ])
                    ->columns(1),

                Section::make(__('app.app_settings.api_settings'))
                    ->description(__('app.app_settings.api_description'))
                    ->schema([
                        TextInput::make('deepseek_api_key')
                            ->label(__('app.app_settings.deepseek_key'))
                            ->password()
                            ->helperText(__('app.app_settings.deepseek_helper')),
                        TextInput::make('baidu_ocr_token')
                            ->label(__('app.app_settings.baidu_token'))
                            ->password()
                            ->helperText(__('app.app_settings.baidu_helper')),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function testBarkNotification(): void
    {
        $barkUrl = $this->form->getState()['bark_url'];

        if (empty($barkUrl)) {
            Notification::make()
                ->title(__('app.notifications.import_failed'))
                ->body('Please enter a Bark URL first')
                ->danger()
                ->send();

            return;
        }

        $success = $this->barkService()->send(
            __('app.app_settings.test_notification'),
            'This is a test notification from Grid application',
            $barkUrl
        );

        if ($success) {
            Notification::make()
                ->title(__('app.notifications.test_sent'))
                ->body('Test notification sent successfully!')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('app.notifications.import_failed'))
                ->body('Failed to send test notification. Please check your Bark URL.')
                ->danger()
                ->send();
        }
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Save app settings
        AppSetting::set('locale', $data['locale'] ?? config('app.locale', 'en'));
        AppSetting::set('notifications_enabled', $data['notifications_enabled'] ?? false);
        AppSetting::set('bark_url', $data['bark_url'] ?? null);
        AppSetting::set('inactive_stocks_threshold', (int) ($data['inactive_stocks_threshold'] ?? 30));
        AppSetting::set('price_change_threshold', (float) ($data['price_change_threshold'] ?? 5));
        AppSetting::set('deepseek_api_key', $data['deepseek_api_key'] ?? null);
        AppSetting::set('baidu_ocr_token', $data['baidu_ocr_token'] ?? null);

        // Apply the locale immediately
        app()->setLocale($data['locale'] ?? config('app.locale', 'en'));

        Notification::make()
            ->title(__('app.notifications.settings_saved'))
            ->success()
            ->send();

        // Redirect to refresh the page with new locale
        $this->redirect(AppSettings::getUrl());
    }
}
