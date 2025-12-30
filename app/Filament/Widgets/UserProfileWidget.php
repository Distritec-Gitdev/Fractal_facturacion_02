<?php

namespace App\Filament\Widgets;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Widgets\Widget;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Facades\Storage;

class UserProfileWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.widgets.user-profile-widget';

    /** Datos del formulario del modal */
    public ?array $data = [];

    public function mount(): void
    {
        $u = auth()->user();
        $this->form->fill([
            'name'   => $u?->name,
            'email'  => $u?->email,
            'avatar' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('avatar')
                ->label('Foto de perfil')
                ->image()
                ->imageCropAspectRatio('1:1')
                ->imageEditor()
                ->directory('avatars/tmp')   // storage/app/public/avatars/tmp
                ->disk('public')
                ->visibility('public')
                ->maxSize(2048),

            Forms\Components\TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('email')
                ->label('Correo')
                ->email()
                ->required()
                ->maxLength(255),
        ])->statePath('data');
    }

    /** URL actual del avatar (local si existe; si no, Gravatar) con cache-busting */
    public function getAvatarUrl(): ?string
    {
        $u = auth()->user();
        if (! $u) {
            return null;
        }

        foreach (['png','jpg','jpeg','webp'] as $ext) {
            $p = "avatars/{$u->id}.{$ext}";
            if (Storage::disk('public')->exists($p)) {
                $ver = @filemtime(storage_path('app/public/' . $p)) ?: time();
                return Storage::disk('public')->url($p) . '?v=' . $ver;
            }
        }

        $hash = md5(strtolower(trim($u->email ?? '')));
        return "https://www.gravatar.com/avatar/{$hash}?s=400&d=mp";
    }

    /** Pre-carga datos y abre el modal de edición */
    public function prepareEdit(): void
    {
        $u = auth()->user();
        if (! $u) {
            return;
        }

        $this->form->fill([
            'name'   => $u->name,
            'email'  => $u->email,
            'avatar' => null,
        ]);

        // Abre modal vía evento (Filament v3)
        $this->dispatch('open-modal', id: 'edit-profile');
    }

    /** Guarda cambios y emite evento para refrescar SOLO los avatars (sin recargar la app) */
    public function save(): void
    {
        $u = auth()->user();
        if (! $u) {
            return;
        }

        $state = $this->form->getState();

        // 1) Nombre / Email
        $u->name  = $state['name']  ?? $u->name;
        $u->email = $state['email'] ?? $u->email;

        // 2) Foto nueva -> mover a avatars/{id}.png (pública)
        $finalPublicUrl = null;

        if (!empty($state['avatar'])) {
            $disk = Storage::disk('public');
            $tmp  = $state['avatar']; // p. ej. "avatars/tmp/abc.png"

            if ($disk->exists($tmp)) {
                $finalPath = "avatars/{$u->id}.png";
                $bin       = $disk->get($tmp);
                $disk->put($finalPath, $bin, 'public');

                if ($tmp !== $finalPath) {
                    $disk->delete($tmp);
                }

                $ver            = @filemtime(storage_path('app/public/' . $finalPath)) ?: time();
                $finalPublicUrl = $disk->url($finalPath) . '?v=' . $ver;
            }
        }

        $u->touch();
        $u->save();

        // Cierra modal y notifica
        $this->dispatch('close-modal', id: 'edit-profile');
        $this->dispatch('notify', type: 'success', message: 'Perfil actualizado.');

        // Si tenemos URL definitiva, avisamos al front para reemplazar src en TODOS los avatars (header, etc.)
        if ($finalPublicUrl) {
            $this->dispatch('avatar-updated', url: $finalPublicUrl);
        } else {
            // Si no cambió la foto, emite igual con la URL actual (por si hay que invalidar caché)
            $this->dispatch('avatar-updated', url: $this->getAvatarUrl());
        }

        // Importante: NO redirigimos ni recargamos.
        // Así no se resetea el modo oscuro ni otros estados de la app.
    }
}
