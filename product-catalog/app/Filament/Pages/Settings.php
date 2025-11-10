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
            '3dai_server_url' => Setting::get('3dai_server_url', ''),
            '3dai_server_login' => Setting::get('3dai_server_login', ''),
            '3dai_server_password' => Setting::get('3dai_server_password', ''),
        ]);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Section::make('3DAiServer')
                    ->description('Configuration du serveur 3DAiServer pour la génération de modèles 3D')
                    ->icon('heroicon-o-server')
                    ->schema([
                        TextInput::make('3dai_server_url')
                            ->label('Server URL')
                            ->placeholder('https://api.3dai.example.com')
                            ->url()
                            ->required()
                            ->maxLength(255)
                            ->helperText('URL complète du serveur 3DAiServer'),
                        
                        TextInput::make('3dai_server_login')
                            ->label('Server Login')
                            ->placeholder('votre_login')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Identifiant de connexion au serveur'),
                        
                        TextInput::make('3dai_server_password')
                            ->label('Server Password')
                            ->placeholder('••••••••')
                            ->password()
                            ->required()
                            ->maxLength(255)
                            ->helperText('Mot de passe de connexion au serveur')
                            ->revealable(),
                    ])
                    ->columns(1),
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
