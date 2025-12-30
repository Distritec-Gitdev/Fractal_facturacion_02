<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

// 👇 modelos externos
use App\Models\SocioDistritec; // asegúrate de crearlo con la conexión correcta
use App\Models\Asesor;
use App\Models\AaPrin;
use App\Models\Agentes;

use Filament\Models\Contracts\HasAvatar;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $guard_name = 'web';
    protected $guarded = [];

    /**
     * Controla quién puede entrar al panel "admin" de Filament.
     * 1) Debe tener un rol permitido.
     * 2) Debe pasar el chequeo externo por cédula (socios_distritec y asesores/aa_prin).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return true;
        }

        $super = config('filament-shield.super_admin.name', 'super_admin');

        // Roles que sí pueden intentar entrar al panel
        $allowedRoles = [
            'admin',
            $super,
            'asesor comercial',
            'asesor_agente',
            'gestor cartera',
            'socio',
            'agente_admin',
            'auxiliar_socio',
            'administrativo',
        ];

        if (! $this->hasAnyRole($allowedRoles)) {
            return false;
        }

        // 👇 Chequeo externo obligatorio
        return $this->passesExternalStatusCheck();
    }

    /**
     * Retorna true si el usuario NO está “bloqueado” externamente.
     * Reglas:
     *  - Primero consulta en `socios_distritec` por Cedula: si ID_Estado == 3 → BLOQUEA.
     *  - Luego (si no bloqueó) busca en `asesores` por Cedula, toma ID_Asesor y revisa en `aa_prin`:
     *      si existe algún registro con ID_Estado == 3 → BLOQUEA.
     */
   public function passesExternalStatusCheck(): bool
{
    // Normaliza/valida cédula
    $cedula = trim((string) ($this->cedula ?? ''));
    if ($cedula === '') {
        return false; // no hay cédula -> bloquear
    }

    // 👉 Descomenta si quieres que admin/super_admin pasen siempre
    // $super = config('filament-shield.super_admin.name', 'super_admin');
    // if ($this->hasAnyRole(['admin', $super])) {
    //     return true;
    // }

    $found = false;

    // 1) socios_distritec
    $socio = SocioDistritec::query()
        ->where('Cedula', $cedula)
        ->select(['ID_Estado'])
        ->first();

    if ($socio) {
        $found = true;
        if ((int) $socio->ID_Estado === 3) {
            return false; // bloqueado por estado
        }
    }

    // 2) agentes
    $agente = Agentes::query()
        ->where('numero_documento', $cedula)
        ->select(['estado'])
        ->first();

    if ($agente) {
        $found = true;
        if ((int) $agente->estado === 0) {
            return false; // bloqueado por estado
        }
    }

    // 3) asesores / aa_prin
    $asesor = Asesor::query()
        ->where('Cedula', $cedula)
        ->select(['ID_Asesor'])
        ->first();

    if ($asesor) {
        $found = true;

        $tieneEstado3 = AaPrin::query()
            ->where('ID_Asesor', $asesor->ID_Asesor)
            ->where('ID_Estado', 3)
            ->exists();

        if ($tieneEstado3) {
            return false; // bloqueado por estado
        }
    }

    // ✅ Si NO se encontró en ninguna fuente, bloquear
    if (! $found) {
        return false;
    }

    // ✅ Se encontró en alguna y no tiene estados bloqueantes
    return true;
}


    public function getFilamentName(): string
    {
        return $this->name ?? $this->email;
    }

  

    public function chats()
    {
        return $this->hasMany(Chat::class);
    }


      public function getFilamentAvatarUrl(): ?string
    {
        $disk = Storage::disk('public');

        // ordena por formatos que podrías guardar
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $path = "avatars/{$this->id}.{$ext}";
            if ($disk->exists($path)) {
                // 👉 SIEMPRE devuelve /storage/avatars/.., no /storage/app/public/..
                return $disk->url($path);
            }
        }

        return null; // Filament mostrará la inicial si no hay imagen
    }


    public function agentTerceroId(): ?int
{
    $cedula = $this->cedula ?? null;
    if (! $cedula) {
        return null;
    }

    return \App\Models\Agentes::query()
        ->where('numero_documento', $cedula)
        ->value('id_tercero'); // null si no existe
}

// App/Models/User.php

public function agentSedeId(): ?int
{
    $cedula = $this->cedula ?? null;
    if (! $cedula) {
        return null;
    }

    return \App\Models\Agentes::query()
        ->where('numero_documento', $cedula)
        ->value('id_sede'); // null si no existe
}


}
