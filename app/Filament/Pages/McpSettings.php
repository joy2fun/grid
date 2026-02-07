<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class McpSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.mcp-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->generateMcpConfig();
        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('app.mcp_settings.title'))
                    ->description(__('app.mcp_settings.description'))
                    ->schema([
                        Textarea::make('mcpConfig')
                            ->label(__('app.mcp_settings.config'))
                            ->rows(12)
                            ->disabled()
                            ->helperText(__('app.mcp_settings.config_helper')),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function generateToken(): void
    {
        $user = auth()->user();

        // Delete existing MCP tokens for this user
        $user->tokens()->where('name', 'mcp-token')->delete();

        // Create new token
        $token = $user->createToken('mcp-token', ['mcp:access'])->plainTextToken;

        $this->generateMcpConfig($token);
        $this->form->fill($this->data);

        Notification::make()
            ->title(__('app.notifications.token_generated'))
            ->body('New MCP token has been generated successfully!')
            ->success()
            ->send();
    }

    private function generateMcpConfig(?string $token = null): void
    {
        $user = auth()->user();
        $currentToken = $token ?? $user->tokens()->where('name', 'mcp-token')->first()?->plainTextToken ?? '{token}';

        $config = [
            'mcpServers' => [
                'grid-trading' => [
                    'url' => config('app.url').'/mcp/grid-trading',
                    'type' => 'http',
                    'headers' => [
                        'Authorization' => 'Bearer '.$currentToken,
                        'Content-Type' => 'application/json',
                    ],
                ],
            ],
        ];

        $this->data['mcpConfig'] = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateToken')
                ->label(__('app.mcp_settings.generate_token'))
                ->icon('heroicon-o-key')
                ->action(function () {
                    $this->generateToken();
                })
                ->color('warning'),
        ];
    }
}
