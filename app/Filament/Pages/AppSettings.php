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
use Illuminate\Support\Facades\Auth;

class AppSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.app-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $user = Auth::user();

        $this->form->fill([
            'user_notifications_enabled' => AppSetting::get("user.{$user->id}.notifications_enabled", true),
            'user_bark_url' => AppSetting::get("user.{$user->id}.bark_url"),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('User Notifications')
                    ->description('Configure your personal notification preferences')
                    ->schema([
                        Toggle::make('user_notifications_enabled')
                            ->label('Enable Notifications')
                            ->default(true),
                        TextInput::make('user_bark_url')
                            ->label('Bark Notification URL')
                            ->placeholder('https://api.day.app/your-key/')
                            ->url()
                            ->helperText('Enter your Bark push notification endpoint URL'),
                    ])
                    ->columns(1),

                Section::make('Application Settings')
                    ->description('Global application configuration')
                    ->schema([
                        // Placeholder for future app-wide settings
                        // Example: market hours, default currency, etc.
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = Auth::user();

        // Save user-specific settings using AppSetting
        AppSetting::set("user.{$user->id}.notifications_enabled", $data['user_notifications_enabled'] ?? false);
        AppSetting::set("user.{$user->id}.bark_url", $data['user_bark_url'] ?? null);

        // Save app settings (for future use)
        // AppSetting::set('some_key', $data['some_field']);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
