<?php
// app/Filament/Resources/GestionClienteResource/Pages/TokenGestionCliente.php

namespace App\Filament\Resources\GestionClienteResource\Pages;

use App\Filament\Resources\GestionClienteResource;
use App\Mail\TokenMail;
use App\Models\Cliente;
use App\Models\Token;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class TokenGestionCliente extends Page implements HasForms
{
    use InteractsWithForms;

    public Cliente $record;
    public ?string $telefono = null;
    public ?string $correo   = null;

    // Flag para controlar error de SMS
    public bool $smsError = false;

        /**
    * Verdadero cuando la gestión del cliente ya está en estado 2 (aprobado)
    */
    public bool $aprobadoCliente = false; 
    public ?int $estadoCredito = null;

    

    protected $listeners = [
        'refreshbuttons'                           => 'handleRefresh',
       // 'echo:gestion-clientes,.ClienteUpdated'    => 'handleRefresh',

        //  NUEVO: escuchar el broadcast del estado del crédito
        //'echo:gestion-clientes,.EstadoCreditoUpdated' => 'handleEstadoCreditoUpdated',
          'refreshbuttons' => 'handleRefresh',
    ];


    protected static string $resource = GestionClienteResource::class;
    protected static string $view     = 'filament.resources.gestion-cliente-resource.pages.token-gestion-cliente';


     public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        // Si quieres que solo admin/super_admin la vean en el menú:
        $super = config('filament-shield.super_admin.name', 'super_admin');
        return auth()->user()?->hasAnyRole([$super, 'admin']) ?? false;

        // O simplemente: return false;  // si no debe aparecer en menú nunca
    }

    
    public function mount(Cliente $record): void
    {
        $this->record   = $record;
        
        // 🔒 Bloqueo por dueño/admin/super_admin
        $user  = auth()->user();
        $super = config('filament-shield.super_admin.name', 'super_admin');

        $esAdmin = $user?->hasAnyRole([$super, 'admin']) ?? false;
        $esDueno = $record->user_id === ($user?->id);

        if (! $esAdmin && ! $esDueno) {
            abort(403);
        }

        $contacto       = $record->clientesContacto;
        $this->telefono = $contacto->tel ?? null;
        $this->correo   = $contacto->correo ?? null;

        // Validar si el último token está confirmado (confirmacion_token == 2)
        $lastToken = \App\Models\Token::where('id_cliente', $record->id_cliente)
            ->latest('created_at')
            ->first();
        if ($lastToken && $lastToken->confirmacion_token == 2) {
            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Documentación ya firmada')
                ->body('La documentación de este cliente ya fue firmada correctamente.')
                ->persistent()
                ->send();
            $this->redirect(static::getResource()::getUrl('index'));
            return;
        }

        $this->refreshAprobacion();
    }

    /**
     * Maneja el refresh Livewire y redirige si el token fue confirmado.
     */
    public function handleRefresh($record = null)
    {
        $this->refreshAprobacion();
        if ($record instanceof \App\Models\Cliente) {
            $this->record = $record;
            $contacto       = $record->clientesContacto;
            $this->telefono = $contacto->tel ?? null;
            $this->correo   = $contacto->correo ?? null;
        }
        $lastToken = \App\Models\Token::where('id_cliente', $this->record->id_cliente)
            ->latest('created_at')
            ->first();
        if ($lastToken && $lastToken->confirmacion_token == 2) {
            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Documentación ya firmada')
                ->body('La documentación de este cliente ya fue firmada correctamente.')
                ->persistent()
                ->send();
            $this->redirect(static::getResource()::getUrl('index'));
            return;
        }
        // Solo limpia validaciones, NO uses $this->reset()
    $this->resetErrorBag();
    $this->resetValidation();
    }


    

    protected function getFormSchema(): array
    {
        return [
          


TextInput::make('telefono')
    ->label('')
    ->disabled()
    ->dehydrated(false)
    ->prefixIcon('heroicon-o-phone')
    ->extraInputAttributes([
        'style' => '
            width: 100%;
            background: rgba(59, 130, 246, 0.15);
            border: 2px solid #3b82f6;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            font-size: 1rem;
            color: #1e293b;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.05);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        ',
        'onfocus' => "
            this.style.background='rgba(255,255,255,0.9)';
            this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)';
            this.style.transform='translateY(-2px)';
        ",
        'onblur'  => "
            this.style.background='rgba(59, 130, 246, 0.15)';
            this.style.boxShadow='inset 0 2px 8px rgba(0,0,0,0.05)';
            this.style.transform='none';
        ",
    ]),

TextInput::make('correo')
    ->label('')
    ->disabled()
    ->dehydrated(false)
    ->prefixIcon('heroicon-o-envelope')
    ->extraInputAttributes([
        'style' => '
            width: 100%;
            background: rgba(16, 185, 129, 0.15);
            border: 2px solid #10b981;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            font-size: 1rem;
            color: #1e293b;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.05);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        ',
        'onfocus' => "
            this.style.background='rgba(255,255,255,0.9)';
            this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)';
            this.style.transform='translateY(-2px)';
        ",
        'onblur'  => "
            this.style.background='rgba(16, 185, 129, 0.15)';
            this.style.boxShadow='inset 0 2px 8px rgba(0,0,0,0.05)';
            this.style.transform='none';
        ",
    ]),
        ];
    }

  
   
    public function generateAndSend(): void
    {
        if (!$this->ensureAprobadoOrWarn()) return;
        // Buscar token existente no confirmado para este cliente
        $last = Token::query()
            ->where('id_cliente', $this->record->id_cliente)
            ->where('authentication_token', '!=', 2)
            ->latest('created_at')
            ->first();

        if ($last) {
            $tokenValue = $last->token;
        } else {
            // Generar nuevo token único de 8 dígitos
            do {
                $tokenValue = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            } while (Token::where('token', $tokenValue)->exists());

            // Crear nuevo registro
            $last = Token::create([
                'id_cliente' => $this->record->id_cliente,
                'token'      => $tokenValue,
            ]);
        }

        // Actualizar authentication_token a 3 (SMS enviado) y sello de tiempo personalizado
        $last->update([
            'authentication_token' => 3,
            'envio_mssg'           => now(),
        ]);


        // Preparar payload API sms.iatechsas
        $api   = config('services.sms_iatechsas');
        $to    = '+57' . preg_replace('/\D+/', '', $this->telefono);
        $text  = "Token Distritec {$last->token}. Ingresa aquí: "
         . route('link.temporal', ['token' => $last->token]);
  
        $payload = [
            'messages' => [
                [
                    'destinations' => [['to' => $to]],
                    'from'         => $api['from'],
                    'text'         => $text,
                ],
            ],
        ];

        $response = Http::withHeaders([
                'Authorization' => 'App ' . $api['api_key'],
                'Accept'        => 'application/json',
            ])
            ->post("{$api['base_url']}/sms/2/text/advanced", $payload);

        if ($response->failed()) {
            $this->smsError = true;
            Notification::make()
                ->danger()
                ->title('Error al enviar SMS')
                ->body("No se pudo entregar el token al número {$this->telefono}.")
                ->send();
        } else {
            $this->smsError = false;
            Notification::make()
                ->success()
                ->title('Token enviado por SMS exitosamente')
                ->body('Se envió el token exitosamente al número ' . $this->telefono . '.')
                ->send();
        }
    }

    public function sendByEmail(): void
    {
        if (!$this->ensureAprobadoOrWarn()) return;
        $last = Token::query()
            ->where('id_cliente', $this->record->id_cliente)
            ->where('authentication_token', '!=', 2)
            ->latest('created_at')
            ->first();

        if (! $last) {
            Notification::make()
                ->danger()
                ->title('No hay token disponible para enviar')
                ->send();
            return;
        }

        $tokenValue      = $last->token;
        $verificationUrl = url("/acceso/{$tokenValue}");

        // Actualizar authentication_token a 4 (Correo enviado)
       // Estado 4 (Correo enviado) y tiempo personalizado
        $last->update([
            'authentication_token' => 4,
            'envio_email'          => now(),
        ]);


        try {
            Mail::to($this->correo)
                ->send(new TokenMail($tokenValue, $verificationUrl));

            Notification::make()
                ->success()
                ->title("Correo enviado")
                ->body("Se envió el token a {$this->correo}.")
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Error al enviar correo')
                ->body($e->getMessage())
                ->send();
        }
    }

    
