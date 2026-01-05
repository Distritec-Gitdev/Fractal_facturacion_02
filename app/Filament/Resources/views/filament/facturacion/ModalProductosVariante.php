<?php

namespace App\Filament\Resources\FacturacionResource\Forms;

use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\ProductosController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ModalProductosVariante
{
    /**
     * Obtiene productos disponibles desde tu API o base de datos
     */
    public static function obtenerProductosDisponibles(
        string $codigoProducto = null,
        string $codigoBodega,
    ): array {
        $request = new Request();
        $request->replace([
            'codigoProducto' => $codigoProducto,
            'codigo_bodega'  => $codigoBodega,
        ]);

        $controller = app(ProductosController::class);
        $response = $controller->productoDisponibles($request);

        // Si viene como JsonResponse, lo convertimos a array
        if ($response instanceof JsonResponse) {
            $response = $response->getData(true);
        }

        if (is_array($response)) {
            try {
                if (isset($response['puedeFacturar']) && !$response['puedeFacturar']) {
                    return [];
                }
                return $response;
            } catch (\Throwable $th) {
                return $response;
            }
        }

        return [];
    }

    public static function validarEnConsignaciones(
        string $cantidadSeleccionada,
        string $codigoProducto,
        string $codigo_bodega,
        string $codigoVariante,
        string $existenciaDisponible,
    ): array {
        $request = new Request();
        $request->replace([
            'cantidadSeleccionada' => $cantidadSeleccionada,
            'codigoProducto'       => $codigoProducto,
            'codigo_bodega'        => $codigo_bodega,
            'codigoVariante'       => $codigoVariante,
            'existenciaDisponible' => $existenciaDisponible,
        ]);

        $controller = app(ProductosController::class);
        $response = $controller->validarEnConsignaciones($request);

        // IMPORTANTE: si el controller retorna JsonResponse, conviértelo
        if ($response instanceof JsonResponse) {
            $response = $response->getData(true);
        }

        // IMPORTANTE: NO devuelvas [] cuando no puede facturar,
        // porque el frontend necesita mensaje/url/accion
        if (is_array($response)) {
            return $response;
        }

        return [
            'puedeFacturar' => false,
            'mensaje' => 'Respuesta inválida del servidor.',
        ];
    }

    /**
     * Genera la lista HTML de productos para selección de variantes
     */
     public static function generarListaProductosVariantes(
        int $cantidad,
        string $codigoProducto,
        string $codigoBodega,
        int $page = 1,
        int $perPage = 10
    ): string {
        $productos = self::obtenerProductosDisponibles($codigoProducto, $codigoBodega);

        $csrfToken = csrf_token();
        $urlValidarConsignacion = route('facturacion.validar-consignacion');

        $codigoProductoJs = htmlspecialchars(json_encode($codigoProducto), ENT_QUOTES, 'UTF-8');
        $codigoBodegaJs   = htmlspecialchars(json_encode($codigoBodega), ENT_QUOTES, 'UTF-8');
        $urlValidarJs     = htmlspecialchars(json_encode($urlValidarConsignacion), ENT_QUOTES, 'UTF-8');

        if (empty($productos)) {
            return '<div class="flex flex-col items-center justify-center py-20 px-6">
                <div class="w-20 h-20 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-700 rounded-2xl flex items-center justify-center shadow-inner">
                    <svg class="w-10 h-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                </div>
                <p class="mt-6 text-base font-medium text-gray-900 dark:text-white">
                    Sin variantes disponibles
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Verifica el código de bodega y producto
                </p>
            </div>';
        }

        $modalId = 'variantes-' . uniqid();

        $productosJson = htmlspecialchars(
            json_encode($productos, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT),
            ENT_QUOTES,
            'UTF-8'
        );

        $html = <<<HTML
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
                .variante-card {
                    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                    cursor: pointer;
                    position: relative;
                }
                .variante-card:hover:not(.disabled) {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 16px rgba(130, 204, 14, 0.25);
                    border-color: #82cc0e;
                }
                .variante-card.selected {
                    background: linear-gradient(135deg, rgba(130, 204, 14, 0.15) 0%, rgba(130, 204, 14, 0.05) 100%);
                    border-color: #82cc0e;
                    box-shadow: 0 0 0 3px rgba(130, 204, 14, 0.3);
                }
                .variante-card.disabled {
                    opacity: 0.4;
                    cursor: not-allowed;
                }
                .check-badge {
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }
                .variante-card.selected .check-badge {
                    transform: scale(1) rotate(0deg);
                    opacity: 1;
                }
                .variante-card:not(.selected) .check-badge {
                    transform: scale(0) rotate(-180deg);
                    opacity: 0;
                }
                .search-box {
                    transition: all 0.3s ease;
                    border: 2px solid #e5e7eb;
                }
                .search-box:focus {
                    box-shadow: 0 0 0 3px rgba(130, 204, 14, 0.15);
                    border-color: #82cc0e;
                }
                
                /* MODO CLARO LIMPIO Y MODERNO */
                .alert-warning {
                    background: #ffffff;
                    border: 2px solid #c40000ff;
                    box-shadow: 0 1px 3px rgba(251, 191, 36, 0.1);
                }
                .alert-success {
                    background: #ffffff;
                    border: 2px solid #82cc0e;
                    box-shadow: 0 1px 3px rgba(130, 204, 14, 0.1);
                }
                
                /* Header limpio sin fondo */
                .modal-header-bg {
                    background: #ffffffff !important;
                    border-bottom: 2px solid #e5e7eb;
                }
                
                /* Footer limpio sin fondo */
                .modal-footer-bg p {
                    color: #111827 !important;
                }
                
                /* Contador con fondo blanco y borde verde */
                .counter-badge {
                    background: #ffffff !important;
                    border: 2px solid #82cc0e !important;
                    box-shadow: 0 2px 8px rgba(130, 204, 14, 0.15) !important;
                }
                
                /* FORZAR fondos blancos en modo claro */
                :not(.dark) .bg-gray-50 {
                    background-color: #ffffff !important;
                }
                :not(.dark) .bg-gray-100 {
                    background-color: #f9fafb !important;
                }
                
                /* Cards siempre con fondo blanco en modo claro */
                .variante-card {
                    background: #ffffff !important;
                }
                .dark .variante-card {
                    background: #000000ff !important;
                }
                
                /* Badge de código con mejor contraste */
                .badge-codigo {
                    background: #f3f4f6 !important;
                    color: #111827 !important;
                    border: 1px solid #e5e7eb;
                }
                .dark .badge-codigo {
                    background: #000000ff !important;
                    color: #f9fafb !important;
                    border: 1px solid #4b5563;
                }
                
                /* Badge de marca con mejor contraste */
                .badge-marca {
                    background: #eff6ff !important;
                    color: #000000ff !important;
                    border: 1px solid #dbeafe;
                }
                .dark .badge-marca {
                    background: rgba(0, 0, 0, 0.2) !important;
                    color: #f7fbffff !important;
                    border: 1px solid rgba(0, 0, 0, 0.3);
                }
                
                /* Modo oscuro mantiene su estilo original */
                .dark .alert-warning {
                    background: linear-gradient(135deg, rgba(158, 18, 18, 0.14) 0%, rgba(136, 12, 12, 0.03) 100%);
                    border-color: #c40000ff;
                    box-shadow: none;
                }
                .dark .alert-success {
                    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.1) 100%);
                    border-color: #82cc0e;
                    box-shadow: none;
                }
                .dark .modal-header-bg {
                    background: transparent !important;
                    border-bottom: 1px solid rgba(255, 255, 255, 1);
                }
                .dark .modal-footer-bg p {
                    color: #d1d5db !important;
                }
                .dark .counter-badge {
                    background: #000000ff !important;
                    border: 2px solid rgba(130, 204, 14, 0.5) !important;
                }
                .dark .search-box {
                    border-color: #000000ff;
                }
                
                .btn-loading {
                    position: relative;
                    pointer-events: none;
                    opacity: 0.7;
                }
                .btn-loading .btn-text {
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

                /* Modal de consignación */
                .modal-overlay {
                    position: fixed;
                    inset: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 9999;
                    animation: fadeIn 0.2s ease-out;
                }
                .modal-content {
                    background: #ffffff !important;
                    border-radius: 12px;
                    max-width: 500px;
                    width: 90%;
                    padding: 24px;
                    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
                    animation: slideUp 0.3s ease-out;
                }
                .dark .modal-content {
                    background: #1f2937 !important;
                }
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                @keyframes slideUp {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            </style>

            <div id="{$modalId}"
                x-data="{
                    selected: [],
                    searchQuery: '',
                    loadingEnviar: false,
                    validandoConsignacion: false,
                    productos: {$productosJson},
                    cantidadRequerida: {$cantidad},
                    codigoProducto: {$codigoProductoJs},
                    codigoBodega: {$codigoBodegaJs},
                    urlValidarConsignacion: {$urlValidarJs},
                    mostrarModalConsignacion: false,
                    mensajeConsignacion: '',
                    urlConsignacion: '',
                    accionConsignacion: '',

                    get filteredProducts() {
                        if (!this.searchQuery) return this.productos;
                        const query = this.searchQuery.toLowerCase();
                        return this.productos.filter(p =>
                            p.codigo.toLowerCase().includes(query) ||
                            p.descripcion.toLowerCase().includes(query) ||
                            (p.marca && p.marca.toLowerCase().includes(query))
                        );
                    },

                    get cantidadRestante() {
                        return this.cantidadRequerida - this.selected.length;
                    },

                    get seleccionCompleta() {
                        return this.selected.length === this.cantidadRequerida;
                    },

                    async validarConsignacion(producto) {
                        this.validandoConsignacion = true;
                        try {
                            const response = await fetch(this.urlValidarConsignacion, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{$csrfToken}',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({
                                    cantidadSeleccionada: 1,
                                    codigoProducto: this.codigoProducto,
                                    codigo_bodega: this.codigoBodega,
                                    codigoVariante: producto.codigo,
                                    existenciaDisponible: String(producto.cantidad ?? '0')
                                })
                            });

                            let data = {};
                            try {
                                data = await response.json();
                            } catch (e) {
                                data = { puedeFacturar: false, mensaje: 'Respuesta no es JSON.' };
                            }

                            if (!response.ok) {
                                this.mensajeConsignacion = data.mensaje || 'Error HTTP al validar consignación.';
                                this.mostrarModalConsignacion = true;
                                return false;
                            }

                            if (data?.puedeFacturar === false) {
                                this.mensajeConsignacion = data.mensaje || 'Este producto está en consignación y no puede ser facturado';
                                this.urlConsignacion = data.url || '';
                                this.accionConsignacion = data.accion || '';
                                this.mostrarModalConsignacion = true;
                                return false;
                            }

                            return true;
                        } catch (error) {
                            console.error('Error al validar consignación:', error);
                            this.mensajeConsignacion = 'Error al validar disponibilidad del producto';
                            this.mostrarModalConsignacion = true;
                            return false;
                        } finally {
                            this.validandoConsignacion = false;
                        }
                    },

                    async toggleProduct(producto) {
                        const index = this.selected.findIndex(p => p.codigo === producto.codigo);
                        if (index !== -1) {
                            this.selected.splice(index, 1);
                            return;
                        }
                        if (this.selected.length >= this.cantidadRequerida) return;
                        const puedeAgregar = await this.validarConsignacion(producto);
                        if (puedeAgregar) {
                            this.selected.push(producto);
                        }
                    },

                    isSelected(codigo) {
                        return this.selected.some(p => p.codigo === codigo);
                    },

                    isDisabled(codigo) {
                        return !this.isSelected(codigo) && this.selected.length >= this.cantidadRequerida;
                    },

                    cerrarModalConsignacion() {
                        this.mostrarModalConsignacion = false;
                        this.mensajeConsignacion = '';
                        this.urlConsignacion = '';
                        this.accionConsignacion = '';
                    },

                    irAConsignaciones() {
                        if (this.urlConsignacion) {
                            window.open(this.urlConsignacion, '_blank');
                        }
                        this.cerrarModalConsignacion();
                    }
                }"
                class="bg-white dark:bg-gray-900 rounded-lg overflow-hidden relative"
            >
                <!-- Modal de Consignación -->
                <div x-show="mostrarModalConsignacion"
                    x-cloak
                    class="modal-overlay bg-black/50 dark:bg-black/70"
                    @click.self="cerrarModalConsignacion()">
                    <div class="modal-content bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                                    Producto en Consignación
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-300" x-text="mensajeConsignacion"></p>
                                <div class="mt-6 flex gap-3 justify-end">
                                    <button
                                        @click="cerrarModalConsignacion()"
                                        class="px-4 py-2 text-sm font-medium text-black bg-[#82cc0e] rounded-lg hover:bg-[#73b80d] transition-colors focus:outline-none focus:ring-2 focus:ring-[#82cc0e] focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-gray-800"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        @click="irAConsignaciones()"
                                        class="px-4 py-2 text-sm font-medium text-black bg-[#82cc0e] rounded-lg hover:bg-[#73b80d] transition-colors focus:outline-none focus:ring-2 focus:ring-[#82cc0e] focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-gray-800"
                                    >
                                        Ir a Consignaciones
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Indicador de validación -->
                <div x-show="validandoConsignacion"
                    x-cloak
                    class="absolute inset-0 bg-white/80 dark:bg-gray-900/80 flex items-center justify-center z-50 rounded-lg">
                    <div class="text-center">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Validando disponibilidad...</p>
                    </div>
                </div>

                <!-- Header -->
                <div class="modal-header-bg px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Seleccionar Variantes
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Selecciona las variantes necesarias (IMEI, Serial, etc.)
                            </p>
                        </div>
                       
                            <div class="counter-badge text-right p-3 rounded-lg">
                                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Seleccionadas:
                                    <span class="font-bold text-2xl text-green-600 dark:text-green-500" x-text="selected.length"></span>
                                    <span class="text-gray-600 dark:text-gray-500 text-lg">/ {$cantidad}</span>
                                </div>
                                <div class="text-xs mt-1" x-show="!seleccionCompleta">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400"">
                                        Faltan <span class="font-semibold text-amber-700 dark:text-yellow-500" x-text="cantidadRestante"></span>
                                    </span>
                                </div>
                                <div class="text-xs mt-1 text-green-600 dark:text-green-500 font-medium" x-show="seleccionCompleta">
                                    ✓ Selección completa
                                </div>
                            </div>
                        </div>
                    

                    <!-- Banner de estado -->
                    <div
                        :class="seleccionCompleta ? 'alert-success' : 'alert-warning'"
                        class="rounded-lg px-4 py-3 flex items-start gap-3 mb-4 transition-all duration-300"
                    >
                        <svg
                            x-show="!seleccionCompleta"
                            class="w-5 h-5 text-amber-600 dark:text-yellow-500 flex-shrink-0 mt-0.5"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <svg
                            x-show="seleccionCompleta"
                            class="w-5 h-5 text-green-600 dark:text-green-500 flex-shrink-0 mt-0.5"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span
                            class="text-sm font-semibold"
                            :class="seleccionCompleta ? 'text-green-700 dark:text-green-200' : 'text-amber-700 dark:text-white-200'"
                        >
                            <span x-show="!seleccionCompleta">Debes seleccionar EXACTAMENTE {$cantidad} variante(s) para continuar</span>
                            <span x-show="seleccionCompleta">¡Perfecto! Has seleccionado las {$cantidad} variantes requeridas</span>
                        </span>
                    </div>

                    <!-- Búsqueda -->
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input
                            type="text"
                            x-model="searchQuery"
                            placeholder="Buscar por código, IMEI, Serial o descripción..."
                            class="search-box block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none"
                        >
                    </div>
                </div>


                <!-- seleccion de variantes -->
                <div class="modal-footer-bg px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between gap-4">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-300">
                        Variantes agregadas:
                        <span class="font-bold text-green-600 dark:text-green-400 text-lg" x-text="selected.length"></span>
                    </p>

                    <button
                        type="button"
                        @click="
                            if (loadingEnviar || !seleccionCompleta) return;
                            loadingEnviar = true;
                            \$el.classList.add('btn-loading');
                            const modal = \$el.closest('.fi-modal');
                            const inputSeleccionados = modal.querySelector('input[data-role=\\'variantes-seleccionadas\\']');
                            if (inputSeleccionados) {
                                inputSeleccionados.value = JSON.stringify(selected);
                                inputSeleccionados.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                            const submitBtn = modal.querySelector('button[type=\\'submit\\']');
                            if (submitBtn) {
                                setTimeout(() => submitBtn.click(), 100);
                            }
                        "
                        :disabled="!seleccionCompleta || loadingEnviar"
                        class="inline-flex items-center px-6 py-2.5 text-white text-sm font-semibold rounded-lg shadow-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                        :class="seleccionCompleta && !loadingEnviar ? 'bg-primary-600 hover:bg-primary-700 cursor-pointer' : 'bg-gray-400 dark:bg-gray-600 cursor-not-allowed'"
                    >
                        <span class="btn-text transition-opacity duration-200">Guardar Variantes</span>
                    </button>
                </div>

                <!-- Grid de variantes -->
                <div class="p-6 max-h-[500px] overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <template x-for="producto in filteredProducts" :key="producto.codigo">
                            <div
                                @click="!isDisabled(producto.codigo) && !validandoConsignacion && toggleProduct(producto)"
                                class="variante-card bg-white dark:bg-gray-800 border-2 rounded-lg p-4 flex flex-col"
                                :class="{
                                    'selected border-primary-500': isSelected(producto.codigo),
                                    'border-gray-200 dark:border-gray-700': !isSelected(producto.codigo),
                                    'disabled': isDisabled(producto.codigo) || validandoConsignacion
                                }"
                            >
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 line-clamp-2" x-text="producto.codigo"></p>
                                    </div>

                                    <div class="ml-3 check-badge">
                                        <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Sin resultados -->
                    <div x-show="filteredProducts.length === 0" class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No se encontraron variantes</p>
                        <button
                            @click="searchQuery = ''"
                            class="mt-2 text-sm text-primary-600 hover:text-primary-700 font-medium"
                        >
                            Limpiar búsqueda
                        </button>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer-bg px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between gap-4">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-300">
                        Variantes agregadas:
                        <span class="font-bold text-green-600 dark:text-green-400 text-lg" x-text="selected.length"></span>
                    </p>

                    <button
                        type="button"
                        @click="
                            if (loadingEnviar || !seleccionCompleta) return;
                            loadingEnviar = true;
                            \$el.classList.add('btn-loading');
                            const modal = \$el.closest('.fi-modal');
                            const inputSeleccionados = modal.querySelector('input[data-role=\\'variantes-seleccionadas\\']');
                            if (inputSeleccionados) {
                                inputSeleccionados.value = JSON.stringify(selected);
                                inputSeleccionados.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                            const submitBtn = modal.querySelector('button[type=\\'submit\\']');
                            if (submitBtn) {
                                setTimeout(() => submitBtn.click(), 100);
                            }
                        "
                        :disabled="!seleccionCompleta || loadingEnviar"
                        class="inline-flex items-center px-6 py-2.5 text-white text-sm font-semibold rounded-lg shadow-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                        :class="seleccionCompleta && !loadingEnviar ? 'bg-primary-600 hover:bg-primary-700 cursor-pointer' : 'bg-gray-400 dark:bg-gray-600 cursor-not-allowed'"
                    >
                        <span class="btn-text transition-opacity duration-200">Guardar Variantes</span>
                    </button>
                </div>
            </div>
        HTML;

        return $html;
    }

    /**
     * Método helper para procesar las selecciones
     */
    public static function procesarSelecciones($variantesSeleccionadas): array
    {
        if (is_string($variantesSeleccionadas)) {
            $variantesSeleccionadas = json_decode($variantesSeleccionadas, true);
        }

        if (!is_array($variantesSeleccionadas)) {
            return [];
        }

        Log::info('Variantes seleccionadas procesadas', [
            'cantidad' => count($variantesSeleccionadas),
            'productos' => $variantesSeleccionadas
        ]);

        return $variantesSeleccionadas;
    }
}
