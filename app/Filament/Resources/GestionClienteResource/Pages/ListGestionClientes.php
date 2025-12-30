<?php

namespace App\Filament\Resources\GestionClienteResource\Pages;

use App\Filament\Resources\GestionClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Redirect;
use App\Repositories\Interfaces\TercerosRepositoryInterface;
use App\Services\TercerosApiService;
use Filament\Notifications\Notification;
use Exception;
use App\Models\Cliente;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\GestionClienteResource\Widgets\GestionClienteStats;
use Filament\Forms\Components\Hidden;
use App\Services\RecompraServiceValSimilar;
use Illuminate\Support\Str;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Services\RecompraServiceValSimilar as R;
use App\Support\Filament\DocumentoHelper;
use App\Filament\Widgets\ChatWidget; //  IMPORTANTE
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage; //+
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ToggleButtons;

use Livewire\Attributes\On;


class ListGestionClientes extends ListRecords
{
    protected static string $resource = GestionClienteResource::class;

    // Refresca la tabla cuando llega el broadcast
    protected $listeners = ['echo:gestion-clientes,.ClienteUpdated' => '$refresh'];

    public string $esRecompra = '';

    /**
     * Widgets del header (mantengo los que ya tenías)
     */
    protected function getHeaderWidgets(): array
    {
        return [
            GestionClienteStats::class,
        ];
    }

    /**
     *  Muestra el widget flotante de Chat SOLO a socios
     */
    protected function getFooterWidgets(): array
    {
        return auth()->user()?->hasRole(['socio', 'asesor_agente','asesor comercial', 'super_admin'])
            ? [ChatWidget::class]
            : [];
    }

