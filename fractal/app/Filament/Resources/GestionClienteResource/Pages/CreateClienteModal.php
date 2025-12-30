<?php

namespace App\Filament\Resources\GestionClienteResource\Pages;

use Exception;
use App\Filament\Resources\GestionClienteResource;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Log;
use App\Models\ZDepartamentos;
use App\Models\ZMunicipios;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Services\TercerosApiService;
use Illuminate\Support\Carbon;
use App\Models\TipoDocumento;

/**
 * Página personalizada (no CRUD estándar) de Filament
 * para crear un cliente mediante un modal y enviar la data a un servicio externo (Yeminus).
 */
class CreateClienteModal extends Page
{
    // Asocia esta página a GestionClienteResource (navegación/URLs)
    protected static string $resource = GestionClienteResource::class;

    // Vista Blade que renderiza la página (modal/formulario)
    protected static string $view = 'filament.resources.gestion-cliente-resource.pages.create-cliente-modal';

    // ==== Propiedades públicas (Livewire) que almacenan estado del formulario / URL ====
    public $cedula;
    public $tipoDocumento;
    public $nit;
    public $codigoInterno;
    public $nombre1;
    public $nombre2;
    public $apellido1;
    public $apellido2;
    public $ciudadExpedicionCedula;
    public $direccion;
    public $codigoCiudad;
    public $barrio;
    public $celular;
    public $telefono1;
    public $email;
    public $clasificacion;
    public $tipoNit;
    public $clase;
    public $nombreComercial;
    public $regimen;
    public $agenteRetenedor;
    public $autoretenedor;
    public $naturalezaJuridica;
    public $residencia_id_departamento; // Departamento (residencia)
    public $fecha_nac;                  // Fecha de nacimiento
    public $fecha_expedicion;           // Fecha expedición documento
    public $ID_Identificacion_Cliente;  // Tipo de identificación (código)
    public $tipoBusqueda;               // Flujo/escenario de navegación posterior

    // Flag para evitar doble submit (doble clic)
    public bool $isCreating = false;

    /**
     * Hook de montaje:
     * - Lee parámetros de la URL (tipo de documento y cédula).
     * - Define valores por defecto y llena el form.
     * - Ajusta tipoNit según tipo de documento.
     */
    public function mount(): void
    {
        $tipoDocumentoUrl = request()->query('id_tipo_documento');
        $cedulaUrl        = request()->query('cedula');

        // Tipo de búsqueda (flujo de redirección posterior): plataforma | convenios | agentes
        $this->tipoBusqueda  = request()->query('tipoBusqueda') ?? 'plataforma';
        $this->tipoDocumento = $tipoDocumentoUrl;
        $this->cedula        = $cedulaUrl;

        // Valores iniciales para el formulario (defaults y campos fijos)
        $initialData = [
            'ID_Identificacion_Cliente' => $tipoDocumentoUrl,
            'nit'               => $cedulaUrl,
            'codigoInterno'     => $cedulaUrl,
            'clasificacion'     => '02', // Cliente
            'clase'             => 'C',  // Cliente
            'regimen'           => '01', // Común (si aplica)
            'naturalezaJuridica'=> 'N',  // Persona Natural (si aplica)
            'agenteRetenedor'   => false,
            'autoretenedor'     => false,
            'permitirTerceroDuplicado' => false,
            'idRegion'          => 1,
            'controlCupoCredito'=> 0,
            'diasEntrega'       => 0,
            'facturarAlcantarillado' => 'S',
            'fecha_expedicion'  => null,
            'fecha_nac'         => null,
        ];

        // Rellena el form con los defaults
        $this->form->fill($initialData);

        // Ajusta tipoNit según el tipo de identificación pre-cargado
        if ($tipoDocumentoUrl !== null) {
            if ($tipoDocumentoUrl === '13') {
                $this->tipoNit = '01'; // Persona Natural
            } elseif ($tipoDocumentoUrl === '31') {
                $this->tipoNit = '02'; // Persona Jurídica
            }
        }
    }