public function sendByWhatsApp(): void
{
    if (!$this->ensureAprobadoOrWarn()) return;
    $last = Token::query()
        ->where('id_cliente', $this->record->id_cliente)
        ->latest('created_at')
        ->first();

    if (! $last) {
        Notification::make()->danger()->title('No hay token para enviar por WhatsApp')->send();
        return;
    }

    // Normalizar teléfono
    $raw    = (string) ($this->telefono ?? '');
    $digits = preg_replace('/\D+/', '', $raw); // solo números
    if (strlen($digits) === 10) {
        $digits = '57' . $digits;              // 10 dígitos -> anteponer 57
    }
    if (! preg_match('/^57\d{10}$/', $digits)) {
        Notification::make()->danger()
            ->title('Teléfono inválido')
            ->body('El celular debe tener el formato 57 + 10 dígitos.')
            ->send();
        return;
    }

    // Actualiza estado y sello personalizado
    $last->update([
        'authentication_token' => 5,
        'envio_wtsapp'         => now(),
    ]);

    // ===== Cloudyx =====
    $endpoint = rtrim(config('services.cloudyx.url'), '/') . '/'; // "https://sms.cloudyx.cloud/api/send/"
    $apiToken = (string) config('services.cloudyx.token');        // puede venir con o sin "Token "
    $auth     = str_starts_with($apiToken, 'Token ') ? $apiToken : ('Token ' . $apiToken);

    // Body EXACTO como en Postman (JSON crudo)
    $payload = [
        "to"      => "{$digits}@s.whatsapp.net",
        "message" => "Token Distritec {$last->token}. Ingresa aquí: " . route('link.temporal', ['token' => $last->token]),
    ];

    $response = Http::withHeaders([
            'Authorization' => $auth,              // 👈 EXACTO como en Postman
            'Accept'        => 'application/json',
        ])
        ->asJson()                                 // 👈 envía cuerpo como JSON (comillas, llaves, etc.)
        ->timeout(30)
        ->post($endpoint, $payload);

    \Log::debug('Cloudyx response', [
        'endpoint' => $endpoint,
        'status'   => $response->status(),
        'body'     => $response->body(),
    ]);

    if ($response->successful()) {
        Notification::make()->success()
            ->title('Token enviado por WhatsApp')
            ->body("Se envió el token al {$digits}.")
            ->send();
    } else {
        Notification::make()->danger()
            ->title('Error al enviar por WhatsApp')
            ->body("Código: {$response->status()} — {$response->body()}")
            ->send();
    }
}



   protected function shouldShowEmailButton(): bool
{
    $last = Token::query()
        ->where('id_cliente', $this->record->id_cliente)
        ->where('authentication_token', '!=', 2)
        ->latest('created_at')
        ->first();

    if (! $last || empty($last->envio_mssg)) {
        return false;
    }

    return now()->diffInSeconds(Carbon::parse($last->envio_mssg)) >= 90;
}


      /**  
     * Solo tras email exitoso, muestra WhatsApp cuando expiren 5 minutos.  
     */
  /** Solo tras email exitoso, muestra WhatsApp cuando expiren 3 minutos. */