    /**
     * Acciones del header (tu buscador/flujo se conserva)
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('buscarPorCedula')
                ->label('Referenciar')
                ->modalHeading('Buscar Cliente por Cédula')
                ->form([

                    #---------------- NUEVO CAMPO DEPENDENCIAS ----------------#
                    Select::make('tipo_dependencia')
                        ->label('Dependencias')
                        ->placeholder('Seleccione una opción')
                        ->options(\App\Models\Dependencias::pluck('Nombre_Dependencia', 'Id_Dependencia')->toArray())
                        ->afterStateUpdated(function ($state) {
                            Notification::make()
                                ->title('Alerta')
                                ->body('En la siguiente pregunta elija si o no de acuerdo a la existencia del producto para verificar si está en tienda o llega por encargo.')
                                ->warning()
                                ->persistent() 
                                ->send();
                            \Log::info('dato_validado', ['estado_recompra' => $state]);
                            session(['tipo_dependencia' => $state]);
                        })
                        ->required()
                        ->live(),
                    #---------------------------------------------------------#

                    #---------------    NUEVO CAMPO PRODUCTO EXISTENTE --------------#
                    ToggleButtons::make('esta_el_producto')
                        ->label('¿El producto esta disponible en tu tienda?') 
                        ->options([
                            1 => 'Sí',
                            2 => 'No',
                            3 => 'Indiferente', 
                        ])
                        // Al abrir/hidratar el form, si no hay 1/2, mostrar 3
                        ->afterStateHydrated(function ($state, Set $set) {
                            $v = is_numeric($state) ? (int) $state : null;
                            $set('esta_el_producto', in_array($v, [1, 2], true) ? $v : 3);
                        })
                        // Si el usuario deja 3, no se persiste (va null)
                        ->dehydrateStateUsing(function ($state) {
                            $v = is_numeric($state) ? (int) $state : null;
                            return in_array($v, [1, 2], true) ? $v : null;
                        })
                        ->dehydrated(true)
                        // Mantén la sesión solo cuando sea 1/2; si es 3, borra
                        ->afterStateUpdated(function ($state) {
                            $v = is_numeric($state) ? (int) $state : null;
                            if (in_array($v, [1, 2], true)) {
                                session(['esta_el_producto' => $v]);
                            } else {
                                session()->forget('esta_el_producto');
                            }
                        })
                        // Validación: obliga a seleccionar Sí o No
                        ->required()
                        ->rule('in:1,2')
                        ->validationMessages([
                            'in'       => 'Seleccione "Sí" o "No" (no se permite "Indiferente").',
                            'required' => 'Este campo es obligatorio.',
                        ])
                        ->extraAttributes(['class' => 'tb-82cc0e'])
                        ->inline()
                        ->live(),






                    #--------------------------------------------------------------

                    Select::make('tipo_busqueda')
                        ->label('Tipo de Búsqueda')
                        ->required()
                        ->options([
                            'plataforma' => 'Plataforma',
                            'convenios'  => 'Convenios',
                            // 'agentes' => 'Agentes',
                        ]),
                    Select::make('id_tipo_documento')
                        ->label('Tipo de Documento')
                        ->required()
                        ->options(\App\Models\TipoDocumento::pluck('desc_identificacion', 'ID_Identificacion_Tributaria')->toArray())
                        ->searchable()
                        ->preload(),
                    Hidden::make('permite_si')
                        ->default(false)
                        ->dehydrated(false),
                    TextInput::make('cedula')
                        ->label('Cédula')
                        ->required()
                        ->numeric()
                        ->minLength(5)
                        ->maxLength(15)
                        ->live(debounce: 500)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                            if (blank($state)) {
                                $set('permite_si', false);
                                return;
                            }
                            $consulta_procesada = false;

                            if ($state !== null && $state !== '' && trim($state) !== '' && strlen($state) > 6) {
                                if ($consulta_procesada === false) {
                                    $apiService = app(TercerosApiService::class);
                                    $tipoDoc = $get('ID_Identificacion_Cliente') ?? 'CC';
                                    $codigoTipoDoc = self::mapTipoDocumentoToCodigo($tipoDoc);

                                    $exists = false;
                                    for ($i = 0; $i < 3; $i++) {
                                        $tercero = $apiService->buscarPorCedula($state, $codigoTipoDoc);
                                        if ($tercero && is_array($tercero) && ($tercero['nit'] ?? null) == $state) {
                                            $exists = true;
                                            break;
                                        } else {
                                            sleep(1);
                                        }
                                    }

                                    $consulta_procesada = true;
                                    $set('permite_si', $exists);

                                    Notification::make()
                                        ->title($exists ? 'Este cliente ya se encuentra registrado.' : 'Cliente nuevo.')
                                        ->{$exists ? 'success' : 'warning'}()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('Actualmente se encuentra una consulta pendiente, por favor espere...')
                                        ->warning()
                                        ->send();
                                }
                            } else {
                                Notification::make()
                                    ->title('Escriba al menos 7 dígitos para consultar.')
                                    ->warning()
                                    ->send();
                            }
                        }),
                    Select::make('es_recompra')
                        ->label('¿Es una recompra?')
                        ->required()
                        ->placeholder('Seleccione una opción')
                        ->options(function (Get $get) {
                            $permiteSi = (bool) ($get('permite_si') ?? false);

                            $q = \App\Models\ZSN::query()
                                ->whereRaw("UPPER(TRIM(Valor)) NOT IN ('M/A','N/A')");

                            if (!$permiteSi) {
                                $q->whereRaw("UPPER(TRIM(Valor)) <> 'SI'");
                            }

                            return $q->pluck('Valor', 'ID_SI_NO')->toArray();
                        })
                        ->afterStateUpdated(function ($state) {
                            Log::info('dato_validado', ["estado_recompra" => $state]);
                            session(['es_recompra' => $state]);
                        })
                        ->live()
                        ->dehydrated(true)

                ])
                ->action(function (array $data) {
                    try {
                         
                        //  VALIDACIÓN PREVIA: 2 últimos (del usuario) con estado 1/2 deben tener contImagenes_ID_SI_NO = 1
                        //  VALIDACIÓN PREVIA: bloquear SOLO si los 2 últimos (estado 1/2) NO tienen contImagenes_ID_SI_NO = 1
                        $userId = auth()->id();
                        $estaEnTienda = $data['esta_el_producto'];
                        $tipo_plataforma = $data['tipo_busqueda'];
                        $tipo_plataforma = $data['tipo_busqueda'] === 'plataforma' ? 1 : 2;


                        // Trae los id_cliente del usuario autenticado
                        $clienteIds = \App\Models\Cliente::query()
                            ->where('user_id', $userId)
                            ->pluck('id_cliente');

                        $detalles = DB::table('detalles_cliente as dc')
                            ->select('dc.ID_Detalles_cliente', 'dc.Id_Dependencia', 'dc.id_cliente')
                            ->whereIn('dc.id_cliente', $clienteIds) 
                            ->where('dc.Id_Dependencia', session('tipo_dependencia'))               
                            ->orderBy('dc.ID_Detalles_cliente')
                            ->get();

 

                        $soloIds = $detalles->pluck('id_cliente');   // Collection: [46372]
                        $soloIdsArray = $soloIds->all();                      // Array puro: [46372]




                        if ($soloIds->isNotEmpty()) {
                            //---------------- consultas ----------------------//
                            $cantidades = 0;

                            $dependencias = DB::table('dependencias')
                                ->select('Id_Dependencia', 'Nombre_Dependencia', 'Codigo_Dependencia', 'id_limite')
                                ->where('Id_Dependencia', session('tipo_dependencia'))
                                ->orderBy('Id_Dependencia', 'asc')
                                ->get();

                            foreach ($dependencias as $dep) {
                                $cantidad = DB::table('limites_documentacion')
                                    ->where('id_limite', $dep->id_limite)
                                    ->value('cantidad_maxima');   

                                $cantidades = $cantidad;
                            }
                         
                            

                            //----------- VALIDAR PLATAFORMA --------------//
                            // Si es plataforma (1), aplicar la validación
                            // Si es convenios/agentes (2), NO aplicar la validación
                            if ($estaEnTienda === 2 && $tipo_plataforma === 2) {//EL PTODUCTO NO ESTA EN TIENDA Y ES CONVENIOS/AGENTES
                                $cantMax = 0; // Desactivar validación cantidad ilimitada
                            }elseif ($estaEnTienda === 1 && $tipo_plataforma === 2) {//SI ESTA EN LA TIENDA Y ES CONVENIOS/AGENTES
                                $cantMax = $cantidades;//limite de la dependencia
                            }elseif ($estaEnTienda === 1 || $estaEnTienda === 2 && $tipo_plataforma === 1) {//EL PTODUCTO ESTA EN TIENDA Y ES PLATAFORMA
                                $cantMax = $cantidades;//limite de la dependencia
                            } 
                            //-----------------------------------------------//
                            if ($cantMax > 0) {

                                
                                $gestiones = DB::table('gestion')
                                    ->select('id_gestion', 'id_cliente', 'ID_Estado_cr', 'contImagenes_ID_SI_NO', 'created_at')
                                    ->whereIn('id_cliente', $soloIds->all())
                                    ->whereIn('ID_Estado_cr', [1, 2])   
                                    ->orderBy('id_cliente')
                                    ->orderByDesc('id_gestion')
                                    // ->limit($cantMax)  
                                    ->get();


                                $total   = $gestiones->count(); 
                                //contar la documentacion aprobada y pendiente
                                $documentacionApYpe = $gestiones->filter(fn ($g) =>(int)$g->ID_Estado_cr == 2 || (int)$g->ID_Estado_cr == 1)->count();
                                //contar la documentacion pendiente
                                $NoDocumentacionPendiente = $gestiones->filter(fn ($g) =>(int)$g->contImagenes_ID_SI_NO === 2)->count();

                                $bloquear = ($NoDocumentacionPendiente >= $cantMax) && ($documentacionApYpe >= $cantMax);

                                /*Notification::make()
                                    ->title('No puedes referenciar aún')
                                    ->body("NoDocumentacionPendiente: {$NoDocumentacionPendiente}, Cant.Max: {$cantMax}, Total Gestiones: {$total}, Documentacion Aprobada y Pendiente: {$documentacionApYpe}")
                                    ->danger()
                                    ->send();*/

                             