    /**
     * Define el esquema del formulario (secciones, grids, inputs),
     * reglas, dependencias reactivas y formatos.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // ====== Sección: Datos Básicos ======
                Section::make('Datos Básicos')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('ID_Identificacion_Cliente')
                                ->label('Tipo de Identificación')
                                ->required()
                                ->options(\App\Models\TipoDocumento::pluck('desc_identificacion', 'ID_Identificacion_Tributaria'))
                                ->default($this->tipoDocumento)   // valor desde URL
                                ->live()
                                ->dehydrateStateUsing(fn ($state) => (int) $state) // guarda como entero
                                ->afterStateUpdated(function ($state, Set $set) {
                                    // Ajusta 'tipoNit' automáticamente según el tipo de identificación
                                    if ($state === '13') {
                                        $set('tipoNit', '01'); // Persona Natural
                                    } elseif ($state === '31') {
                                        $set('tipoNit', '02'); // Persona Jurídica
                                    }
                                }),

                            TextInput::make('nit')
                                ->label('Número de Identificación')
                                ->required()
                                ->readonly()  // viene de la URL, no editable
                                ->numeric(),

                            TextInput::make('codigoInterno')
                                ->label('Código Interno')
                                ->required()
                                ->hidden(),   // oculto en UI (se manda en payload)
                        ]),
                    ]),

                // ====== Sección: Información Personal ======
                Section::make('Información Personal')
                    ->schema([
                        Grid::make(2)->schema([
                            // Campos de nombres/apellidos: convierten a mayúsculas y actualizan nombreComercial
                            TextInput::make('nombre1')
                                ->label('Primer Nombre')
                                ->required()
                                ->maxLength(50)
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set) =>
                                    $set('nombreComercial', strtoupper(implode(' ', array_filter([$get('nombre1'), $get('nombre2'), $get('apellido1'), $get('apellido2')]))))
                                )
                                ->extraInputAttributes(['oninput' => 'this.value = this.value.toUpperCase()']),

                            TextInput::make('nombre2')
                                ->label('Segundo Nombre')
                                ->maxLength(50)
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set) =>
                                    $set('nombreComercial', strtoupper(implode(' ', array_filter([$get('nombre1'), $get('nombre2'), $get('apellido1'), $get('apellido2')]))))
                                )
                                ->extraInputAttributes(['oninput' => 'this.value = this.value.toUpperCase()']),

                            TextInput::make('apellido1')
                                ->label('Primer Apellido')
                                ->required()
                                ->maxLength(50)
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set) =>
                                    $set('nombreComercial', strtoupper(implode(' ', array_filter([$get('nombre1'), $get('nombre2'), $get('apellido1'), $get('apellido2')]))))
                                )
                                ->extraInputAttributes(['oninput' => 'this.value = this.value.toUpperCase()']),

                            TextInput::make('apellido2')
                                ->label('Segundo Apellido')
                                ->maxLength(50)
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set) =>
                                    $set('nombreComercial', strtoupper(implode(' ', array_filter([$get('nombre1'), $get('nombre2'), $get('apellido1'), $get('apellido2')]))))
                                )
                                ->extraInputAttributes(['oninput' => 'this.value = this.value.toUpperCase()']),

                            DatePicker::make('fecha_nac')
                                ->label('Fecha de Nacimiento')
                                ->format('Y-m-d')        // formato de guardado
                                ->displayFormat('d/m/Y') // formato visual
                                ->maxDate(Carbon::yesterday())
                                ->rule('before:today')   // valida servidor
                                ->required(),
                        ]),
                    ]),

                // ====== Sección: Información de Ubicación ======
                Section::make('Información de Ubicación')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('direccion')
                                ->label('Dirección')
                                ->required()
                                ->minLength(5)
                                ->maxLength(100)
                                ->extraInputAttributes(['oninput' => 'this.value = this.value.toUpperCase()']),

                            DatePicker::make('fecha_expedicion')
                                ->label('Fecha de Expedición')
                                ->format('Y-m-d')
                                ->displayFormat('d/m/Y')
                                ->maxDate(Carbon::yesterday())
                                ->rule('before:today')
                                ->required(),

                            // Departamento: al cambiar borra municipio y loguea
                            Select::make('residencia_id_departamento')
                                ->label('Departamento de Residencia')
                                ->required()
                                ->options(ZDepartamentos::all()->pluck('name_departamento', 'id'))
                                ->searchable()
                                ->preload()
                                ->reactive()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    Log::info('Departamento seleccionado:', ['departamento_id' => $state]);
                                    $set('codigoCiudad', null);
                                }),

                            // Municipios dependientes del departamento elegido
                            Select::make('codigoCiudad')
                                ->label('Municipio de Residencia')
                                ->required()
                                ->options(function (Get $get) {
                                    $departamentoId = $get('residencia_id_departamento');
                                    Log::info('Departamento seleccionado para municipios:', ['departamento_id' => $departamentoId]);
                                    if ($departamentoId) {
                                        $municipios = ZMunicipios::where('departamento_id', $departamentoId)
                                            ->pluck('name_municipio', 'id');
                                        Log::info('Municipios cargados:', ['municipios' => $municipios]);
                                        return $municipios;
                                    }
                                    return [];
                                })
                                ->searchable()
                                ->preload(),

                            TextInput::make('barrio')
                                ->label('Barrio')
                                ->maxLength(50)
                                ->extraInputAttributes(['oninput' => 'this.value = this.value.toUpperCase()']),
                        ]),
                    ]),

                // ====== Sección: Información de Contacto ======
                Section::make('Información de Contacto')
                    ->schema([
                        Grid::make(2)->schema([
                            // Celular: fuerza 10 dígitos en frontend + validación básica
                            TextInput::make('celular')
                                ->label('Celular')
                                ->tel()
                                ->required()
                                ->minLength(10)
                                ->maxLength(10)
                                ->validationAttribute('teléfono alternativo')
                                ->helperText('Debe tener exactamente 10 dígitos.')
                                ->extraInputAttributes([
                                    'inputmode' => 'numeric',
                                    'pattern'   => '\d{10}',
                                    'oninput'   => "this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)",
                                ])
                                ->live(onBlur: true),

                            // Teléfono fijo con la misma restricción de 10 dígitos
                            TextInput::make('telefono1')
                                ->label('Teléfono Alterno')
                                ->tel()
                                ->required()
                                ->minLength(10)
                                ->maxLength(10)
                                ->validationAttribute('teléfono alternativo')
                                ->helperText('Debe tener exactamente 10 dígitos.')
                                ->extraInputAttributes([
                                    'inputmode' => 'numeric',
                                    'pattern'   => '\d{10}',
                                    'oninput'   => "this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)",
                                ])
                                ->live(onBlur: true),

                            TextInput::make('email')
                                ->label('Correo Electrónico')
                                ->email()
                                ->maxLength(100)
                                ->required(),
                        ]),
                    ]),

                // ====== Sección: Información Adicional (oculta en UI) ======
                Section::make('Información Adicional')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('clasificacion')
                                ->label('Clasificación')
                                ->required()
                                ->options(['02' => 'Cliente'])
                                ->default('02'),

                            Select::make('tipoNit')
                                ->label('Tipo NIT')
                                ->required()
                                ->options([
                                    '01' => 'Persona Natural',
                                    '02' => 'Persona Jurídica',
                                ]),

                            Select::make('clase')
                                ->label('Clase')
                                ->required()
                                ->options([
                                    'C'  => 'Cliente',
                                    'P'  => 'Proveedor',
                                    'CP' => 'Cliente y Proveedor',
                                ])
                                ->default('C'),

                            TextInput::make('nombreComercial')
                                ->label('Nombre Comercial')
                                ->hidden()
                                ->maxLength(100),
                        ]),
                    ])->hidden(),

                // ====== Sección: Configuración Tributaria (oculta en UI) ======
                Section::make('Configuración Tributaria')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('regimen')
                                ->label('Régimen')
                                ->required()
                                ->options([
                                    '01' => 'Común',
                                    '02' => 'Simplificado',
                                    '03' => 'Gran Contribuyente',
                                ])
                                ->default('01'),

                            Checkbox::make('agenteRetenedor')
                                ->label('Agente Retenedor')
                                ->default(false),

                            Checkbox::make('autoretenedor')
                                ->label('Autorretenedor')
                                ->default(false),

                            Select::make('naturalezaJuridica')
                                ->label('Naturaleza Jurídica')
                                ->required()
                                ->options([
                                    'N' => 'Persona Natural',
                                    'J' => 'Persona Jurídica',
                                ])
                                ->default('N'),
                        ]),
                    ])->hidden(),
            ]);
    }

    /**
     * Acción principal del modal: construir payload, llamar API (TercerosApiService),
     * manejar respuesta/errores, setear sesión, redirigir según flujo y evitar doble envío.
     */
    public function create()
    {
        // Evita doble envío si ya se está procesando
        if ($this->isCreating) {
            return;
        }
        $this->isCreating = true;

        // Estado actual del formulario
        $data = $this->form->getState();
        Log::info('Datos del formulario en CreateClienteModal:', $data);
        Log::info('Estado del formulario en CreateClienteModal:', $this->form->getState());

        // Carga municipio de residencia (para mapear código DANE/código interno)
        $municipioResidencia = !empty($data['codigoCiudad'])
            ? ZMunicipios::find($data['codigoCiudad'])
            : null;

        // Carga municipio de expedición (si se diligenció)
        $municipioExpedicion = !empty($data['ciudadExpedicionCedula'])
            ? ZMunicipios::find($data['ciudadExpedicionCedula'])
            : null;

        Log::debug('CreateClienteModal - Valor de ID_Identificacion_Cliente antes de API:', [
            'ID_Identificacion_Cliente' => $data['ID_Identificacion_Cliente'] ?? 'null',
        ]);

        // ==== Mapeo de datos del form al payload esperado por la API externa (Yeminus) ====
        $datosApi = [
            "tipoIdentificacionTributaria" => (string)($data['ID_Identificacion_Cliente'] ?? ''),
            "nit"                => (string)($data['nit'] ?? ''),
            "codigoInterno"      => (string)($data['nit'] ?? ''), // usa nit como código interno
            "nombre1"            => $data['nombre1'] ?? '',
            "nombre2"            => $data['nombre2'] ?? '',
            "apellido1"          => $data['apellido1'] ?? '',
            "apellido2"          => $data['apellido2'] ?? '',
            "ciudadExpecidionCedula" => $municipioExpedicion ? $municipioExpedicion->code_municipio : '',
            "descripcion"        => strtoupper(implode(' ', array_filter([$data['nombre1'] ?? '', $data['nombre2'] ?? '', $data['apellido1'] ?? '', $data['apellido2'] ?? '']))),
            "nombreComercial"    => strtoupper(implode(' ', array_filter([$data['nombre1'] ?? '', $data['nombre2'] ?? '', $data['apellido1'] ?? '', $data['apellido2'] ?? '']))),
            "clasificacion"      => $data['clasificacion'] ?? '',
            "tipoNit"            => $data['tipoNit'] ?? '',
            "clase"              => $data['clase'] ?? 'C',
            "observacionGeneral" => '',
            "direccion"          => $data['direccion'] ?? '',
            "codigoCiudad"       => $municipioResidencia ? $municipioResidencia->id : '',
            "barrio"             => $data['barrio'] ?? '',
            "celular"            => $data['celular'] ?? '',
            "telefono1"          => $data['telefono1'] ?? '',
            "email"              => $data['email'] ?? '',
            "fechaNacimiento"    => isset($data['fecha_nac']) ? \Illuminate\Support\Carbon::parse($data['fecha_nac'])->format('Y-m-d\TH:i:s') : null,
            "sexo"               => '',
            "idRegion"           => 1,
            "controlCupoCredito" => 0,
            "regimen"            => $data['regimen'] ?? '',
            "emailFacturacionElectronica" => $data['email'] ?? '',
            "agenteRetenedor"    => ($data['agenteRetenedor'] ?? false) ? 'S' : 'N',
            "autoretenedor"      => ($data['autoretenedor'] ?? false) ? 'S' : 'N',
            "naturalezaJuridica" => $data['naturalezaJuridica'] ?? 'N',
            "permitirTerceroDuplicado" => false,
            "fechaExpedicionCedula" => isset($data['fecha_expedicion']) ? \Illuminate\Support\Carbon::parse($data['fecha_expedicion'])->format('Y-m-d\TH:i:s') : null,
            "fechaFinalMetrica"  => now()->timezone('UTC')->format('Y-m-d\TH:i:s.v\Z'),
            "usuarioResponsable" => 'YEMINUS',
            "diasEntrega"        => 0,
            "facturarAlcantarillado" => 'S',

            // → Campos adicionales no presentes en el formulario (se envían null o default)
            "codigoGS1" => null,
            "digitoChequeo" => null,
            "telefono2" => '',
            "tipoNit" => $data['tipoNit'] ?? '',
            "descripcionTipoNit" => '',
            "clasificacion" => $data['clasificacion'] ?? '',
            "formaPago" => null,
            "descripcionSector" => null,
            "descripcionClasifTercero" => '',
            "descripcionUnidadesNegocio" => null,
            "descripcionFormaPago" => null,
            "descripcionVendedor" => null,
            "descripcionZona" => '',
            "afiliacion" => null,
            "fechaAfiliacion" => null,
            "matricula" => null,
            "fechaMatricula" => null,
            "fechaModificacion" => null,
            "usuarioModificacion" => null,
            "usuarioCreacion" => 'YEMINUS',
            "fechaUltimaCompra" => null,
            "hobby" => null,
            "patron" => null,
            "fechaReactivar" => null,
            "fechaInactivo" => null,
            "observacionesInactivo" => null,
            "fechaCualificacion" => null,
            "informacionCertificadoCalidad" => null,
            "estado" => 'A',
            "paginaWeb" => null,
            "cupoCredito" => null,
            "mensajeAlerta" => null,
            "ocupacion" => null,
            "descripcionCampana" => null,
            "regimen" => $data['regimen'] ?? '',
            "descripcionActividadEconomica" => null,
            "centroDeCosto" => null,
            "naturalezaJuridica" => $data['naturalezaJuridica'] ?? 'N',
            "usuarioResponsable" => 'YEMINUS',
            "agenteRetenedor" => ($data['agenteRetenedor'] ?? false) ? 'S' : 'N',
        ];

        Log::info('Valor de codigoCiudad antes de enviar a la API:', [
            'codigoCiudad' => $data['codigoCiudad'],
            'municipioResidencia' => $municipioResidencia
        ]);

        try {
            // Instancia del servicio (IoC container)
            $tercerosApiService = app(TercerosApiService::class);

            // Llama a la API externa para crear el tercero
            $respuestaApi = $tercerosApiService->crearTercero($datosApi);

            // ===== Manejo de respuesta exitosa =====
            if (isset($respuestaApi['infoOperacion']['esExitosa']) && $respuestaApi['infoOperacion']['esExitosa'] === true) {
                Notification::make()
                    ->title('Cliente creado exitosamente en Yeminus')
                    ->success()
                    ->send();

                // Prepara datos para reconsultar el tercero recién creado (para sesión)
                $tipoDoc = $this->tipoDocumento ?: ($data['ID_Identificacion_Cliente'] ?? '13');
                $doc     = $data['nit'] ?? $this->cedula;

                /** @var \App\Repositories\Interfaces\TercerosRepositoryInterface $apiRepository */
                $apiRepository = app(\App\Repositories\Interfaces\TercerosRepositoryInterface::class);

                // Traducción opcional del tipo doc a "letra" (para la UI)
                $tipoDocNumero = (string) ($this->tipoDocumento ?: ($data['ID_Identificacion_Cliente'] ?? '13'));
                $tipoDocLetra  = match ($tipoDocNumero) {
                    '12' => 'TI',
                    '22' => 'CE',
                    '41' => 'PA',
                    '11' => 'RC',
                    '31' => 'NIT',
                    default => '', // 13 → CC
                };

                $doc = (string) ($data['nit'] ?? $this->cedula);

                // Pequeño retry por consistencia eventual de la API (hasta 3 intentos)
                $tercero = null;
                for ($i = 0; $i < 3; $i++) {
                    try {
                        $tercero = $apiRepository->buscarPorCedula($doc, $tipoDocNumero);
                        if ($tercero) break;
                        usleep(200000); // 200ms
                    } catch (\Throwable $e) {
                        \Log::warning('Reintento buscarPorCedula tras crear tercero', [
                            'intento' => $i + 1,
                            'doc'     => $doc,
                            'tipoDoc' => $tipoDocNumero,
                            'error'   => $e->getMessage(),
                        ]);
                        usleep(200000);
                    }
                }

                // Setea en sesión los mismos datos que usa tu flujo de búsqueda
                if (in_array($this->tipoBusqueda, ['convenios', 'agentes'], true)) {
                    session(['convenio_buscado_data' => $tercero]);
                    session(['convenio_buscado_tipo_documento' => $tipoDocLetra]);
                } else {
                    session(['cliente_buscado_data' => $tercero]);
                    session(['cliente_buscado_tipo_documento' => $tipoDocLetra]);
                }

                // Redirección según el flujo elegido en el modal (tipoBusqueda)
                switch ($this->tipoBusqueda) {
                    case 'agentes':
                        return $this->redirect(
                            GestionClienteResource::getUrl('gestion-agentes', [
                                'cedula'        => $doc,
                                'tipoDocumento' => $tipoDoc,
                            ])
                        );

                    case 'convenios':
                        return $this->redirect(
                            GestionClienteResource::getUrl('gestion-convenios', [
                                'cedula'        => $doc,
                                'tipoDocumento' => $tipoDoc,
                            ])
                        );

                    case 'plataforma':
                    default:
                        return $this->redirect(GestionClienteResource::getUrl('create'));
                }
            }

            // ===== Manejo de respuesta con error de la API =====
            $errorMessage = $respuestaApi['mensaje'] ?? 'Error desconocido al crear cliente en Yeminus';
            if (isset($respuestaApi['infoOperacion']['listaErrores']) && is_array($respuestaApi['infoOperacion']['listaErrores'])) {
                $errorMessage .= '. Errores: ' . implode('; ', $respuestaApi['infoOperacion']['listaErrores']);
            }
            if (isset($respuestaApi['datos']['mensaje'])) {
                $errorMessage .= '. Mensaje datos: ' . $respuestaApi['datos']['mensaje'];
            }

            Log::error('Error al crear cliente en Yeminus:', ['respuesta' => $respuestaApi, 'datos_enviados' => $datosApi]);

            Notification::make()
                ->title('Error al crear cliente')
                ->body($errorMessage)
                ->danger()
                ->send();

        } catch (Exception $e) {
            // Errores inesperados (excepciones)
            Log::error('Excepción al llamar al servicio TercerosApiService:', ['exception' => $e]);
            Notification::make()
                ->title('Error en el sistema')
                ->body('Ocurrió un error inesperado al intentar crear el cliente.')
                ->danger()
                ->send();
        } finally {
            // Siempre restablece el flag para permitir nuevos intentos
            $this->isCreating = false;
        }
    }

    // Render estándar (usa la vista configurada en $view)
    public function render(): \Illuminate\Contracts\View\View
    {
        return parent::render();
    }

    /**
     * Mutación previa a crear (si llegara a usarse create de Resource).
     * Aquí fuerza el user_id al usuario autenticado y deja logs.
     * (En esta página personalizada es más una salvaguarda que un flujo real.)
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Forzar propietario al usuario logueado (seguridad)
        $data['user_id'] = auth()->id();

        // Si vienen detalles anidados, se respetan sin mutar
        if (isset($data['detallesCliente']) && is_array($data['detallesCliente'])) {
            foreach ($data['detallesCliente'] as $key => $detalle) {
                // sin cambios
            }
        }

        \Illuminate\Support\Facades\Log::info('CreateClienteModal → propietario asignado', [
            'user_id'         => $data['user_id'] ?? 'N/A',
            'ID_tipo_credito' => $data['ID_tipo_credito'] ?? 'N/A',
        ]);

        return $data;
    }
}