protected function shouldShowWhatsAppButton(): bool
{
    $last = Token::query()
        ->where('id_cliente', $this->record->id_cliente)
        ->latest('created_at')
        ->first();

    return $last
        && $last->authentication_token === 4
        && ! empty($last->envio_email)
        && now()->diffInSeconds(Carbon::parse($last->envio_email)) >= 90;
}


public function resetToken(): void
{
    if (!$this->ensureAprobadoOrWarn()) return;
    Token::where('id_cliente', $this->record->id_cliente)->delete();

    Notification::make()
        ->success()
        ->title('Proceso reiniciado')
        ->body('Se eliminaron los tokens y puedes iniciar el proceso nuevamente.')
        ->send();

    // 👇 SIN return
    $this->redirect(
        url: static::getResource()::getUrl('token', [
            'record' => $this->record->id_cliente,
        ]),
        navigate: true
    );
}



/**
* Actualiza la bandera de aprobación desde la tabla `gestion`.
*/
public function refreshAprobacion(): void
{
    $clienteId = $this->record->id_cliente ?? $this->record->id ?? null;

    if (! $clienteId) {
        $this->estadoCredito   = null;
        $this->aprobadoCliente = false;

        Log::debug('🔎 refreshAprobacion: sin clienteId', [
            'record' => $this->record ?? null,
        ]);
        return;
    }

    $estado = DB::table('gestion')
        ->where('id_cliente', $clienteId)
        ->value('ID_Estado_cr'); // <— confirma que la columna se llama así exactamente

    $this->estadoCredito   = is_null($estado) ? null : (int) $estado;
    $this->aprobadoCliente = ($this->estadoCredito === 2);

    Log::debug('🔎 refreshAprobacion', [
        'clienteId'  => $clienteId,
        'estado'     => $this->estadoCredito,
        'aprobado'   => $this->aprobadoCliente,
    ]);
}

