<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
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

    public function mount(): void
    {
        $this->form->fill([
            'notifications_enabled' => AppSetting::get('notifications_enabled', true),
            'bark_url' => AppSetting::get('bark_url'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Application Settings')
                    ->description('Configure application notification preferences')
                    ->schema([
                        Toggle::make('notifications_enabled')
                            ->label('Enable Notifications')
                            ->default(true),
                        TextInput::make('bark_url')
                            ->label('Bark Notification URL')
                            ->placeholder('https://api.day.app/your-key/')
                            ->url()
                            ->helperText('Enter your Bark push notification endpoint URL'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Save app settings
        AppSetting::set('notifications_enabled', $data['notifications_enabled'] ?? false);
        AppSetting::set('bark_url', $data['bark_url'] ?? null);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
