<?php

namespace App\Filament\Resources\GestionClienteResource\Pages;

use App\Filament\Resources\GestionClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Arr;
use App\Models\ZDepartamentos;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use App\Models\ZMunicipios;
use App\Models\Cliente; 
use Illuminate\Support\Facades\Schema;
use App\Events\ClienteUpdated;
use App\Events\ClienteCreatedLight;



class CreateGestionCliente extends CreateRecord
{
    protected static string $resource = GestionClienteResource::class;

    public $datosCliente = null;
    public $tipoDocumento = null;

    

    public function mount(): void
    {
        Log::info('MOUNT EJECUTADO EN CreateGestionCliente');
        parent::mount();
        Log::info('CreateGestionCliente - Mount llamado');

        // Leer datos de la sesión si existen
        $datosApi = session('cliente_buscado_data', null);
        $tipoDocumentoSesion = session('cliente_buscado_tipo_documento', null);

        // Limpiar los datos de la sesión después de leerlos
        session()->forget('cliente_buscado_data');
        session()->forget('cliente_buscado_tipo_documento');

        Log::info('CreateGestionCliente - Datos API de sesión en mount:', ['datosApi' => $datosApi, 'tipoDocumentoSesion' => $tipoDocumentoSesion]);

        // Preparar $formData
        $formData = $this->form->getRawState() ?? [];

        // --- Solución para eliminar duplicados: Reiniciar y luego llenar --- //
        if ($datosApi && is_array($datosApi)) {
            // Nombre Completo NO es un repeater, es una relación HasOne. Sus campos deben ir anidados.
            // Inicializar la estructura para clientesNombreCompleto si no existe
            if (!isset($formData['clientesNombreCompleto'])) {
                $formData['clientesNombreCompleto'] = [];
            }
            // Este SÍ es un HasOne Repeater (para multiples contactos se usaria Repeater, pero se esta usando como HasOne)
            if (!isset($formData['clientesContacto'])) {
                $formData['clientesContacto'] = [];
            }

            // Asignar datos de la API a los campos anidados de Nombre Completo
            $formData['clientesNombreCompleto']['Primer_nombre_cliente'] = isset($datosApi['nombre1']) ? (string) $datosApi['nombre1'] : '';
            $formData['clientesNombreCompleto']['Segundo_nombre_cliente'] = isset($datosApi['nombre2']) ? (string) $datosApi['nombre2'] : '';
            $formData['clientesNombreCompleto']['Primer_apellido_cliente'] = isset($datosApi['apellido1']) ? (string) $datosApi['apellido1'] : '';
            $formData['clientesNombreCompleto']['Segundo_apellido_cliente'] = isset($datosApi['apellido2']) ? (string) $datosApi['apellido2'] : '';

            if (isset($datosApi['celular']) || isset($datosApi['telefono1']) || isset($datosApi['email']) || isset($datosApi['direccion'])) {
                $formData['clientesContacto']['tel'] = isset($datosApi['celular']) ? (string) $datosApi['celular'] : '';
                $formData['clientesContacto']['tel_alternativo'] = isset($datosApi['telefono1']) ? (string) $datosApi['telefono1'] : '';
                $formData['clientesContacto']['correo'] = isset($datosApi['email']) ? (string) $datosApi['email'] : '';
                $formData['clientesContacto']['direccion'] = isset($datosApi['direccion']) ? (string) $datosApi['direccion'] : '';

                if (isset($datosApi['fechaExpedicionCedula'])) {
                    $formData['clientesContacto']['fecha_expedicion'] = \Illuminate\Support\Carbon::parse((string) $datosApi['fechaExpedicionCedula'])->format('Y-m-d');
                }
                if (isset($datosApi['esIndependiente'])) {
                    $formData['clientesContacto']['es_independiente'] = (bool) $datosApi['esIndependiente'];
                }
                if (isset($datosApi['empresaLabora'])) {
                    $formData['clientesContacto']['empresa_labor'] = (string) $datosApi['empresaLabora'];
                }
                if (isset($datosApi['telefonoEmpresa'])) {
                    $formData['clientesContacto']['tel_empresa'] = (string) $datosApi['telefonoEmpresa'];
                }
                
                if (isset($datosApi['descripcionZona'])) {
                    $idDepartamentoResidencia = $this->getDepartamentoId($datosApi['descripcionZona']);
                    $formData['clientesContacto']['residencia_id_departamento'] = $idDepartamentoResidencia;
                }
                if (isset($datosApi['codigoCiudad'])) {
                    $formData['clientesContacto']['residencia_id_municipio'] = (string) $datosApi['codigoCiudad'];
                }
            }

            // Asignar campos directos del formulario
            $formData['cedula'] = $datosApi['nit'] ?? ($datosApi['codigoInterno'] ?? null);
            $formData['ID_Identificacion_Cliente'] = $tipoDocumentoSesion ?? 'CC';
            $formData['fecha_nac'] = $datosApi['fechaNacimiento'] ? \Illuminate\Support\Carbon::parse($datosApi['fechaNacimiento'])->format('Y-m-d') : null;

            // --- NUEVO: Llenar id_departamento y id_municipio del formulario principal ---
            $departamentoPrincipalId = $this->getDepartamentoId($datosApi['descripcionZona'] ?? null);
            $municipioPrincipalId = $this->getMunicipioId($datosApi['descripcionCiudad'] ?? null);

            $formData['id_departamento'] = $departamentoPrincipalId;
            $formData['id_municipio'] = $municipioPrincipalId;

            Log::info('CreateGestionCliente Mount - Set main id_departamento:', ['value' => $formData['id_departamento'], 'from_api' => $datosApi['descripcionZona'] ?? null]);
            Log::info('CreateGestionCliente Mount - Set main id_municipio:', ['value' => $formData['id_municipio'], 'from_api' => $datosApi['descripcionCiudad'] ?? null]);

        } else {
            // Si NO hay datos de la API, asegurar que los repeaters se inicializan con un solo elemento [0] vacío
            // Para clientesNombreCompleto (HasOne)
            if (!isset($formData['clientesNombreCompleto'])) {
                $formData['clientesNombreCompleto'] = [
                    'Primer_nombre_cliente' => '',
                    'Segundo_nombre_cliente' => '',
                    'Primer_apellido_cliente' => '',
                    'Segundo_apellido_cliente' => '',
                ];
            }
            // Para clientesContacto (HasOne)
            if (!isset($formData['clientesContacto'])) {
                $formData['clientesContacto'] = [
                    'tel' => '',
                    'tel_alternativo' => '',
                    'correo' => '',
                    'direccion' => '',
                    'residencia_id_departamento' => null,
                    'residencia_id_municipio' => null,
                    'fecha_expedicion' => null,
                    'empresa_labor' => '',
                    'tel_empresa' => '',
                    'es_independiente' => false,
                ];
            }
            // Inicializar los campos individuales de nombre si no hay datos de la API
            // Estos campos ya no son necesarios aquí si clientesNombreCompleto es un HasOne anidado.
            // if (!isset($formData['Primer_nombre_cliente'])) {
            //     $formData['Primer_nombre_cliente'] = '';
            // }
            // if (!isset($formData['Segundo_nombre_cliente'])) {
            //     $formData['Segundo_nombre_cliente'] = '';
            // }
            // if (!isset($formData['Primer_apellido_cliente'])) {
            //     $formData['Primer_apellido_cliente'] = '';
            // }
            // if (!isset($formData['Segundo_apellido_cliente'])) {
            //     $formData['Segundo_apellido_cliente'] = '';
            // }
        }
        
        Log::info('ANTES DE FILL (optimizado):', ['formData' => $formData]);
        $this->form->fill($formData);
        Log::info('CreateGestionCliente - Formulario llenado en mount con datos API y estructura limpia:', ['formData' => $formData]);
    }

