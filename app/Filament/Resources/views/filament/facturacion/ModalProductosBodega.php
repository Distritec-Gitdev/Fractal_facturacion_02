<?php


namespace App\Filament\Resources\FacturacionResource\Forms;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ProductosController;

  

class ModalProductosBodega
{
    public static function generarTablaProductos(
        string $codigoBodega = null,
        ?string $codigo = null,
        ?string $nombre = null,
        ?string $marca = null,
        ?string $cedula = null,
        ?array $array_codigos = null,
        ?array $array_bodega = null,
        int $page = 1,
        int $perPage = 10
    ): string {
        $busquedaCodigo = $codigo;
        $busquedaNombre = $nombre;
        $busquedaMarca  = $marca;

        $cedulaCliente = $cedula ?? Session::get('cedula') ?? Session::get('nuevo_nit');
        $nit           = Session::get('nuevo_nit');

        Log::info('Generando tabla productos con:', [
            'bodega' => $codigoBodega,
            'codigo' => $busquedaCodigo,
            'nombre' => $busquedaNombre,
            'marca'  => $busquedaMarca,
            'cedula' => $cedulaCliente,
            'nit'    => $nit,
            'page'   => $page,
            'array_codigos' =>$array_codigos,
            'array_bodega' => $array_bodega,
        ]);

        try {
            $request = Request::create('', 'GET', [
                'nit'           => $nit,
                'codigo_bodega' => $codigoBodega,
            ]);

            $controller = app(ProductosController::class);
            $response   = $controller->productosBodegas($request);


            if (! is_array($response)) {
                throw new \RuntimeException('Se esperaba un array de productos, se recibió: ' . gettype($response));
            }

            $productos = [];


            foreach ($response as $item) {
                $stock = floatval($item['cantidad'] ?? 0);

               
                if ($stock <= 0) {
                    continue;
                }

                $codigoItem = $item['producto']    ?? '';
                $nombreItem = $item['descripcion'] ?? '';
                $marcaItem  = $item['referencia']  ?? '';


                if (is_array($array_codigos) || is_array($array_bodega)) {
                    if (in_array($codigoItem, $array_codigos, true) && in_array($codigoBodega, $array_bodega, true)) {
                        continue;
                    }
                }

                $precio = floatval(
                    $item['precio1']      ??
                    $item['precio2']      ??
                    $item['precio3']      ??
                    $item['precioUltimo'] ??
                    0
                );

                if ($busquedaCodigo && stripos($codigoItem, $busquedaCodigo) === false) {
                    continue;
                }

                if ($busquedaNombre && stripos($nombreItem, $busquedaNombre) === false) {
                    continue;
                }

                if ($busquedaMarca && stripos($marcaItem, $busquedaMarca) === false) {
                    continue;
                }

                $productos[] = [
                    'codigo'   => $codigoItem,
                    'nombre'   => $nombreItem,
                    'marca'    => $marcaItem,
                    'precio'   => $precio,
                    'cantidad' => $stock,
                ];
            }

            // Ordenar por cantidad (mayor a menor)
            usort($productos, function ($a, $b) {
                return $b['cantidad'] <=> $a['cantidad'];
            });

            if (empty($productos)) {
                return '
                <div class="text-center py-8">
                    <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">No se encontraron productos</p>
                </div>';
            }

            $totalProductos = count($productos);
            $totalPages     = max(1, (int) ceil($totalProductos / $perPage));
            $page           = max(1, min($page, $totalPages));
        } catch (\Exception $e) {
            Log::error('Error consultando API de productos: ' . $e->getMessage());

            return '
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="mt-2 text-sm text-red-600">Error al consultar productos</p>
            </div>';
        }

        $modalId = 'productos-table-' . uniqid();

        $html = '
        <style>
            .fi-modal-close-btn,
            button[aria-label="Close"],
            .fi-modal-header button[type="button"]:first-child,
            [x-on\\:click\\.stop="close()"] {
                display: none !important;
            }
            
            [x-on\\:click="close()"] {
                pointer-events: none !important;
            }
            
            .fi-modal-window {
                pointer-events: auto !important;
            }

            .btn-loading {
                position: relative;
                pointer-events: none;
                opacity: 0.7;
            }



            .btn-loading {
                position: relative;
                pointer-events: none;
                opacity: 0.7;
            }
            .btn-loading .btn-text,
            .btn-loading .btn-icon {
                opacity: 0;
            }
            .btn-loading::before {
                content: "";
                position: absolute;
                top: 50%;
                left: 50%;
                width: 18px;
                height: 18px;
                margin-top: -9px;
                margin-left: -9px;
                border: 2.5px solid rgba(255, 255, 255, 0.25);
                border-radius: 50%;
                border-top-color: #ffffff;
                animation: spinner 0.7s linear infinite;
            }
            @keyframes spinner {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .table-row-hover {
                transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                cursor: pointer;
            }
            .table-row-hover:hover {
                background: rgba(130, 204, 14, 0.1);
                transform: translateX(2px);
                box-shadow: 0 2px 8px -2px rgba(130, 204, 14, 0.3);
            }
            .dark .table-row-hover:hover {
                background: rgba(130, 204, 14, 0.15);
                box-shadow: 0 2px 12px -2px rgba(130, 204, 14, 0.4);
            }
            .search-input {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            .search-input:focus {
                transform: translateY(-2px);
                box-shadow: 0 8px 16px -4px rgba(130, 204, 14, 0.3);
            }
            .dark .search-input:focus {
                box-shadow: 0 8px 20px -4px rgba(130, 204, 14, 0.5);
            }
            .badge-count {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 24px;
                height: 24px;
                padding: 0 8px;
                font-size: 12px;
                font-weight: 700;
                border-radius: 12px;
                background: #82cc0e;
                color: white;
                box-shadow: 0 4px 12px rgba(130, 204, 14, 0.4);
                animation: pulse-badge 2s ease-in-out infinite;
            }
            @keyframes pulse-badge {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.05); }
            }
            .btn-primary {
                background: #82cc0e;
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }
            .btn-primary::before {
                content: "";
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
                transition: left 0.5s ease;
            }
            .btn-primary:hover::before {
                left: 100%;
            }
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 20px -4px rgba(130, 204, 14, 0.6);
            }
            .btn-secondary {
                transition: all 0.3s ease;
            }
            .btn-secondary:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.15);
            }
            .dark .btn-secondary:hover {
                box-shadow: 0 4px 16px -2px rgba(255, 255, 255, 0.1);
            }
            .header-gradient {
                background: linear-gradient(135deg, #ffffff 0%, #f5f5f5 100%);
                position: relative;
                overflow: hidden;
            }
            .dark .header-gradient {
                background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
            }
            .header-gradient::before {
                content: "";
                position: absolute;
                top: -50%;
                right: -50%;
                width: 200%;
                height: 200%;
                background: radial-gradient(circle, rgba(130, 204, 14, 0.1) 0%, transparent 70%);
                animation: rotate-gradient 20s linear infinite;
            }
            @keyframes rotate-gradient {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .filters-section {
                background: linear-gradient(to bottom, rgba(255, 255, 255, 0.9), rgba(245, 245, 245, 0.8));
                backdrop-filter: blur(10px);
            }
            .dark .filters-section {
                background: linear-gradient(to bottom, rgba(0, 0, 0, 0.9), rgba(26, 26, 26, 0.8));
            }
            .icon-hover {
                transition: all 0.3s ease;
            }
            .icon-hover:hover {
                transform: scale(1.1) rotate(5deg);
                color: #82cc0e;
            }
            .table-container {
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            }
            .dark .table-container {
                box-shadow: 0 1px 6px rgba(0, 0, 0, 0.4);
            }
            .filters-active-indicator {
                background: #82cc0e;
                color: white;
                padding: 6px 12px;
                border-radius: 8px;
                font-weight: 600;
                animation: glow-green 2s ease-in-out infinite;
            }
            .dark .filters-active-indicator {
                background: #82cc0e;
                color: white;
            }
            @keyframes glow-green {
                0%, 100% { box-shadow: 0 0 10px rgba(130, 204, 14, 0.3); }
                50% { box-shadow: 0 0 20px rgba(130, 204, 14, 0.6); }
            }
            .input-codigo:focus,
            .input-nombre:focus,
            .input-marca:focus {
                border-color: #82cc0e !important;
                box-shadow: 0 8px 16px -4px rgba(130, 204, 14, 0.3) !important;
            }
            .dark .input-codigo:focus,
            .dark .input-nombre:focus,
            .dark .input-marca:focus {
                box-shadow: 0 8px 20px -4px rgba(130, 204, 14, 0.5) !important;
            }
        </style>

        <div id="' . $modalId . '"
            x-data="{
                page: ' . $page . ',
                perPage: ' . $perPage . ',
                total: ' . $totalProductos . ',
                totalPages: ' . $totalPages . ',
                selected: [],
                loadingEnviar: false,
                maxVisible: 7,

                filtroCodigo: \'' . addslashes($busquedaCodigo ?? '') . '\',
                filtroNombre: \'' . addslashes($busquedaNombre ?? '') . '\',
                filtroMarca: \'' . addslashes($busquedaMarca ?? '') . '\',

                pages() {
                    const total = this.totalPages;
                    const current = this.page;
                    const max = this.maxVisible;

                    if (total <= max) {
                        return Array.from({ length: total }, (_, i) => i + 1);
                    }

                    const pages = [];
                    const half = Math.floor(max / 2);
                    let start = current - half;
                    let end = current + half;

                    if (start < 1) {
                        start = 1;
                        end = max;
                    }

                    if (end > total) {
                        end = total;
                        start = total - max + 1;
                    }

                    for (let i = start; i <= end; i++) {
                        pages.push(i);
                    }

                    return pages;
                },

                toggleProduct(producto) {
                    const index = this.selected.findIndex(p => p.codigo === producto.codigo);
                    if (index === -1) {
                        this.selected.push(producto);
                    } else {
                        this.selected.splice(index, 1);
                    }
                },

                isSelected(codigo) {
                    return this.selected.some(p => p.codigo === codigo);
                },

                filterRow(codigo, nombre, marca, index) {
                    const fc = (this.filtroCodigo || \'\').toLowerCase();
                    const fn = (this.filtroNombre || \'\').toLowerCase();
                    const fm = (this.filtroMarca  || \'\').toLowerCase();

                    const c = (codigo || \'\').toLowerCase();
                    const n = (nombre || \'\').toLowerCase();
                    const m = (marca  || \'\').toLowerCase();

                    if (fc && !c.includes(fc)) return false;
                    if (fn && !n.includes(fn)) return false;
                    if (fm && !m.includes(fm)) return false;

                    const hayFiltro = fc || fn || fm;
                    if (hayFiltro) return true; // con filtro: mostrar todos los que coinciden

                    const start = (this.page - 1) * this.perPage;
                    const end   = this.page * this.perPage;

                    return index >= start && index < end;
                },

                clearFilters() {
                    this.filtroCodigo = \'\';
                    this.filtroNombre = \'\';
                    this.filtroMarca  = \'\';
                    this.page = 1;
                },

                hasActiveFilters() {
                    return this.filtroCodigo || this.filtroNombre || this.filtroMarca;
                }
            }"
            class="bg-white dark:bg-gray-900 rounded-xl shadow-2xl overflow-hidden border border-gray-200 dark:border-gray-700"
        >
            <!-- Header -->
            <div class="header-gradient px-6 py-5 border-b border-gray-200 dark:border-gray-700 relative">
                <div class="flex items-center justify-between relative z-10">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                            Seleccionar Productos
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-0.5">
                            Los productos mostrados son de la <span class="font-semibold">' . htmlspecialchars(self::obtenerNombreBodega($codigoBodega), ENT_QUOTES, 'UTF-8') . '</span>
                        </p>
                    </div>
                    <div x-show="selected.length > 0" 
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-90"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="flex items-center gap-3 bg-white dark:bg-gray-800 px-4 py-2 rounded-xl shadow-lg">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Agregados:
                        </span>
                        <span class="badge-count" x-text="selected.length"></span>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-section px-6 py-6 border-b border-gray-200 dark:border-gray-700">
                <div class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div class="relative">
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white mb-2">
                                Código del Producto
                            </label>
                            <input
                                type="text"
                                x-model="filtroCodigo"
                                placeholder="Ej: PROD-001"
                                class="input-codigo search-input block w-full pl-4 pr-10 py-3 text-sm border-2 border-gray-300 dark:border-gray-600 rounded-xl shadow-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:ring-4 focus:ring-primary-500/20 dark:focus:ring-primary-500/40 focus:border-primary-500 dark:focus:border-primary-400 transition-colors"
                            >
                        </div>

                        <div class="relative">
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white mb-2">
                                Nombre del Producto
                            </label>
                            <input
                                type="text"
                                x-model="filtroNombre"
                                placeholder="Nombre del producto..."
                                class="input-nombre search-input block w-full pl-4 pr-10 py-3 text-sm border-2 border-gray-300 dark:border-gray-600 rounded-xl shadow-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:ring-4 focus:ring-primary-500/20 dark:focus:ring-primary-500/40 focus:border-primary-500 dark:focus:border-primary-400 transition-colors"
                            >
                        </div>

                        <div class="relative">
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white mb-2">
                                Marca
                            </label>
                            <input
                                type="text"
                                x-model="filtroMarca"
                                placeholder="Marca del producto..."
                                class="input-marca search-input block w-full pl-4 pr-10 py-3 text-sm border-2 border-gray-300 dark:border-gray-600 rounded-xl shadow-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:ring-4 focus:ring-primary-500/20 dark:focus:ring-primary-500/40 focus:border-primary-500 dark:focus:border-primary-400 transition-colors"
                            >
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-3">
                        <div x-show="hasActiveFilters()" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 -translate-x-4"
                             x-transition:enter-end="opacity-100 translate-x-0"
                             class="filters-active-indicator flex items-center gap-2 text-sm">
                            Filtros aplicados
                        </div>
                        
                        <div class="flex gap-3">
                            <button
                                type="button"
                                @click="page = 1"
                                class="btn-primary inline-flex items-center px-6 py-3 text-sm font-semibold rounded-xl text-white shadow-lg hover:shadow-xl"
                            >
                                Buscar
                            </button>

                            <button
                                type="button"
                                @click="clearFilters()"
                                x-show="hasActiveFilters()"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 scale-90"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="btn-secondary inline-flex items-center px-6 py-3 text-sm font-semibold rounded-xl border-2 border-red-300 dark:border-red-600 bg-white dark:bg-gray-800 text-red-600 dark:text-red-400 shadow-md hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                            >
                                Limpiar Filtros
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto table-container">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gradient-to-r from-gray-50 via-gray-100 to-gray-50 dark:from-gray-800 dark:via-gray-850 dark:to-gray-800">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                                Código
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                                Nombre del Producto
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                                Marca
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider w-32">
                                
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
        ';
        









        // Paginación
        if ($totalPages > 1) {
            $html .= '
            <div class="bg-gray-50 dark:bg-gray-800 px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center sm:justify-center">
                <div class="flex-1 flex justify-center sm:hidden">
                    <button 
                        type="button"
                        @click="if (page > 1) page--"
                        :disabled="page <= 1"
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
                    >
                        Anterior
                    </button>
                    <button 
                        type="button"
                        @click="if (page < totalPages) page++"
                        :disabled="page >= totalPages"
                        class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
                    >
                        Siguiente
                    </button>
                </div>

                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                            Mostrando 
                            <span class="font-medium" x-text="total === 0 ? 0 : (page - 1) * perPage + 1"></span>
                            a 
                            <span class="font-medium" x-text="Math.min(page * perPage, total)"></span>
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px max-w-full overflow-x-auto" aria-label="Pagination">
                            <button 
                                type="button"
                                @click="if (page > 1) page--"
                                :disabled="page <= 1"
                                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
                            >
                                «
                            </button>

                            <template x-for="p in pages()" :key="p">
                                <button
                                    type="button"
                                    @click="page = p"
                                    class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                                    :class="p === page
                                        ? \'bg-primary-600 border-primary-600 text-white\'
                                        : \'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700\'"
                                    x-text="p"
                                ></button>
                            </template>

                            <button 
                                type="button"
                                @click="if (page < totalPages) page++"
                                :disabled="page >= totalPages"
                                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
                            >
                                »
                            </button>
                        </nav>
                    </div>
                </div>
            </div>';
        } else {
            $html .= '
            <div class="bg-gray-50 dark:bg-gray-800 px-6 py-3 border-t border-gray-200 dark:border-gray-700">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Mostrando <span class="font-semibold text-gray-900 dark:text-gray-100">' . $totalProductos . '</span> producto(s)
                </p>
            </div>';
        }

        // FILAS
        $index = 0;
        foreach ($productos as $producto) {
            $codigoRaw = $producto['codigo'] ?? '';
            $nombreRaw = $producto['nombre'] ?? '';
            $marcaRaw  = $producto['marca']  ?? '';

            $codigo   = htmlspecialchars($codigoRaw, ENT_QUOTES, 'UTF-8');
            $nombre   = htmlspecialchars($nombreRaw, ENT_QUOTES, 'UTF-8');
            $marca    = htmlspecialchars($marcaRaw, ENT_QUOTES, 'UTF-8');
            $precio   = $producto['precio'];
            $cantidad = number_format($producto['cantidad'], 2);

            // *** AQUÍ ESTÁ EL CAMBIO IMPORTANTE ***
            // Usar json_encode para generar cadenas seguras para JS
            $codigoJs = htmlspecialchars(json_encode($codigoRaw, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
            $nombreJs = htmlspecialchars(json_encode($nombreRaw, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
            $marcaJs  = htmlspecialchars(json_encode($marcaRaw,  JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

            $html .= '
                <tr 
                    x-show="filterRow(' . $codigoJs . ', ' . $nombreJs . ', ' . $marcaJs . ', ' . $index . ')"
                    x-cloak
                    class="table-row-hover h-16 text-center"
                >
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-mono font-semibold text-gray-900 dark:text-gray-100">' . $codigo . '</td>
                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100 text-center">
                        <div class="max-w-xs mx-auto overflow-hidden text-ellipsis line-clamp-2" title="' . $nombre . '">
                            ' . $nombre . '
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                        <div class="max-w-[150px] mx-auto overflow-hidden text-ellipsis whitespace-nowrap" title="' . $marca . '">
                            ' . $marca . '
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <button 
                            type="button"
                            @click="toggleProduct({
                                codigo: ' . $codigoJs . ',
                                nombre: ' . $nombreJs . ',
                                marca: ' . $marcaJs . ',
                                precio: ' . $precio . ',
                                stock: ' . $producto['cantidad'] . '
                            })"
                            class="inline-flex items-center px-4 py-2 text-white text-sm font-medium rounded-md shadow-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 hover:shadow-md active:scale-95"
                            :class="isSelected(' . $codigoJs . ') 
                                ? \'bg-red-600 hover:bg-red-700\' 
                                : \'bg-primary-600 hover:bg-primary-700\'"
                        >
                            <span class="btn-text transition-opacity duration-200" 
                                x-text="isSelected(' . $codigoJs . ') ? \'Quitar\' : \'Agregar\'"
                            ></span>
                        </button>
                    </td>
                </tr>';

            $index++;
        }

        $html .= '
            <div class="px-6 py-3 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <p class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                    Productos agregados: 
                    <span class="font-semibold" x-text="selected.length"></span>
                </p>

                <button
                    type="button"
                    @click="
                        if (loadingEnviar || selected.length === 0) return;
                        loadingEnviar = true;
                        $el.classList.add(\'btn-loading\');

                        const modal = $el.closest(\'.fi-modal\');
                        const inputSeleccionados = modal.querySelector(\'input[data-role=\\\'productos-seleccionados\\\']\' );

                        if (inputSeleccionados) {
                            inputSeleccionados.value = JSON.stringify(selected);
                            inputSeleccionados.dispatchEvent(new Event(\'input\', { bubbles: true }));
                        }

                        const submitBtn = modal.querySelector(\'button[type=\\\'submit\\\']\');
                        if (submitBtn) {
                            setTimeout(() => submitBtn.click(), 100);
                        }
                    "
                    :disabled="selected.length === 0 || loadingEnviar"
                    class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 text-white text-sm font-medium rounded-md shadow-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 hover:shadow-md active:scale-95"
                >
                    <span class="btn-text transition-opacity duration-200">Enviar selección</span>
                </button>
            </div>

            </div>';

        $html .= '
                    </tbody>
                </table>
            </div>
        ';

        // Paginación
        if ($totalPages > 1) {
            $html .= '
            <div class="bg-gray-50 dark:bg-gray-800 px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center sm:justify-center">
                <div class="flex-1 flex justify-center sm:hidden">
                    <button 
                        type="button"
                        @click="if (page > 1) page--"
                        :disabled="page <= 1"
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
                    >
                        Anterior
                    </button>
                    <button 
                        type="button"
                        @click="if (page < totalPages) page++"
                        :disabled="page >= totalPages"
                        class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
                    >
                        Siguiente
                    </button>
                </div>

                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                            Mostrando 
                            <span class="font-medium" x-text="total === 0 ? 0 : (page - 1) * perPage + 1"></span>
                            a 
                            <span class="font-medium" x-text="Math.min(page * perPage, total)"></span>
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px max-w-full overflow-x-auto" aria-label="Pagination">
                            <button 
                                type="button"
                                @click="if (page > 1) page--"
                                :disabled="page <= 1"
                                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
                            >
                                «
                            </button>

                            <template x-for="p in pages()" :key="p">
                                <button
                                    type="button"
                                    @click="page = p"
                                    class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                                    :class="p === page
                                        ? \'bg-primary-600 border-primary-600 text-white\'
                                        : \'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700\'"
                                    x-text="p"
                                ></button>
                            </template>

                            <button 
                                type="button"
                                @click="if (page < totalPages) page++"
                                :disabled="page >= totalPages"
                                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
                            >
                                »
                            </button>
                        </nav>
                    </div>
                </div>
            </div>';
        } else {
            $html .= '
            <div class="bg-gray-50 dark:bg-gray-800 px-6 py-3 border-t border-gray-200 dark:border-gray-700">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Mostrando <span class="font-semibold text-gray-900 dark:text-gray-100">' . $totalProductos . '</span> producto(s)
                </p>
            </div>';
        }

        // Resumen + botón enviar selección
        $html .= '
        <div class="px-6 py-3 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <p class="text-sm text-gray-500 dark:text-gray-300">
                Productos agregados: 
                <span class="font-semibold" x-text="selected.length"></span>
            </p>

            <button
                type="button"
                @click="
                    if (loadingEnviar || selected.length === 0) return;
                    loadingEnviar = true;
                    $el.classList.add(\'btn-loading\');

                    const modal = $el.closest(\'.fi-modal\');
                    const inputSeleccionados = modal.querySelector(\'input[data-role=\\\'productos-seleccionados\\\']\' );

                    if (inputSeleccionados) {
                        inputSeleccionados.value = JSON.stringify(selected);
                        inputSeleccionados.dispatchEvent(new Event(\'input\', { bubbles: true }));
                    }

                    const submitBtn = modal.querySelector(\'button[type=\\\'submit\\\']\');
                    if (submitBtn) {
                        setTimeout(() => submitBtn.click(), 100);
                    }
                "
                :disabled="selected.length === 0 || loadingEnviar"
                class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 text-white text-sm font-medium rounded-md shadow-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 hover:shadow-md active:scale-95"
            >
                <span class="btn-text transition-opacity duration-200">Enviar selección</span>
            </button>
        </div>

        </div>';

        return $html;
    }
    private static function obtenerNombreBodega(string $codigoBodega): string
    {
        try {
            $bodega = \App\Models\ZBodegaFacturacion::where('Cod_Bog', $codigoBodega)->first();
            return $bodega ? $bodega->Nombre_Bodega : 'Bodega: ' . $codigoBodega;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error obteniendo nombre de bodega: ' . $e->getMessage());
            return 'Bodega: ' . $codigoBodega;
        }
    }
}
