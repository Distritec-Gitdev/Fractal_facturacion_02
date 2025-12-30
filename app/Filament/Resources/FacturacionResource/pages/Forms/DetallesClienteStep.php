<?php

namespace App\Filament\Resources\FacturacionResource\Forms;

use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;

// Tus modelos y helpers
use App\Models\Asesor;
use App\Models\AaPrin;
use App\Models\SocioDistritec;
use App\Models\Sede;
use App\Models\InfTrab;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class DetallesClienteStep
{
    public static function make(): Step
    {
        return Step::make('Detalles Vendedor')
            ->icon('heroicon-o-briefcase') // Enfoque en el asesor/vendedor
            ->schema([
            
                // Step::make('Detalles Cliente')
                Section::make('Detalles')
                    ->schema([
                        Repeater::make('detallesCliente')
                            ->relationship('detallesCliente')
                            ->minItems(1)
                            ->maxItems(1)
                            ->defaultItems(1)
                            ->schema([
                                Grid::make(2)->schema([
                                                // Checkbox CAMBIO DE ASESOR (solo SOCIO)
                                            Checkbox::make('cambio_asesor')
                                                ->label('Cambio de asesor')
                                                ->reactive()
                                                ->dehydrated(false) // solo controla la UI, no se guarda
                                                ->visible(fn () => auth()->user()?->hasRole('socio'))
                                                // Si la sede seleccionada es ID_Sede_Socio = 2, bloquear checkbox
                                                ->disabled(function (Get $get) {
                                                    if (! auth()->user()?->hasRole('socio')) {
                                                        return false;
                                                    }
                                                    $valor = (int) ($get('id_sede_socio') ?? 0);
                                                    return $valor === 2;
                                                })
                                                ->afterStateUpdated(function (bool $state, callable $set) {
                                                    $user = auth()->user();

                                                    if (! $user?->hasRole('socio')) {
                                                        return;
                                                    }

                                                    // Cuando SE MARCA: empezar desde cero (sin código ni nombre)
                                                    if ($state) {
                                                        \Log::info('[Facturacion] cambio_asesor marcado - reseteando asesor actual');

                                                        $set('nombre_asesor', null);
                                                        $set('nombre_asesor_select', null);
                                                        $set('codigo_asesor', null);
                                                        $set('ID_Asesor', null);
                                                        return;
                                                    }

                                                    // Cuando se DESMARCA => volver a la info original del socio
                                                    $cedula = $user->cedula ?? null;
                                                    if (! $cedula) {
                                                        return;
                                                    }

                                                    $socio = \App\Models\SocioDistritec::where('Cedula', $cedula)->first();
                                                    if (! $socio) {
                                                        return;
                                                    }

                                                    \Log::info('[Facturacion] cambio_asesor desmarcado - reset a socio', [
                                                        'ID_Socio' => $socio->ID_Socio ?? null,
                                                        'Socio'    => $socio->Socio ?? null,
                                                        'cod_ven'  => $socio->soc_cod_vendedor ?? null,
                                                    ]);

                                                    // Volver al código y nombre del socio
                                                    $set('codigo_asesor', $socio->soc_cod_vendedor);
                                                    $set('nombre_asesor', mb_strtoupper($socio->Socio ?? '', 'UTF-8'));

                                                    // limpiar select auxiliar
                                                    $set('nombre_asesor_select', null);
                                                })
                                                ->columnSpanFull(),



                                                // =========================
                                                // 1) SEDE (SELECT POR SOCIO)
                                                // =========================
                                            Select::make('idsede_select')
                                                ->label('Sede')
                                                ->options(function (Get $get) {
                                                    $user = auth()->user();

                                                    \Log::info('[Facturacion] options(idsede_select) directo por user', [
                                                        'user_id' => $user?->id,
                                                        'roles'   => $user?->getRoleNames()->toArray(),
                                                        'cedula'  => $user?->cedula ?? null,
                                                    ]);

                                                    // Solo aplica para SOCIO
                                                    if (! $user?->hasRole('socio')) {
                                                        \Log::info('[Facturacion] options(idsede_select) - usuario no es socio');
                                                        return [];
                                                    }

                                                    $cedula = $user->cedula ?? null;
                                                    if (! $cedula) {
                                                        \Log::warning('[Facturacion] options(idsede_select) - socio sin cédula en user');
                                                        return [];
                                                    }

                                                    $socio = \App\Models\SocioDistritec::where('Cedula', $cedula)->first();

                                                    \Log::info('[Facturacion] options(idsede_select) - socio encontrado para sedes', [
                                                        'socio' => $socio,
                                                    ]);

                                                    if (! $socio) {
                                                        return [];
                                                    }

                                                    // Si cambio_asesor está activo, solo mostrar sedes con ID_Sede_Socio = 1
                                                    $soloTipo1 = (bool) $get('cambio_asesor');

                                                    $query = \App\Models\Sede::where('ID_Socio', $socio->ID_Socio);

                                                    if ($soloTipo1) {
                                                        \Log::info('[Facturacion] options(idsede_select) - solo sedes ID_Sede_Socio = 1 por cambio_asesor');
                                                        $query->where('ID_Sede_Socio', 1);
                                                    }

                                                    $sedes = $query
                                                        ->orderBy('Name_Sede')
                                                        ->pluck('Name_Sede', 'ID_Sede')
                                                        ->toArray();

                                                    // Mayúsculas
                                                    $sedes = collect($sedes)
                                                        ->mapWithKeys(fn ($name, $id) => [$id => mb_strtoupper($name, 'UTF-8')])
                                                        ->toArray();

                                                    \Log::info('[Facturacion] options(idsede_select) - sedes encontradas', [
                                                        'ID_Socio'   => $socio->ID_Socio,
                                                        'count'      => count($sedes),
                                                        'soloTipo1'  => $soloTipo1,
                                                        'sedes'      => $sedes,
                                                    ]);

                                                    return $sedes;
                                                })
                                                ->visible(fn () => auth()->user()?->hasRole('socio'))
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                                    \Log::info('[Facturacion] afterStateUpdated(idsede_select)', ['state' => $state]);

                                                    $esCambioAsesor = (bool) $get('cambio_asesor');

                                                    // Cuando cambio_asesor está activo, al cambiar de sede se debe limpiar asesor/código
                                                    if ($esCambioAsesor) {
                                                        \Log::info('[Facturacion] idsede_select - reseteando asesor por cambio_asesor');

                                                        $set('nombre_asesor', null);
                                                        $set('nombre_asesor_select', null);
                                                        $set('codigo_asesor', null);
                                                        $set('ID_Asesor', null);
                                                    } else {
                                                        // Si NO está en cambio_asesor, mantenemos código y nombre del socio.
                                                        \Log::info('[Facturacion] idsede_select - NO resetea asesor (cambio_asesor = false)');
                                                        $set('nombre_asesor_select', null);
                                                    }

                                                    if ($state) {
                                                        $sede = \App\Models\Sede::find($state);
                                                        \Log::info('[Facturacion] afterStateUpdated(idsede_select) - sede encontrada', [
                                                            'sede' => $sede,
                                                        ]);

                                                        $set('idsede', $state);
                                                        $set('nombre_sede', $sede ? mb_strtoupper($sede->Name_Sede ?? '', 'UTF-8') : '');
                                                        $set('id_sede_socio', $sede->ID_Sede_Socio ?? null);

                                                        // Datos extra de la sede
                                                        if ($sede) {
                                                            $set('codigo_de_sucursal', $sede->Codigo_de_sucursal ?? null);
                                                            $set('codigo_caja',        $sede->Codigo_caja        ?? null);
                                                            $set('prefijo',            $sede->Prefijo            ?? null);
                                                            $set('centro_costos',      $sede->Centro_costos      ?? null);

                                                        }

                                                        // Si la sede es tipo 2, desactivar y limpiar cambio_asesor
                                                        if ((int) ($sede->ID_Sede_Socio ?? 0) === 2) {
                                                            \Log::info('[Facturacion] idsede_select - sede tipo 2, desactivando cambio_asesor y restaurando socio');
                                                            $set('cambio_asesor', false);

                                                            $user = auth()->user();

                                                            // Solo aplica para socios
                                                            if ($user?->hasRole('socio')) {
                                                                $cedula = $user->cedula ?? null;
                                                                $socio  = $cedula
                                                                    ? \App\Models\SocioDistritec::where('Cedula', $cedula)->first()
                                                                    : null;

                                                                \Log::info('[Facturacion] sede tipo 2 - socio para restaurar asesor', [
                                                                    'socio' => $socio,
                                                                ]);

                                                                if ($socio) {
                                                                    // Volvemos a dejar el asesor como el SOCIO
                                                                    $set('codigo_asesor', $socio->soc_cod_vendedor);
                                                                    $set('nombre_asesor', mb_strtoupper($socio->Socio ?? '', 'UTF-8'));
                                                                    $set('ID_Socio', $socio->ID_Socio);
                                                                    // ID_Asesor lo puedes dejar null porque es socio, no asesor de tabla asesores
                                                                    $set('ID_Asesor', null);
                                                                } else {
                                                                    // Fallback si algo raro pasa
                                                                    $set('nombre_asesor', null);
                                                                    $set('codigo_asesor', null);
                                                                    $set('ID_Asesor', null);
                                                                }
                                                            } else {
                                                                // Si por alguna razón no es socio, limpiamos
                                                                $set('nombre_asesor', null);
                                                                $set('codigo_asesor', null);
                                                                $set('ID_Asesor', null);
                                                            }
                                                        }

                                                    } else {
                                                        \Log::info('[Facturacion] afterStateUpdated(idsede_select) - limpiando sede');
                                                        $set('idsede', null);
                                                        $set('nombre_sede', '');
                                                        $set('id_sede_socio', null);

                                                        $set('codigo_de_sucursal', null);
                                                        $set('codigo_caja',        null);
                                                        $set('prefijo',            null);
                                                        $set('centro_costos',      null);
                                                    }

                                                    // Siempre que cambie de sede, limpiar la sede de pago
                                                    $set('sede_pago', null);
                                                })
                                                ->extraAttributes(['class' => 'text-lg font-bold']),


                                                Select::make('sede_pago')
                                                ->label('¿Dónde le pagaron los productos?')
                                                ->options(function () {
                                                    $user = auth()->user();
                                                    if (! $user?->hasRole('socio')) {
                                                        // Nunca debe mostrar opciones si no es socio
                                                        return [];
                                                    }

                                                    $cedula = $user->cedula ?? null;
                                                    if (! $cedula) {
                                                        \Log::warning('[Facturacion] options(sede_pago) - socio sin cédula');
                                                        return [];
                                                    }

                                                    $socio = \App\Models\SocioDistritec::where('Cedula', $cedula)->first();
                                                    if (! $socio) {
                                                        \Log::warning('[Facturacion] options(sede_pago) - no se encontró socio para la cédula', [
                                                            'cedula' => $cedula,
                                                        ]);
                                                        return [];
                                                    }

                                                    // 🔹 Solo sedes de ese socio con ID_Sede_Socio = 1
                                                    $sedesPago = \App\Models\Sede::where('ID_Socio', $socio->ID_Socio)
                                                        ->where('ID_Sede_Socio', 1)
                                                        ->orderBy('Name_Sede')
                                                        ->pluck('Name_Sede', 'ID_Sede')
                                                        ->toArray();

                                                    // Mayúsculas
                                                    $sedesPago = collect($sedesPago)
                                                        ->mapWithKeys(fn ($name, $id) => [$id => mb_strtoupper($name, 'UTF-8')])
                                                        ->toArray();

                                                    \Log::info('[Facturacion] options(sede_pago) - sedes tipo 1', [
                                                        'ID_Socio' => $socio->ID_Socio,
                                                        'count'    => count($sedesPago),
                                                        'sedes'    => $sedesPago,
                                                    ]);

                                                    return $sedesPago;
                                                })
                                                // SOLO visible si:
                                                //   - es socio
                                                //   - la sede principal tiene ID_Sede_Socio = 2
                                                //   - y el checkbox cambio_asesor está desactivado
                                                ->visible(function (Get $get) {
                                                    $user = auth()->user();
                                                    if (! $user || ! $user->hasRole('socio')) {
                                                        return false;
                                                    }

                                                    return (int) ($get('id_sede_socio') ?? 0) === 2
                                                        && ! (bool) $get('cambio_asesor');
                                                })
                                                // Requerido SOLO cuando está visible
                                                ->required(function (Get $get) {
                                                    return auth()->user()?->hasRole('socio')
                                                        && (int) ($get('id_sede_socio') ?? 0) === 2
                                                        && ! (bool) $get('cambio_asesor');
                                                })
                                                ->validationMessages([
                                                    'required' => 'Debe seleccionar la sede donde le pagaron los productos.',
                                                ])
                                                ->reactive()
                                                // Nunca debe tener valor por defecto cuando se activa la condición
                                                ->afterStateHydrated(function ($state, callable $set, Get $get) {
                                                    if (
                                                        auth()->user()?->hasRole('socio')
                                                        && (int) ($get('id_sede_socio') ?? 0) === 2
                                                        && ! (bool) $get('cambio_asesor')
                                                    ) {
                                                        \Log::info('[Facturacion] afterStateHydrated(sede_pago) - limpiando para no tener valor por defecto');
                                                        $set('sede_pago', null);
                                                    }
                                                })
                                                ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                                    \Log::info('[Facturacion] afterStateUpdated(sede_pago)', ['state' => $state]);

                                                    // Si lo limpian y está en contexto en que es obligatorio → notificación
                                                    if (! $state) {
                                                        if (
                                                            auth()->user()?->hasRole('socio')
                                                            && (int) ($get('id_sede_socio') ?? 0) === 2
                                                            && ! (bool) $get('cambio_asesor')
                                                        ) {
                                                            Notification::make()
                                                                ->title('Dato requerido')
                                                                ->body('Debe elegir en qué sede le pagaron los productos.')
                                                                ->warning()
                                                                ->send();
                                                        }

                                                        // No tocamos codigo_caja si lo quitó a propósito
                                                        return;
                                                    }

                                                    // Cuando selecciona una sede de pago, cargamos su caja
                                                    $sede = \App\Models\Sede::find($state);
                                                    \Log::info('[Facturacion] afterStateUpdated(sede_pago) - sede pago encontrada', [
                                                        'sede' => $sede,
                                                    ]);

                                                    if ($sede) {
                                                        $set('codigo_caja', $sede->Codigo_caja ?? null);
                                                    } else {
                                                        Notification::make()
                                                            ->title('Error')
                                                            ->body('No se encontró la información de la sede seleccionada.')
                                                            ->danger()
                                                            ->dehydrated(true)
                                                            ->send();
                                                    }
                                                })
                                                ->extraAttributes(['class' => 'text-lg']),



                                                // =========================
                                                // 2) NOMBRE ASESOR (SELECT cuando hay cambio_asesor)
                                                // =========================
                                                Select::make('nombre_asesor_select')
                                                    ->label('Nombre Asesor')
                                                    ->options(function (Get $get) {
                                                        $user = auth()->user();

                                                        if (! $user?->hasRole('socio')) {
                                                            return [];
                                                        }

                                                        $sedeId = $get('idsede');
                                                        if (! $sedeId) {
                                                            \Log::info('[Facturacion] nombre_asesor_select - sin idsede');
                                                            return [];
                                                        }

                                                        // IMPORTANTE: solo asesores con ID_Estado 1 o 2
                                                        $aaPrinList = \App\Models\AaPrin::where('ID_Sede', $sedeId)
                                                            ->whereIn('ID_Estado', [1, 2])
                                                            ->with('asesor')
                                                            ->get();

                                                        $options = [];

                                                        foreach ($aaPrinList as $row) {
                                                            $infTrab = \App\Models\InfTrab::find($row->ID_Inf_trab);
                                                            if (! $infTrab) {
                                                                continue;
                                                            }

                                                            $codigo = $infTrab->Codigo_vendedor;
                                                            $nombre = $row->asesor->Nombre ?? ('Asesor '.$row->ID_Asesor);
                                                            $nombre = mb_strtoupper($nombre, 'UTF-8');

                                                            // clave: código asesor, valor: nombre en mayúsculas
                                                            $options[$codigo] = $nombre;
                                                        }

                                                        \Log::info('[Facturacion] nombre_asesor_select - opciones', [
                                                            'idsede'  => $sedeId,
                                                            'options' => $options,
                                                        ]);

                                                        return $options;
                                                    })
                                                    ->visible(function (Get $get) {
                                                        return auth()->user()?->hasRole('socio')
                                                            && (bool) $get('cambio_asesor')
                                                            && filled($get('idsede'));
                                                    })
                                                    ->reactive()
                                                    ->afterStateUpdated(function ($state, callable $set) {
                                                        \Log::info('[Facturacion] afterStateUpdated(nombre_asesor_select)', [
                                                            'state' => $state,
                                                        ]);

                                                        if (! $state) {
                                                            return;
                                                        }

                                                        $infTrab = \App\Models\InfTrab::where('Codigo_vendedor', $state)
                                                            ->with('aaPrin.asesor', 'aaPrin.sede')
                                                            ->first();

                                                        \Log::info('[Facturacion] afterStateUpdated(nombre_asesor_select) - infTrab encontrado', [
                                                            'infTrab' => $infTrab,
                                                        ]);

                                                        if ($infTrab && $infTrab->aaPrin) {
                                                            $sede = $infTrab->aaPrin->sede;

                                                            $set('codigo_asesor', $infTrab->Codigo_vendedor);
                                                            $set('nombre_asesor', mb_strtoupper($infTrab->aaPrin->asesor->Nombre ?? '', 'UTF-8'));
                                                            $set('ID_Asesor', $infTrab->aaPrin->ID_Asesor);
                                                            $set('idsede', $infTrab->aaPrin->ID_Sede);
                                                            $set('nombre_sede', $sede ? mb_strtoupper($sede->Name_Sede ?? '', 'UTF-8') : '');

                                                            // 🔹 NUEVO: actualizar datos extra de la sede (cuando socio elige asesor)
                                                            if ($sede) {
                                                                $set('codigo_de_sucursal', $sede->Codigo_de_sucursal ?? null);
                                                                $set('codigo_caja',        $sede->Codigo_caja        ?? null);
                                                                $set('prefijo',            $sede->Prefijo            ?? null);
                                                                $set('centro_costos',      $sede->Centro_costos      ?? null);

                                                            }
                                                        }
                                                    })
                                                    ->extraAttributes(['class' => 'text-lg font-bold']),

                                                // =========================
                                                // 2b) NOMBRE ASESOR (texto normal cuando NO hay cambio_asesor)
                                                // =========================
                                                TextInput::make('nombre_asesor')
                                                    ->label('Nombre Asesor')
                                                    ->disabled()
                                                    ->hidden(function (Get $get) {
                                                        // oculto para agentes, y para SOCIO cuando está haciendo cambio de asesor
                                                        return auth()->user()?->hasAnyRole(['agente_admin', 'asesor_agente'])
                                                            || (auth()->user()?->hasRole('socio') && (bool) $get('cambio_asesor'));
                                                    })
                                                    ->dehydrated(fn () => ! auth()->user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->default(function () {
                                                        $user   = auth()->user();
                                                        $cedula = $user?->cedula;

                                                        if (! $cedula) {
                                                            \Log::warning('[Facturacion] default(nombre_asesor) - usuario sin cédula');
                                                            return null;
                                                        }

                                                        if ($user?->hasRole('socio')) {
                                                            $socio = \App\Models\SocioDistritec::where('Cedula', $cedula)->first();
                                                            \Log::info('[Facturacion] default(nombre_asesor) - socio detectado', [
                                                                'socio' => $socio,
                                                            ]);
                                                            return $socio ? mb_strtoupper($socio->Socio ?? '', 'UTF-8') : null;
                                                        }

                                                        $asesor = \App\Models\Asesor::where('Cedula', $cedula)->first();
                                                        \Log::info('[Facturacion] default(nombre_asesor) - asesor detectado', [
                                                            'asesor' => $asesor,
                                                        ]);

                                                        return $asesor ? mb_strtoupper($asesor->Nombre ?? '', 'UTF-8') : null;
                                                    })
                                                    ->extraAttributes(['class' => 'text-lg font-bold']),

                                                // =========================
                                                // 3) CÓDIGO ASESOR
                                                // =========================
                                                Select::make('codigo_asesor')
                                                    ->label('Código Asesor')
                                                    ->options(\App\Models\InfTrab::pluck('Codigo_vendedor', 'Codigo_vendedor'))
                                                    ->searchable()
                                                    ->reactive()
                                                    ->required()
                                                    ->disabled()
                                                    ->required(fn () => ! auth()->user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->hidden(fn ()   =>   auth()->user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->dehydrated(fn () => ! auth()->user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->default(function () {
                                                        $user   = auth()->user();
                                                        $cedula = $user?->cedula;

                                                        \Log::info('[Facturacion] default(codigo_asesor)', [
                                                            'user_id' => $user?->id,
                                                            'cedula'  => $cedula,
                                                            'roles'   => $user?->getRoleNames()->toArray(),
                                                        ]);

                                                        if (! $cedula) {
                                                            \Log::warning('[Facturacion] default(codigo_asesor) - usuario sin cédula');
                                                            return null;
                                                        }

                                                        if ($user?->hasRole('socio')) {
                                                            $socio = \App\Models\SocioDistritec::where('Cedula', $cedula)->first();
                                                            \Log::info('[Facturacion] default(codigo_asesor) - socio detectado', [
                                                                'socio' => $socio,
                                                            ]);
                                                            return $socio?->soc_cod_vendedor;
                                                        }

                                                        $asesor = \App\Models\Asesor::where('Cedula', $cedula)->first();
                                                        \Log::info('[Facturacion] default(codigo_asesor) - asesor buscado', [
                                                            'asesor' => $asesor,
                                                        ]);
                                                        if (! $asesor) return null;

                                                        $aaPrin = \App\Models\AaPrin::where('ID_Asesor', $asesor->ID_Asesor)
                                                            ->where('ID_Estado', '!=', 3)
                                                            ->orderByDesc('ID_Inf_trab')
                                                            ->first();

                                                        \Log::info('[Facturacion] default(codigo_asesor) - aaPrin', [
                                                            'aaPrin' => $aaPrin,
                                                        ]);

                                                        if (! $aaPrin) return null;

                                                        $infTrab = \App\Models\InfTrab::find($aaPrin->ID_Inf_trab);
                                                        \Log::info('[Facturacion] default(codigo_asesor) - infTrab', [
                                                            'infTrab' => $infTrab,
                                                        ]);

                                                        return $infTrab?->Codigo_vendedor;
                                                    })
                                                    ->extraAttributes(['class' => 'text-lg font-bold']),

                                                // =========================
                                                // NOMBRE SEDE (TEXTO) EN MAYÚSCULAS
                                                // =========================
                                                TextInput::make('nombre_sede')
                                                    ->label('Sede (Nombre)')
                                                    ->disabled()
                                                    ->hidden(fn ()   => auth()->user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->dehydrated(fn () => ! auth()->user()?->hasAnyRole(['agente_admin', 'asesor_agente']))
                                                    ->default(function () {
                                                        $user   = auth()->user();
                                                        $cedula = $user?->cedula;

                                                        // Para SOCIO, la sede se define desde idsede_select
                                                        if ($user?->hasRole('socio')) {
                                                            \Log::info('[Facturacion] default(nombre_sede) - socio, sede se elige manualmente');
                                                            return null;
                                                        }

                                                        if (! $cedula) {
                                                            \Log::warning('[Facturacion] default(nombre_sede) - usuario sin cédula');
                                                            return null;
                                                        }

                                                        $asesor = \App\Models\Asesor::where('Cedula', $cedula)->first();
                                                        if (! $asesor) {
                                                            \Log::warning('[Facturacion] default(nombre_sede) - asesor no encontrado');
                                                            return null;
                                                        }

                                                        $aaPrin = \App\Models\AaPrin::where('ID_Asesor', $asesor->ID_Asesor)
                                                            ->where('ID_Estado', '!=', 3)
                                                            ->orderByDesc('ID_Inf_trab')
                                                            ->with('sede')
                                                            ->first();

                                                        \Log::info('[Facturacion] default(nombre_sede) - aaPrin asesor', [
                                                            'aaPrin' => $aaPrin,
                                                        ]);

                                                        return $aaPrin && $aaPrin->sede
                                                            ? mb_strtoupper($aaPrin->sede->Name_Sede ?? '', 'UTF-8')
                                                            : null;
                                                    })
                                                    ->extraAttributes(['class' => 'text-lg font-bold']),

                                                // =========================
                                                // NUEVOS CAMPOS SOLO DE LECTURA DE LA SEDE (TODOS LOS ROLES)
                                                // =========================
                                                TextInput::make('codigo_de_sucursal')
                                                    ->label('Código de sucursal')
                                                    ->disabled()
                                                    ->extraAttributes(['class' => 'text-lg'])
                                                    ->dehydrated(true),
                                                    

                                                TextInput::make('codigo_caja')
                                                    ->label('Código de caja')
                                                    ->disabled()
                                                    ->extraAttributes(['class' => 'text-lg'])
                                                    ->dehydrated(true),

                                                TextInput::make('prefijo')
                                                    ->label('Prefijo')
                                                    ->disabled()
                                                    ->extraAttributes(['class' => 'text-lg'])
                                                    ->dehydrated(true),

                                                TextInput::make('centro_costos')
                                                    ->label('Centro de costos')
                                                    ->disabled()
                                                    ->extraAttributes(['class' => 'text-lg'])
                                                    ->dehydrated(true),

                                                // =========================
                                                // HIDDEN: ID_Asesor / ID_Socio / idsede
                                                // =========================

                                                Hidden::make('id_sede_socio')
                                                ->dehydrated(true),

                                                Hidden::make('ID_Asesor')
                                                    ->default(function () {
                                                        $user   = auth()->user();
                                                        $cedula = $user?->cedula;

                                                        if (! $cedula || $user?->hasRole('socio')) {
                                                            return null;
                                                        }

                                                        $asesor = \App\Models\Asesor::where('Cedula', $cedula)->first();
                                                        return $asesor?->ID_Asesor;
                                                    })
                                                    ->dehydrated(true),

                                                Hidden::make('ID_Socio')
                                                    ->default(function () {
                                                        $user   = auth()->user();
                                                        $cedula = $user?->cedula;

                                                        if (! $cedula || ! $user?->hasRole('socio')) {
                                                            return null;
                                                        }

                                                        $socio = \App\Models\SocioDistritec::where('Cedula', $cedula)->first();
                                                        return $socio?->ID_Socio;
                                                    })
                                                    ->dehydrated(false)
                                                    ->live(),

                                                Hidden::make('idsede')
                                                    ->default(function () {
                                                        $user   = auth()->user();
                                                        $cedula = $user?->cedula;

                                                        // Para socio, se setea desde idsede_select / nombre_asesor_select
                                                        if ($user?->hasRole('socio')) {
                                                            return null;
                                                        }

                                                        if (! $cedula) {
                                                            return null;
                                                        }

                                                        $asesor = \App\Models\Asesor::where('Cedula', $cedula)->first();
                                                        if (! $asesor) {
                                                            return null;
                                                        }

                                                        $aaPrin = \App\Models\AaPrin::where('ID_Asesor', $asesor->ID_Asesor)
                                                            ->where('ID_Estado', '!=', 3)
                                                            ->orderByDesc('ID_Inf_trab')
                                                            ->first();

                                                        return $aaPrin?->ID_Sede;
                                                    })
                                                    ->hidden(fn () => auth()->user()?->hasAnyRole(['agente_admin','asesor_agente']))
                                                    ->dehydrated(fn () => ! auth()->user()?->hasAnyRole(['agente_admin','asesor_agente']))
                                                    ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) $state : null)
                                                    // 🔹 Cuando idsede se hidrata (en asesores), también cargamos los datos de la sede
                                                    ->afterStateHydrated(function ($state, callable $set) {
                                                    if (! $state) {
                                                        return;
                                                    }

                                                    $sede = \App\Models\Sede::find($state);
                                                    if (! $sede) {
                                                        return;
                                                    }

                                                    $set('nombre_sede', mb_strtoupper($sede->Name_Sede ?? '', 'UTF-8'));
                                                    $set('codigo_de_sucursal', $sede->Codigo_de_sucursal ?? null);
                                                    $set('codigo_caja',        $sede->Codigo_caja        ?? null);
                                                    $set('prefijo',            $sede->Prefijo            ?? null);
                                                    $set('centro_costos',      $sede->Centro_costos      ?? null);
                                                    $set('id_sede_socio',      $sede->ID_Sede_Socio ?? null); // 🔹 nuevo
                                                }),
                                                ]), // fin Grid
                                    ]) ->deletable(false),
                    ]),
            ]);
    }

}