                                if ($bloquear) {
                                    Notification::make()
                                        ->title('No puedes referenciar aún')
                                        ->body("Tienes {$NoDocumentacionPendiente} registros recientes sin documentación completa.")
                                        ->danger()
                                        ->send();

                                    \Log::warning('Referenciar bloqueado por documentación incompleta', [
                                        'user_id'     => $userId ?? null,
                                        'cliente_ids' => $soloIds->all(),
                                        'cantidades'  => $NoDocumentacionPendiente,
                                        'gestiones'   => $gestiones,
                                    ]);

                                    return;
                                }
                            }
                            //------------------------------------------------------
                        }
                        
                        /** @var TercerosRepositoryInterface $apiRepository */
                        $apiRepository = app(TercerosRepositoryInterface::class);

                        $tipoDoc = $data['id_tipo_documento'] ?? ' ';
                        $codigoTipoDoc = match ($tipoDoc) {
                            'CC' => '13',
                            'TI' => '12',
                            'CE' => '22',
                            'PA' => '41',
                            'RC' => '11',
                            'NIT' => '31',
                            default => ' ',
                        };

                        Log::info('ListGestionClientes - Modal Action Data:', [
                            'tipo_documento' => $data['id_tipo_documento'] ?? null,
                            'cedula' => $data['cedula'] ?? null,
                            'codigo_tipo_doc' => $codigoTipoDoc,
                            'tipo_busqueda' => $data['tipo_busqueda'] ?? null,
                        ]);

                        $tercero = $apiRepository->buscarPorCedula($data['cedula'], $codigoTipoDoc);
                        Log::info('Resultado buscarPorCedula en modal:', ['tercero' => $tercero]);

                        if (in_array($data['tipo_busqueda'], ['convenios', 'agentes'])) {
                            if ($tercero) {
                                $url = $data['tipo_busqueda'] === 'agentes'
                                    ? '/admin/gestion-clientes/gestion-agentes?cedula=' . $data['cedula'] . '&tipoDocumento=' . $codigoTipoDoc
                                    : '/admin/gestion-clientes/gestion-convenios?cedula=' . $data['cedula'] . '&tipoDocumento=' . $codigoTipoDoc;

                                session(['convenio_buscado_data' => $tercero]);
                                session(['convenio_buscado_tipo_documento' => $tipoDoc]);

                                return Redirect::to($url);
                            } else {
                                Notification::make()
                                    ->title('No se encontró al Cliente')
                                    ->warning()
                                    ->send();

                                return Redirect::to(GestionClienteResource::getUrl('create-cliente-modal', [
                                    'cedula' => $data['cedula'],
                                    'tipoDocumento' => $codigoTipoDoc,
                                    'tipoBusqueda'  => $data['tipo_busqueda'] ?? 'plataforma',
                                ]));
                            }
                        }

                        // Búsqueda "plataforma"
                        if ($tercero) {
                            $svc = app(RecompraServiceValSimilar::class);
                            $valor = session('es_recompra');
                            $svc->procesarRecompraPorValorSimilar(
                                $valor,
                                $data['cedula'],
                                'clientes',
                                'cedula',
                                'La cédula ' . $data['cedula'] . ' ya se encuentra registrada.',
                                'like',
                                ['pattern' => 'contains', 'normalize' => true]
                            );

                            session(['cliente_buscado_data' => $tercero]);
                            session(['cliente_buscado_tipo_documento' => $tipoDoc]);

                            Notification::make()
                                ->title('Cliente encontrado, EXITOSAMENTE')
                                ->success()
                                ->send();

                            return Redirect::to(GestionClienteResource::getUrl('create'));
                        } else {
                            Notification::make()
                                ->title('No se encontraron datos para esta cédula')
                                ->warning()
                                ->send();

                            return Redirect::to(GestionClienteResource::getUrl('create-cliente-modal', [
                                'cedula' => $data['cedula'],
                                'tipoDocumento' => $codigoTipoDoc,
                            ]));
                        }
                    } catch (Exception $e) {
                        Notification::make()
                            ->title('Error al consultar los datos')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        Log::error('Error al consultar datos de terceros desde modal', [
                            'exception' => $e,
                            'message' => $e->getMessage(),
                            'cedula' => $data['cedula'] ?? 'N/A',
                        ]);
                    }
                }),
        ];
    }

    public static function mapTipoDocumentoToCodigo($tipo)
    {
        return match ($tipo) {
            'CC' => '13',
            'TI' => '12',
            'CE' => '22',
            'PA' => '41',
            'RC' => '11',
            'NIT' => '31',
            default => '13',
        };
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->with(['tipoCredito', 'tipoDocumento']);
    }

    /**
     * Agrego la acción de tabla "Chat" para socios.
     * Envía el id del cliente al ChatWidget y lo abre (sin modal).
     */
    protected function getTableActions(): array
    {
        $actions = [
            \Filament\Tables\Actions\EditAction::make()
                ->url(fn ($record) => $this->getEditUrlByTipoCredito($record)),
        ];

        if (auth()->user()?->hasRole('socio')) {
            $actions[] = TableAction::make('chat')
                ->label('Chat')
                ->icon('heroicon-o-chat-bubble-left')
                ->color('primary')
                ->visible(fn () => auth()->user()?->hasRole('socio')) // guarda por si Filament cachea
                ->action(function (Cliente $record) {
                    // Defensa extra: solo socios
                    if (!auth()->user()?->hasRole('socio')) {
                        Notification::make()
                            ->title('Acceso restringido')
                            ->body('Esta función está disponible solo para socios.')
                            ->warning()
                            ->send();
                        return;
                    }

                    // Livewire v3
                    if (method_exists($this, 'dispatch')) {
                        $this->dispatch('setClientId', $record->id_cliente)->to(ChatWidget::class);
                        $this->dispatch('openChat')->to(ChatWidget::class);
                        return;
                    }

                    // Livewire v2
                    if (method_exists($this, 'emitTo')) {
                        $this->emitTo(ChatWidget::class, 'setClientId', $record->id_cliente);
                        $this->emitTo(ChatWidget::class, 'openChat');
                    }
                });
        }

        return $actions;
    }

    protected function getEditUrlByTipoCredito($record)
    {
        switch ($record->ID_tipo_credito ?? $record->id_tipo_credito ?? null) {
            case 1:
                return \App\Filament\Resources\GestionClienteResource::getUrl('edit-modal', ['record' => $record->id_cliente ?? $record->id]);
            case 2:
                return \App\Filament\Resources\GestionClienteResource::getUrl('edit-convenio', ['record' => $record->id_cliente ?? $record->id]);
            case 3:
                return \App\Filament\Resources\GestionClienteResource::getUrl('edit-agente', ['record' => $record->id_cliente ?? $record->id]);
            default:
                return \App\Filament\Resources\GestionClienteResource::getUrl('edit-modal', ['record' => $record->id_cliente ?? $record->id]);
        }
    }