    public function handleDatosCliente($data)
    {
        // Este método ya no es necesario para llenar el formulario inicial, pero lo mantengo
        // por si se usa para otra lógica, aunque el evento no debería llegar aquí
        // después de la redirección.
        Log::info('CreateGestionCliente - handleDatosCliente llamado (debería ser infrecuente):', $data);

        // La lógica de llenado ahora está en mount.
    }

    private function getDepartamentoId(?string $nombreDepartamento): ?int
    {
        if (!$nombreDepartamento) {
            return null;
        }
        
        $departamento = ZDepartamentos::where('name_departamento', $nombreDepartamento)->first();
        Log::info('getDepartamentoId - Input:', ['nombre' => $nombreDepartamento, 'found_id' => $departamento ? $departamento->id : null]);
        return $departamento ? $departamento->id : null;
    }

    private function getMunicipioId(?string $nombreMunicipio): ?int
    {
        if (!$nombreMunicipio) {
            return null;
        }

        $municipio = ZMunicipios::where('name_municipio', $nombreMunicipio)->first();
        Log::info('getMunicipioId - Input:', ['nombre' => $nombreMunicipio, 'found_id' => $municipio ? $municipio->id : null]);
        return $municipio ? $municipio->id : null;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
         // Reforzar que el owner sea siempre el usuario logueado
        $data['user_id'] = auth()->id();
        return $data;
        
        // DEBUG: Log the incoming data before any mutation
        Log::debug('mutateFormDataBeforeCreate - Incoming data:', $data);

        Log::info('CreateGestionCliente: Iniciando mutateFormDataBeforeCreate', [
            'route' => Route::currentRouteName(),
            'data' => $data,
            'user' => auth()->user() ? auth()->user()->id : 'no user',
            'session' => session()->all()
        ]);

        // $data['ID_tipo_credito'] = 2; // Eliminamos la asignación forzada

        if (isset($data['detallesCliente']) && is_array($data['detallesCliente'])) {
            foreach ($data['detallesCliente'] as $key => $detalle) {
                // $data['detallesCliente'][$key]['id_tipo_credito'] = 2; // Eliminamos la asignación forzada
            }
        }

        // Comentar este bloque para evitar interferencia con el guardado de relaciones HasOne
        // if (isset($data['clientesContacto'][0])) {
        //     session(['trabajo_data' => [
        //         'empresa_labor' => $data['clientesContacto'][0]['empresa_labor'] ?? null,
        //         'num_empresa' => $data['clientesContacto'][0]['tel_empresa'] ?? null,
        //         'es_independiente' => $data['clientesContacto'][0]['es_independiente'] ?? 0,
        //     ]]);
        // }

        Log::info('CreateGestionCliente: Datos después de la mutación', [
            'ID_tipo_credito' => $data['ID_tipo_credito'] ?? 'N/A',
            'detallesCliente' => $data['detallesCliente'] ?? 'No data'
        ]);

        return $data;
        
    }