/**
* Impide ejecutar acciones cuando el cliente no está aprobado (ID_Estado_cr != 2)
*/
private function ensureAprobadoOrWarn(): bool // NUEVO
{
        if ($this->aprobadoCliente) {
        return true;
        }


        Notification::make()
        ->warning()
        ->title('En espera de aprobación del cliente')
        ->body('Aún no puedes continuar: la gestión del cliente debe estar en estado 2 para seguir con el envío del token y la firma.')
        ->persistent()
        ->send();


        return false;
        }


public function handleEstadoCreditoUpdated(array $payload): void
{
    $pageClienteId   = (int) ($this->record->id_cliente ?? $this->record->id ?? 0);
    $eventoClienteId = (int) ($payload['cliente_id'] ?? 0);
    $estadoId        = $payload['estado_id'] ?? null;
    $estadoTexto     = $payload['estado_texto'] ?? null;

    \Log::debug('🔔 [TokenGestionCliente] EstadoCreditoUpdated recibido', [
        'page_cliente_id'   => $pageClienteId,
        'payload_cliente_id'=> $eventoClienteId,
        'estado_id'         => $estadoId,
        'estado_texto'      => $estadoTexto,
    ]);

    // Si el evento no es para este cliente, lo ignoramos
    if ($eventoClienteId === 0 || $eventoClienteId !== $pageClienteId) {
        \Log::debug('🔕 [TokenGestionCliente] Evento no corresponde a este cliente, se ignora');
        return;
    }

    // Recalcular flags de aprobación desde la tabla `gestion`
    $this->refreshAprobacion();

    // Resetear validaciones para que no queden estados raros
    $this->resetErrorBag();
    $this->resetValidation();

    \Log::debug('✅ [TokenGestionCliente] Estado refrescado tras EstadoCreditoUpdated', [
        'estado_credito' => $this->estadoCredito,
        'aprobado'       => $this->aprobadoCliente,
    ]);
}

protected function getCurrentTokenValue(): ?string
{
    // El token “vigente” que está usando el flujo
    $last = Token::query()
        ->where('id_cliente', $this->record->id_cliente)
        ->latest('created_at')
        ->first();

    return $last?->token ?: null;
}

public function getListeners(): array
{
    $listeners = $this->listeners;

    $clienteId = (int) ($this->record->id_cliente ?? 0);

    // ===== 1) Firma (cliente + token) =====
    $token = (string) ($this->getCurrentTokenValue() ?? '');
    if ($clienteId > 0 && $token !== '') {
        // broadcastAs() = 'cliente.firmado' -> Echo lo escucha con punto: '.cliente.firmado'
        $listeners["echo-private:cliente.$clienteId.token.$token,.cliente.firmado"] = 'onClienteFirmado';
    }

    // ===== 2) Estado de crédito (solo por cliente, SIN token) =====
    if ($clienteId > 0) {
        // broadcastAs() = 'EstadoCreditoUpdated' -> Echo lo escucha con punto: '.EstadoCreditoUpdated'
        $listeners["echo-private:gestion-clientes.$clienteId,.EstadoCreditoUpdated"] = 'handleEstadoCreditoUpdated';
    }

    return $listeners;
}


public function onClienteFirmado(array $payload = []): void
{
    $pageClienteId = (int) $this->record->id_cliente;
    $eventoClienteId = (int) ($payload['clienteId'] ?? 0);

    Log::info('[TokenGestionCliente] cliente.firmado recibido', [
        'page_cliente_id' => $pageClienteId,
        'payload' => $payload,
    ]);

    // Si no es para este cliente, chao.
    if ($eventoClienteId !== $pageClienteId) {
        return;
    }

    // Aquí NO hagas refresh brutal. Haz lo mínimo:
    // Verifica confirmación y redirige.
    $lastToken = Token::where('id_cliente', $pageClienteId)
        ->latest('created_at')
        ->first();

    if ($lastToken && (int) $lastToken->confirmacion_token === 2) {
        Notification::make()
            ->success()
            ->title('Documentación firmada')
            ->body('El cliente ya firmó correctamente.')
            ->send();

        $this->redirect(static::getResource()::getUrl('index'));
    }
}



}