//
//se comenta proque no es necesesario que se refresque al actualizar el estado de credito en la tabla del asesor , consume muchas cpu. 
//   protected function getListeners(): array
//   {
//       // Heredas listeners de Filament (refresh, etc)
//       $listeners = parent::getListeners();
//
//       // Livewire escuchando el broadcast privado:
//       $listeners["echo-private:App.Models.User." . auth()->id() . ",ClienteUpdated"]
//           = 'handleClienteUpdated';
//
//       return $listeners;
//   }
//
//   public function handleClienteUpdated(array $payload): void
//   {
//       // Si quieres, filtrá por cliente:
//       // if (($payload['cliente']['user_id'] ?? null) !== auth()->id()) return;
//
//       // Esto dispara el 'refresh' que la tabla ya escucha internamente
//       $this->dispatch('refresh');
//   }

  //   #[On('estado-credito-actualizado')]
  // public function onEstadoCreditoActualizado($payload = null): void
  // {
  //     \Log::debug(' ListClientes recibió estado-credito-actualizado', [
  //         'payload' => $payload,
  //     ]);

  //     if (!is_array($payload)) {
  //         return;
  //     }

  //     $clienteId = (int)($payload['clienteId'] ?? 0);
  //     $estadoId  = (int)($payload['estadoId'] ?? 0);
  //     $texto     = (string)($payload['texto'] ?? '');

  //     \Log::debug(' Procesando estado-credito-actualizado', [
  //         'cliente_id' => $clienteId,
  //         'estado_id'  => $estadoId,
  //         'texto'      => $texto,
  //     ]);

  //     if ($clienteId <= 0) {
  //         return;
  //     }

  //     // Aquí refrescas la tabla de Filament
  //     // Opción A: refresh completo del componente
  //     $this->dispatch('$refresh');

  //     // Opción B (si usas InteractsWithTable y quieres ser explícito):
  //     // if (method_exists($this, 'resetTable')) {
  //     //     $this->resetTable();
  //     // }
  // }
}