     protected function handleRecordCreation(array $data): Cliente
    {
        /** @var Cliente $record */
        $record = parent::handleRecordCreation($data);

        // Tu lógica para ID_Cliente_Nombre
        $clienteNombreCompleto = $record->clientesNombreCompleto()->first();
        if ($clienteNombreCompleto) {
            $record->ID_Cliente_Nombre = $clienteNombreCompleto->ID_Cliente_Nombre;
            $record->save();

            Log::info('CreateGestionCliente - ID_Cliente_Nombre guardado', [
                'id_cliente'        => $record->id_cliente,
                'ID_Cliente_Nombre' => $record->ID_Cliente_Nombre,
            ]);
        }

        return $record;
    }

      /**
     * Redirige tras crear, basándose en el canal de venta
     */
    protected function getRedirectUrl(): string
    {
        $canal = $this->record->ID_Canal_venta;

        return match ($canal) {
            1 => static::getResource()::getUrl('firma', ['record' => $this->record]),
            2 => static::getResource()::getUrl('token', ['record' => $this->record]),
            default => static::getResource()::getUrl('index'),
        };
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCancelFormAction(),
            $this->getCreateFormAction()->label('Crear'),
        ];
    }


    // Si necesitas un método para mapear códigos de API a tus tipos de documento
    // protected function mapCodigoApiToTipoDocumento(string $codigoApi): ?string
    // {
    //     return match($codigoApi) {
    //         '13' => 'CC',
    //         '12' => 'TI',
    //         // ... otros mapeos
    //         default => null,
    //     };
    // }

