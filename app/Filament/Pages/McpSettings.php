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
                Section::make('MCP Configuration')
                    ->description('Model Context Protocol (MCP) server configuration for integrating with Grid Trading')
                    ->schema([
                        Textarea::make('mcpConfig')
                            ->label('MCP Server Configuration')
                            ->rows(12)
                            ->disabled()
                            ->helperText('Copy this configuration to your MCP client settings file (usually .mcp.json or similar)'),
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
            ->title('Token Generated')
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
                ->label('Generate mcp settings')
                ->icon('heroicon-o-key')
                ->action(function () {
                    $this->generateToken();
                })
                ->color('warning'),
        ];
    }
}
