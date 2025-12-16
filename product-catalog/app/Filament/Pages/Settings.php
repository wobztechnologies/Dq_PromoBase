<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.settings';

    protected static ?string $navigationLabel = 'Paramètres';

    protected static ?int $navigationSort = 999;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'fal_api_key' => Setting::get('fal_api_key', ''),
            'meshy_api_key' => Setting::get('meshy_api_key', ''),
            'meshy_webhook_secret' => Setting::get('meshy_webhook_secret', ''),
            'meshy_webhook_url' => Setting::get('meshy_webhook_url', ''),
            // Anciennes clés 3DAiServer (gardées pour compatibilité)
            '3dai_server_url' => Setting::get('3dai_server_url', ''),
            '3dai_server_login' => Setting::get('3dai_server_login', ''),
            '3dai_server_password' => Setting::get('3dai_server_password', ''),
        ]);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Section::make('fal.ai API')
                    ->description('Configuration de fal.ai API pour la génération de modèles 3D (Standard)')
                    ->icon('heroicon-o-sparkles')
                    ->schema([
                        TextInput::make('fal_api_key')
                            ->label('API Key')
                            ->placeholder('votre_clé_api_fal')
                            ->required()
                            ->maxLength(255)
                            ->password()
                            ->revealable()
                            ->helperText('Clé API fal.ai. Obtenez-la sur https://fal.ai/'),
                    ])
                    ->columns(1)
                    ->collapsible(),
                
                Section::make('Meshy API')
                    ->description('Configuration de Meshy API pour la génération de modèles 3D (Max)')
                    ->icon('heroicon-o-sparkles')
                    ->schema([
                        TextInput::make('meshy_api_key')
                            ->label('API Key')
                            ->placeholder('votre_clé_api_meshy')
                            ->required()
                            ->maxLength(255)
                            ->password()
                            ->revealable()
                            ->helperText('Clé API Meshy. Obtenez-la sur https://www.meshy.ai/'),
                        
                        TextInput::make('meshy_webhook_url')
                            ->label('Webhook URL')
                            ->placeholder('https://votre-domaine.com/webhooks/meshy')
                            ->url()
                            ->maxLength(255)
                            ->helperText('URL publique pour recevoir les webhooks Meshy (optionnel)'),
                        
                        TextInput::make('meshy_webhook_secret')
                            ->label('Webhook Secret')
                            ->placeholder('secret_pour_validation')
                            ->maxLength(255)
                            ->password()
                            ->revealable()
                            ->helperText('Secret pour valider les webhooks Meshy (optionnel)'),
                    ])
                    ->columns(1)
                    ->collapsible(),
                
                Section::make('3DAiServer (Ancien - Déprécié)')
                    ->description('Configuration de l\'ancien serveur 3DAiServer (déprécié, utilisez Meshy)')
                    ->icon('heroicon-o-server')
                    ->schema([
                        TextInput::make('3dai_server_url')
                            ->label('Server URL')
                            ->placeholder('https://api.3dai.example.com')
                            ->url()
                            ->maxLength(255)
                            ->helperText('URL complète du serveur 3DAiServer (déprécié)'),
                        
                        TextInput::make('3dai_server_login')
                            ->label('Server Login')
                            ->placeholder('votre_login')
                            ->maxLength(255)
                            ->helperText('Identifiant de connexion au serveur (déprécié)'),
                        
                        TextInput::make('3dai_server_password')
                            ->label('Server Password')
                            ->placeholder('••••••••')
                            ->password()
                            ->maxLength(255)
                            ->helperText('Mot de passe de connexion au serveur (déprécié)')
                            ->revealable(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Sauvegarder')
                ->action(function () {
                    $this->save();
                }),
        ];
    }

    public function save()
    {
        try {
            $data = $this->form->getState();

            // Sauvegarder les paramètres fal.ai
            Setting::set('fal_api_key', $data['fal_api_key'] ?? '');
            
            // Sauvegarder les paramètres Meshy
            Setting::set('meshy_api_key', $data['meshy_api_key'] ?? '');
            Setting::set('meshy_webhook_secret', $data['meshy_webhook_secret'] ?? '');
            Setting::set('meshy_webhook_url', $data['meshy_webhook_url'] ?? '');
            
            // Sauvegarder les anciens paramètres 3DAiServer (pour compatibilité)
            Setting::set('3dai_server_url', $data['3dai_server_url'] ?? '');
            Setting::set('3dai_server_login', $data['3dai_server_login'] ?? '');
            Setting::set('3dai_server_password', $data['3dai_server_password'] ?? '');

            Notification::make()
                ->title('Paramètres sauvegardés')
                ->success()
                ->send();
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title('Erreur de validation')
                ->body('Veuillez vérifier les champs du formulaire.')
                ->danger()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur lors de la sauvegarde')
                ->body('Une erreur est survenue : ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