//inyectar id_cliente en gestion
protected function afterCreate(): void
{
    $cliente = $this->record->refresh();

    // 1) Gestion igual que antes
    $cliente->gestion()->firstOrCreate(
        ['id_cliente' => $cliente->id_cliente],
        ['id_cliente' => $cliente->id_cliente],
    );

    // 2) Sincronizar referencias_personales de forma tolerante
    try {
        if (! Schema::hasTable('referencias_personales')) {
            Log::warning('Pivot referencias_personales no existe; omito upsert', [
                'id_cliente' => $cliente->id_cliente,
            ]);
        } else {
            $idRef1 = null;
            $idRef2 = null;

            // PRIORIDAD 1: desde BD
            if (Schema::hasTable('referencia_personal1')) {
                $ref1 = \App\Models\ReferenciaPersonal1::query()
                    ->where('ID_Cliente', $cliente->id_cliente)
                    ->latest('ID_Referencia1')
                    ->first();
                $idRef1 = $ref1?->ID_Referencia1;
            }

            if (Schema::hasTable('referencia_personal2')) {
                $ref2 = \App\Models\ReferenciaPersonal2::query()
                    ->where('ID_Cliente', $cliente->id_cliente)
                    ->latest('ID_Referencia2')
                    ->first();
                $idRef2 = $ref2?->ID_Referencia2;
            }

            // PRIORIDAD 2: estado del form si BD no tiene nada
            if (! $idRef1 || ! $idRef2) {
                $data = $this->form->getState() ?? [];

                if (! $idRef1 && !empty($data['referenciasPersonales1'][0] ?? null)) {
                    $rf1   = $data['referenciasPersonales1'][0];
                    $idRef1 = $rf1['ID_Referencia1'] ?? ($rf1['id'] ?? null);
                }

                if (! $idRef2 && !empty($data['referenciasPersonales2'][0] ?? null)) {
                    $rf2   = $data['referenciasPersonales2'][0];
                    $idRef2 = $rf2['ID_Referencia2'] ?? ($rf2['id'] ?? null);
                }
            }

            Log::info('Sync pivot referencias_personales - IDs detectados', [
                'id_cliente'     => $cliente->id_cliente,
                'ID_Referencia1' => $idRef1,
                'ID_Referencia2' => $idRef2,
            ]);

            \App\Models\ReferenciasPersonales::updateOrCreate(
                ['id_cliente' => $cliente->id_cliente],
                [
                    'ID_Referencia1' => $idRef1,
                    'ID_Referencia2' => $idRef2,
                ]
            );
        }
    } catch (\Throwable $e) {
        Log::error('No se pudo sincronizar referencias_personales: '.$e->getMessage(), [
            'id_cliente' => $cliente->id_cliente,
        ]);
        // importante: NO hacemos return; el flujo sigue
    }

    // 3) Evento LIGERO solo para sonido + refresh tabla (siempre que haya cliente)
    try {
        event(new ClienteCreatedLight(
            clienteId: (int) $cliente->id_cliente,
            cedula: (string) ($cliente->cedula ?? ''),
            userId: auth()->id() ? (int) auth()->id() : null,
        ));

        Log::debug('✅ [afterCreate] ClienteCreatedLight disparado', [
            'cliente_id' => $cliente->id_cliente,
        ]);
    } catch (\Throwable $e) {
        Log::error('❌ [afterCreate] Error disparando ClienteCreatedLight: '.$e->getMessage(), [
            'cliente_id' => $cliente->id_cliente,
        ]);
    }


     $sucupoId = \App\Models\ZPlataformaCredito::where('plataforma', 'SUCUPO')->value('idplataforma');

        $detalles = $this->data['detallesCliente'] ?? [];
        $primero  = collect($detalles)->first();
        $idPlat   = $primero['idplataforma'] ?? null;

        logger()->debug('AFTER CREATE - gestion check', [
            'idPlat' => $idPlat,
            'sucupoId' => $sucupoId,
            'isSucupo' => (int) $idPlat === (int) $sucupoId,
        ]);

        // Si es SUCUPO: crear/actualizar gestion con ID_Estado_cr = 2
        if ((int) $idPlat === (int) $sucupoId) {
            $this->record->gestion()->updateOrCreate(
                [], // para hasOne funciona perfecto
                ['ID_Estado_cr' => 2]
            );

            logger()->debug('GESTION creada/actualizada', [
                'cliente_id' => $this->record->getKey(),
                'ID_Estado_cr' => 2,
            ]);
        } else {
            // Si NO es SUCUPO: como si no existiera
            // (opcional) borrar si por alguna razón ya había
            $this->record->gestion()->delete();

            logger()->debug('GESTION eliminada (no sucupo)', [
                'cliente_id' => $this->record->getKey(),
            ]);
        }
}



}
