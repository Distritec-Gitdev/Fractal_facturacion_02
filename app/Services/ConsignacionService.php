<?php

namespace App\Services;

use App\Models\Consignacion;
use App\Models\Producto;
use App\Models\VarianteConsignacion;
use Carbon\Carbon;
use App\Models\ZBodegaFacturacion;
use App\Models\CodigoProductoConsignacion;
use App\Models\ClienteConsignacion;
use Illuminate\Support\Facades\Log;

class ConsignacionService
{
    /**
     * Retorna el mayor número de días que alguno de los productos
     * ha estado en consignación (solo variantes activas) PARA UN CLIENTE.
     */
    public function calcularDiasConsignacion($nit)
    {
        // Buscar el cliente por cédula (obtenemos un solo modelo)
        $cliente = ClienteConsignacion::where('cedula', $nit)->first();
        
        // Si no existe el cliente, retornar 0
        if (!$cliente) {
            return 0;
        }
        
        // Ahora sí podemos usar $cliente->id_cliente
        $consignaciones = Consignacion::with(['producto.variante'])
            ->where('id_cliente', $cliente->id_cliente)
            ->get();

        if ($consignaciones->isEmpty()) {
            return 0;
        }

        $diasMayor = 0;

        foreach ($consignaciones as $consignacion) {
            $producto = $consignacion->producto;
            if (!$producto) {
                continue;
            }

            $variante = $producto->variante;
            if (!$variante) {
                continue;
            }

            // Solo variantes activas
            if ((int) $variante->estado !== 1) {
                continue;
            }

            $fecha = Carbon::parse($consignacion->fecha);
            $dias  = $fecha->diffInDays(now());

            if ($dias > $diasMayor) {
                $diasMayor = $dias;
            }
        }

        return $diasMayor;
    }




    public function calcularValorTotalConsignacionActiva($nit)
    {
        // Buscar el cliente por cédula
        $cliente = ClienteConsignacion::where('cedula', $nit)->first();
        
        // Si no existe el cliente, retornar 0
        if (!$cliente) {
            return 0;
        }

        // IDs de consignaciones del cliente usando id_cliente
        $idsConsignacion = Consignacion::where('id_cliente', $cliente->id_cliente)
            ->pluck('id');

        if ($idsConsignacion->isEmpty()) {
            return 0;
        }

        // Productos relacionados a esas consignaciones (con variante)
        $productos = Producto::with('variante')
            ->whereIn('id_consignacion', $idsConsignacion)
            ->get();

        if ($productos->isEmpty()) {
            return 0;
        }

        $total = 0.0;

        foreach ($productos as $producto) {
            $variante = $producto->variante;

            // Solo variantes activas
            if (!$variante || (int) $variante->estado !== 1) {
                continue;
            }

            $valorUnitario = (float) $producto->valor_unitario;
            $cantidad      = (int) ($producto->cantidad ?? 1);

            $total += $valorUnitario * $cantidad;
        }

        return $total;
    }

    
    public function verificarProductoEnConsignacion(
        string $codigoProducto,
        string $codigoBodega,
        string $codigoVariante = null,
    ): array {
        
    
        // Validar por variante
        if ($codigoVariante != 0 && $codigoVariante != null) {
            
            $variante = VarianteConsignacion::query()
                ->where('variante', $codigoVariante)
                ->where('estado', 1) 
                ->first(); 

            if($variante){
                $producto = Producto::where('producto_id', $variante->producto_id)->first();
                $codigo = CodigoProductoConsignacion::where('id', $producto->codigo)->first();
        
                $consignacion = Consignacion::where('id', $producto->id_consignacion)->first();
    
                $bodega = ZBodegaFacturacion::where('ID_Bog', $consignacion->bodega_id)->first();
                $varianteEnConsignacion = ($variante->variante == $codigoVariante);

                if ($bodega->Cod_Bog == $codigoBodega && $codigo->codigo == $codigoProducto && $varianteEnConsignacion) {
                    return [
                        'en_consignacion' => true,
                        'cantidad_consignada_bodega' => $producto->cantidad ?? 0,
                    ];
                } else {
                    return [
                        'en_consignacion' => false,
                        'cantidad_consignada_bodega' => 0,
                    ];
                }
            }else{
                return [
                    'en_consignacion' => false,
                    'cantidad_consignada_bodega' => 0,
                ];
            }

        }
        // Validar por producto (sin variante)
        else {
            $codigo = CodigoProductoConsignacion::where('codigo', $codigoProducto)->first();

            if ($codigoProducto != null) {
                $producto = Producto::where('codigo', $codigo->id)->first();
                $codigo = CodigoProductoConsignacion::where('id', $producto->codigo)->first();

                $variante = VarianteConsignacion::query()
                    ->where('variante', $codigoVariante)
                    ->where('estado', 1) 
                    ->first(); 
                
                if($producto == true && $variante == true){
                    $consignacion = Consignacion::where('id', $producto->id_consignacion)->first();
                    $bodega = ZBodegaFacturacion::where('ID_Bog', $consignacion->bodega_id)->first();
                    $varianteEnConsignacion = ($variante->variante == $codigoVariante);

                    if ($bodega->Cod_Bog == $codigoBodega && $codigo->codigo == $codigoProducto && $varianteEnConsignacion) {
                        return [
                            'en_consignacion' => true,
                            'cantidad_consignada_bodega' => $producto->cantidad ?? 0,
                        ];
                    } else {
                        return [
                            'en_consignacion' => false,
                            'cantidad_consignada_bodega' => 0,
                        ];
                    }
                }else{
                    return [
                        'en_consignacion' => false,
                        'cantidad_consignada_bodega' => 0,
                        
                    ];
                }
            }else{
                return [
                    'en_consignacion' => false,
                    'cantidad_consignada_bodega' => 0,
                ];
            }
        }
    }
}

